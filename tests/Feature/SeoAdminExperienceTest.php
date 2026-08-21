<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\SeoRedirect;
use App\Services\SeoRedirectService;
use App\Services\SeoMetadataEditorVersionService;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoAdminExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_guided_content_save_is_locale_bound_and_normalizes_visibility_schema_and_media(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category();

        $this->actingAs($admin, 'admin')->from(route('seo.content.edit', ['category', $category->id]))
            ->put(route('seo.content.update', ['category', $category->id]), $this->payload([
                'locale' => 'bn',
            ]))
            ->assertRedirect(route('seo.content.edit', ['category', $category->id]))
            ->assertSessionHasErrors('locale');
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
        ]);

        $payload = $this->payload();
        $payload['expected_seo_version'] = $this->seoToken($category, 'en');
        $payload['seo']['robots_index'] = 0;
        $payload['seo']['exclude_from_sitemap'] = 0;
        $payload['seo']['og_image'] = '/storage/media/share.jpg';
        $payload['seo']['twitter_image'] = '/storage/media/share.jpg';
        $payload['seo']['schema_markup'] = '[]';

        $this->actingAs($admin, 'admin')
            ->put(route('seo.content.update', ['category', $category->id]), $payload)
            ->assertRedirect(route('seo.content.edit', ['category', $category->id]));

        $metadata = SeoMetadata::where('seoable_type', Category::class)
            ->where('seoable_id', $category->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->assertFalse($metadata->robots_index);
        $this->assertTrue($metadata->exclude_from_sitemap);
        $this->assertNull($metadata->schema_markup);
        $this->assertSame(url('/storage/media/share.jpg'), $metadata->og_image);
        $this->assertSame(url('/storage/media/share.jpg'), $metadata->twitter_image);
    }

    public function test_permalink_change_reuses_a_normalized_legacy_redirect(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category(['slug' => 'old-address']);
        app(SeoRedirectService::class)->create([
            'from_path' => '/category/old-address/',
            'to_url' => '/category/temporary-target',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $payload = $this->payload();
        $payload['expected_seo_version'] = $this->seoToken($category, 'en');
        $payload['permalink_slug'] = 'new-address';
        $this->actingAs($admin, 'admin')
            ->put(route('seo.content.update', ['category', $category->id]), $payload)
            ->assertRedirect(route('seo.content.edit', ['category', $category->id]));

        $this->assertSame('new-address', $category->fresh()->slug);
        $this->assertSame(1, SeoRedirect::count());
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/category/old-address',
            'to_url' => '/category/new-address',
            'status_code' => 301,
            'is_active' => true,
            'locale' => 'en',
        ]);
    }

    public function test_bangla_dashboard_uses_english_inventory_and_surfaces_missing_translation(): void
    {
        $admin = $this->metadataAdmin();
        config(['seo.robots.indexing_enabled' => true]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'English-only impact page',
            'sub_title' => 'An English source page awaiting translation.',
            'slug' => 'english-only-impact-page',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('seo.index', ['locale' => 'bn']))
            ->assertOk()
            ->assertSee('Preview environment: search indexing is blocked here')
            ->assertDontSee('Search indexing is enabled.')
            ->assertSee('English-only impact page')
            ->assertSee('Create the translation before editing SEO')
            ->assertSee('translation missing')
            ->assertSee('Bangla');
    }

    public function test_bangla_editor_preview_canonical_and_generated_schema_use_the_localized_url(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category([
            'name' => 'বাংলা জলবায়ু কার্যক্রম',
            'slug' => 'bangla-climate-programs',
            'language' => 'bn',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['category', $category->id]))
            ->assertOk()
            ->assertViewHas('editor', function (array $editor): bool {
                $expected = url('/category/bangla-climate-programs?lang=bn');
                $schema = json_decode((string) $editor['generated_schemas']['webpage'], true);

                return $editor['default_url'] === $expected
                    && $editor['effective']['url'] === $expected
                    && data_get($schema, 'url') === $expected
                    && data_get($schema, 'inLanguage') === 'bn'
                    && $editor['permalink']['editable'] === true;
            });
    }

    public function test_non_default_language_slug_change_creates_only_a_language_scoped_redirect(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category([
            'slug' => 'bangla-original-address',
            'language' => 'bn',
        ]);
        $payload = $this->payload([
            'locale' => 'bn',
            'permalink_slug' => 'bangla-new-address',
        ]);
        $payload['expected_seo_version'] = $this->seoToken($category, 'bn');

        $this->actingAs($admin, 'admin')
            ->from(route('seo.content.edit', ['category', $category->id]))
            ->put(route('seo.content.update', ['category', $category->id]), $payload)
            ->assertRedirect(route('seo.content.edit', ['category', $category->id]));

        $this->assertSame('bangla-new-address', $category->fresh()->slug);
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/category/bangla-original-address',
            'to_url' => '/category/bangla-new-address?lang=bn',
            'locale' => 'bn',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('seo_redirects', [
            'from_path' => '/category/bangla-original-address',
            'locale' => null,
        ]);
    }

    public function test_default_page_and_category_slug_changes_do_not_capture_translated_siblings(): void
    {
        $admin = $this->metadataAdmin();
        $pageUuid = (string) Str::uuid();
        $englishPage = $this->page('shared-page-address', 'en', $pageUuid);
        $banglaPage = $this->page('shared-page-address', 'bn', $pageUuid);
        $banglaPage->forceFill(['status' => 0, 'publication_status' => 'draft'])->save();
        $categoryUuid = (string) Str::uuid();
        $englishCategory = $this->category([
            'uuid' => $categoryUuid,
            'slug' => 'shared-category-address',
        ]);
        $this->category([
            'uuid' => $categoryUuid,
            'slug' => 'shared-category-address',
            'language' => 'bn',
            'status' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('seo.content.edit', ['page', $englishPage->id]))
            ->put(route('seo.content.update', ['page', $englishPage->id]), $this->payload([
                'permalink_slug' => 'new-english-page-address',
            ]))
            ->assertRedirect(route('seo.content.edit', ['page', $englishPage->id]));
        $categoryPayload = $this->payload([
            'permalink_slug' => 'new-english-category-address',
        ]);
        $categoryPayload['expected_seo_version'] = $this->seoToken($englishCategory, 'en');
        $this->actingAs($admin, 'admin')
            ->from(route('seo.content.edit', ['category', $englishCategory->id]))
            ->put(route('seo.content.update', ['category', $englishCategory->id]), $categoryPayload)
            ->assertRedirect(route('seo.content.edit', ['category', $englishCategory->id]));

        $this->assertSame('new-english-page-address', $englishPage->fresh()->slug);
        $this->assertSame('new-english-category-address', $englishCategory->fresh()->slug);
        $this->assertSame('shared-page-address', $banglaPage->fresh()->slug);
        $this->assertSame(2, SeoRedirect::count());
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/page/shared-page-address',
            'to_url' => '/page/new-english-page-address',
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/category/shared-category-address',
            'to_url' => '/category/new-english-category-address',
            'locale' => 'en',
        ]);
    }

    public function test_schema_template_is_allowlisted_and_custom_json_is_size_limited(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category();

        $this->actingAs($admin, 'admin')
            ->from(route('seo.content.edit', ['category', $category->id]))
            ->put(route('seo.content.update', ['category', $category->id]), $this->payload([
                'schema_template' => 'untrusted-template',
            ]))
            ->assertSessionHasErrors('schema_template');

        $this->actingAs($admin, 'admin')
            ->from(route('seo.content.edit', ['category', $category->id]))
            ->put(route('seo.content.update', ['category', $category->id]), $this->payload([
                'schema_template' => 'expert',
                'seo' => ['schema_markup' => json_encode(['value' => str_repeat('x', 50001)])],
            ]))
            ->assertSessionHasErrors('seo.schema_markup');
    }

    public function test_legacy_content_forms_delegate_to_the_single_guided_seo_editor(): void
    {
        $views = [
            resource_path('views/admin/page/add.blade.php'),
            resource_path('views/admin/page/edit.blade.php'),
            resource_path('views/admin/category/add.blade.php'),
            resource_path('views/admin/category/edit.blade.php'),
        ];

        foreach ($views as $view) {
            $markup = file_get_contents($view);
            $this->assertStringNotContainsString('name="meta_title[', $markup);
            $this->assertStringNotContainsString('name="meta_keyword[', $markup);
            $this->assertStringNotContainsString('name="meta_description[', $markup);
            $this->assertStringContainsString('Search &amp; sharing', $markup);
        }

        foreach ([
            resource_path('views/admin/service/add.blade.php'),
            resource_path('views/admin/service/edit.blade.php'),
        ] as $retiredView) {
            $markup = file_get_contents($retiredView);
            $this->assertStringNotContainsString('name="meta_title[', $markup);
            $this->assertStringNotContainsString('name="meta_keyword[', $markup);
            $this->assertStringNotContainsString('name="meta_description[', $markup);
        }

        $pageController = file_get_contents(app_path('Http/Controllers/Admin/PageController.php'));
        $categoryController = file_get_contents(app_path('Http/Controllers/Admin/CategoryController.php'));
        $this->assertStringNotContainsString('updateForPage(', $pageController);
        $this->assertStringNotContainsString("'meta_title' => @\$request", $pageController.$categoryController);
        $this->assertStringNotContainsString("'meta_keyword' => @\$request", $pageController.$categoryController);
        $this->assertStringNotContainsString("'meta_description' => @\$request", $pageController.$categoryController);
    }

    public function test_expert_schema_validation_preserves_the_selected_mode_and_limits_payload_size(): void
    {
        $admin = $this->metadataAdmin();
        $category = $this->category();
        $editUrl = route('seo.content.edit', ['category', $category->id]);

        $invalid = $this->payload([
            'schema_template' => 'expert',
            'seo' => ['schema_markup' => '{"@context":"https://schema.org",'],
        ]);
        $this->actingAs($admin, 'admin')->from($editUrl)
            ->put(route('seo.content.update', ['category', $category->id]), $invalid)
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('seo.schema_markup')
            ->assertSessionHasInput('schema_template', 'expert')
            ->assertSessionHasInput('seo.schema_markup', $invalid['seo']['schema_markup']);

        $oversized = $this->payload([
            'schema_template' => 'expert',
            'seo' => ['schema_markup' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'description' => str_repeat('x', 51000),
            ], JSON_THROW_ON_ERROR)],
        ]);
        $this->actingAs($admin, 'admin')->from($editUrl)
            ->put(route('seo.content.update', ['category', $category->id]), $oversized)
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('seo.schema_markup');

        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
        ]);
    }

    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Climate resilience',
            'slug' => 'climate-resilience',
            'description' => 'Community-led climate programs and practical local support.',
            'language' => 'en',
            'status' => 1,
        ], $overrides));
    }

    private function page(string $slug, string $locale, string $uuid): Page
    {
        return Page::create([
            'uuid' => $uuid,
            'name' => Str::headline($slug),
            'sub_title' => 'A translated public page.',
            'slug' => $slug,
            'language' => $locale,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'locale' => 'en',
            'expected_editor_version' => 0,
            'schema_template' => 'webpage',
            'seo' => [
                'title' => 'Community climate action',
                'description' => 'Learn how community-led climate programs help families prepare, adapt, and build long-term resilience together.',
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
        ], $overrides);
    }

    private function seoToken(Category $category, string $locale): string
    {
        return app(SeoMetadataEditorVersionService::class)->currentForModel($category, $locale);
    }

    private function metadataAdmin(): Admin
    {
        $permission = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $role = Role::create([
            'name' => 'SEO editor',
            'permission' => '',
            'actionPermission' => (string) $permission->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'SEO QA',
            'username' => 'seo-qa',
            'email' => 'seo-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
