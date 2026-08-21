<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadataRevision;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataService;
use App\Services\SeoRouteRegistry;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoPageEditorVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_page_seo_save_advances_every_locale_but_non_page_save_does_not(): void
    {
        $admin = $this->adminWith(['seo.metadata.edit']);
        $uuid = (string) Str::uuid();
        $english = $this->page('seo-version-en', 'en', $uuid, 1);
        $bangla = $this->page('seo-version-bn', 'bn', $uuid, 3);
        $category = $this->category('seo-version-category');

        $pagePayload = $this->editorPayload('Page SEO title');
        $pagePayload['permalink_slug'] = 'seo-version-en-renamed';
        $pagePayload['expected_editor_version'] = 3;
        $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['page', $english->id]))
            ->assertOk()
            ->assertSee('name="expected_editor_version" value="3"', false);
        $this->actingAs($admin, 'admin')
            ->put(route('seo.content.update', ['page', $english->id]), $pagePayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $english->fresh()->editor_version);
        $this->assertSame(4, $bangla->fresh()->editor_version);
        $this->assertSame('seo-version-en-renamed', $english->fresh()->slug);
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/page/seo-version-en',
            'to_url' => '/page/seo-version-en-renamed',
            'locale' => 'en',
        ]);

        $stalePayload = $this->editorPayload('Stale Page SEO title');
        $stalePayload['permalink_slug'] = 'seo-version-en-stale';
        $stalePayload['expected_editor_version'] = 3;
        $this->put(route('seo.content.update', ['page', $english->id]), $stalePayload)
            ->assertStatus(409);

        $this->assertSame(4, $english->fresh()->editor_version);
        $this->assertSame(4, $bangla->fresh()->editor_version);
        $this->assertSame('seo-version-en-renamed', $english->fresh()->slug);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $english->id,
            'title' => 'Page SEO title',
        ]);
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/page/seo-version-en',
            'to_url' => '/page/seo-version-en-renamed',
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('seo_redirects', [
            'from_path' => '/page/seo-version-en',
            'to_url' => '/page/seo-version-en-stale',
        ]);
        $this->assertDatabaseMissing('seo_redirects', [
            'from_path' => '/page/seo-version-en-renamed',
            'to_url' => '/page/seo-version-en-stale',
        ]);

        $currentPayload = $this->editorPayload('Current Page SEO title');
        $currentPayload['expected_editor_version'] = 4;
        $this->put(route('seo.content.update', ['page', $english->id]), $currentPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $english->fresh()->editor_version);
        $this->assertSame(5, $bangla->fresh()->editor_version);

        $categoryPayload = $this->editorPayload('Category SEO title');
        $categoryPayload['expected_seo_version'] = app(SeoMetadataEditorVersionService::class)
            ->currentForModel($category, 'en');
        $this->put(route('seo.content.update', ['category', $category->id]), $categoryPayload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(5, $english->fresh()->editor_version);
        $this->assertSame(5, $bangla->fresh()->editor_version);
    }

    public function test_guided_route_save_uses_and_invalidates_its_backing_page_only(): void
    {
        $admin = $this->adminWith(['seo.metadata.edit']);
        $uuid = (string) Str::uuid();
        $english = $this->page('about-us', 'en', $uuid, 2);
        $bangla = $this->page('about-us-bn', 'bn', $uuid, 7);

        $this->actingAs($admin, 'admin')
            ->put(route('seo.update'), $this->routePayload('frontend.about', 'Managed About SEO', 7))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $english->fresh()->editor_version);
        $this->assertSame(8, $bangla->fresh()->editor_version);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $english->id,
            'route_name' => null,
            'locale' => 'en',
            'title' => 'Managed About SEO',
        ]);
        $this->assertDatabaseMissing('seo_metadata', [
            'route_name' => 'frontend.about',
            'locale' => 'en',
        ]);

        $this->put(route('seo.update'), $this->routePayload('frontend.contactUs', 'Contact route SEO'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $english->fresh()->editor_version);
        $this->assertSame(8, $bangla->fresh()->editor_version);
        $this->assertDatabaseHas('seo_metadata', [
            'route_name' => 'frontend.contactUs',
            'locale' => 'en',
            'title' => 'Contact route SEO',
        ]);

        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkRouteItem('frontend.about', 'Stale Managed About bulk SEO', 7),
            $this->bulkRouteItem('frontend.contactUs', 'Atomic route must not save'),
        ]])->assertStatus(409);
        $this->assertSame(8, $english->fresh()->editor_version);
        $this->assertSame(8, $bangla->fresh()->editor_version);
        $this->assertDatabaseHas('seo_metadata', [
            'route_name' => 'frontend.contactUs',
            'title' => 'Contact route SEO',
        ]);
        $this->assertDatabaseMissing('seo_metadata', [
            'title' => 'Atomic route must not save',
        ]);

        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkRouteItem('frontend.about', 'Managed About bulk SEO', 8),
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(9, $english->fresh()->editor_version);
        $this->assertSame(9, $bangla->fresh()->editor_version);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $english->id,
            'route_name' => null,
            'title' => 'Managed About bulk SEO',
        ]);
    }

    public function test_bulk_page_seo_save_advances_each_logical_page_once(): void
    {
        $admin = $this->adminWith(['seo.metadata.edit']);
        $uuid = (string) Str::uuid();
        $english = $this->page('bulk-version-en', 'en', $uuid, 4);
        $bangla = $this->page('bulk-version-bn', 'bn', $uuid, 6);
        $category = $this->category('bulk-version-category');

        $this->actingAs($admin, 'admin')
            ->get(route('seo.bulk.index', ['search' => 'bulk-version-']))
            ->assertOk()
            ->assertSee('expected_editor_version', false)
            ->assertSee('value="6"', false);

        $this->actingAs($admin, 'admin')->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem('page', $english->id, 'en', 'Stale English bulk version', 5),
            $this->bulkItem('page', $bangla->id, 'bn', 'বাংলা বাল্ক সংস্করণ', 6),
            $this->bulkItem('category', $category->id, 'en', 'Category bulk version'),
        ]])->assertStatus(409);

        $this->assertSame(4, $english->fresh()->editor_version);
        $this->assertSame(6, $bangla->fresh()->editor_version);
        $this->assertDatabaseMissing('seo_metadata', ['title' => 'Stale English bulk version']);
        $this->assertDatabaseMissing('seo_metadata', ['title' => 'বাংলা বাল্ক সংস্করণ']);
        $this->assertDatabaseMissing('seo_metadata', ['title' => 'Category bulk version']);

        $this->put(route('seo.bulk.update'), ['items' => [
            $this->bulkItem('page', $english->id, 'en', 'English bulk version', 6),
            $this->bulkItem('page', $bangla->id, 'bn', 'বাংলা বাল্ক সংস্করণ', 6),
            $this->bulkItem('category', $category->id, 'en', 'Category bulk version'),
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7, $english->fresh()->editor_version);
        $this->assertSame(7, $bangla->fresh()->editor_version);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $english->id,
            'title' => 'English bulk version',
        ]);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
            'title' => 'Category bulk version',
        ]);
    }

    public function test_page_seo_restore_advances_versions_and_failed_restore_rolls_back_the_advance(): void
    {
        $admin = $this->adminWith(['seo.metadata.edit']);
        $uuid = (string) Str::uuid();
        $english = $this->page('restore-version-en', 'en', $uuid, 2);
        $bangla = $this->page('restore-version-bn', 'bn', $uuid, 5);
        $metadata = app(SeoMetadataService::class)->updateForModel($english, [
            'title' => 'Earlier title',
            'description' => $this->description(),
            'canonical_url' => url('/page/restore-version-en'),
        ], 'en');
        $revision = app(SeoMetadataRevisionService::class)->capture($metadata, 'Earlier safe version');
        $metadata->forceFill(['title' => 'Current title'])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['page', $english->id]))
            ->assertOk()
            ->assertSee('name="expected_editor_version" value="5"', false);
        $this->post(route('seo.revisions.restore', $revision), ['expected_editor_version' => 5])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $english->fresh()->editor_version);
        $this->assertSame(6, $bangla->fresh()->editor_version);
        $this->assertSame('Earlier title', $metadata->fresh()->title);

        $externalCanonical = 'https://archive.example.test/restore-version';
        $metadata->forceFill([
            'title' => 'Unsafe historical title',
            'canonical_url' => $externalCanonical,
        ])->save();
        $unsafeRevision = app(SeoMetadataRevisionService::class)->capture($metadata, 'External canonical version');
        $metadata->forceFill([
            'title' => 'Safe current title',
            'canonical_url' => url('/page/restore-version-en'),
        ])->save();
        $historyCount = SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count();

        $this->post(route('seo.revisions.restore', $unsafeRevision), ['expected_editor_version' => 5])
            ->assertStatus(409);
        $this->assertSame(6, $english->fresh()->editor_version);
        $this->assertSame('Safe current title', $metadata->fresh()->title);
        $this->assertSame(
            $historyCount,
            SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count()
        );

        $this->from(route('seo.content.edit', ['page', $english->id]))
            ->post(route('seo.revisions.restore', $unsafeRevision), ['expected_editor_version' => 6])
            ->assertSessionHasErrors('seo.canonical_url');

        $this->assertSame(6, $english->fresh()->editor_version);
        $this->assertSame(6, $bangla->fresh()->editor_version);
        $this->assertSame('Safe current title', $metadata->fresh()->title);
        $this->assertSame(
            $historyCount,
            SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count()
        );
    }

    private function page(string $slug, string $locale, string $uuid, int $version): Page
    {
        $page = Page::create([
            'uuid' => $uuid,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'sub_title' => $this->description(),
            'language' => $locale,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
        $page->forceFill(['editor_version' => $version])->save();

        return $page->fresh();
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

    /** @return array<string, mixed> */
    private function editorPayload(string $title): array
    {
        return [
            'locale' => 'en',
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

    /** @return array<string, mixed> */
    private function routePayload(string $routeName, string $title, ?int $expectedVersion = null): array
    {
        $payload = $this->editorPayload($title) + ['route_name' => $routeName];
        if ($expectedVersion !== null) {
            $payload['expected_editor_version'] = $expectedVersion;
        } else {
            $path = (string) app(SeoRouteRegistry::class)->path($routeName);
            $payload['expected_seo_version'] = app(SeoMetadataEditorVersionService::class)
                ->currentForRoute($routeName, $path, 'en');
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function bulkItem(
        string $ownerType,
        int $ownerId,
        string $locale,
        string $title,
        ?int $expectedVersion = null
    ): array
    {
        $item = [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'route_name' => null,
            'locale' => $locale,
            'mode' => 'custom',
            'title' => $title,
            'description' => $this->description(),
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ];
        if ($expectedVersion !== null) {
            $item['expected_editor_version'] = $expectedVersion;
        } elseif ($ownerType === 'category') {
            $item['expected_seo_version'] = app(SeoMetadataEditorVersionService::class)
                ->currentForModel(Category::findOrFail($ownerId), $locale);
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function bulkRouteItem(string $routeName, string $title, ?int $expectedVersion = null): array
    {
        $item = [
            'owner_type' => 'route',
            'owner_id' => null,
            'route_name' => $routeName,
            'locale' => 'en',
            'mode' => 'custom',
            'title' => $title,
            'description' => $this->description(),
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ];
        if ($expectedVersion !== null) {
            $item['expected_editor_version'] = $expectedVersion;
        } else {
            $path = (string) app(SeoRouteRegistry::class)->path($routeName);
            $item['expected_seo_version'] = app(SeoMetadataEditorVersionService::class)
                ->currentForRoute($routeName, $path, 'en');
        }

        return $item;
    }

    private function description(): string
    {
        return 'Learn how Ignite works alongside communities to deliver practical education, health and livelihood support throughout Bangladesh.';
    }

    /** @param array<int, string> $actions */
    private function adminWith(array $actions): Admin
    {
        $menuIds = AuthMenu::whereIn('link', ['seo.index'])->pluck('id')->implode(',');
        $actionIds = MenuAction::whereIn('link', $actions)->pluck('id')->implode(',');
        $role = Role::create([
            'name' => 'SEO version QA ' . Str::random(8),
            'permission' => $menuIds,
            'actionPermission' => $actionIds,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'SEO version QA',
            'username' => 'seo-version-' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
