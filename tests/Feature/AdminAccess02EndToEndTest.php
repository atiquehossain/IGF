<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccess02EndToEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ACCESS-02: an owner can create isolated and combined operational staff,
     * and disabling any account invalidates the session it already held.
     */
    public function test_access_02_owner_creates_scopes_and_immediately_revokes_hr_workshop_and_combined_staff(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ownerRole = Role::query()->where('is_owner', true)->firstOrFail();
        $owner = $this->admin($ownerRole, 'access-02-owner', false);

        $hrCapabilities = [
            'recruitment.jobs.index',
            'recruitment.applications.index',
            'recruitment.jobs.create',
            'recruitment.jobs.edit',
            'recruitment.jobs.status',
            'recruitment.jobs.destroy',
            'recruitment.jobs.templates.manage',
            'recruitment.applications.edit',
            'recruitment.applications.status',
            'recruitment.applications.export',
            'recruitment.applications.download',
            'recruitment.applications.import',
        ];
        $workshopCapabilities = [
            'workshops.index',
            'workshop.registrations.index',
            'workshops.create',
            'workshops.edit',
            'workshops.status',
            'workshops.destroy',
            'workshops.templates.manage',
            'workshop.registrations.edit',
            'workshop.registrations.status',
            'workshop.registrations.export',
            'workshop.registrations.download',
            'workshop.registrations.import',
        ];

        $hrRole = $this->createOperationalRole($owner, 'ACCESS-02 HR only', $hrCapabilities, 200);
        $workshopRole = $this->createOperationalRole($owner, 'ACCESS-02 Workshop only', $workshopCapabilities, 210);
        $combinedRole = $this->createOperationalRole(
            $owner,
            'ACCESS-02 HR and Workshop',
            array_values(array_unique(array_merge($hrCapabilities, $workshopCapabilities))),
            220,
        );

        [$hr, $hrTemporary] = $this->createAndActivateStaff($owner, $hrRole, 'access-02-hr');
        [$workshop, $workshopTemporary] = $this->createAndActivateStaff($owner, $workshopRole, 'access-02-workshop');
        [$combined, $combinedTemporary] = $this->createAndActivateStaff($owner, $combinedRole, 'access-02-combined');

        $this->assertRoleIsolationAfterRealLogin(
            $hr,
            $hrTemporary,
            'Replacement-HR-Password!23',
            allowed: ['recruitment.jobs.index', 'recruitment.applications.index'],
            forbidden: ['workshops.index', 'workshop.registrations.index'],
        );
        $this->assertRoleIsolationAfterRealLogin(
            $workshop,
            $workshopTemporary,
            'Replacement-Workshop-Password!23',
            allowed: ['workshops.index', 'workshop.registrations.index'],
            forbidden: ['recruitment.jobs.index', 'recruitment.applications.index'],
        );
        $this->assertRoleIsolationAfterRealLogin(
            $combined,
            $combinedTemporary,
            'Replacement-Combined-Password!23',
            allowed: [
                'recruitment.jobs.index',
                'recruitment.applications.index',
                'workshops.index',
                'workshop.registrations.index',
            ],
            forbidden: [],
        );

        foreach ([$hr, $workshop, $combined] as $staff) {
            $staff->refresh();
            $oldAuthVersion = (int) $staff->auth_version;
            $this->assertTrue((bool) $staff->status);
            $this->assertSame(1, $oldAuthVersion, 'The real forced-password flow must version the active staff session.');

            $this->actingAs($owner, 'admin')
                ->putJson(route('admin.status', $staff->id), ['id' => $staff->id])
                ->assertOk()
                ->assertJson(['status' => false]);

            $disabled = $staff->fresh();
            $this->assertFalse((bool) $disabled->status);
            $this->assertSame($oldAuthVersion + 1, (int) $disabled->auth_version);

            // Model the cookie/session that was established before the owner
            // disabled this account. The next request must revoke it at once.
            $this->actingAs($disabled, 'admin')
                ->withSession([Admin::SESSION_AUTH_VERSION => $oldAuthVersion])
                ->get(route('dashboard.index'))
                ->assertRedirect(route('admin.login'));
            $this->assertGuest('admin');

            $this->assertDatabaseHas('admin_audit_events', [
                'action' => 'admin.status_changed',
                'actor_admin_id' => $owner->id,
                'target_id' => (string) $staff->id,
            ]);
        }

        $this->assertSame(3, AdminAuditEvent::query()->where('action', 'role.created')->count());
        $this->assertSame(3, AdminAuditEvent::query()->where('action', 'role.permissions_changed')->count());
        $this->assertSame(3, AdminAuditEvent::query()->where('action', 'admin.created')->count());
        $disabledEvents = AdminAuditEvent::query()->where('action', 'admin.status_changed')->get()
            ->filter(fn (AdminAuditEvent $event): bool => data_get($event->changes, 'status.after') === false);
        $this->assertCount(3, $disabledEvents);
    }

    /** @param list<string> $capabilities */
    private function createOperationalRole(
        Admin $owner,
        string $name,
        array $capabilities,
        int $rank,
    ): Role {
        $this->actingAs($owner, 'admin')->from(route('role.index'))->post(route('role.store'), [
            'name' => $name,
            'security_rank' => $rank,
            'order_by' => $rank,
        ])->assertRedirect(route('role.index'));

        $role = Role::query()->where('name', $name)->firstOrFail();
        $this->assertFalse((bool) $role->status);
        $menus = AuthMenu::query()->whereIn('link', $capabilities)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $actions = MenuAction::query()->whereIn('link', $capabilities)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertNotEmpty($menus);
        $this->assertNotEmpty($actions);

        $this->actingAs($owner, 'admin')->from(route('role.permission', $role->id))
            ->post(route('role.permission.store'), [
                'id' => $role->id,
                'permission' => $menus,
                'actionPermission' => $actions,
            ])->assertRedirect(route('role.permission', $role->id));

        $this->actingAs($owner, 'admin')->putJson(route('role.status', $role->id))
            ->assertOk()
            ->assertJson(['status' => true]);

        $role->refresh();
        $this->assertTrue((bool) $role->status);
        $this->assertEqualsCanonicalizing($menus, $this->permissionIds($role->permission));
        $this->assertEqualsCanonicalizing($actions, $this->permissionIds($role->actionPermission));

        return $role;
    }

    /** @return array{Admin, string} */
    private function createAndActivateStaff(Admin $owner, Role $role, string $username): array
    {
        $response = $this->actingAs($owner, 'admin')->from(route('admin.index'))->post(route('admin.store'), [
            'role' => $role->id,
            'name' => str_replace('-', ' ', ucfirst($username)),
            'username' => $username,
            'email' => $username . '@example.test',
        ])->assertRedirect(route('admin.index'))->assertSessionHas('temporary_password');

        $temporaryPassword = (string) $response->getSession()->get('temporary_password');
        $staff = Admin::query()->where('username', $username)->firstOrFail();
        $this->assertFalse((bool) $staff->status);
        $this->assertTrue($staff->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $staff->password));

        $this->actingAs($owner, 'admin')->putJson(route('admin.status', $staff->id), ['id' => $staff->id])
            ->assertOk()
            ->assertJson(['status' => true]);
        $this->assertTrue((bool) $staff->fresh()->status);

        return [$staff->fresh(), $temporaryPassword];
    }

    /** @param list<string> $allowed
     *  @param list<string> $forbidden
     */
    private function assertRoleIsolationAfterRealLogin(
        Admin $staff,
        string $temporaryPassword,
        string $replacementPassword,
        array $allowed,
        array $forbidden,
    ): void {
        Auth::guard('admin')->logout();
        $this->post(route('admin.login'), [
            'username' => $staff->username,
            'password' => $temporaryPassword,
        ])->assertRedirect(route('admin.password'))
            ->assertSessionHas(Admin::SESSION_AUTH_VERSION, 0);

        $this->put(route('admin.password.update'), [
            'password' => $replacementPassword,
            'password_confirmation' => $replacementPassword,
        ])->assertRedirect(route('dashboard.index'))
            ->assertSessionHas(Admin::SESSION_AUTH_VERSION, 1);

        $staff->refresh();
        $this->assertFalse($staff->must_change_password);
        $this->assertTrue(Hash::check($replacementPassword, $staff->password));
        // Feature tests reuse one application container; a real next request
        // reloads the authenticated administrator from the session provider.
        Auth::forgetGuards();
        foreach ($allowed as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
        foreach ($forbidden as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    /** @return list<int> */
    private function permissionIds(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function admin(Role $role, string $username, bool $mustChangePassword): Admin
    {
        return Admin::query()->create([
            'name' => str_replace('-', ' ', ucfirst($username)),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Owner-Password!23'),
            'must_change_password' => $mustChangePassword,
            'password_changed_at' => now(),
        ]);
    }
}
