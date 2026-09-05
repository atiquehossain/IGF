<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PrivateListingSearchController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Support\AdminPermissionRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class RecruitmentWorkshopPermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registry_uses_stable_new_ids_and_maps_the_planned_route_vocabulary(): void
    {
        $menus = AdminPermissionRegistry::menus();
        foreach ([
            'recruitment.jobs.index' => 67,
            'recruitment.applications.index' => 68,
            'workshops.index' => 69,
            'workshop.registrations.index' => 70,
        ] as $capability => $id) {
            $this->assertSame($id, $menus[$capability]['id'], $capability);
        }

        $actions = AdminPermissionRegistry::actions();
        foreach ([
            'recruitment.jobs.create' => 236,
            'recruitment.jobs.edit' => 237,
            'recruitment.jobs.status' => 238,
            'recruitment.jobs.destroy' => 239,
            'recruitment.applications.create' => 240,
            'recruitment.applications.edit' => 241,
            'recruitment.applications.status' => 242,
            'recruitment.applications.destroy' => 243,
            'workshops.create' => 244,
            'workshops.edit' => 245,
            'workshops.status' => 246,
            'workshops.destroy' => 247,
            'workshop.registrations.create' => 248,
            'workshop.registrations.edit' => 249,
            'workshop.registrations.status' => 250,
            'workshop.registrations.destroy' => 251,
            'recruitment.applications.export' => 252,
            'workshop.registrations.export' => 253,
            'recruitment.applications.download' => 254,
            'workshop.registrations.download' => 255,
            'recruitment.applications.import' => 256,
            'workshop.registrations.import' => 257,
            'recruitment.jobs.templates.manage' => 258,
            'workshops.templates.manage' => 259,
            'recruitment.applications.anonymize' => 260,
            'workshop.registrations.anonymize' => 261,
        ] as $capability => $id) {
            $this->assertSame($id, $actions[$capability]['id'], $capability);
        }

        foreach ([
            'recruitment.jobs.store' => ['recruitment.jobs.create'],
            'recruitment.jobs.update' => ['recruitment.jobs.edit'],
            'recruitment.jobs.status' => ['recruitment.jobs.status'],
            'recruitment.applications.search' => ['recruitment.applications.index'],
            'recruitment.applications.search.clear' => ['recruitment.applications.index'],
            'recruitment.applications.bulk' => ['recruitment.applications.edit'],
            'recruitment.applications.workflow' => ['recruitment.applications.edit'],
            'recruitment.applications.assign' => ['recruitment.applications.edit'],
            'recruitment.applications.score' => ['recruitment.applications.edit'],
            'recruitment.applications.notes.store' => ['recruitment.applications.edit'],
            'recruitment.applications.delete' => ['recruitment.applications.destroy'],
            'recruitment.applications.download' => ['recruitment.applications.download'],
            'recruitment.applications.anonymize' => ['recruitment.applications.anonymize'],
            'recruitment.imports.preview' => ['recruitment.applications.import'],
            'recruitment.forms.index' => ['recruitment.jobs.index'],
            'recruitment.forms.trash' => ['recruitment.jobs.index'],
            'recruitment.forms.create' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.edit' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.metadata' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.publish' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.duplicate' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.destroy' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.restore' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.force-destroy' => ['recruitment.jobs.templates.manage'],
            'recruitment.forms.preview' => ['recruitment.jobs.index'],
            'workshops.store' => ['workshops.create'],
            'workshops.update' => ['workshops.edit'],
            'workshops.status' => ['workshops.status'],
            'workshop.registrations.search' => ['workshop.registrations.index'],
            'workshop.registrations.search.clear' => ['workshop.registrations.index'],
            'workshop.registrations.bulk' => ['workshop.registrations.edit'],
            'workshop.registrations.workflow' => ['workshop.registrations.edit'],
            'workshop.registrations.assign' => ['workshop.registrations.edit'],
            'workshop.registrations.score' => ['workshop.registrations.edit'],
            'workshop.registrations.notes.store' => ['workshop.registrations.edit'],
            'workshop.registrations.delete' => ['workshop.registrations.destroy'],
            'workshop.registrations.download' => ['workshop.registrations.download'],
            'workshop.registrations.anonymize' => ['workshop.registrations.anonymize'],
            'workshop.imports.confirm' => ['workshop.registrations.import'],
            'workshop.forms.index' => ['workshops.index'],
            'workshop.forms.trash' => ['workshops.index'],
            'workshop.forms.create' => ['workshops.templates.manage'],
            'workshop.forms.edit' => ['workshops.templates.manage'],
            'workshop.forms.metadata' => ['workshops.templates.manage'],
            'workshop.forms.publish' => ['workshops.templates.manage'],
            'workshop.forms.duplicate' => ['workshops.templates.manage'],
            'workshop.forms.destroy' => ['workshops.templates.manage'],
            'workshop.forms.restore' => ['workshops.templates.manage'],
            'workshop.forms.force-destroy' => ['workshops.templates.manage'],
            'workshop.forms.preview' => ['workshops.index'],
        ] as $route => $capabilities) {
            $this->assertSame($capabilities, AdminPermissionRegistry::capabilitiesForRoute($route), $route);
        }

        foreach ([
            'recruitment.applications.destroy',
            'recruitment.applications.anonymize',
            'workshop.registrations.destroy',
            'workshop.registrations.anonymize',
        ] as $capability) {
            $this->assertTrue(AdminPermissionRegistry::isOwnerOnlyCapability($capability), $capability);
        }
        $this->assertFalse(AdminPermissionRegistry::isOwnerOnlyCapability('recruitment.jobs.destroy'));
        $this->assertFalse(AdminPermissionRegistry::isOwnerOnlyCapability('workshops.destroy'));
    }

    public function test_recruitment_and_workshop_roles_are_isolated_and_owner_actions_fail_closed(): void
    {
        $recruitmentRole = $this->role(
            'Recruitment Manager',
            ['recruitment.jobs.index', 'recruitment.applications.index'],
            [
                'recruitment.jobs.create',
                'recruitment.jobs.edit',
                'recruitment.applications.edit',
                'recruitment.applications.export',
                'recruitment.applications.download',
                'recruitment.applications.import',
                'recruitment.jobs.templates.manage',
                // Deliberately inject owner-only IDs to prove middleware does
                // not trust a stale or manually altered role CSV.
                'recruitment.applications.destroy',
                'recruitment.applications.anonymize',
            ]
        );
        $workshopRole = $this->role(
            'Workshop Manager',
            ['workshops.index', 'workshop.registrations.index'],
            [
                'workshops.create',
                'workshops.edit',
                'workshop.registrations.edit',
                'workshop.registrations.export',
                'workshop.registrations.download',
                'workshop.registrations.import',
                'workshops.templates.manage',
            ]
        );
        $recruiter = $this->admin($recruitmentRole, 'permission-recruiter');
        $workshopManager = $this->admin($workshopRole, 'permission-workshop-manager');
        $permission = app(Permission::class);

        foreach ([
            'recruitment.jobs.store',
            'recruitment.applications.index',
            'recruitment.applications.workflow',
            'recruitment.applications.export',
            'recruitment.applications.download',
            'recruitment.imports.preview',
            'recruitment.forms.duplicate',
        ] as $route) {
            $this->assertTrue($permission->allows($recruiter, $route), $route);
            $this->assertFalse($permission->allows($workshopManager, $route), $route);
        }

        foreach ([
            'workshops.store',
            'workshop.registrations.index',
            'workshop.registrations.workflow',
            'workshop.registrations.export',
            'workshop.registrations.download',
            'workshop.imports.preview',
            'workshop.forms.duplicate',
        ] as $route) {
            $this->assertTrue($permission->allows($workshopManager, $route), $route);
            $this->assertFalse($permission->allows($recruiter, $route), $route);
        }

        $this->assertFalse($permission->allows($recruiter, 'recruitment.applications.delete'));
        $this->assertFalse($permission->allows($recruiter, 'recruitment.applications.anonymize'));

        $ownerRole = Role::query()->where('is_owner', true)->firstOrFail();
        $owner = $this->admin($ownerRole, 'permission-owner');
        foreach ([
            'recruitment.applications.delete',
            'recruitment.applications.anonymize',
            'workshop.registrations.delete',
            'workshop.registrations.anonymize',
        ] as $route) {
            $this->assertTrue($permission->allows($owner, $route), $route);
        }
    }

    public function test_owner_only_application_actions_are_filtered_from_delegated_role_editor(): void
    {
        $controller = app(RoleController::class);
        $isRoutableAction = new ReflectionMethod($controller, 'isRoutableAction');
        $isRoutableAction->setAccessible(true);

        foreach ([
            'recruitment.applications.edit',
            'workshop.registrations.edit',
        ] as $capability) {
            $this->assertTrue($isRoutableAction->invoke(
                $controller,
                MenuAction::query()->where('link', $capability)->firstOrFail()
            ), $capability);
        }

        $routableActionIds = new ReflectionMethod($controller, 'routableActionIds');
        $routableActionIds->setAccessible(true);
        $menuIds = AuthMenu::query()
            ->whereIn('link', ['recruitment.applications.index', 'workshop.registrations.index'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $delegableIds = $routableActionIds->invoke($controller, $menuIds);

        $this->assertContains(
            (int) MenuAction::query()->where('link', 'recruitment.applications.edit')->value('id'),
            $delegableIds->all()
        );
        foreach ([
            'recruitment.applications.destroy',
            'recruitment.applications.anonymize',
            'workshop.registrations.destroy',
            'workshop.registrations.anonymize',
        ] as $capability) {
            $this->assertNotContains(
                (int) MenuAction::query()->where('link', $capability)->value('id'),
                $delegableIds->all(),
                $capability
            );
        }

        foreach ([
            'recruitment.applications.destroy',
            'recruitment.applications.anonymize',
            'workshop.registrations.destroy',
            'workshop.registrations.anonymize',
        ] as $capability) {
            $this->assertFalse($isRoutableAction->invoke(
                $controller,
                MenuAction::query()->where('link', $capability)->firstOrFail()
            ), $capability);
        }
    }

    public function test_private_search_registry_contains_isolated_application_scopes(): void
    {
        $targets = (new ReflectionClass(PrivateListingSearchController::class))
            ->getReflectionConstant('TARGETS')
            ?->getValue();

        $this->assertIsArray($targets);
        $this->assertSame('recruitment.applications.index', $targets['recruitment-applications']);
        $this->assertSame('workshop.registrations.index', $targets['workshop-registrations']);
    }

    /** @param list<string> $menus
     *  @param list<string> $actions
     */
    private function role(string $name, array $menus, array $actions): Role
    {
        return Role::query()->create([
            'name' => $name,
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => AuthMenu::query()->whereIn('link', $menus)->pluck('id')->implode(','),
            'actionPermission' => MenuAction::query()->whereIn('link', $actions)->pluck('id')->implode(','),
            'serial' => '[]',
            'order_by' => 200,
            'status' => 1,
        ]);
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => str($username)->headline()->toString(),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

}
