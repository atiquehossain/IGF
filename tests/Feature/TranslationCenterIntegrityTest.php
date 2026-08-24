<?php

namespace Tests\Feature;

use App\Helper\Translation;
use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageMenu;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use App\Models\TranslationString;
use App\Services\PageEditorVersionService;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TranslationCenterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_sees_one_excel_style_translation_center_with_missing_progress(): void
    {
        $admin = $this->makePageEditor();
        $this->makeEnglishPage();

        $this->actingAs($admin, 'admin')
            ->get(route('translations.index'))
            ->assertOk()
            ->assertSee('Translation Center')
            ->assertSee('English source')
            ->assertSee('Bangla translation')
            ->assertSee('Missing required')
            ->assertSee('Optional / unpublished')
            ->assertSee('Required public rows')
            ->assertSee('Content Hub')
            ->assertSee('Enable Bangla')
            ->assertSee('disabled', false)
            ->assertSee('][precondition]', false)
            ->assertSee('pagination')
            ->assertDontSee('<svg', false);
    }

    public function test_bulk_save_writes_to_real_page_block_menu_setting_and_interface_records(): void
    {
        $admin = $this->makePageEditor();
        [$page, $block] = $this->makeEnglishPage();
        $menu = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs',
            'description' => 'Explore our programs',
            'type' => 'main',
            'link' => 'frontend.home',
            'language' => 'en',
            'order_by' => 1,
            'status' => 1,
        ]);

        $rows = app(TranslationCenterService::class)->rows()->keyBy(fn (array $row) => json_encode($row['identity']));
        $pageRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'page' && $row['identity']['source_id'] === $page->id && $row['identity']['field'] === 'name');
        $blockRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'block' && $row['identity']['source_block_id'] === $block->id && $row['identity']['path'] === 'heading');
        $menuRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'menu' && $row['identity']['source_id'] === $menu->id && $row['identity']['field'] === 'name');
        $menuDescriptionRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'menu' && $row['identity']['source_id'] === $menu->id && $row['identity']['field'] === 'description');
        $settingRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'setting' && $row['identity']['group'] === 'header' && $row['identity']['field'] === 'annual_reports_label');
        $interfaceRow = $rows->first(fn (array $row) => $row['identity']['type'] === 'interface');

        $translations = [
            ['key' => $pageRow['key'], 'precondition' => $pageRow['precondition'], 'value' => 'আমাদের কাজ'],
            ['key' => $blockRow['key'], 'precondition' => $blockRow['precondition'], 'value' => 'আশা থেকে পরিবর্তন'],
            ['key' => $menuRow['key'], 'precondition' => $menuRow['precondition'], 'value' => 'কর্মসূচি'],
            ['key' => $menuDescriptionRow['key'], 'precondition' => $menuDescriptionRow['precondition'], 'value' => 'আমাদের কর্মসূচি দেখুন'],
            ['key' => $settingRow['key'], 'precondition' => $settingRow['precondition'], 'value' => 'বার্ষিক প্রতিবেদন'],
            ['key' => $interfaceRow['key'], 'precondition' => $interfaceRow['precondition'], 'value' => 'বাংলা ইন্টারফেস লেখা'],
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('translations.update'), [
                'source_locale' => 'en',
                'target_locale' => 'bn',
                'translations' => $translations,
            ])
            ->assertRedirect();

        $banglaPage = Page::where('uuid', $page->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertSame('আমাদের কাজ', $banglaPage->name);
        $this->assertSame('draft', $banglaPage->publication_status);
        $this->assertFalse((bool) $banglaPage->status);
        $this->assertSame('আশা থেকে পরিবর্তন', $banglaPage->blocks()->where('translation_key', $block->translation_key)->firstOrFail()->content['heading']);
        $this->assertDatabaseHas('page_menus', ['uuid' => $menu->uuid, 'language' => 'bn', 'name' => 'কর্মসূচি', 'description' => 'আমাদের কর্মসূচি দেখুন', 'status' => 0]);
        $this->assertDatabaseHas('site_settings', ['group' => 'header', 'key' => 'annual_reports_label', 'locale' => 'bn', 'value' => 'বার্ষিক প্রতিবেদন']);
        $this->assertDatabaseHas('translation_strings', ['key' => $interfaceRow['identity']['path'], 'locale' => 'bn', 'value' => 'বাংলা ইন্টারফেস লেখা', 'status' => 'translated']);
        $this->assertSame(
            [1],
            Page::where('uuid', $page->uuid)->pluck('editor_version')->unique()->values()->all(),
            'One mixed Page/PageBlock batch must advance its logical Page generation exactly once.'
        );
    }

    public function test_stale_page_block_batch_is_rejected_before_any_row_is_written(): void
    {
        [$sourcePage, $sourceBlock] = $this->makeEnglishPage();
        $service = app(TranslationCenterService::class);
        $initialRows = $service->rows('en', 'bn');
        $initialName = $initialRows->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'page'
            && ($row['identity']['source_id'] ?? null) === $sourcePage->id
            && ($row['identity']['field'] ?? null) === 'name'
        );
        $initialHeading = $initialRows->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'block'
            && ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
            && ($row['identity']['path'] ?? null) === 'heading'
        );

        $service->save('en', 'bn', [[
            'key' => $initialName['key'],
            'precondition' => $initialName['precondition'],
            'value' => 'আমাদের কাজ',
        ], [
            'key' => $initialHeading['key'],
            'precondition' => $initialHeading['precondition'],
            'value' => 'প্রথম অনুবাদ',
        ]], null);

        $staleRows = $service->rows('en', 'bn');
        $staleName = $staleRows->firstWhere('key', $initialName['key']);
        $staleHeading = $staleRows->firstWhere('key', $initialHeading['key']);
        $targetPage = Page::where('uuid', $sourcePage->uuid)->where('language', 'bn')->firstOrFail();
        $targetBlock = $targetPage->blocks()->where('translation_key', $sourceBlock->translation_key)->firstOrFail();
        $newerContent = $targetBlock->content;
        data_set($newerContent, 'heading', 'বিল্ডারের নতুন লেখা');
        $targetBlock->update(['content' => $newerContent]);
        app(PageEditorVersionService::class)->advance((string) $sourcePage->uuid);

        try {
            $service->save('en', 'bn', [[
                'key' => $staleName['key'],
                'precondition' => $staleName['precondition'],
                'value' => 'এটি লেখা উচিত নয়',
            ], [
                'key' => $staleHeading['key'],
                'precondition' => $staleHeading['precondition'],
                'value' => 'পুরোনো ট্যাবের লেখা',
            ]], null);
            $this->fail('A stale Translation Center batch should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(409, $exception->status);
            $this->assertStringContainsString('Nothing was saved', $exception->errors()['translations'][0]);
        }

        $this->assertSame('আমাদের কাজ', $targetPage->fresh()->name);
        $this->assertSame('বিল্ডারের নতুন লেখা', $targetBlock->fresh()->content['heading']);
        $this->assertSame(
            [2],
            Page::where('uuid', $sourcePage->uuid)->pluck('editor_version')->unique()->values()->all()
        );
    }

    public function test_translation_update_requires_the_signed_row_precondition(): void
    {
        $admin = $this->makePageEditor();
        [$page] = $this->makeEnglishPage();
        $row = app(TranslationCenterService::class)->rows('en', 'bn')->first(fn (array $candidate) =>
            ($candidate['identity']['type'] ?? null) === 'page'
            && ($candidate['identity']['source_id'] ?? null) === $page->id
            && ($candidate['identity']['field'] ?? null) === 'name'
        );

        $this->actingAs($admin, 'admin')
            ->put(route('translations.update'), [
                'source_locale' => 'en',
                'target_locale' => 'bn',
                'translations' => [[
                    'key' => $row['key'],
                    'value' => 'প্রাকশর্ত ছাড়া লেখা',
                ]],
            ])
            ->assertSessionHasErrors('translations.0.precondition');

        $this->assertDatabaseMissing('pages', [
            'uuid' => $page->uuid,
            'language' => 'bn',
        ]);
    }

    public function test_tampered_row_precondition_is_rejected_with_conflict_status(): void
    {
        [$page] = $this->makeEnglishPage();
        $service = app(TranslationCenterService::class);
        $row = $service->rows('en', 'bn')->first(fn (array $candidate) =>
            ($candidate['identity']['type'] ?? null) === 'page'
            && ($candidate['identity']['source_id'] ?? null) === $page->id
            && ($candidate['identity']['field'] ?? null) === 'name'
        );
        $tampered = str_repeat($row['precondition'][0] === 'a' ? 'b' : 'a', 64);

        try {
            $service->save('en', 'bn', [[
                'key' => $row['key'],
                'precondition' => $tampered,
                'value' => 'পরিবর্তিত লেখা',
            ]], null);
            $this->fail('A forged row precondition should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(409, $exception->status);
        }

        $this->assertDatabaseMissing('pages', [
            'uuid' => $page->uuid,
            'language' => 'bn',
        ]);
    }

    public function test_block_uuid_fields_remain_hidden_machine_identity_and_survive_translation_save(): void
    {
        [$page] = $this->makeEnglishPage();
        $projectUuid = (string) Str::uuid();
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'ways_to_give',
            'label' => 'Ways to Give',
            'content' => [
                'heading' => 'Support a project',
                'selection_mode' => 'manual',
                'selected_items' => ['cause:' . Str::uuid()],
                'project_uuid' => $projectUuid,
                'layout' => 'single_cta',
            ],
            'sort_order' => 2,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $service = app(TranslationCenterService::class);
        $prepared = $service->prepareBlockTranslationContent($block->content);

        $this->assertSame('', $prepared['heading']);
        $this->assertSame($projectUuid, $prepared['project_uuid']);
        $this->assertSame($block->content['selected_items'], $prepared['selected_items']);

        $blockRows = $service->rows('en', 'bn')->filter(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'block'
            && ($row['identity']['source_block_id'] ?? null) === $block->id
        );
        $this->assertFalse($blockRows->contains(fn (array $row) => ($row['identity']['path'] ?? null) === 'project_uuid'));
        $headingRow = $blockRows->firstWhere('identity.path', 'heading');
        $this->assertNotNull($headingRow);

        $service->save('en', 'bn', [[
            'key' => $headingRow['key'],
            'precondition' => $headingRow['precondition'],
            'value' => 'একটি প্রকল্পে সহায়তা করুন',
        ]], null);

        $translatedPage = Page::where('uuid', $page->uuid)->where('language', 'bn')->firstOrFail();
        $translatedBlock = $translatedPage->blocks()->where('translation_key', $block->translation_key)->firstOrFail();
        $this->assertSame('একটি প্রকল্পে সহায়তা করুন', $translatedBlock->content['heading']);
        $this->assertSame($projectUuid, $translatedBlock->content['project_uuid']);
        $this->assertSame($block->content['selected_items'], $translatedBlock->content['selected_items']);
    }

    public function test_media_choice_and_sources_are_machine_fields_while_the_caption_is_translatable(): void
    {
        [$page] = $this->makeEnglishPage();
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'media_text',
            'label' => 'Media story',
            'content' => [
                'heading' => 'Community story',
                'media_type' => 'youtube',
                'video_url' => '/storage/media/community.mp4',
                'youtube_url' => 'youtube.com/watch?v=abcdefghijk',
                'poster' => '/storage/media/community-poster.jpg',
                'caption' => 'Children describe their learning project.',
            ],
            'sort_order' => 3,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $service = app(TranslationCenterService::class);
        $prepared = $service->prepareBlockTranslationContent($block->content);

        $this->assertSame('youtube', $prepared['media_type']);
        $this->assertSame($block->content['video_url'], $prepared['video_url']);
        $this->assertSame($block->content['youtube_url'], $prepared['youtube_url']);
        $this->assertSame($block->content['poster'], $prepared['poster']);
        $this->assertSame('', $prepared['caption']);

        $paths = $service->rows('en', 'bn')
            ->filter(fn (array $row) =>
                ($row['identity']['type'] ?? null) === 'block'
                && ($row['identity']['source_block_id'] ?? null) === $block->id
            )
            ->pluck('identity.path');

        $this->assertFalse($paths->contains('media_type'));
        $this->assertFalse($paths->contains('video_url'));
        $this->assertFalse($paths->contains('youtube_url'));
        $this->assertFalse($paths->contains('poster'));
        $this->assertTrue($paths->contains('caption'));
    }

    public function test_bangla_cannot_be_enabled_while_required_cells_are_missing(): void
    {
        $admin = $this->makePageEditor();
        $this->makeEnglishPage();

        $this->actingAs($admin, 'admin')
            ->from(route('translations.index'))
            ->put(route('translations.toggle'), [
                'source_locale' => 'en',
                'target_locale' => 'bn',
                'enabled' => true,
            ])
            ->assertRedirect(route('translations.index'))
            ->assertSessionHasErrors('enabled');

        $this->assertFalse(TranslationLocale::findOrFail('bn')->is_enabled);
    }

    public function test_only_currently_public_active_source_rows_count_toward_completion(): void
    {
        $admin = $this->makePageEditor();
        [$publicPage, $visibleBlock] = $this->makeEnglishPage();
        $disabledBlock = PageBlock::create([
            'page_id' => $publicPage->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Disabled section',
            'content' => ['heading' => 'Disabled section copy'],
            'sort_order' => 2,
            'is_enabled' => false,
        ]);
        $futureBlock = PageBlock::create([
            'page_id' => $publicPage->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Future section',
            'content' => ['heading' => 'Future section copy'],
            'sort_order' => 3,
            'is_enabled' => true,
            'available_from' => now()->addDay(),
        ]);
        $draftPage = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Draft-only source page',
            'sub_title' => 'Draft-only introduction',
            'slug' => 'draft-only-source-page',
            'description' => 'Prepare this translation before publishing.',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'draft',
            'visibility' => 'public',
        ]);
        $privatePage = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Private source page',
            'sub_title' => 'Private introduction',
            'slug' => 'private-source-page',
            'description' => 'Private source copy.',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'private',
        ]);
        $activeMenu = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active menu source',
            'type' => 'main',
            'link' => 'frontend.home',
            'language' => 'en',
            'order_by' => 1,
            'status' => 1,
        ]);
        $inactiveMenu = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive menu source',
            'type' => 'main',
            'link' => 'frontend.home',
            'language' => 'en',
            'order_by' => 2,
            'status' => 0,
        ]);
        $activeBanner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active banner source',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 1,
        ]);
        $inactiveBanner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive banner source',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 0,
        ]);

        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn');
        $find = fn (string $type, int $sourceId, string $field) => $rows->first(
            fn (array $row) => ($row['identity']['type'] ?? null) === $type
                && ($row['identity']['source_id'] ?? $row['identity']['source_block_id'] ?? null) === $sourceId
                && (($row['identity']['field'] ?? $row['identity']['path'] ?? null) === $field)
        );

        $selected = collect([
            $find('page', $publicPage->id, 'name'),
            $find('block', $visibleBlock->id, 'heading'),
            $find('block', $disabledBlock->id, 'heading'),
            $find('block', $futureBlock->id, 'heading'),
            $find('page', $draftPage->id, 'name'),
            $find('page', $privatePage->id, 'name'),
            $find('menu', $activeMenu->id, 'name'),
            $find('menu', $inactiveMenu->id, 'name'),
            $find('content', $activeBanner->id, 'name'),
            $find('content', $inactiveBanner->id, 'name'),
        ]);

        $this->assertFalse($selected->contains(null));
        $this->assertSame([true, true, false, false, false, false, true, false, true, false], $selected->pluck('required')->all());
        $this->assertSame([
            'total' => 4,
            'translated' => 0,
            'missing' => 4,
            'percent' => 0,
            'available_total' => 10,
            'optional' => 6,
        ], $service->summary($selected));

        $this->actingAs($admin, 'admin')
            ->get(route('translations.index', ['status' => 'optional', 'search' => 'Draft-only source page']))
            ->assertOk()
            ->assertSee('Draft-only source page')
            ->assertSee('Optional until public');

        $this->actingAs($admin, 'admin')
            ->get(route('translations.index', ['status' => 'missing', 'search' => 'Draft-only source page']))
            ->assertOk()
            ->assertDontSee('Prepare this translation before publishing.')
            ->assertSee('No translation rows match these filters.');
    }

    public function test_enabled_database_locale_controls_the_public_switch_and_dictionary_overlay(): void
    {
        TranslationLocale::where('locale', 'bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        TranslationString::create([
            'key' => 'vue.demo_label',
            'locale' => 'bn',
            'value' => 'ডেমো',
            'source_hash' => hash('sha256', 'demo'),
            'status' => 'translated',
        ]);

        $this->get('/language/bn')->assertRedirect();
        $this->assertSame('bn', session('locale'));
        $this->assertSame('ডেমো', data_get(Translation::language('bn'), 'vue.demo_label'));
    }

    public function test_banner_structured_copy_and_navigation_descriptions_are_required_translation_rows(): void
    {
        $banner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => '<b>Legacy headline</b> Legacy support',
            'eyebrow' => 'Our work',
            'headline' => 'Communities lead change',
            'subheadline' => 'We work in partnership',
            'description' => 'A managed banner description.',
            'image_alt' => 'People meeting outdoors',
            'cta_label' => 'Learn more',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 1,
        ]);

        $fields = app(TranslationCenterService::class)->rows('en', 'bn')
            ->filter(fn (array $row) => ($row['identity']['type'] ?? null) === 'content'
                && ($row['identity']['model'] ?? null) === 'banner'
                && ($row['identity']['source_id'] ?? null) === $banner->id)
            ->pluck('identity.field')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['name', 'eyebrow', 'headline', 'subheadline', 'description', 'image_alt', 'cta_label'],
            $fields
        );
    }

    public function test_translation_center_excludes_legacy_seo_fields_and_does_not_copy_them_to_new_translations(): void
    {
        [$page] = $this->makeEnglishPage();
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Education',
            'slug' => 'education',
            'description' => '<p>Education programs.</p>',
            'meta_title' => 'Legacy English title',
            'meta_keyword' => 'legacy keyword',
            'meta_description' => 'Legacy English description.',
            'language' => 'en',
            'status' => 1,
        ]);

        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn');
        $legacyFields = ['meta_title', 'meta_keyword', 'meta_description'];
        $this->assertFalse($rows->contains(fn (array $row) => in_array($row['identity']['field'] ?? null, $legacyFields, true)));

        $categoryName = $rows->first(fn (array $row) => ($row['identity']['type'] ?? null) === 'content'
            && ($row['identity']['model'] ?? null) === 'category'
            && ($row['identity']['source_id'] ?? null) === $category->id
            && ($row['identity']['field'] ?? null) === 'name');
        $this->assertNotNull($categoryName);
        $service->save('en', 'bn', [[
            'key' => $categoryName['key'],
            'precondition' => $categoryName['precondition'],
            'value' => 'শিক্ষা',
        ]], null);

        $translated = Category::where('uuid', $category->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertSame('শিক্ষা', $translated->name);
        $this->assertSame('', (string) $translated->meta_title);
        $this->assertSame('', (string) $translated->meta_keyword);
        $this->assertSame('', (string) $translated->meta_description);
        $this->assertSame('Our work', $page->meta_title);
    }

    public function test_event_translation_created_by_translation_center_keeps_the_source_identity(): void
    {
        $event = NoticeBoard::create([
            'title' => 'Community learning day',
            'sub_title' => 'Families and volunteers learn together.',
            'slug' => 'community-learning-day',
            'description' => '<p>A public learning event.</p>',
            'language' => 'en',
            'status' => 1,
            'published_at' => now()->subDay(),
        ]);

        $service = app(TranslationCenterService::class);
        $titleRow = $service->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'content'
            && ($row['identity']['model'] ?? null) === 'event'
            && ($row['identity']['source_id'] ?? null) === $event->id
            && ($row['identity']['field'] ?? null) === 'title'
        );

        $this->assertNotNull($titleRow);
        $service->save('en', 'bn', [[
            'key' => $titleRow['key'],
            'precondition' => $titleRow['precondition'],
            'value' => 'কমিউনিটি শিক্ষা দিবস',
        ]], null);

        $translated = NoticeBoard::where('translation_key', $event->translation_key)
            ->where('language', 'bn')
            ->firstOrFail();
        $this->assertSame('কমিউনিটি শিক্ষা দিবস', $translated->title);
        $this->assertSame($event->translation_key, $translated->translation_key);
        $this->assertFalse((bool) $translated->status);
    }

    public function test_annual_report_translation_pair_survives_a_source_permalink_change(): void
    {
        $english = AnnualReport::create([
            'title' => 'Annual impact report',
            'slug' => 'annual-impact-report',
            'description' => 'English accountability report.',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'status' => 1,
        ]);
        $bangla = AnnualReport::create([
            'translation_key' => $english->translation_key,
            'title' => 'বার্ষিক প্রভাব প্রতিবেদন',
            'slug' => 'bangla-impact-report',
            'description' => 'বাংলা জবাবদিহিতা প্রতিবেদন।',
            'language' => 'bn',
            'published_at' => now()->subDay(),
            'status' => 0,
        ]);
        $english->update(['slug' => 'annual-impact-report-updated']);

        $service = app(TranslationCenterService::class);
        $row = $service->rows('en', 'bn')->first(fn (array $candidate): bool =>
            ($candidate['identity']['type'] ?? null) === 'content'
            && ($candidate['identity']['model'] ?? null) === 'annual_report'
            && ($candidate['identity']['source_id'] ?? null) === $english->id
            && ($candidate['identity']['field'] ?? null) === 'title'
        );

        $this->assertNotNull($row);
        $this->assertSame($bangla->title, $row['target']);
        $service->save('en', 'bn', [[
            'key' => $row['key'],
            'precondition' => $row['precondition'],
            'value' => 'হালনাগাদ বার্ষিক প্রতিবেদন',
        ]], null);

        $this->assertSame(2, AnnualReport::where('translation_key', $english->translation_key)->count());
        $this->assertSame('হালনাগাদ বার্ষিক প্রতিবেদন', $bangla->fresh()->title);
    }

    public function test_translation_save_rejects_missing_single_brace_placeholders(): void
    {
        $service = app(TranslationCenterService::class);
        $row = $service->rows('en', 'bn')->first(fn (array $candidate) =>
            ($candidate['identity']['type'] ?? null) === 'setting'
            && ($candidate['identity']['group'] ?? null) === 'shared_blocks'
            && ($candidate['identity']['field'] ?? null) === 'team_profile_accessible_label'
        );
        $this->assertNotNull($row);

        $this->expectException(ValidationException::class);
        $service->save('en', 'bn', [[
            'key' => $row['key'],
            'precondition' => $row['precondition'],
            'value' => 'View the team profile',
        ]], null);
    }

    public function test_completed_locale_records_follow_the_source_publication_state(): void
    {
        [$sourcePage] = $this->makeEnglishPage();
        $targetPage = $sourcePage->replicate();
        $targetPage->language = 'bn';
        $targetPage->name = 'আমাদের কাজ';
        $targetPage->status = 0;
        $targetPage->publication_status = 'draft';
        $targetPage->save();

        $sourceMenu = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs',
            'type' => 'main',
            'link' => 'custom',
            'slug' => '/programs',
            'language' => 'en',
            'order_by' => 1,
            'status' => 1,
        ]);
        $targetMenu = $sourceMenu->replicate();
        $targetMenu->language = 'bn';
        $targetMenu->name = 'কর্মসূচি';
        $targetMenu->status = 0;
        $targetMenu->save();

        $sourceBanner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Home banner',
            'headline' => 'Act together',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 1,
        ]);
        $targetBanner = $sourceBanner->replicate();
        $targetBanner->language = 'bn';
        $targetBanner->name = 'হোম ব্যানার';
        $targetBanner->headline = 'একসাথে কাজ করি';
        $targetBanner->status = 0;
        $targetBanner->save();

        $counts = app(TranslationCenterService::class)->syncPublicationState('en', 'bn');

        $this->assertGreaterThanOrEqual(1, $counts['pages']);
        $this->assertGreaterThanOrEqual(1, $counts['menus']);
        $this->assertGreaterThanOrEqual(1, $counts['content']);
        $this->assertTrue((bool) $targetPage->fresh()->status);
        $this->assertSame('published', $targetPage->fresh()->publication_status);
        $this->assertTrue((bool) $targetMenu->fresh()->status);
        $this->assertTrue((bool) $targetBanner->fresh()->status);

        $version = (int) $targetPage->fresh()->editor_version;
        app(TranslationCenterService::class)->syncPublicationState('en', 'bn');
        $this->assertSame($version, (int) $targetPage->fresh()->editor_version);
    }

    public function test_publication_sync_prelocks_shared_seo_owners_in_the_canonical_order(): void
    {
        $categoryUuid = (string) Str::uuid();
        Category::create([
            'uuid' => $categoryUuid,
            'name' => 'Education',
            'slug' => 'education',
            'language' => 'en',
            'status' => 1,
        ]);
        $targetCategory = Category::create([
            'uuid' => $categoryUuid,
            'name' => 'শিক্ষা',
            'slug' => 'education',
            'language' => 'bn',
            'status' => 0,
        ]);

        $event = NoticeBoard::create([
            'title' => 'Community event',
            'slug' => 'community-event',
            'language' => 'en',
            'status' => 1,
        ]);
        $targetEvent = NoticeBoard::create([
            'translation_key' => $event->translation_key,
            'title' => 'কমিউনিটি অনুষ্ঠান',
            'slug' => 'community-event',
            'language' => 'bn',
            'status' => 0,
        ]);

        $report = AnnualReport::create([
            'title' => 'Impact report',
            'slug' => 'impact-report',
            'language' => 'en',
            'status' => 1,
        ]);
        $targetReport = AnnualReport::create([
            'translation_key' => $report->translation_key,
            'title' => 'প্রভাব প্রতিবেদন',
            'slug' => 'impact-report',
            'language' => 'bn',
            'status' => 0,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TranslationCenterService::class)->syncPublicationState('en', 'bn');
        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $query): string => strtolower($query));
        DB::disableQueryLog();

        $firstPrimaryKeyOrderedRead = function (string $table) use ($queries): int {
            $tablePattern = preg_quote($table, '/');
            $position = $queries->search(fn (string $query): bool =>
                preg_match('/\bfrom\s+[`"]?' . $tablePattern . '[`"]?\b/i', $query) === 1
                && preg_match('/\border\s+by\s+(?:[`"]?' . $tablePattern . '[`"]?\.)?[`"]?id[`"]?\s+asc\b/i', $query) === 1
            );
            $this->assertNotFalse($position, "Expected a primary-key ordered {$table} lock read.");

            return (int) $position;
        };

        $categoryLock = $firstPrimaryKeyOrderedRead('categories');
        $eventLock = $firstPrimaryKeyOrderedRead('notice_boards');
        $reportLock = $firstPrimaryKeyOrderedRead('annual_reports');
        $this->assertLessThan($eventLock, $categoryLock);
        $this->assertLessThan($reportLock, $eventLock);
        $this->assertTrue((bool) $targetCategory->fresh()->status);
        $this->assertTrue((bool) $targetEvent->fresh()->status);
        $this->assertTrue((bool) $targetReport->fresh()->status);
    }

    private function makeEnglishPage(): array
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Our work',
            'sub_title' => 'Work led by communities',
            'slug' => 'our-work',
            'description' => '<p>Learn about our work.</p>',
            'meta_title' => 'Our work',
            'meta_keyword' => 'community',
            'meta_description' => 'Community-led programs.',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
        $translationKey = (string) Str::uuid();
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => $translationKey,
            'type' => 'hero',
            'label' => 'Opening hero',
            'content' => [
                'heading' => 'Hope becomes change',
                'body' => 'Community action creates lasting results.',
                'primary_label' => 'Learn more',
                'primary_url' => '/about-us',
                'image' => '/image/hero.jpg',
            ],
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        return [$page, $block];
    }

    private function makePageEditor(): Admin
    {
        $menu = AuthMenu::where('link', 'translations.index')->firstOrFail();
        $actions = MenuAction::whereIn('link', ['translations.edit', 'translations.status'])->get();
        $role = Role::create([
            'name' => 'Translator',
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Translation Editor',
            'username' => 'translation-editor',
            'email' => 'translator@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
