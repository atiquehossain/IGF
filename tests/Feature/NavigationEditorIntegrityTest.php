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

    public function test_create_and_edit_permissions_cannot_bypass_navigation_publication_permission(): void
    {
        $creator = $this->makeAdmin(['page.menu.create']);
        $created = $this->actingAs($creator, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Creator draft',
            'enabled' => true,
        ]))->assertCreated()->json('item');
        $this->assertDatabaseHas('page_menus', ['uuid' => $created['uuid'], 'status' => 0]);
        $this->actingAs($creator, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('New items are hidden until someone with publication access makes them visible.');

        $visible = $this->makeMenuItem('Visible navigation');
        $editor = $this->makeAdmin(['page.menu.edit']);
        $this->actingAs($editor, 'admin')->putJson(route('page.menu.item.update', $visible->uuid), [
            'locale' => 'en',
            'label' => 'Edited without publishing',
            'description' => '',
            'enabled' => false,
            'custom_url' => null,
        ])->assertOk()->assertJsonPath('item.status', true);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => $visible->uuid,
            'name' => 'Edited without publishing',
            'status' => 1,
        ]);

        $publisher = $this->makeAdmin(['page.menu.edit', 'page.menu.status']);
        $this->actingAs($publisher, 'admin')->putJson(route('page.menu.item.update', $visible->uuid), [
            'locale' => 'en',
            'label' => 'Hidden by publisher',
            'description' => '',
            'enabled' => false,
            'custom_url' => null,
        ])->assertOk()->assertJsonPath('item.status', false);
        $this->assertDatabaseHas('page_menus', ['uuid' => $visible->uuid, 'status' => 0]);

        $publishingCreator = $this->makeAdmin(['page.menu.create', 'page.menu.status']);
        $published = $this->actingAs($publishingCreator, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Published at creation',
            'enabled' => true,
        ]))->assertCreated()->json('item');
        $this->assertDatabaseHas('page_menus', ['uuid' => $published['uuid'], 'status' => 1]);
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

    public function test_direct_restore_cannot_republish_without_publication_permission(): void
    {
        $blocked = $this->makeMenuItem('Unauthorized restore');
        $blocked->delete();
        $viewer = $this->makeAdmin(['page.menu.trash.view']);
        $this->actingAs($viewer, 'admin')
            ->post(route('page.menu.restore', $blocked->uuid))
            ->assertForbidden();
        $this->assertSoftDeleted('page_menus', ['id' => $blocked->id]);

        $active = $this->makeMenuItem('Active deleted navigation');
        $active->delete();
        $editor = $this->makeAdmin(['page.menu.edit']);

        $this->actingAs($editor, 'admin')
            ->post(route('page.menu.restore', $active->uuid))
            ->assertRedirect();
        $this->assertNotSoftDeleted('page_menus', ['id' => $active->id]);
        $this->assertDatabaseHas('page_menus', ['id' => $active->id, 'status' => 0]);

        $published = $this->makeMenuItem('Publisher restore');
        $published->delete();
        $publisher = $this->makeAdmin(['page.menu.edit', 'page.menu.status']);
        $this->actingAs($publisher, 'admin')
            ->post(route('page.menu.restore', $published->uuid))
            ->assertRedirect();
        $this->assertNotSoftDeleted('page_menus', ['id' => $published->id]);
        $this->assertDatabaseHas('page_menus', ['id' => $published->id, 'status' => 1]);
    }

    public function test_restore_revalidates_its_parent_and_rolls_back_when_the_parent_is_deleted(): void
    {
        $parent = $this->makeMenuItem('Deleted restore parent');
        $child = $this->makeChild($parent, 'Deleted restore child');
        $child->delete();
        $parent->delete();
        $editor = $this->makeAdmin(['page.menu.edit']);

        $this->actingAs($editor, 'admin')
            ->postJson(route('page.menu.restore', $child->uuid))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent');
        $this->assertSoftDeleted('page_menus', ['id' => $child->id]);
    }

    public function test_simple_editor_creates_builtin_page_nested_and_safe_custom_items(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.status']);
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

        $workshop = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Workshop',
            'destination_type' => 'route',
            'destination' => 'frontend.workshops.index',
            'parent_uuid' => $story['uuid'],
        ]))->assertCreated()->json('item');

        $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Unsupported fourth level',
            'destination_type' => 'label',
            'parent_uuid' => $workshop['uuid'],
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
        $this->assertDatabaseHas('page_menus', [
            'uuid' => $workshop['uuid'],
            'parent_id' => $story['id'],
            'link' => 'frontend.workshops.index',
            'status' => 1,
        ]);
        $this->actingAs($admin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('frontend.workshops.index')
            ->assertSee('Workshop');
        $publicMenu = MyMenu::frontMenus('en')->toArray();
        $this->assertSame('Home', $publicMenu[0]['name']);
        $this->assertSame('Our story', $publicMenu[0]['children'][0]['name']);
        $this->assertSame('Workshop', $publicMenu[0]['children'][0]['children'][0]['name']);
    }

    public function test_simple_parent_choices_are_scoped_existing_and_limited_to_valid_parent_depths(): void
    {
        $admin = $this->makeAdmin(['page.menu.create']);
        $root = $this->createLabel($admin, 'Root');
        $child = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Child',
            'parent_uuid' => $root['uuid'],
        ]))->assertCreated()->json('item');
        $grandchild = $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
            'label' => 'Grandchild',
            'parent_uuid' => $child['uuid'],
        ]))->assertCreated()->json('item');

        $this->actingAs($admin, 'admin')->get(route('page.menu.index'))
            ->assertOk()
            ->assertSee('option value="'.$root['uuid'].'"', false)
            ->assertSee('option value="'.$child['uuid'].'"', false)
            ->assertDontSee('option value="'.$grandchild['uuid'].'"', false);

        $footer = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Footer parent',
            'type' => 'footer',
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
        ]);
        $bangla = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bangla parent',
            'type' => 'main',
            'language' => 'bn',
            'order_by' => 0,
            'status' => 1,
        ]);
        $deleted = $this->makeMenuItem('Deleted parent');
        $deleted->delete();

        foreach ([$footer, $bangla, $deleted] as $invalidParent) {
            $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $this->payload([
                'label' => 'Invalid child '.$invalidParent->id,
                'parent_uuid' => $invalidParent->uuid,
            ]))->assertUnprocessable()->assertJsonValidationErrors('parent_uuid');
        }
    }

    public function test_reorder_requires_the_complete_location_allows_three_levels_and_rejects_four_or_cycles(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit']);
        $first = $this->createLabel($admin, 'First');
        $second = $this->createLabel($admin, 'Second');
        $third = $this->createLabel($admin, 'Third');
        $fourth = $this->createLabel($admin, 'Fourth');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => null, 'order' => 1],
                ['uuid' => $fourth['uuid'], 'parent_uuid' => null, 'order' => 2],
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
                ['uuid' => $fourth['uuid'], 'parent_uuid' => null, 'order' => 1],
            ],
        ])->assertOk();
        $this->assertSame($first['id'], PageMenu::where('uuid', $third['uuid'])->value('parent_id'));

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => $first['uuid'], 'order' => 0],
                ['uuid' => $fourth['uuid'], 'parent_uuid' => $third['uuid'], 'order' => 0],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => $third['uuid'], 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => $first['uuid'], 'order' => 0],
                ['uuid' => $fourth['uuid'], 'parent_uuid' => null, 'order' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $footer = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Footer parent',
            'type' => 'footer',
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
        ]);
        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'main',
            'items' => [
                ['uuid' => $second['uuid'], 'parent_uuid' => $footer->uuid, 'order' => 0],
                ['uuid' => $first['uuid'], 'parent_uuid' => $second['uuid'], 'order' => 0],
                ['uuid' => $third['uuid'], 'parent_uuid' => $first['uuid'], 'order' => 0],
                ['uuid' => $fourth['uuid'], 'parent_uuid' => null, 'order' => 1],
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
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit', 'page.menu.status']);
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

    public function test_legacy_custom_destinations_use_the_same_safe_url_policy_as_the_simple_editor(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit']);
        $unsafeUrls = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '\\\\attacker.test/share',
        ];

        foreach ($unsafeUrls as $index => $unsafeUrl) {
            $payload = $this->legacyCreatePayload('Unsafe legacy custom '.$index, null);
            $payload['link']['en'] = 'custom';
            $payload['slug']['en'] = $unsafeUrl;
            $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug.en');
        }

        $target = $this->makeMenuItem('Legacy custom update');
        $target->update(['link' => 'custom', 'slug' => '/safe-before-update']);
        foreach ($unsafeUrls as $unsafeUrl) {
            $payload = $this->legacyUpdatePayload($target, null);
            $payload['slug']['en'] = $unsafeUrl;
            $this->actingAs($admin, 'admin')->putJson(route('page.menu.update'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug.en');
            $this->assertSame('/safe-before-update', $target->fresh()->slug);
        }

        $safe = $this->legacyCreatePayload('Safe legacy custom', null);
        $safe['link']['en'] = 'custom';
        $safe['slug']['en'] = '//example.org/community';
        $this->actingAs($admin, 'admin')->post(route('page.menu.store'), $safe)
            ->assertRedirect(route('page.menu.index'));
        $this->assertDatabaseHas('page_menus', [
            'name' => 'Safe legacy custom',
            'link' => 'custom',
            'slug' => 'https://example.org/community',
        ]);
    }

    public function test_legacy_parent_mutations_reject_self_descendants_cross_scope_deleted_and_fourth_level_parents(): void
    {
        $admin = $this->makeAdmin(['page.menu.create', 'page.menu.edit']);
        $root = $this->makeMenuItem('Legacy root');
        $child = $this->makeChild($root, 'Legacy child');
        $grandchild = $this->makeChild($child, 'Legacy grandchild');
        $loose = $this->makeMenuItem('Loose item');
        $footer = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Footer item',
            'type' => 'footer',
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
        ]);
        $deleted = $this->makeMenuItem('Deleted legacy parent');
        $deleted->delete();

        foreach ([$root->id, $child->id, $footer->id, $deleted->id] as $invalidParentId) {
            $this->actingAs($admin, 'admin')->putJson(
                route('page.menu.update'),
                $this->legacyUpdatePayload($root, $invalidParentId)
            )->assertUnprocessable()->assertJsonValidationErrors('parent.en');
        }

        $this->actingAs($admin, 'admin')->postJson(
            route('page.menu.store'),
            $this->legacyCreatePayload('Legacy fourth level', $grandchild->id)
        )->assertUnprocessable()->assertJsonValidationErrors('parent.en');
        $this->actingAs($admin, 'admin')->postJson(
            route('page.menu.store'),
            $this->legacyCreatePayload('Legacy cross scope', $footer->id)
        )->assertUnprocessable()->assertJsonValidationErrors('parent.en');

        $this->actingAs($admin, 'admin')->put(
            route('page.menu.update'),
            $this->legacyUpdatePayload($loose, $child->id)
        )->assertRedirect(route('page.menu.index'));
        $this->assertDatabaseHas('page_menus', ['id' => $loose->id, 'parent_id' => $child->id]);

        $parents = $this->actingAs($admin, 'admin')->getJson(route('page.menu.showParent', [
            'id' => 'main',
            'lang' => 'en',
            'exclude_uuid' => $loose->uuid,
        ]))->assertOk()->json('data');
        $parentIds = collect($parents)->pluck('id');
        $this->assertTrue($parentIds->contains($root->id));
        $this->assertTrue($parentIds->contains($child->id));
        $this->assertFalse($parentIds->contains($loose->id));
        $this->assertFalse($parentIds->contains($grandchild->id));
    }

    public function test_public_menu_rejects_cross_scope_children_and_api_decorates_all_three_levels(): void
    {
        $root = $this->makeMenuItem('API root');
        $child = $this->makeChild($root, 'API child');
        $grandchild = $this->makeChild($child, 'API grandchild');
        $this->makeChild($root, 'Wrong location', ['type' => 'footer']);
        $this->makeChild($root, 'Wrong language', ['language' => 'bn']);

        $publicRoot = collect(MyMenu::frontMenus('en')->toArray())->firstWhere('uuid', $root->uuid);
        $this->assertSame(['API child'], collect($publicRoot['children'])->pluck('name')->all());
        $this->assertSame('API grandchild', $publicRoot['children'][0]['children'][0]['name']);

        $response = $this->withHeader('locale', 'en')->getJson('/api/v1/menu')->assertOk();
        $apiRoot = collect($response->json('data'))->firstWhere('uuid', $root->uuid);
        $this->assertSame(route('api.frontend.page', [$root->slug]), $apiRoot['api']);
        $this->assertSame(route('api.frontend.page', [$child->slug]), $apiRoot['children'][0]['api']);
        $this->assertSame(route('api.frontend.page', [$grandchild->slug]), $apiRoot['children'][0]['children'][0]['api']);
    }

    public function test_public_menu_api_sanitizes_legacy_custom_urls_at_every_depth(): void
    {
        $root = $this->makeMenuItem('Unsafe API root');
        $root->update(['link' => 'custom', 'slug' => 'javascript:alert(1)']);
        $child = $this->makeChild($root, 'Unsafe API child', [
            'link' => 'custom',
            'slug' => 'data:text/html,<script>alert(1)</script>',
        ]);
        $this->makeChild($child, 'Unsafe API grandchild', [
            'link' => 'custom',
            'slug' => '\\\\attacker.test/share',
        ]);

        $response = $this->withHeader('locale', 'en')->getJson('/api/v1/menu')->assertOk();
        $apiRoot = collect($response->json('data'))->firstWhere('uuid', $root->uuid);
        $this->assertSame('#', $apiRoot['slug']);
        $this->assertSame('#', $apiRoot['api']);
        $this->assertSame('#', $apiRoot['children'][0]['slug']);
        $this->assertSame('#', $apiRoot['children'][0]['api']);
        $this->assertSame('#', $apiRoot['children'][0]['children'][0]['slug']);
        $this->assertSame('#', $apiRoot['children'][0]['children'][0]['api']);
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

    private function makeChild(PageMenu $parent, string $name, array $overrides = []): PageMenu
    {
        return PageMenu::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parent->id,
            'name' => $name,
            'description' => null,
            'type' => $parent->type,
            'link' => 'frontend.page',
            'slug' => Str::slug($name),
            'language' => $parent->language,
            'order_by' => 0,
            'status' => 1,
        ], $overrides));
    }

    private function legacyUpdatePayload(PageMenu $menu, ?int $parentId): array
    {
        return [
            'uuid' => $menu->uuid,
            'language' => ['en' => 'en'],
            'name' => ['en' => $menu->name],
            'description' => ['en' => $menu->description],
            'parent' => ['en' => $parentId],
            'type' => ['en' => $menu->type],
            'link' => ['en' => $menu->link],
            'slug' => ['en' => $menu->slug],
            'icon' => ['en' => null],
            'banner_id' => ['en' => null],
            'order_by' => ['en' => $menu->order_by],
        ];
    }

    private function legacyCreatePayload(string $name, ?int $parentId): array
    {
        return [
            'language' => ['en' => 'en'],
            'name' => ['en' => $name],
            'description' => ['en' => ''],
            'parent' => ['en' => $parentId],
            'type' => ['en' => 'main'],
            'link' => ['en' => null],
            'slug' => ['en' => null],
            'icon' => ['en' => null],
            'banner_id' => ['en' => null],
            'order_by' => ['en' => 0],
        ];
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
