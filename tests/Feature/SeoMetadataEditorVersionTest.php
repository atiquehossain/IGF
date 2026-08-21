<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use App\Models\Tag;
use App\Models\TranslationLocale;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataService;
use App\Services\SeoRouteRegistry;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeoMetadataEditorVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_route_and_dynamic_editors_reject_stale_tokens_and_accept_current_tokens(): void
    {
        $admin = $this->admin();
        $versions = app(SeoMetadataEditorVersionService::class);
        $routeName = 'frontend.contactUs';
        $routePath = (string) app(SeoRouteRegistry::class)->path($routeName);
        $missingRoute = $versions->currentForRoute($routeName, $routePath, 'en');

        $this->actingAs($admin, 'admin')
            ->put(route('seo.update'), $this->routePayload($routeName, 'First route title', $missingRoute))
            ->assertRedirect()->assertSessionHasNoErrors();
        $routeMetadata = SeoMetadata::where('route_name', $routeName)->where('locale', 'en')->firstOrFail();
        $this->assertSame(1, $routeMetadata->editor_version);

        $this->put(route('seo.update'), $this->routePayload($routeName, 'Stale route title', $missingRoute))
            ->assertStatus(409);
        $this->assertSame('First route title', $routeMetadata->fresh()->title);
        $this->put(route('seo.update'), $this->routePayload(
            $routeName,
            'Current route title',
            $versions->currentForRoute($routeName, $routePath, 'en')
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $routeMetadata->fresh()->editor_version);

        $category = $this->category('seo-generation-owner');
        $missingCategory = $versions->currentForModel($category, 'en');
        $this->put(
            route('seo.content.update', ['category', $category->id]),
            $this->contentPayload('First category title', $missingCategory)
        )->assertRedirect()->assertSessionHasNoErrors();
        $categoryMetadata = SeoMetadata::where('seoable_type', Category::class)
            ->where('seoable_id', $category->id)->where('locale', 'en')->firstOrFail();

        $this->put(
            route('seo.content.update', ['category', $category->id]),
            $this->contentPayload('Stale category title', $missingCategory)
        )->assertStatus(409);
        $this->assertSame('First category title', $categoryMetadata->fresh()->title);

        $beforeOwnerChange = $versions->currentForModel($category->fresh(), 'en');
        $category->forceFill(['name' => 'Owner title changed elsewhere'])->save();
        $this->put(
            route('seo.content.update', ['category', $category->id]),
            $this->contentPayload('Unseen owner overwrite', $beforeOwnerChange)
        )->assertStatus(409);
        $this->assertSame('First category title', $categoryMetadata->fresh()->title);

        $this->put(
            route('seo.content.update', ['category', $category->id]),
            $this->contentPayload(
                'Current category title',
                $versions->currentForModel($category->fresh(), 'en')
            )
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Current category title', $categoryMetadata->fresh()->title);
        $this->assertSame(2, $categoryMetadata->fresh()->editor_version);
    }

    public function test_bulk_rejects_one_stale_non_page_row_atomically_and_binds_tokens_to_identity(): void
    {
        $admin = $this->admin();
        $versions = app(SeoMetadataEditorVersionService::class);
        $first = $this->category('bulk-generation-first');
        $second = $this->category('bulk-generation-second');
        $staleFirst = $versions->currentForModel($first, 'en');
        $secondToken = $versions->currentForModel($second, 'en');
        app(SeoMetadataService::class)->updateForModel($first, [
            'title' => 'Newer first title',
            'description' => $this->description(),
        ], 'en');

        $this->actingAs($admin, 'admin')->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem($first, 'Stale first bulk title', $staleFirst),
            $this->bulkItem($second, 'Second row must roll back', $secondToken),
        ]])->assertStatus(409);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $first->id,
            'title' => 'Newer first title',
            'editor_version' => 1,
        ]);
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $second->id,
        ]);

        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem($first, 'Current first bulk title', $versions->currentForModel($first->fresh(), 'en')),
            $this->bulkItem($second, 'Current second bulk title', $versions->currentForModel($second->fresh(), 'en')),
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', ['seoable_id' => $first->id, 'title' => 'Current first bulk title']);
        $this->assertDatabaseHas('seo_metadata', ['seoable_id' => $second->id, 'title' => 'Current second bulk title']);

        $secondBefore = SeoMetadata::where('seoable_type', Category::class)
            ->where('seoable_id', $second->id)->firstOrFail();
        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem($second, 'Token from another owner', $versions->currentForModel($first->fresh(), 'en')),
        ]])->assertStatus(409);
        $this->assertSame('Current second bulk title', $secondBefore->fresh()->title);
        $this->assertSame(1, $secondBefore->fresh()->editor_version);
    }

    public function test_non_page_revision_restore_requires_the_loaded_seo_generation(): void
    {
        $admin = $this->admin();
        $category = $this->category('restore-generation-owner');
        $metadata = app(SeoMetadataService::class)->updateForModel($category, [
            'title' => 'Historical category title',
            'description' => $this->description(),
        ], 'en');
        $revision = app(SeoMetadataRevisionService::class)->capture($metadata, 'Historical category SEO');
        $versions = app(SeoMetadataEditorVersionService::class);
        $staleToken = $versions->currentForModel($category, 'en');
        app(SeoMetadataService::class)->updateForModel($category, [
            'title' => 'Current category title',
            'description' => $this->description(),
        ], 'en');
        $historyCount = SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count();

        $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['category', $category->id]))
            ->assertOk()
            ->assertSee('name="expected_seo_version"', false);
        $this->post(route('seo.revisions.restore', $revision), ['expected_seo_version' => $staleToken])
            ->assertStatus(409);
        $this->assertSame('Current category title', $metadata->fresh()->title);
        $this->assertSame(2, $metadata->fresh()->editor_version);
        $this->assertSame($historyCount, SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count());

        $this->post(route('seo.revisions.restore', $revision), [
            'expected_seo_version' => $versions->currentForModel($category->fresh(), 'en'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Historical category title', $metadata->fresh()->title);
        $this->assertSame(3, $metadata->fresh()->editor_version);
        $this->assertSame($historyCount + 1, SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count());
    }

    public function test_project_bulk_rows_use_the_selected_supported_locale(): void
    {
        $admin = $this->admin();
        $project = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared project SEO locale',
            'slug' => 'shared-project-seo-locale',
            'status' => 1,
        ]);

        $sheet = $this->actingAs($admin, 'admin')->get(route('seo.bulk.index', [
            'locale' => 'bn',
            'type' => 'project',
            'search' => 'Shared project SEO locale',
        ]))->assertOk();
        $target = collect($sheet->viewData('targets')->items())->firstWhere('owner_id', $project->id);
        $this->assertNotNull($target);
        $this->assertSame('bn', $target['locale']);

        $this->put(route('seo.bulk.update'), ['items' => [[
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'route_name' => null,
            'locale' => 'bn',
            'expected_seo_version' => $target['expected_seo_version'],
            'mode' => 'custom',
            'title' => 'বাংলা প্রকল্প সার্চ শিরোনাম',
            'description' => 'বাংলাদেশের কমিউনিটির জন্য দীর্ঘমেয়াদি প্রকল্প সহায়তা ও অগ্রগতির বিস্তারিত তথ্য।',
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Tag::class,
            'seoable_id' => $project->id,
            'locale' => 'bn',
            'title' => 'বাংলা প্রকল্প সার্চ শিরোনাম',
        ]);
    }

    public function test_guided_route_rejects_a_form_when_page_ownership_appeared_after_it_loaded(): void
    {
        $admin = $this->admin();
        $versions = app(SeoMetadataEditorVersionService::class);
        $routeName = 'frontend.about';
        $routePath = (string) app(SeoRouteRegistry::class)->path($routeName);
        $routeOwnedToken = $versions->currentForRoute($routeName, $routePath, 'en');
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'About us owner created concurrently',
            'slug' => 'about-us',
            'sub_title' => $this->description(),
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);

        $staleRouteForm = $this->routePayload($routeName, 'Ignored route-owned title', $routeOwnedToken);
        $this->actingAs($admin, 'admin')->put(route('seo.update'), $staleRouteForm)->assertStatus(409);
        $this->assertDatabaseMissing('seo_metadata', ['route_name' => $routeName, 'locale' => 'en']);
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
        ]);
        $this->put(route('seo.bulk.update'), ['items' => [[
            'owner_type' => 'route',
            'owner_id' => null,
            'route_name' => $routeName,
            'locale' => 'en',
            'expected_seo_version' => $routeOwnedToken,
            'mode' => 'custom',
            'title' => 'Ignored stale bulk route title',
            'description' => $this->description(),
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ]]])->assertStatus(409);
        $this->assertDatabaseMissing('seo_metadata', ['route_name' => $routeName, 'locale' => 'en']);
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
        ]);

        $currentPageForm = $this->routePayload($routeName, 'Current page-owned title', $routeOwnedToken);
        unset($currentPageForm['expected_seo_version']);
        $currentPageForm['expected_editor_version'] = 0;
        $this->put(route('seo.update'), $currentPageForm)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'route_name' => null,
            'locale' => 'en',
            'title' => 'Current page-owned title',
        ]);
    }

    public function test_route_and_content_forms_keep_the_token_for_the_exact_metadata_they_render(): void
    {
        $admin = $this->admin();
        $versions = app(SeoMetadataEditorVersionService::class);
        $metadataService = app(SeoMetadataService::class);
        $routeName = 'frontend.contactUs';
        $routePath = (string) app(SeoRouteRegistry::class)->path($routeName);
        $routeMetadata = $metadataService->updateForRoute($routeName, $routePath, 'en', [
            'title' => 'Rendered route snapshot',
            'description' => $this->description(),
        ]);
        $renderedRoute = $routeMetadata->fresh();
        $renderedRouteToken = $versions->forRouteSnapshot($routeName, $routePath, 'en', $renderedRoute);
        $routeMutated = false;
        Event::listen('eloquent.retrieved: ' . SeoMetadata::class, function (SeoMetadata $metadata) use ($routeMetadata, &$routeMutated): void {
            if ($routeMutated || (int) $metadata->getKey() !== (int) $routeMetadata->getKey()) {
                return;
            }

            $routeMutated = true;
            DB::table('seo_metadata')->where('id', $metadata->getKey())->update([
                'title' => 'Newer route snapshot',
                'editor_version' => (int) $metadata->editor_version + 1,
            ]);
        });

        $routeResponse = $this->actingAs($admin, 'admin')->get(route('seo.index', [
            'route' => $routeName,
            'locale' => 'en',
        ]))->assertOk();
        $this->assertTrue($routeMutated);
        $this->assertSame('Rendered route snapshot', data_get($routeResponse->viewData('editor'), 'values.title'));
        $this->assertSame($renderedRouteToken, data_get($routeResponse->viewData('editor'), 'seo_editor_version'));
        $this->put(
            route('seo.update'),
            $this->routePayload($routeName, 'Rendered route snapshot', $renderedRouteToken)
        )->assertStatus(409);
        $this->assertSame('Newer route snapshot', $routeMetadata->fresh()->title);

        $category = $this->category('rendered-seo-snapshot');
        $categoryMetadata = $metadataService->updateForModel($category, [
            'title' => 'Rendered content snapshot',
            'description' => $this->description(),
        ], 'en');
        $renderedCategory = $category->fresh();
        $renderedCategorySeo = $categoryMetadata->fresh();
        $renderedCategoryToken = $versions->forModelSnapshot(
            $renderedCategory,
            'en',
            $renderedCategorySeo
        );
        $categoryMutated = false;
        Event::listen('eloquent.retrieved: ' . SeoMetadata::class, function (SeoMetadata $metadata) use ($categoryMetadata, &$categoryMutated): void {
            if ($categoryMutated || (int) $metadata->getKey() !== (int) $categoryMetadata->getKey()) {
                return;
            }

            $categoryMutated = true;
            DB::table('seo_metadata')->where('id', $metadata->getKey())->update([
                'title' => 'Newer content snapshot',
                'editor_version' => (int) $metadata->editor_version + 1,
            ]);
        });

        $contentResponse = $this->get(route('seo.content.edit', ['category', $category->id]))->assertOk();
        $this->assertTrue($categoryMutated);
        $this->assertSame('Rendered content snapshot', data_get($contentResponse->viewData('editor'), 'values.title'));
        $this->assertSame($renderedCategoryToken, data_get($contentResponse->viewData('editor'), 'seo_editor_version'));
        $this->put(
            route('seo.content.update', ['category', $category->id]),
            $this->contentPayload('Rendered content snapshot', $renderedCategoryToken)
        )->assertStatus(409);
        $this->assertSame('Newer content snapshot', $categoryMetadata->fresh()->title);
    }

    public function test_bulk_rows_keep_snapshot_tokens_and_deleted_metadata_can_be_restored_on_save(): void
    {
        $admin = $this->admin();
        $versions = app(SeoMetadataEditorVersionService::class);
        $metadataService = app(SeoMetadataService::class);
        $category = $this->category('bulk-rendered-seo-snapshot');
        $metadata = $metadataService->updateForModel($category, [
            'title' => 'Rendered bulk snapshot',
            'description' => $this->description(),
        ], 'en');
        $renderedOwner = $category->fresh();
        $renderedSeo = $metadata->fresh();
        $renderedToken = $versions->forModelSnapshot($renderedOwner, 'en', $renderedSeo);
        $mutated = false;
        Event::listen('eloquent.retrieved: ' . SeoMetadata::class, function (SeoMetadata $loaded) use ($metadata, &$mutated): void {
            if ($mutated || (int) $loaded->getKey() !== (int) $metadata->getKey()) {
                return;
            }

            $mutated = true;
            DB::table('seo_metadata')->where('id', $loaded->getKey())->update([
                'title' => 'Newer bulk snapshot',
                'editor_version' => (int) $loaded->editor_version + 1,
            ]);
        });

        $bulkResponse = $this->actingAs($admin, 'admin')->get(route('seo.bulk.index', [
            'locale' => 'en',
            'type' => 'category',
            'search' => 'Bulk Rendered Seo Snapshot',
        ]))->assertOk();
        $target = collect($bulkResponse->viewData('targets')->items())->firstWhere('owner_id', $category->id);
        $this->assertTrue($mutated);
        $this->assertNotNull($target);
        $this->assertSame('Rendered bulk snapshot', $target['effective_title']);
        $this->assertSame($renderedToken, $target['expected_seo_version']);
        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem($category, 'Rendered bulk snapshot', $target['expected_seo_version']),
        ]])->assertStatus(409);
        $this->assertSame('Newer bulk snapshot', $metadata->fresh()->title);

        $routeName = 'frontend.contactUs';
        $routePath = (string) app(SeoRouteRegistry::class)->path($routeName);
        $deleted = $metadataService->updateForRoute($routeName, $routePath, 'en', [
            'title' => 'Deleted route metadata',
            'description' => $this->description(),
        ]);
        $deleted->delete();
        $deletedSnapshot = SeoMetadata::withTrashed()->findOrFail($deleted->id);
        $deletedToken = $versions->forRouteSnapshot($routeName, $routePath, 'en', $deletedSnapshot);

        $deletedResponse = $this->get(route('seo.index', [
            'route' => $routeName,
            'locale' => 'en',
        ]))->assertOk();
        $this->assertSame($deletedToken, data_get($deletedResponse->viewData('editor'), 'seo_editor_version'));
        $this->put(
            route('seo.update'),
            $this->routePayload($routeName, 'Restored route metadata', $deletedToken)
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($deleted->fresh()->deleted_at);
        $this->assertSame('Restored route metadata', $deleted->fresh()->title);
    }

    public function test_page_seo_form_uses_the_page_generation_loaded_before_its_metadata_snapshot(): void
    {
        $admin = $this->admin();
        $uuid = (string) Str::uuid();
        $page = Page::create([
            'uuid' => $uuid,
            'name' => 'Page SEO rendered snapshot',
            'slug' => 'page-seo-rendered-snapshot',
            'sub_title' => $this->description(),
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
        DB::table('pages')->where('id', $page->id)->update(['editor_version' => 4]);
        $metadata = app(SeoMetadataService::class)->updateForModel($page, [
            'title' => 'Rendered Page SEO snapshot',
            'description' => $this->description(),
        ], 'en');
        $mutated = false;
        Event::listen('eloquent.retrieved: ' . SeoMetadata::class, function (SeoMetadata $loaded) use ($metadata, $uuid, &$mutated): void {
            if ($mutated || (int) $loaded->getKey() !== (int) $metadata->getKey()) {
                return;
            }

            $mutated = true;
            DB::table('seo_metadata')->where('id', $loaded->getKey())->update([
                'title' => 'Newer Page SEO snapshot',
                'editor_version' => (int) $loaded->editor_version + 1,
            ]);
            DB::table('pages')->where('uuid', $uuid)->update(['editor_version' => 5]);
        });

        $response = $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk();
        $this->assertTrue($mutated);
        $this->assertSame('Rendered Page SEO snapshot', data_get($response->viewData('editor'), 'values.title'));
        $this->assertSame(4, data_get($response->viewData('editor'), 'page_editor_version'));

        $payload = $this->contentPayload('Rendered Page SEO snapshot', 'unused-for-page');
        unset($payload['expected_seo_version']);
        $payload['expected_editor_version'] = 4;
        $this->put(route('seo.content.update', ['page', $page->id]), $payload)->assertStatus(409);
        $this->assertSame('Newer Page SEO snapshot', $metadata->fresh()->title);
        $this->assertSame(5, (int) $page->fresh()->editor_version);
    }

    public function test_missing_curated_page_translation_is_read_only_with_a_permission_gated_handoff(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'English About owner',
            'slug' => 'about-us',
            'sub_title' => $this->description(),
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
        $admin = $this->admin();

        $readOnly = $this->actingAs($admin, 'admin')->get(route('seo.index', [
            'route' => 'frontend.about',
            'locale' => 'bn',
        ]))->assertOk();
        $this->assertTrue($readOnly->viewData('missingManagedPageTranslation'));
        $this->assertFalse($readOnly->viewData('editorCanEditMetadata'));
        $this->assertNull($readOnly->viewData('translationCenterUrl'));
        $readOnly
            ->assertSee('BN translation required.')
            ->assertSee('Ask a Translation Center editor to create it before editing search settings.')
            ->assertDontSee('Save search &amp; sharing', false)
            ->assertDontSee('Open page');

        $role = Role::findOrFail((int) $admin->role);
        $translationMenu = AuthMenu::where('link', 'translations.index')->firstOrFail();
        $role->forceFill([
            'permission' => collect(explode(',', (string) $role->permission))
                ->push((string) $translationMenu->id)
                ->filter()
                ->unique()
                ->implode(','),
        ])->save();
        $handoff = $this->get(route('seo.index', [
            'route' => 'frontend.about',
            'locale' => 'bn',
        ]))->assertOk();
        $this->assertNotNull($handoff->viewData('translationCenterUrl'));
        $handoff->assertSee('Create this translation in Translation Center');
    }

    public function test_seo_generation_migration_is_reentrant_and_restores_legacy_pending_requests(): void
    {
        $metadata = app(SeoMetadataService::class)->updateForRoute(
            'frontend.contactUs',
            '/contact-us',
            'en',
            ['title' => 'Legacy pending review']
        );
        $metadata->forceFill(['review_status' => 'pending'])->save();
        $migration = require database_path('migrations/2026_08_21_130000_add_editor_version_to_seo_metadata.php');

        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasColumn('seo_metadata', 'editor_version'));
        $this->assertTrue(Schema::hasColumn('seo_metadata', 'review_request_version'));

        $migration->down();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('seo_metadata', 'editor_version'));
        $this->assertFalse(Schema::hasColumn('seo_metadata', 'review_request_version'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('seo_metadata', 'editor_version'));
        $this->assertTrue(Schema::hasColumn('seo_metadata', 'review_request_version'));
        $this->assertSame(1, (int) SeoMetadata::query()->findOrFail($metadata->id)->review_request_version);
    }

    private function routePayload(string $routeName, string $title, string $token): array
    {
        return $this->contentPayload($title, $token) + ['route_name' => $routeName];
    }

    private function contentPayload(string $title, string $token): array
    {
        return [
            'locale' => 'en',
            'expected_seo_version' => $token,
            'schema_template' => 'none',
            'seo' => [
                'title' => $title,
                'description' => $this->description(),
                'focus_keyword' => '',
                'canonical_url' => '',
                'robots_index' => 1,
                'robots_follow' => 1,
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'twitter_card' => 'summary_large_image',
                'twitter_title' => '',
                'twitter_description' => '',
                'twitter_image' => '',
                'schema_markup' => '',
                'sitemap_priority' => 0.5,
                'sitemap_change_frequency' => 'monthly',
                'exclude_from_sitemap' => 0,
            ],
        ];
    }

    private function bulkItem(Category $category, string $title, string $token): array
    {
        return [
            'owner_type' => 'category',
            'owner_id' => $category->id,
            'route_name' => null,
            'locale' => 'en',
            'expected_seo_version' => $token,
            'mode' => 'custom',
            'title' => $title,
            'description' => $this->description(),
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ];
    }

    private function category(string $slug): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'description' => $this->description(),
            'language' => 'en',
            'status' => 1,
        ]);
    }

    private function description(): string
    {
        return 'Learn how Ignite works alongside communities to deliver practical education, health and livelihood support throughout Bangladesh.';
    }

    private function admin(): Admin
    {
        $menuIds = AuthMenu::whereIn('link', ['seo.index'])->pluck('id')->implode(',');
        $actionIds = MenuAction::whereIn('link', ['seo.metadata.edit'])->pluck('id')->implode(',');
        $role = Role::create([
            'name' => 'SEO generation QA ' . Str::random(8),
            'permission' => $menuIds,
            'actionPermission' => $actionIds,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'SEO generation QA',
            'username' => 'seo-generation-' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
