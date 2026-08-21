<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Role;
use App\Models\ReusableBlock;
use App\Models\TranslationLocale;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTranslationCategoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_first_translation_is_remapped_when_its_category_translation_is_created_and_published(): void
    {
        $pageUuid = (string) Str::uuid();
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Visit Ignite School',
            'slug' => 'visit-ignite-school',
            'description' => 'Visit our inclusive school.',
            'display_mode' => 'landing_page',
            'landing_page_uuid' => $pageUuid,
            'language' => 'en',
            'status' => 1,
        ]);
        $page = $this->makePage([
            'uuid' => $pageUuid,
            'category_id' => (string) $category->id,
            'slug' => 'ignite-school-campus',
            'visibility' => 'unlisted',
        ]);
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'School introduction',
            'content' => ['heading' => 'Every child belongs here.'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn');
        $pageName = $rows->first(fn (array $row) => ($row['identity']['type'] ?? null) === 'page'
            && ($row['identity']['source_id'] ?? null) === $page->id
            && ($row['identity']['field'] ?? null) === 'name');
        $blockHeading = $rows->first(fn (array $row) => ($row['identity']['type'] ?? null) === 'block'
            && ($row['identity']['source_block_id'] ?? null) === $block->id
            && ($row['identity']['path'] ?? null) === 'heading');
        $this->assertNotNull($pageName);
        $this->assertNotNull($blockHeading);

        // Deliberately create the page before its translated category. The
        // stable category UUID keeps the relationship safe in the meantime.
        $service->save('en', 'bn', [
            ['key' => $pageName['key'], 'precondition' => $pageName['precondition'], 'value' => 'ইগনাইট স্কুল ক্যাম্পাস'],
            ['key' => $blockHeading['key'], 'precondition' => $blockHeading['precondition'], 'value' => 'এখানে প্রতিটি শিশু আপন।'],
        ], null);
        $banglaPage = Page::where('uuid', $pageUuid)->where('language', 'bn')->firstOrFail();
        $this->assertSame($category->uuid, $banglaPage->category_id);
        $this->assertSame(
            [1],
            Page::where('uuid', $pageUuid)->pluck('editor_version')->unique()->values()->all()
        );

        $categoryName = $service->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'content'
            && ($row['identity']['model'] ?? null) === 'category'
            && ($row['identity']['source_id'] ?? null) === $category->id
            && ($row['identity']['field'] ?? null) === 'name'
        );
        $this->assertNotNull($categoryName);
        $service->save('en', 'bn', [[
            'key' => $categoryName['key'],
            'precondition' => $categoryName['precondition'],
            'value' => 'ইগনাইট স্কুল দেখুন',
        ]], null);

        $banglaCategory = Category::where('uuid', $category->uuid)->where('language', 'bn')->firstOrFail();
        $banglaCategory->update(['slug' => 'bangla-ignite-school']);
        $this->assertSame((string) $banglaCategory->id, $banglaPage->fresh()->category_id);
        $this->assertSame(
            [2],
            Page::where('uuid', $pageUuid)->pluck('editor_version')->unique()->values()->all(),
            'Category translation remapping should advance the already-locked logical Page once.'
        );

        // Publication also repairs translations created by older workflows
        // that still carry the source locale's numeric category ID.
        $banglaPage->update(['category_id' => (string) $category->id]);
        $service->syncPublicationState('en', 'bn');
        $this->assertSame((string) $banglaCategory->id, $banglaPage->fresh()->category_id);
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $this->get('/category/bangla-ignite-school?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('data.category.slug', 'bangla-ignite-school')
                ->where('data.landing_page.uuid', $pageUuid)
                ->where('data.landing_page.visible_blocks.0.content.heading', 'এখানে প্রতিটি শিশু আপন।')
            );
    }

    public function test_bulk_translation_uses_target_category_but_duplicate_and_uncategorized_pages_are_unchanged(): void
    {
        $admin = $this->authorizedAdmin();
        $categoryUuid = (string) Str::uuid();
        $englishCategory = Category::create([
            'uuid' => $categoryUuid,
            'name' => 'Education',
            'slug' => 'education',
            'language' => 'en',
            'status' => 1,
        ]);
        $banglaCategory = Category::create([
            'uuid' => $categoryUuid,
            'name' => 'শিক্ষা',
            'slug' => 'bangla-education',
            'language' => 'bn',
            'status' => 1,
        ]);
        $categorized = $this->makePage([
            'name' => 'Education landing content',
            'category_id' => (string) $englishCategory->id,
        ]);
        $sourceBlock = PageBlock::create([
            'page_id' => $categorized->id,
            'uuid' => '69400000-0000-4000-8000-000000000002',
            'translation_key' => null,
            'type' => 'stats',
            'label' => 'Impact',
            'content' => [
                'heading' => 'School impact',
                'items' => [['value' => '100+', 'label' => 'Graduates', 'icon' => 'report']],
            ],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $library = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared English appeal',
            'type' => 'cta',
            'locale' => '*',
            'content' => [
                'heading' => 'Help every child learn',
                'primary_url' => '/donate',
                'value' => '35',
                'selection_mode' => 'manual',
                'selected_items' => ['uuid-a', 'uuid-b'],
                'items' => [[
                    'heading' => 'First milestone',
                    'status' => 'Completed',
                    'platform' => 'facebook',
                    'url' => 'https://example.test/story',
                ]],
            ],
            'settings' => ['tone' => 'warm'],
            'is_enabled' => true,
        ]);
        $reusableSourceBlock = PageBlock::create([
            'page_id' => $categorized->id,
            'reusable_block_id' => $library->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => null,
            'type' => 'cta',
            'label' => 'Shared appeal',
            'content' => ['heading' => 'Stale local copy'],
            'settings' => [],
            'sort_order' => 2,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$categorized->id],
            'action' => 'translate',
            'target_language' => 'bn',
        ])->assertOk()->assertJsonPath('created', 1);

        $translation = Page::where('uuid', $categorized->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertSame((string) $banglaCategory->id, $translation->category_id);
        $this->assertSame('', $translation->name);
        $this->assertSame('', $translation->sub_title);
        $this->assertSame('', $translation->description);
        $translatedBlock = $translation->blocks()->where('translation_key', $sourceBlock->uuid)->firstOrFail();
        $this->assertSame($sourceBlock->uuid, $translatedBlock->translation_key);
        $this->assertSame('', $translatedBlock->content['heading']);
        $this->assertSame('', $translatedBlock->content['items'][0]['label']);
        $this->assertSame('100+', $translatedBlock->content['items'][0]['value']);
        $translatedReusable = $translation->blocks()
            ->where('translation_key', $reusableSourceBlock->uuid)
            ->firstOrFail();
        $this->assertNull($translatedReusable->reusable_block_id);
        $this->assertSame('', $translatedReusable->content['heading']);
        $this->assertSame('/donate', $translatedReusable->content['primary_url']);
        $this->assertSame('35', $translatedReusable->content['value']);
        $this->assertSame('manual', $translatedReusable->content['selection_mode']);
        $this->assertSame(['uuid-a', 'uuid-b'], $translatedReusable->content['selected_items']);
        $this->assertSame('', $translatedReusable->content['items'][0]['status']);
        $this->assertSame('facebook', $translatedReusable->content['items'][0]['platform']);
        $this->assertSame('https://example.test/story', $translatedReusable->content['items'][0]['url']);
        $this->assertSame(['tone' => 'warm'], $translatedReusable->settings);

        $service = app(TranslationCenterService::class);
        $blockHeading = $service->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'block'
            && ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
            && ($row['identity']['path'] ?? null) === 'heading'
        );
        $this->assertNotNull($blockHeading);
        $this->assertSame('missing', $blockHeading['status']);
        $reusableHeading = $service->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'block'
            && ($row['identity']['source_block_id'] ?? null) === $reusableSourceBlock->id
            && ($row['identity']['path'] ?? null) === 'heading'
        );
        $this->assertNotNull($reusableHeading);
        $this->assertSame('Help every child learn', $reusableHeading['source']);
        $this->assertSame('missing', $reusableHeading['status']);
        $this->assertFalse($service->rows('en', 'bn')->contains(
            fn (array $row) => in_array($row['source'], ['uuid-a', 'uuid-b', 'facebook'], true)
        ));
        $statusRow = $service->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['source_block_id'] ?? null) === $reusableSourceBlock->id
            && ($row['identity']['path'] ?? null) === 'items.0.status'
        );
        $this->assertNotNull($statusRow);
        $this->assertSame('Completed', $statusRow['source']);
        $this->assertSame('missing', $statusRow['status']);
        $service->save('en', 'bn', [[
            'key' => $blockHeading['key'],
            'precondition' => $blockHeading['precondition'],
            'value' => 'স্কুলের প্রভাব',
        ], [
            'key' => $reusableHeading['key'],
            'precondition' => $reusableHeading['precondition'],
            'value' => 'প্রতিটি শিশুকে শিখতে সহায়তা করুন',
        ]], null);
        $this->assertSame(2, $translation->blocks()->count());
        $this->assertSame('স্কুলের প্রভাব', $translatedBlock->fresh()->content['heading']);
        $this->assertSame(
            'প্রতিটি শিশুকে শিখতে সহায়তা করুন',
            $translatedReusable->fresh()->content['heading']
        );

        $metricMigration = require database_path('migrations/2026_08_19_120200_remove_unverified_school_graduate_metric.php');
        $metricMigration->up();
        $this->assertNotContains(
            '100+',
            array_column($translatedBlock->fresh()->content['items'], 'value')
        );

        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$categorized->id],
            'action' => 'duplicate',
        ])->assertOk()->assertJsonPath('created', 1);
        $duplicate = Page::where('name', 'Education landing content (Copy)')->firstOrFail();
        $this->assertSame((string) $englishCategory->id, $duplicate->category_id);
        $this->assertSame('en', $duplicate->language);

        $uncategorized = $this->makePage([
            'name' => 'Uncategorized page',
            'category_id' => null,
        ]);
        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$uncategorized->id],
            'action' => 'translate',
            'target_language' => 'bn',
        ])->assertOk()->assertJsonPath('created', 1);
        $this->assertNull(Page::where('uuid', $uncategorized->uuid)
            ->where('language', 'bn')
            ->firstOrFail()
            ->category_id);
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Landing page content',
            'sub_title' => 'A localized landing page.',
            'slug' => 'landing-page-' . Str::lower(Str::random(8)),
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function authorizedAdmin(): Admin
    {
        $menu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        $edit = MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Edit pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);
        $role = Role::create([
            'name' => 'Landing translator',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $edit->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Landing Translator',
            'username' => 'landing-translator',
            'email' => 'landing-translator@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
