<?php

namespace Tests\Feature;

use App\Helper\MyMenu;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageMenu;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationEditorIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_editor_replaces_the_legacy_create_form_with_one_simple_screen(): void
    {
        $admin = $this->makeAdmin(['page.menu.create']);

        $this->actingAs($admin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('Build your website menus without route names or order numbers.')
            ->assertSee('Add a menu item')
            ->assertSee('Parent item (optional)')
            ->assertDontSee('Save menu')
            ->assertDontSee('Open navigation trash')
            ->assertSee('data-menu-list', false)
            ->assertDontSee('Order By');

        $this->actingAs($admin, 'admin')->get(route('page.menu.create'))
            ->assertRedirect(route('page.menu.index'));
    }

    public function test_view_only_navigation_hides_every_mutating_control(): void
    {
        $item = $this->makeMenuItem();
        $admin = $this->makeAdmin([]);

        $this->actingAs($admin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee($item->name)
            ->assertDontSee('Add to menu')
            ->assertDontSee('Save menu')
            ->assertDontSee('data-menu-action="', false)
            ->assertDontSee('title="Edit item"', false)
            ->assertDontSee('data-url="'.route('page.menu.item.update', $item->uuid).'"', false)
            ->assertDontSee('data-url="'.route('page.menu.destroy', $item->uuid).'"', false)
            ->assertDontSee('data-url="'.route('page.menu.status', $item->uuid).'"', false);
    }

    public function test_navigation_item_controls_match_edit_status_and_delete_capabilities(): void
    {
        $item = $this->makeMenuItem();

        $editAdmin = $this->makeAdmin(['page.menu.edit']);
        $this->actingAs($editAdmin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('Save menu')
            ->assertSee('data-url="'.route('page.menu.item.update', $item->uuid).'"', false)
            ->assertDontSee('data-delete-menu-item data-url=', false)
            ->assertDontSee('data-toggle-menu-status data-current-status=', false);

        $statusAdmin = $this->makeAdmin(['page.menu.status']);
        $this->actingAs($statusAdmin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('data-toggle-menu-status data-current-status=', false)
            ->assertDontSee('data-url="'.route('page.menu.item.update', $item->uuid).'"', false)
            ->assertDontSee('data-delete-menu-item data-url=', false);
        $this->actingAs($statusAdmin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('page.menu.status', $item->uuid))
            ->assertOk();
        $this->assertFalse((bool) $item->fresh()->status);

        $deleteAdmin = $this->makeAdmin(['page.menu.destroy']);
        $this->actingAs($deleteAdmin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('data-delete-menu-item data-url=', false)
            ->assertDontSee('data-url="'.route('page.menu.item.update', $item->uuid).'"', false)
            ->assertDontSee('data-toggle-menu-status data-current-status=', false);
    }

    public function test_navigation_trash_controls_match_restore_and_permanent_delete_capabilities(): void
    {
        $item = $this->makeMenuItem();
        $item->delete();

        $viewer = $this->makeAdmin(['page.menu.trash.view']);
        $this->actingAs($viewer, 'admin')->get(route('page.menu.trash'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee('View only')
            ->assertDontSee('action="'.route('page.menu.restore', $item->uuid).'"', false)
            ->assertDontSee('action="'.route('page.menu.force-destroy', $item->uuid).'"', false);

        $restorer = $this->makeAdmin(['page.menu.trash.view', 'page.menu.edit']);
        $this->actingAs($restorer, 'admin')->get(route('page.menu.trash'))
            ->assertOk()
            ->assertSee('action="'.route('page.menu.restore', $item->uuid).'"', false)
            ->assertDontSee('action="'.route('page.menu.force-destroy', $item->uuid).'"', false);

        $destroyer = $this->makeAdmin(['page.menu.trash.view', 'page.menu.destroy']);
        $this->actingAs($destroyer, 'admin')->get(route('page.menu.trash'))
            ->assertOk()
            ->assertDontSee('action="'.route('page.menu.restore', $item->uuid).'"', false)
            ->assertSee('action="'.route('page.menu.force-destroy', $item->uuid).'"', false);
    }

    public function test_simple_editor_creates_builtin_page_nested_and_safe_custom_items(): void
    {
        $admin = $this->makeAdmin(['page.menu.create']);
        $page = $this->makePage('our-story');
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs',
            'slug' => 'programs',
            'status' => 1,
            'language' => 'en',
        ]);

        $home = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Home',
            'destination_type' => 'route',
            'destination' => 'frontend.home',
        ]))->assertCreated()->json('item');

        $story = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Our story',
            'destination_type' => 'page',
            'destination' => $page->slug,
            'parent_uuid' => $home['uuid'],
        ]))->assertCreated()->json('item');

        $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Unsupported third level',
            'destination_type' => 'label',
            'parent_uuid' => $story['uuid'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('parent_uuid');

        $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Programs',
            'destination_type' => 'category',
            'destination' => $category->slug,
        ]))->assertCreated();

        $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Unsafe',
            'destination_type' => 'custom',
            'destination' => 'javascript:alert(1)',
        ]))->assertUnprocessable()->assertJsonValidationErrors('destination');

        $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Partner site',
            'destination_type' => 'custom',
            'destination' => 'https://example.org/partner',
        ]))->assertCreated();

        $this->assertDatabaseHas('page_menus', [
            'uuid' => $story['uuid'],
            'parent_id' => $home['id'],
            'link' => 'frontend.page',
            'slug' => 'our-story',
            'status' => 1,
        ]);
        $publicMenu = MyMenu::frontMenus('en')->toArray();
        $this->assertSame('Home', $publicMenu[0]['name']);
        $this->assertSame('Our story', $publicMenu[0]['children'][0]['name']);
    }

    public function test_reorder_requires_the_complete_location_and_prevents_unsupported_depth(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit']);
        $first = $this->createLabel($admin, 'First');
        $second = $this->createLabel($admin, 'Second');
        $third = $this->createLabel($admin, 'Third');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => null, 'order' => 1],
            ],
        ])->assertOk();

        $this->assertSame($second['id'], PageMenu::where('uuid', $first['uuid'])->value('parent_id'));
        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => $first['uuid'], 'order' => 0],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_inline_edit_sanitizes_custom_url_and_parent_delete_is_safe(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit', 'page.menu.destroy']);
        $parent = $this->createLabel($admin, 'Parent');
        $child = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Custom child',
            'destination_type' => 'custom',
            'destination' => '/contact-us',
            'parent_uuid' => $parent['uuid'],
        ]))->assertCreated()->json('item');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.item.update', $child['uuid']), [
            'locale' => 'en',
            'label' => 'Updated child',
            'enabled' => false,
            'custom_url' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors('custom_url');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.item.update', $child['uuid']), [
            'locale' => 'en',
            'label' => 'Updated child',
            'enabled' => false,
            'custom_url' => '/about-us',
        ])->assertOk()->assertJsonPath('item.status', false);
        $this->assertDatabaseHas('page_menus', ['uuid' => $child['uuid'], 'name' => 'Updated child', 'slug' => '/about-us', 'status' => 0]);

        $this->actingAs($admin, 'admin')->deleteJson(route('page.menu.destroy', $parent['uuid']))
            ->assertUnprocessable();
        $this->actingAs($admin, 'admin')->deleteJson(route('page.menu.destroy', $child['uuid']))->assertOk();
        $this->actingAs($admin, 'admin')->deleteJson(route('page.menu.destroy', $parent['uuid']))->assertOk();
        $this->assertSoftDeleted('page_menus', ['uuid' => $parent['uuid']]);
    }

    public function test_menu_descriptions_are_plain_text_editable_and_shared_with_public_navigation(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit']);
        $item = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Our work',
            'description' => '<strong>Programs</strong> led with communities.',
        ]))->assertCreated()->json('item');

        $this->assertDatabaseHas('page_menus', [
            'uuid' => $item['uuid'],
            'description' => 'Programs led with communities.',
        ]);
        $this->assertSame('Programs led with communities.', MyMenu::frontMenus('en')->first()->description);

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.item.update', $item['uuid']), [
            'locale' => 'en',
            'label' => 'Our work',
            'description' => 'Explore education, health, and livelihoods.',
            'enabled' => true,
            'custom_url' => null,
        ])->assertOk()->assertJsonPath('item.description', 'Explore education, health, and livelihoods.');
    }

    public function test_legacy_editor_resolves_rows_by_route_uuid_and_locale_not_a_forged_hidden_id(): void
    {
        $admin = $this->makeAdmin(['page.menu.edit']);
        $target = $this->makeMenuItem('Target navigation');
        $victim = $this->makeMenuItem('Unrelated navigation');

        $this->actingAs($admin, 'admin')->put(route('page.menu.update'), [
            'uuid' => $target->uuid,
            'language' => ['en' => 'en'],
            'id' => ['en' => $victim->id],
            'name' => ['en' => 'Updated target'],
            'description' => ['en' => 'Only the routed item may change.'],
            'parent' => ['en' => null],
            'type' => ['en' => 'main'],
            'link' => ['en' => 'frontend.page'],
            'slug' => ['en' => 'updated-target'],
            'icon' => ['en' => null],
            'banner_id' => ['en' => null],
            'order_by' => ['en' => 1],
        ])->assertRedirect(route('page.menu.index'));

        $this->assertDatabaseHas('page_menus', [
            'id' => $target->id,
            'uuid' => $target->uuid,
            'name' => 'Updated target',
            'slug' => 'updated-target',
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $victim->id,
            'uuid' => $victim->uuid,
            'name' => 'Unrelated navigation',
            'slug' => 'about-us',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'simple' => true,
            'locale' => 'en',
            'location' => 'main',
            'label' => 'Menu item',
            'description' => '',
            'destination_type' => 'label',
            'destination' => '',
            'parent_uuid' => null,
            'enabled' => true,
        ], $overrides);
    }

    private function createLabel(Admin $admin, string $label): array
    {
        return $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload(['label' => $label]))
            ->assertCreated()->json('item');
    }

    private function makePage(string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => '',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
    }

    private function makeMenuItem(string $name = 'About us'): PageMenu
    {
        return PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => 'Learn more about our work.',
            'type' => 'main',
            'link' => 'frontend.page',
            'slug' => 'about-us',
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
        ]);
    }

    private function makeAdmin(array $actionLinks): Admin
    {
        $suffix = Str::lower(Str::random(8));
        $menu = AuthMenu::create(['name' => 'Navigation', 'link' => 'page.menu.index', 'status' => 1]);
        $actions = collect($actionLinks)->unique()->map(fn (string $link) => MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => Str::headline(str_replace('.', ' ', $link)),
            'link' => $link,
            'status' => 1,
        ]));
        $role = Role::create([
            'name' => 'Navigation editor',
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Navigation QA',
            'username' => 'navigation-'.$suffix,
            'email' => 'navigation-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
