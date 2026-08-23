<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataService;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoContentQualityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_guided_editor_includes_saved_page_content_in_setup_completeness(): void
    {
        $admin = $this->seoAdmin();
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community health outreach',
            'slug' => 'community-health-outreach',
            'sub_title' => 'A practical local health program.',
            'description' => '<p>Families receive practical support and trusted information.</p><p><img src="/storage/media/clinic.jpg" alt=""></p>',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        app(SeoMetadataService::class)->updateForModel($page, [
            'focus_keyword' => 'maternal health',
        ], 'en');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertSee('SEO setup completeness')
            ->assertSee('Saved page content')
            ->assertSee('without AI or keyword-density scoring')
            ->assertSee('Use the focus phrase naturally in the saved page body')
            ->assertSee('content images covered');

        $analysis = data_get($response->viewData('editor'), 'content_analysis');
        $healthKeys = collect(data_get($response->viewData('editor'), 'health.issues'))->pluck('key');
        $this->assertTrue((bool) data_get($analysis, 'available'));
        $this->assertSame(1, data_get($analysis, 'h1_count'));
        $this->assertSame(1, data_get($analysis, 'image_count'));
        $this->assertSame(0, data_get($analysis, 'images_with_alt'));
        $this->assertTrue($healthKeys->contains('content_missing_image_alt'));
        $this->assertTrue($healthKeys->contains('focus_missing_body'));
    }

    public function test_bulk_workspace_only_saves_explicitly_selected_rows(): void
    {
        $admin = $this->seoAdmin();
        $selected = $this->category('selected-bulk-row');
        $ignored = $this->category('ignored-bulk-row');

        $this->actingAs($admin, 'admin')->get(route('seo.bulk.index', ['type' => 'category']))
            ->assertOk()
            ->assertSee('Select all editable rows')
            ->assertSee('Save selected rows')
            ->assertSee('data-bulk-title-quality', false)
            ->assertSee('data-bulk-description-quality', false)
            ->assertSee('Social image description for', false);

        $this->put(route('seo.bulk.update'), [
            'selection_mode' => 'explicit',
            'items' => [
                $this->bulkItem($selected, 'Selected metadata title', true),
                $this->bulkItem($ignored, 'This title must be ignored', false, 'deliberately-stale-token'),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $selected->id,
            'title' => 'Selected metadata title',
            'social_image_alt' => 'Families participating in a community program',
        ]);
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $ignored->id,
        ]);
    }

    public function test_bulk_workspace_rejects_an_explicit_save_with_no_selected_rows(): void
    {
        $admin = $this->seoAdmin();
        $category = $this->category('no-selection-bulk-row');

        $this->actingAs($admin, 'admin')->from(route('seo.bulk.index'))
            ->put(route('seo.bulk.update'), [
                'selection_mode' => 'explicit',
                'items' => [$this->bulkItem($category, 'Should not save', false)],
            ])
            ->assertRedirect(route('seo.bulk.index'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
        ]);
    }

    private function category(string $slug): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'description' => 'Clear information about this community program and its local outcomes.',
            'language' => 'en',
            'status' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function bulkItem(Category $category, string $title, bool $selected, ?string $token = null): array
    {
        return [
            'selected' => $selected ? 1 : 0,
            'owner_type' => 'category',
            'owner_id' => $category->id,
            'route_name' => null,
            'locale' => 'en',
            'expected_seo_version' => $token ?: app(SeoMetadataEditorVersionService::class)->currentForModel($category, 'en'),
            'mode' => 'custom',
            'title' => $title,
            'description' => 'Learn how this community program delivers practical support, useful information and measurable local outcomes for families across Bangladesh.',
            'image' => 'https://example.test/share.jpg',
            'image_alt' => 'Families participating in a community program',
            'indexable' => 1,
            'schema_template' => 'none',
        ];
    }

    private function seoAdmin(): Admin
    {
        $menuIds = AuthMenu::whereIn('link', ['seo.index'])->pluck('id')->implode(',');
        $actionIds = MenuAction::whereIn('link', ['seo.metadata.edit'])->pluck('id')->implode(',');
        $role = Role::create([
            'name' => 'SEO content quality ' . Str::random(8),
            'permission' => $menuIds,
            'actionPermission' => $actionIds,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'SEO quality editor',
            'username' => 'seo-quality-' . Str::random(6),
            'email' => Str::random(8) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
