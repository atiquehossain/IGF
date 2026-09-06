<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Models\Role;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReusableBlockLibraryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_library_offers_open_preview_and_permission_aware_edit_actions(): void
    {
        $block = $this->reusableBlock('Shared impact introduction');
        $owner = $this->owner();

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('href="'.route('reusable-blocks.show', $block).'"', false)
            ->assertSee('href="'.route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']).'"', false)
            ->assertSee('href="'.route('reusable-blocks.edit', $block).'"', false)
            ->assertSee('Open')
            ->assertSee('Preview')
            ->assertSee('Edit');

        $viewer = $this->viewer();
        $this->actingAs($viewer, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('href="'.route('reusable-blocks.show', $block).'"', false)
            ->assertSee('href="'.route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']).'"', false)
            ->assertDontSee('href="'.route('reusable-blocks.edit', $block).'"', false);
        $this->actingAs($viewer, 'admin')->get(route('reusable-blocks.edit', $block))->assertForbidden();
    }

    public function test_editor_names_every_affected_page_and_requires_explicit_impact_acknowledgement(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Shared impact introduction');
        $pageA = $this->page('Community health', 'community-health');
        $pageB = $this->page('Inclusive education', 'inclusive-education');
        $this->attach($pageA, $block);
        $this->attach($pageB, $block);

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Saving updates 2 pages')
            ->assertSee($pageA->name)
            ->assertSee($pageB->name)
            ->assertSee(route('page.builder.edit', ['uuid' => $pageA->uuid, 'locale' => 'en']), false)
            ->assertSee(route('page.builder.preview', ['uuid' => $pageB->uuid, 'locale' => 'en']), false)
            ->assertSee('I reviewed the affected pages')
            ->assertSee('Desktop preview')
            ->assertSee('Tablet preview')
            ->assertSee('Mobile preview')
            ->assertSee('No code or JSON is required');

        $payload = $this->libraryPayload($block, [$pageA, $pageB]);
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact_acknowledged');

        $payload['impact_acknowledged'] = true;
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertOk()
            ->assertJsonPath('connected_page_count', 2)
            ->assertJsonPath('block.content.heading', 'Updated shared heading')
            ->assertJsonPath('block.editor_version', 1);

        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
    }

    public function test_unattached_library_section_remains_editable_without_an_impact_confirmation(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Unattached introduction');

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Not used on a page yet')
            ->assertSee('Save library section')
            ->assertDontSee('name="impact_acknowledged"', false);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), [
            'expected_version' => 0,
            'name' => 'Unattached introduction updated',
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Safe unattached update',
                'body' => '<p>Useful copy</p><script>alert(1)</script>',
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertOk()
            ->assertJsonPath('connected_page_count', 0)
            ->assertJsonPath('block.content.heading', 'Safe unattached update');

        $this->assertSame('Unattached introduction updated', $block->fresh()->name);
        $this->assertStringNotContainsString('<script', $block->fresh()->content['body']);
    }

    public function test_editor_rejects_a_save_when_the_affected_page_list_changed(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Changing usage section');
        $first = $this->page('First connected page', 'first-connected-page');
        $second = $this->page('Second connected page', 'second-connected-page');
        $this->attach($first, $block);
        $payload = $this->libraryPayload($block, [$first]);
        $payload['impact_acknowledged'] = true;

        $this->attach($second, $block);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'The pages using this section changed while it was open. Reload the editor and review the updated page list before saving.');

        $this->assertSame('Shared impact introduction', $block->fresh()->content['heading']);
        $this->assertSame(0, (int) $block->fresh()->editor_version);
    }

    public function test_preview_uses_the_public_page_block_renderer_even_for_a_disabled_section(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Disabled preview section');
        $block->update(['is_enabled' => false]);

        $this->actingAs($owner, 'admin')
            ->get(route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reusable-block-preview')
                ->where('libraryStatus', 'disabled')
                ->where('previewLocale', 'en')
                ->where('block.type', 'rich_text')
                ->where('block.content.heading', 'Shared impact introduction')
                ->where('block.is_enabled', true));
    }

    public function test_library_editor_uses_named_managed_choices_instead_of_raw_identifiers(): void
    {
        $owner = $this->owner();
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared ways to give',
            'type' => 'ways_to_give',
            'locale' => 'en',
            'content' => [
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['sponsor'],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Sponsor a Child')
            ->assertSee('data-e2e="reusable-managed-items"', false)
            ->assertSee('Internal record IDs are never required.');
    }

    public function test_library_editor_reuses_builder_validation_for_managed_content(): void
    {
        $owner = $this->owner();
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Validated ways to give',
            'type' => 'ways_to_give',
            'locale' => 'en',
            'content' => [
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['sponsor'],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), [
            'expected_version' => 0,
            'name' => $block->name,
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['not-a-managed-destination'],
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('content.selected_items');

        $this->assertSame(['sponsor'], $block->fresh()->content['selected_items']);
    }

    private function libraryPayload(ReusableBlock $block, array $pages): array
    {
        return [
            'expected_version' => (int) $block->editor_version,
            'name' => $block->name,
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Updated shared heading',
                'body' => '<p>Updated shared body.</p>',
                'section_presentation' => 'soft',
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => json_encode(collect($pages)->pluck('uuid')->unique()->sort()->values()->all()),
            'expected_connected_page_ids_json' => json_encode(collect($pages)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()),
        ];
    }

    private function owner(): Admin
    {
        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Reusable library owner '.$suffix,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);

        return Admin::create([
            'name' => 'Reusable Library Owner',
            'username' => 'reusable-owner-'.$suffix,
            'email' => 'reusable-owner-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function viewer(): Admin
    {
        $menu = AuthMenu::where('link', 'reusable-blocks.index')->firstOrFail();
        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Reusable library viewer '.$suffix,
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Reusable Library Viewer',
            'username' => 'reusable-viewer-'.$suffix,
            'email' => 'reusable-viewer-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function reusableBlock(string $name): ReusableBlock
    {
        return ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => [
                'eyebrow' => 'Our work',
                'heading' => 'Shared impact introduction',
                'body' => '<p>Shared body copy.</p>',
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);
    }

    private function page(string $name, string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'sub_title' => $name.' introduction',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
    }

    private function attach(Page $page, ReusableBlock $block): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'reusable_block_id' => $block->id,
            'uuid' => (string) Str::uuid(),
            'type' => $block->type,
            'label' => $block->name,
            'content' => $block->content,
            'settings' => $block->settings,
            'sort_order' => 0,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }
}
