<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Testimonial;
use App\Http\Middleware\Permission;
use App\Services\SeoMetadataService;
use App\Services\SeoRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unregistered_admin_capability_fails_closed(): void
    {
        [$admin] = $this->makeAdminRole();

        $this->asAdmin($admin)
            ->get('/admin/seo')
            ->assertForbidden();
        $this->get(route('seo.redirects.index'))->assertForbidden();
    }

    public function test_restricted_role_cannot_access_mapped_capability_without_action_permission(): void
    {
        [$admin] = $this->makeAdminRole();
        $menu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Edit pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);

        $this->asAdmin($admin)
            ->get('/admin/seo')
            ->assertForbidden();
    }

    public function test_page_editor_permission_does_not_authorize_seo_pack(): void
    {
        [$admin, $role] = $this->makeAdminRole();
        $menu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        $action = MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Edit pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);
        $role->update(['actionPermission' => (string) $action->id]);

        $this->asAdmin($admin)
            ->get('/admin/seo')
            ->assertForbidden();
    }

    public function test_dedicated_seo_metadata_permission_authorizes_dashboard_but_not_redirects(): void
    {
        [$admin, $role] = $this->makeAdminRole();
        $metadata = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $role->update(['actionPermission' => (string) $metadata->id]);

        $this->asAdmin($admin)
            ->get(route('seo.redirects.index'))
            ->assertForbidden();
        $this->post(route('seo.redirects.store'), [
            'from_path' => '/legacy',
            'to_url' => '/current',
            'status_code' => 301,
            'is_active' => true,
        ])->assertForbidden();
        $privateRedirectSource = '/redirect-private-to-redirect-managers';
        app(SeoRedirectService::class)->create([
            'from_path' => $privateRedirectSource,
            'to_url' => '/current',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->asAdmin($admin)
            ->get('/admin/seo')
            ->assertOk()
            ->assertDontSee($privateRedirectSource);

        $permission = app(Permission::class);
        $this->assertTrue($permission->allows($admin, 'seo.update'));
        $this->assertFalse($permission->allows($admin, 'seo.redirects.index'));
        $this->assertFalse($permission->allows($admin, 'seo.redirects.store'));
        $this->assertFalse($permission->allows($admin, 'seo.redirects.destroy'));
    }

    public function test_dedicated_redirect_permission_authorizes_redirect_ui_only(): void
    {
        [$admin, $role] = $this->makeAdminRole();
        $redirectCreate = MenuAction::where('link', 'seo.redirects.create')->firstOrFail();
        $redirectDestroy = MenuAction::where('link', 'seo.redirects.destroy')->firstOrFail();
        $role->update(['actionPermission' => (string) $redirectCreate->id]);

        $permission = app(Permission::class);
        $this->assertFalse($permission->allows($admin, 'seo.index'));
        $this->assertTrue($permission->allows($admin, 'seo.redirects.index'));
        $this->assertTrue($permission->allows($admin, 'seo.redirects.store'));
        $this->assertFalse($permission->allows($admin, 'seo.redirects.destroy'));
        $this->assertFalse($permission->allows($admin, 'seo.update'));

        $this->asAdmin($admin)
            ->get(route('seo.redirects.index'))
            ->assertOk()
            ->assertSee('Language scope')
            ->assertSee('All languages (global)');
        $this->post(route('seo.redirects.store'), [
            'from_path' => '/bangla-old-address',
            'to_url' => '/bangla-current-address?lang=bn',
            'status_code' => 301,
            'is_active' => true,
            'locale' => 'bn',
        ])->assertRedirect();
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/bangla-old-address',
            'locale' => 'bn',
        ]);
        $this->from(route('seo.redirects.index'))->post(route('seo.redirects.store'), [
            'from_path' => '/unsupported-language-address',
            'to_url' => '/current',
            'status_code' => 301,
            'is_active' => true,
            'locale' => 'zz',
        ])->assertSessionHasErrors('locale');
        $this->put(route('seo.update'), [])->assertForbidden();

        $privateMetadataTitle = 'Metadata private to search editors';
        app(SeoMetadataService::class)->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => $privateMetadataTitle,
        ]);
        $this->get(route('seo.redirects.index'))
            ->assertOk()
            ->assertDontSee($privateMetadataTitle);

        $this->get('/admin/seo')->assertForbidden();

        $role->update(['actionPermission' => $redirectCreate->id . ',' . $redirectDestroy->id]);
        $this->assertTrue($permission->allows($admin, 'seo.redirects.destroy'));
    }

    public function test_redirect_ui_only_renders_controls_authorized_for_create_and_destroy_roles(): void
    {
        $redirectCreate = MenuAction::where('link', 'seo.redirects.create')->firstOrFail();
        $redirectDestroy = MenuAction::where('link', 'seo.redirects.destroy')->firstOrFail();
        $service = app(SeoRedirectService::class);
        $active = $service->create([
            'from_path' => '/old-active-page',
            'to_url' => '/current-page',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $deleted = $service->create([
            'from_path' => '/old-deleted-page',
            'to_url' => '/replacement-page',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $service->delete($deleted);

        [$createAdmin, $createRole] = $this->makeAdminRole('Redirect creator');
        $createRole->update(['actionPermission' => (string) $redirectCreate->id]);

        $this->asAdmin($createAdmin)
            ->get(route('seo.redirects.index'))
            ->assertOk()
            ->assertSee('Create redirect')
            ->assertSee('data-redirect-edit="' . $active->id . '"', false)
            ->assertSee('Pause')
            ->assertDontSee(route('seo.redirects.destroy', $active), false);
        $this->get(route('seo.redirects.index', ['redirect_trash' => 1]))
            ->assertOk()
            ->assertSee('/old-deleted-page')
            ->assertSee('Restore disabled');

        [$destroyAdmin, $destroyRole] = $this->makeAdminRole('Redirect destroyer');
        $destroyRole->update(['actionPermission' => (string) $redirectDestroy->id]);

        $this->asAdmin($destroyAdmin)
            ->get(route('seo.redirects.index'))
            ->assertOk()
            ->assertSee(route('seo.redirects.destroy', $active), false)
            ->assertSee('Trash')
            ->assertDontSee('Create redirect')
            ->assertDontSee('data-redirect-edit="' . $active->id . '"', false)
            ->assertDontSee('Pause');
        $this->get(route('seo.redirects.index', ['redirect_trash' => 1]))
            ->assertOk()
            ->assertSee('/old-deleted-page')
            ->assertSee('Deleted')
            ->assertDontSee('Restore disabled');
    }

    public function test_page_editor_permission_does_not_authorize_testimonial_manager(): void
    {
        [$admin, $role] = $this->makeAdminRole();
        $menu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        $action = MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Edit pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);
        $role->update(['actionPermission' => (string) $action->id]);

        $this->asAdmin($admin)
            ->get('/admin/testimonial')
            ->assertForbidden();
    }

    public function test_dedicated_testimonial_view_permission_renders_existing_story(): void
    {
        [$admin, $role] = $this->makeAdminRole();
        $menu = AuthMenu::where('link', 'testimonial.index')->firstOrFail();
        $actions = MenuAction::whereIn('link', ['testimonial.status', 'testimonial.destroy'])->get();
        $role->update([
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
        ]);
        $testimonial = Testimonial::create([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Amina Rahman',
            'designation' => 'Program participant',
            'testimonial' => 'A short community story.',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->asAdmin($admin)
            ->get('/admin/testimonial')
            ->assertOk()
            ->assertSee('Amina Rahman')
            ->assertSee(route('testimonial.status', $testimonial->uuid))
            ->assertSee(route('testimonial.destroy', $testimonial->uuid));
    }

    public function test_super_admin_can_open_donations_and_donors_while_restricted_sidebar_hides_them(): void
    {
        $donations = AuthMenu::where('link', 'donations.index')->firstOrFail();
        $donors = AuthMenu::where('link', 'user.index')->firstOrFail();
        $role = Role::create([
            'name' => 'Super Admin',
            'permission' => $donations->id . ',' . $donors->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'QA Owner',
            'username' => 'qa-owner',
            'email' => 'qa-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);

        $this->asAdmin($admin)->get('/admin')
            ->assertOk()
            ->assertSee(route('donations.index'));
        $this->get(route('donations.index'))->assertOk();
        $this->get(route('user.index'))->assertOk();

        [$restricted] = $this->makeAdminRole();
        $this->asAdmin($restricted)->get('/admin')
            ->assertOk()
            ->assertDontSee(route('donations.index'))
            ->assertDontSee(route('user.index'));
    }

    private function makeAdminRole(string $name = 'QA Editor'): array
    {
        $role = Role::create([
            'name' => $name . ' role',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => $name,
            'username' => str($name)->slug()->toString(),
            'email' => str($name)->slug()->append('@example.test')->toString(),
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);

        return [$admin, $role];
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }
}
