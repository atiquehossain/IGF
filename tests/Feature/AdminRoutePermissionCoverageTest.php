<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Support\AdminPermissionRegistry;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRoutePermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_named_permission_route_resolves_to_an_active_registered_capability(): void
    {
        $missingNames = [];
        $missingMappings = [];
        $missingRegistryRows = [];

        foreach (Route::getRoutes() as $route) {
            if (!$this->usesPermissionMiddleware($route->gatherMiddleware())) {
                continue;
            }

            $name = $route->getName();
            $description = implode('|', $route->methods()) . ' ' . $route->uri();
            if (!$name) {
                // The third-party file manager ships one inert demo route without
                // a name. Unknown/unnamed routes remain fail-closed in middleware.
                if (!str_contains($route->getActionName(), 'LaravelFilemanager\\Controllers\\DemoController')) {
                    $missingNames[] = $description . ' -> ' . $route->getActionName();
                }
                continue;
            }

            if (AdminPermissionRegistry::isEssentialRoute($name)) {
                continue;
            }

            $capabilities = AdminPermissionRegistry::capabilitiesForRoute($name);
            if ($capabilities === []) {
                $missingMappings[] = $description . ' [' . $name . ']';
                continue;
            }

            foreach ($capabilities as $capability) {
                $isActive = AuthMenu::where('link', $capability)->where('status', 1)->exists()
                    || MenuAction::where('link', $capability)->where('status', 1)->exists();
                if (!$isActive) {
                    $missingRegistryRows[] = $description . ' [' . $name . '] -> ' . $capability;
                }
            }
        }

        $this->assertSame([], $missingNames, "Unnamed permission-protected routes:\n" . implode("\n", $missingNames));
        $this->assertSame([], $missingMappings, "Routes without a capability mapping:\n" . implode("\n", $missingMappings));
        $this->assertSame([], $missingRegistryRows, "Routes mapped to inactive or missing capabilities:\n" . implode("\n", $missingRegistryRows));
    }

    public function test_registry_owner_can_authorize_every_named_permission_route(): void
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $admin = Admin::create([
            'name' => 'Route Coverage',
            'username' => 'route-coverage',
            'email' => 'route-coverage@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        $permission = app(Permission::class);
        $denied = [];

        foreach (Route::getRoutes() as $route) {
            if (!$this->usesPermissionMiddleware($route->gatherMiddleware()) || !$route->getName()) {
                continue;
            }
            if (!$permission->allows($admin, $route->getName())) {
                $denied[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' [' . $route->getName() . ']';
            }
        }

        $this->assertSame([], $denied, "A role containing every active capability was denied:\n" . implode("\n", $denied));
    }

    public function test_crud_routes_follow_the_shared_capability_vocabulary(): void
    {
        $expected = [
            'album.store' => ['album.create'],
            'album.update' => ['album.edit'],
            'album.show' => ['album.index'],
            'volunteer.export.excel' => ['volunteer.export'],
            'subscriber-excel-download.index' => ['subscriber.export'],
            'album.status' => ['album.status'],
            'album.destroy' => ['album.destroy'],
            'page.builder.block.store' => ['page.builder.create'],
            'page.builder.block.update' => ['page.builder.edit'],
            'page.builder.block.destroy' => ['page.builder.destroy'],
            'seo.redirects.store' => ['seo.redirects.create'],
            'seo.redirects.destroy' => ['seo.redirects.destroy'],
            'sponsorships.workflow' => ['sponsorships.edit'],
            'volunteer.workflow' => ['volunteer.edit'],
            'contact-message.workflow' => ['contact-message.edit'],
        ];

        foreach ($expected as $route => $capabilities) {
            $this->assertSame($capabilities, AdminPermissionRegistry::capabilitiesForRoute($route), $route);
        }

        $this->assertSame([], AdminPermissionRegistry::capabilitiesForRoute('admin.unregistered-operation'));
    }

    public function test_registry_sync_is_idempotent_and_owner_roles_receive_new_capabilities(): void
    {
        $before = [AuthMenu::count(), MenuAction::count()];
        $this->seed(AdminPermissionRegistrySeeder::class);
        $this->seed(AdminPermissionRegistrySeeder::class);

        $this->assertSame($before, [AuthMenu::count(), MenuAction::count()]);
        $owner = Role::where('is_owner', true)->firstOrFail();
        $menuIds = $this->ids($owner->permission);
        $actionIds = $this->ids($owner->actionPermission);

        foreach (AdminPermissionRegistry::menus() as $link => $definition) {
            $this->assertContains((string) AuthMenu::where('link', $link)->value('id'), $menuIds, $link);
        }
        foreach (AdminPermissionRegistry::actions() as $link => $definition) {
            $this->assertContains((string) MenuAction::where('link', $link)->value('id'), $actionIds, $link);
        }
    }

    public function test_registry_sync_preserves_owner_managed_definition_state_and_revoked_grants(): void
    {
        $menu = AuthMenu::where('link', 'page.index')->firstOrFail();
        $action = MenuAction::where('link', 'page.edit')->firstOrFail();
        $source = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $revoked = MenuAction::where('link', 'seo.metadata.view')->firstOrFail();
        $role = Role::create([
            'name' => 'Deliberately restricted editor',
            'permission' => '',
            'actionPermission' => (string) $source->id,
            'serial' => '[]',
            'status' => 1,
        ]);
        $nameCollisionRole = Role::create([
            'name' => 'Super Admin',
            'security_rank' => 500,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        $menu->forceFill([
            'name' => 'Owner label for pages',
            'parent_id' => null,
            'order_by' => 987,
            'status' => 0,
        ])->save();
        $action->forceFill([
            'name' => 'Owner label for page editing',
            'auth_menu_id' => AuthMenu::where('link', 'role.index')->value('id'),
            'type' => 8,
            'order_by' => 654,
            'status' => 0,
        ])->save();

        $this->seed(AdminPermissionRegistrySeeder::class);

        $this->assertDatabaseHas('auth_menus', [
            'id' => $menu->id,
            'name' => 'Owner label for pages',
            'parent_id' => null,
            'order_by' => 987,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('menu_actions', [
            'id' => $action->id,
            'name' => 'Owner label for page editing',
            'auth_menu_id' => AuthMenu::where('link', 'role.index')->value('id'),
            'type' => 8,
            'order_by' => 654,
            'status' => 0,
        ]);
        $this->assertNotContains((string) $revoked->id, $this->ids($role->fresh()->actionPermission));
        $this->assertSame('', (string) $nameCollisionRole->fresh()->permission);
        $this->assertSame('', (string) $nameCollisionRole->fresh()->actionPermission);
    }

    public function test_legacy_broad_permissions_are_backfilled_to_the_new_dedicated_capabilities(): void
    {
        $introducedMenus = AuthMenu::whereIn('link', [
            'site.settings.index',
            'translations.index',
            'annual.report.index',
            'media.index',
            'testimonial.index',
        ])->pluck('id');
        MenuAction::whereIn('auth_menu_id', $introducedMenus)->delete();
        MenuAction::where('link', 'page.builder.destroy')->delete();
        AuthMenu::whereIn('id', $introducedMenus)->delete();
        $pageEdit = MenuAction::where('link', 'page.edit')->firstOrFail();
        $galleryMenu = AuthMenu::where('link', 'gallery.index')->firstOrFail();
        $galleryActions = MenuAction::whereIn('link', ['gallery.create', 'gallery.edit', 'gallery.destroy'])->get();
        $role = Role::create([
            'name' => 'Legacy website editor',
            'permission' => (string) $galleryMenu->id,
            'actionPermission' => $galleryActions->pluck('id')->push($pageEdit->id)->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'Legacy Editor',
            'username' => 'legacy-editor',
            'email' => 'legacy-editor@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);

        $this->seed(AdminPermissionRegistrySeeder::class);
        $permission = app(Permission::class);

        $this->assertTrue($permission->allows($admin, 'site.settings.update'));
        $this->assertTrue($permission->allows($admin, 'translations.update'));
        $this->assertTrue($permission->allows($admin, 'annual.report.store'));
        $this->assertTrue($permission->allows($admin, 'testimonial.destroy'));
        $this->assertTrue($permission->allows($admin, 'page.builder.block.destroy'));
        $this->assertTrue($permission->allows($admin, 'media.index'));
        $this->assertTrue($permission->allows($admin, 'media.store'));
        $this->assertTrue($permission->allows($admin, 'media.update'));
        $this->assertTrue($permission->allows($admin, 'media.destroy'));
    }

    /** @param list<string> $middleware */
    private function usesPermissionMiddleware(array $middleware): bool
    {
        return collect($middleware)
            ->map(fn ($name) => strtok((string) $name, ':'))
            ->contains(fn (string $name): bool => $name === 'permission' || $name === Permission::class);
    }

    /** @return list<string> */
    private function ids(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
