<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSessionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_schema_supports_versioned_session_invalidation(): void
    {
        $this->assertTrue(Schema::hasColumn('admins', 'auth_version'));
        $this->assertTrue(Schema::hasColumn('admins', 'remember_token'));
    }

    public function test_admin_login_records_the_current_auth_version(): void
    {
        $admin = $this->admin([
            'username' => 'versioned-login-admin',
            'auth_version' => 7,
        ]);

        $this->post(route('admin.login'), [
            'username' => $admin->username,
            'password' => 'Strong-Admin-Password!',
        ])->assertRedirect(route('dashboard.index'))
            ->assertSessionHas(Admin::SESSION_AUTH_VERSION, 7);

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_login_preserves_tag_like_password_characters(): void
    {
        $password = 'Strong<em>Admin-Password!23';
        $admin = $this->admin([
            'username' => 'tag-password-admin',
            'password' => Hash::make($password),
        ]);

        $this->post(route('admin.login'), [
            'username' => $admin->username,
            'password' => $password,
        ])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_disabled_admin_is_denied_on_the_next_request(): void
    {
        $admin = $this->admin(['status' => 0]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_disable_and_reenable_does_not_resurrect_an_old_admin_session(): void
    {
        $this->seed();
        $superAdmin = $this->admin(['username' => 'status-super-admin']);
        $target = $this->admin(['username' => 'status-target-admin', 'role' => 2]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->actingAs($superAdmin, 'admin')
            ->putJson(route('admin.status', $target->id), ['id' => $target->id])
            ->assertOk();

        $target = $target->fresh();
        $this->assertFalse((bool) $target->status);
        $this->assertSame(1, (int) $target->auth_version);

        $target->updateQuietly(['status' => 1]);

        $this->actingAs($target->fresh(), 'admin')
            ->withSession([Admin::SESSION_AUTH_VERSION => 0])
            ->get(route('dashboard.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_password_reset_invalidates_the_targets_old_session(): void
    {
        $this->seed();
        $superAdmin = $this->admin(['username' => 'reset-super-admin']);
        $target = $this->admin(['username' => 'reset-target-admin', 'role' => 2]);

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.reset.perform', $target->id))
            ->assertSessionHas('temporary_password');

        $target = $target->fresh();
        $resetPasswordHash = $target->password;
        $this->assertTrue($target->must_change_password);
        $this->assertSame(1, (int) $target->auth_version);
        $this->assertNotNull($target->password_changed_at);

        $this->actingAs($target, 'admin')
            ->withSession([Admin::SESSION_AUTH_VERSION => 0])
            ->put(route('admin.password.update'), [
                'password' => 'Attacker-Chosen-Password!23',
                'password_confirmation' => 'Attacker-Chosen-Password!23',
            ])->assertRedirect(route('admin.login'));

        $this->assertSame($resetPasswordHash, $target->fresh()->password);
        $this->assertGuest('admin');
    }

    public function test_forced_password_change_keeps_only_the_current_versioned_session(): void
    {
        $admin = $this->admin([
            'username' => 'temporary-password-admin',
            'must_change_password' => true,
            'auth_version' => 3,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.password.update'), [
                'password' => 'Replacement-Admin-Password!23',
                'password_confirmation' => 'Replacement-Admin-Password!23',
            ])->assertSessionHas(Admin::SESSION_AUTH_VERSION, 4);

        $admin = $admin->fresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertSame(4, (int) $admin->auth_version);
        $this->assertTrue(Hash::check('Replacement-Admin-Password!23', $admin->password));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_forced_password_change_page_renders_with_the_available_translation_labels(): void
    {
        $this->seed();

        $admin = $this->admin([
            'username' => 'temporary-password-page-admin',
            'must_change_password' => true,
            'auth_version' => 4,
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession([Admin::SESSION_AUTH_VERSION => 4])
            ->get(route('admin.password'))
            ->assertOk()
            ->assertSee('Your temporary password must be replaced before you can continue.')
            ->assertSee('Confirm Password');
    }

    private function admin(array $overrides = []): Admin
    {
        $attributes = array_merge([
            'name' => 'Session Security Admin',
            'username' => 'session-security-admin',
            'email' => 'session-security-admin@example.test',
            'role' => 1,
            'status' => 1,
            'password' => Hash::make('Strong-Admin-Password!'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ], $overrides);
        if (!array_key_exists('email', $overrides)) {
            $attributes['email'] = $attributes['username'] . '@example.test';
        }
        $authVersion = (int) ($attributes['auth_version'] ?? 0);
        unset($attributes['auth_version']);

        $admin = Admin::create($attributes);
        $admin->forceFill(['auth_version' => $authVersion])->saveQuietly();

        return $admin;
    }
}
