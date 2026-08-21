<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Role;
use App\Models\SeoMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentHubIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_hub_exposes_language_type_translation_and_bulk_controls(): void
    {
        $admin = $this->authorizedAdmin();
        $this->page(['language' => 'en']);
        $this->page(['language' => 'bn']);

        $this->actingAs($admin, 'admin')->get(route('page.index'))
            ->assertOk()
            ->assertSee('Content type')
            ->assertSee('Language')
            ->assertSee('Needs translation')
            ->assertSee('Select all on this page')
            ->assertSee('data-bulk="translate"', false)
            ->assertSee('data-bulk="duplicate"', false)
            ->assertSee('data-bulk="delete"', false);
    }

    public function test_view_only_content_manager_gets_a_clear_read_only_hub(): void
    {
        $role = Role::create([
            'name' => 'Content viewer',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $menu = AuthMenu::where('link', 'page.index')->firstOrFail();
        $role->update(['permission' => (string) $menu->id]);
        $admin = Admin::create([
            'name' => 'Content Viewer',
            'username' => 'content-viewer',
            'email' => 'content-viewer@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        $page = $this->page(['name' => 'Preview-only page']);

        $this->actingAs($admin, 'admin')->get(route('page.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee(route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]), false)
            ->assertDontSee('<a class="hub-create"', false)
            ->assertDontSee('<button type="button" data-bulk=', false)
            ->assertDontSee('class="hub-select hub-row-select"', false)
            ->assertDontSee('title="Move to trash"', false)
            ->assertDontSee('title="Open visual editor"', false);
    }

    public function test_bulk_duplicate_and_translate_create_complete_safe_drafts(): void
    {
        $admin = $this->authorizedAdmin();
        $source = $this->page(['name' => 'Original page', 'language' => 'en']);
        PageBlock::create([
            'page_id' => $source->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Original section',
            'content' => ['heading' => 'Original content'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $source->id,
            'locale' => 'en',
            'title' => 'Original SEO',
            'robots_index' => true,
            'robots_follow' => true,
            'review_status' => 'approved',
            'review_note' => 'Approved source copy',
            'review_content_hash' => str_repeat('a', 64),
            'review_requested_by' => $admin->id,
            'review_requested_at' => now()->subHour(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$source->id],
            'action' => 'duplicate',
        ])->assertOk()->assertJsonPath('created', 1);

        $duplicate = Page::where('name', 'Original page (Copy)')->firstOrFail();
        $this->assertSame('draft', $duplicate->publication_status);
        $this->assertFalse($duplicate->status);
        $this->assertSame(1, $duplicate->blocks()->count());
        $duplicateSeo = $duplicate->seo()->firstOrFail();
        $this->assertFalse((bool) $duplicateSeo->robots_index);
        $this->assertSame('draft', $duplicateSeo->review_status);
        $this->assertNull($duplicateSeo->review_note);
        $this->assertNull($duplicateSeo->review_content_hash);
        $this->assertNull($duplicateSeo->review_requested_by);
        $this->assertNull($duplicateSeo->review_requested_at);
        $this->assertNull($duplicateSeo->reviewed_by);
        $this->assertNull($duplicateSeo->reviewed_at);
        $this->assertSame($admin->id, (int) $duplicateSeo->created_by);

        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$source->id],
            'action' => 'translate',
            'target_language' => 'bn',
        ])->assertOk()->assertJsonPath('created', 1);

        $translation = Page::where('uuid', $source->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertSame('draft', $translation->publication_status);
        $this->assertSame(1, $translation->blocks()->count());

        $this->actingAs($admin, 'admin')->postJson(route('page.bulk.copy'), [
            'page_ids' => [$source->id],
            'action' => 'translate',
            'target_language' => 'bn',
        ])->assertOk()->assertJsonPath('skipped', 1);
        $this->assertSame(1, Page::where('uuid', $source->uuid)->where('language', 'bn')->count());
    }

    public function test_bulk_delete_moves_all_language_versions_to_recoverable_trash(): void
    {
        $admin = $this->authorizedAdmin();
        $source = $this->page(['language' => 'en']);
        $translation = $this->page(['uuid' => $source->uuid, 'slug' => $source->slug, 'language' => 'bn']);

        $this->actingAs($admin, 'admin')->deleteJson(route('page.bulk.destroy'), [
            'page_ids' => [$source->id],
        ])->assertOk();

        $this->assertSoftDeleted('pages', ['id' => $source->id]);
        $this->assertSoftDeleted('pages', ['id' => $translation->id]);
        $this->assertSame(2, Page::onlyTrashed()->where('uuid', $source->uuid)->count());
    }

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Hub page ' . Str::random(5),
            'sub_title' => 'Content hub test',
            'slug' => 'hub-' . Str::lower(Str::random(8)),
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function authorizedAdmin(): Admin
    {
        $role = Role::create([
            'name' => 'Content manager',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $menu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        $edit = MenuAction::create(['auth_menu_id' => $menu->id, 'name' => 'Edit pages', 'link' => 'page.edit', 'status' => 1]);
        $destroy = MenuAction::create(['auth_menu_id' => $menu->id, 'name' => 'Delete pages', 'link' => 'page.destroy', 'status' => 1]);
        $role->update([
            'permission' => (string) $menu->id,
            'actionPermission' => $edit->id . ',' . $destroy->id,
        ]);

        return Admin::create([
            'name' => 'Content QA',
            'username' => 'content-qa',
            'email' => 'content-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
