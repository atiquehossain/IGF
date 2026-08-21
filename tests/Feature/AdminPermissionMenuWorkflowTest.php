<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPermissionMenuWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_view_only_permission_staff_do_not_receive_mutation_controls(): void
    {
        $menuView = AuthMenu::query()->where('link', 'menu.index')->firstOrFail();
        $actionView = MenuAction::query()->where('link', 'menu.action.index')->firstOrFail();
        $role = $this->role('Permission viewer', [$menuView->id], [$actionView->id]);
        $admin = $this->admin($role, 'permission-viewer');

        $menuPage = $this->actingAs($admin, 'admin')->get(route('menu.index'))->assertOk();
        $menuPage->assertSee('Read-only access.')
            ->assertDontSee('<form action="' . route('menu.store') . '" method="post"', false)
            ->assertDontSee('class="edit btn', false)
            ->assertDontSee('class="btn btn-warning btn-sm1 status', false)
            ->assertDontSee('class="btn btn-danger btn-sm1 trash', false);

        $actionPage = $this->get(route('menu.action.index', $menuView->id))->assertOk();
        $actionPage->assertSee('Read-only access.')
            ->assertDontSee('<form action="' . route('menu.action.store') . '" method="post"', false)
            ->assertDontSee('class="edit btn', false)
            ->assertDontSee('class="btn btn-warning btn-sm1 status', false)
            ->assertDontSee('class="btn btn-danger btn-sm1 trash', false);
    }

    public function test_inactive_parent_menu_disables_its_child_capabilities(): void
    {
        $menu = AuthMenu::query()->where('link', 'menu.index')->firstOrFail();
        $action = MenuAction::query()->where('link', 'menu.edit')->firstOrFail();
        $role = $this->role('Menu editor', [$menu->id], [$action->id]);
        $admin = $this->admin($role, 'menu-editor');
        $permissions = app(Permission::class);

        $this->assertTrue($permissions->allows($admin, 'menu.edit'));
        $menu->update(['status' => 0]);
        $this->assertFalse($permissions->allows($admin, 'menu.edit'));

        $this->actingAs($admin, 'admin')->get(route('menu.edit', $menu->id))->assertForbidden();
    }

    public function test_dependency_safe_deletion_and_legacy_redirects_never_return_blank_pages(): void
    {
        $owner = $this->owner('permission-owner');
        $parent = AuthMenu::query()->where('link', 'menu.index')->firstOrFail();
        $action = MenuAction::query()->where('auth_menu_id', $parent->id)->firstOrFail();

        $this->actingAs($owner, 'admin')
            ->deleteJson(route('menu.destroy', $parent->id))
            ->assertStatus(409);
        $this->assertDatabaseHas('auth_menus', ['id' => $parent->id]);
        $this->assertDatabaseHas('menu_actions', ['id' => $action->id]);

        $this->get(route('menu.action.create'))->assertRedirect(route('menu.index'));
        $this->get(route('menu.action.show', $action->id))
            ->assertRedirect(route('menu.action.index', $parent->id));
        $this->get(route('menu.action.index'))->assertRedirect(route('menu.index'));
    }

    public function test_permission_definition_changes_are_validated_and_audited(): void
    {
        $owner = $this->owner('permission-auditor');
        $this->actingAs($owner, 'admin')->post(route('menu.store'), [
            'name' => 'Audited capability area',
            'link' => 'admin.language',
            'icon' => 'fa-shield',
            'order_by' => 500,
        ])->assertRedirect();

        $menu = AuthMenu::query()->where('link', 'admin.language')->firstOrFail();
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'permission_menu.created',
            'target_id' => (string) $menu->id,
        ]);

        $this->post(route('menu.action.store'), [
            'auth_menu_id' => $menu->id,
            'type' => '8',
            'name' => 'View audited capability',
            'link' => 'admin.password',
            'order_by' => 1,
        ])->assertRedirect();

        $action = MenuAction::query()->where('link', 'admin.password')->firstOrFail();
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'permission_action.created',
            'target_id' => (string) $action->id,
        ]);

        $this->post(route('menu.action.store'), [
            'auth_menu_id' => 999999,
            'type' => '999',
            'name' => 'Forged action',
            'link' => 'bad link with spaces',
        ])->assertSessionHasErrors(['auth_menu_id', 'type', 'link']);
        $this->assertDatabaseMissing('menu_actions', ['name' => 'Forged action']);

        $this->post(route('menu.action.store'), [
            'auth_menu_id' => $menu->id,
            'type' => '8',
            'name' => 'Nonexistent route action',
            'link' => 'admin.route.that.does.not.exist',
        ])->assertSessionHasErrors(['link']);
    }

    public function test_non_owner_cannot_mutate_permission_schema_even_when_legacy_capabilities_are_assigned(): void
    {
        $menu = AuthMenu::query()->where('link', 'menu.index')->firstOrFail();
        $mutationActions = MenuAction::query()->whereIn('link', [
            'menu.create',
            'menu.edit',
            'menu.status',
            'menu.destroy',
            'menu.action.index',
            'menu.action.create',
            'menu.action.edit',
            'menu.action.status',
            'menu.action.destroy',
        ])->get();
        $role = $this->role('Delegated permission editor', [$menu->id], $mutationActions->pluck('id')->all());
        $admin = $this->admin($role, 'delegated-permission-editor');
        $disposableMenu = AuthMenu::query()->create([
            'name' => 'Disposable permission menu',
            'link' => 'disposable.permission.index',
            'status' => 1,
        ]);
        $disposableAction = MenuAction::query()->create([
            'auth_menu_id' => $disposableMenu->id,
            'name' => 'Disposable permission action',
            'link' => 'disposable.permission.edit',
            'type' => 2,
            'status' => 1,
        ]);

        $this->actingAs($admin, 'admin');
        $this->get(route('menu.index'))->assertOk()
            ->assertSee('Permission definitions can only be changed by a deployment owner.')
            ->assertDontSee('<form action="' . route('menu.store') . '" method="post"', false);
        $this->get(route('menu.action.index', $menu->id))->assertOk()
            ->assertSee('Permission definitions can only be changed by a deployment owner.')
            ->assertDontSee('<form action="' . route('menu.action.store') . '" method="post"', false);

        $this->post(route('menu.store'), [
            'name' => 'Forged permission menu',
            'link' => 'forged.permission.index',
        ])->assertForbidden();
        $this->put(route('menu.update'), [
            'id' => $disposableMenu->id,
            'name' => 'Relabelled permission menu',
            'link' => 'admin.create.staged',
        ])->assertForbidden();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('menu.status', $disposableMenu->id))
            ->assertForbidden();
        $this->deleteJson(route('menu.destroy', $disposableMenu->id))->assertForbidden();

        $this->post(route('menu.action.store'), [
            'auth_menu_id' => $menu->id,
            'type' => 2,
            'name' => 'Forged permission action',
            'link' => 'forged.permission.edit',
        ])->assertForbidden();
        $this->put(route('menu.action.update'), [
            'id' => $disposableAction->id,
            'auth_menu_id' => $disposableMenu->id,
            'type' => 2,
            'name' => 'Relabelled permission action',
            'link' => 'role.permission.edit.staged',
        ])->assertForbidden();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('menu.action.status', $disposableAction->id))
            ->assertForbidden();
        $this->deleteJson(route('menu.action.destroy', $disposableAction->id))->assertForbidden();

        $this->assertDatabaseMissing('auth_menus', ['link' => 'forged.permission.index']);
        $this->assertDatabaseHas('auth_menus', [
            'id' => $disposableMenu->id,
            'name' => 'Disposable permission menu',
            'link' => 'disposable.permission.index',
            'status' => 1,
        ]);
        $this->assertDatabaseMissing('menu_actions', ['link' => 'forged.permission.edit']);
        $this->assertDatabaseHas('menu_actions', [
            'id' => $disposableAction->id,
            'name' => 'Disposable permission action',
            'link' => 'disposable.permission.edit',
            'status' => 1,
        ]);
    }

    private function owner(string $username): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $role->forceFill(['status' => 1, 'security_rank' => 0])->save();

        return $this->admin($role, $username);
    }

    /** @param array<int, int|string> $menus
     *  @param array<int, int|string> $actions
     */
    private function role(string $name, array $menus, array $actions): Role
    {
        return Role::query()->create([
            'name' => $name,
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => implode(',', $menus),
            'actionPermission' => implode(',', $actions),
            'serial' => '[]',
            'status' => 1,
        ]);
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
        ]);
    }
}
