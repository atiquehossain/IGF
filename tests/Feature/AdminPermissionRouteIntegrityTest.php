<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionRouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_modules_are_hidden_and_cannot_be_granted_to_a_role(): void
    {
        $ownerRole = Role::query()->where('is_owner', true)->firstOrFail();
        $ownerRole->forceFill(['status' => 1, 'security_rank' => 0])->save();
        $owner = Admin::query()->create([
            'name' => 'Permission owner',
            'username' => 'permission-owner',
            'email' => 'permission-owner@example.test',
            'role' => (string) $ownerRole->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        $target = Role::query()->create([
            'name' => 'Content editor',
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $removedMenu = AuthMenu::query()->create([
            'name' => 'Removed learning module',
            'link' => 'removed-learning-module.index',
            'status' => 1,
        ]);
        $removedAction = MenuAction::query()->create([
            'auth_menu_id' => $removedMenu->id,
            'name' => 'Edit removed module',
            'link' => 'removed-learning-module.edit',
            'status' => 1,
        ]);
        $validMenu = AuthMenu::query()->where('link', 'page.index')->firstOrFail();
        $orphanAction = MenuAction::query()->create([
            'auth_menu_id' => $validMenu->id,
            'name' => 'Manage retired page workflow',
            'link' => 'page.retired-workflow',
            'status' => 1,
        ]);

        $this->actingAs($owner, 'admin')
            ->get(route('role.permission', $target->id))
            ->assertOk()
            ->assertDontSee('Removed learning module')
            ->assertDontSee('Manage retired page workflow');

        $this->from(route('role.permission', $target->id))
            ->post(route('role.permission.store'), [
                'id' => $target->id,
                'permission' => [$removedMenu->id],
                'actionPermission' => [$removedAction->id],
            ])
            ->assertRedirect(route('role.permission', $target->id))
            ->assertSessionHasErrors(['permission.0', 'actionPermission.0']);

        $this->assertSame('', (string) $target->fresh()->permission);
        $this->assertSame('', (string) $target->fresh()->actionPermission);

        $this->from(route('role.permission', $target->id))
            ->post(route('role.permission.store'), [
                'id' => $target->id,
                'permission' => [$validMenu->id],
                'actionPermission' => [$orphanAction->id],
            ])
            ->assertRedirect(route('role.permission', $target->id))
            ->assertSessionDoesntHaveErrors('permission.0')
            ->assertSessionHasErrors('actionPermission.0');

        $this->assertSame('', (string) $target->fresh()->permission);
        $this->assertSame('', (string) $target->fresh()->actionPermission);
    }
}
