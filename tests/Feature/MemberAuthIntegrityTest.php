<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MemberAuthIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_supports_pending_member_registration(): void
    {
        foreach (['org', 'designation', 'is_approved'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
        }
        $this->assertContains('throttle:5,1', app('router')->getRoutes()->getByName('register')->gatherMiddleware());
        $this->get(route('register.form'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('siteSettings.member_area.registration_enabled', true)
                ->where('siteSettings.member_area.registration_title', 'Apply for member access')
            );
        $this->assertStringContainsString("route('register.form')", file_get_contents(resource_path('js/Pages/auth/login.vue')));
        $this->assertStringContainsString("form.post(route('register')", file_get_contents(resource_path('js/Pages/auth/register.vue')));

        $this->post(route('register'), [
            'name' => 'Pending Member',
            'phone_no' => '01700000000',
            'email' => 'pending@example.test',
            'password' => 'Strong-Member-Password!',
            'org' => 'Community Group',
            'designation' => 'Volunteer',
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', 'Your member application was submitted for administrator approval.');

        $this->assertDatabaseHas('users', [
            'phone_no' => '01700000000',
            'org' => 'Community Group',
            'designation' => 'Volunteer',
            'status' => 1,
            'is_approved' => 0,
        ]);
        $this->assertGuest();
    }

    public function test_pending_member_cannot_bypass_approval_through_any_local_login(): void
    {
        $user = User::create([
            'name' => 'Pending Member',
            'phone_no' => '01800000000',
            'email' => 'pending-login@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 0,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);

        $this->post(route('login'), ['phone_no' => $user->phone_no, 'password' => 'Strong-Member-Password!'])
            ->assertSessionHas('message.type', 'error');
        $this->assertGuest();
        $this->post(route('login2fa.perform'), ['email' => $user->email, 'password' => 'Strong-Member-Password!'])
            ->assertSessionHas('message.type', 'error');
        $this->assertGuest();
        $this->postJson('/api/v1/auth/login', ['phone_no' => $user->phone_no, 'password' => 'Strong-Member-Password!'])
            ->assertJson(['status' => false]);
        $this->postJson('/api/v1/auth/login-2fa', ['phone_no' => $user->phone_no, 'password' => 'Strong-Member-Password!'])
            ->assertJson(['status' => false]);
    }

    public function test_enrolled_member_cannot_use_password_only_web_login(): void
    {
        $user = User::create([
            'name' => 'Enrolled Member',
            'phone_no' => '01900000000',
            'email' => 'enrolled-member@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
            'google2fa_secret' => app('pragmarx.google2fa')->generateSecretKey(),
        ]);

        $this->post(route('login'), [
            'phone_no' => $user->phone_no,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('login2fa'))
            ->assertSessionHas('message.type', 'warning');

        $this->assertGuest();
    }

    public function test_unenrolled_member_can_still_use_password_only_web_login(): void
    {
        $user = User::create([
            'name' => 'Password Member',
            'phone_no' => '01600000000',
            'email' => 'password-member@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('abc123'),
        ]);

        $this->post(route('login'), [
            'phone_no' => $user->phone_no,
            'password' => 'abc123',
        ])->assertRedirect(route('frontend.home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_authenticates_the_exact_eligible_local_user_and_preserves_password(): void
    {
        $phone = '01600000001';
        User::create([
            'name' => 'Social Duplicate',
            'phone_no' => $phone,
            'email' => 'social-duplicate@example.test',
            'provider_type' => 'google',
            'social_id' => 'duplicate-social-id',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Different-Social-Password!'),
        ]);
        $password = 'Strong<em>Member-Password!23';
        $localUser = User::create([
            'name' => 'Eligible Local Member',
            'phone_no' => $phone,
            'email' => 'eligible-local@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make($password),
        ]);

        $this->post(route('login'), [
            'phone_no' => $phone,
            'password' => $password,
        ])->assertRedirect(route('frontend.home'));

        $this->assertAuthenticatedAs($localUser);
    }

    public function test_first_web_two_factor_enrollment_generates_an_svg_qr_without_imagick(): void
    {
        $user = User::create([
            'name' => 'First Enrollment Member',
            'phone_no' => '01600000002',
            'email' => 'first-enrollment@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);

        $this->post(route('login2fa.perform'), [
            'email' => $user->email,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('login2fa.verify'))
            ->assertSessionHas('data.enrollment_required', true)
            ->assertSessionHas('data.qr_image', function ($value): bool {
                $prefix = 'data:image/svg+xml;base64,';
                if (!is_string($value) || !str_starts_with($value, $prefix)) {
                    return false;
                }

                $svg = base64_decode(substr($value, strlen($prefix)), true);
                return is_string($svg) && str_contains($svg, '<svg');
            });
    }

    public function test_inactive_two_factor_login_feedback_does_not_append_the_status_value(): void
    {
        $user = User::create([
            'name' => 'Inactive Two Factor Member',
            'phone_no' => '01600000003',
            'email' => 'inactive-two-factor@example.test',
            'provider_type' => 'local',
            'status' => 0,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);

        $this->post(route('login2fa.perform'), [
            'email' => $user->email,
            'password' => 'Strong-Member-Password!',
        ])->assertSessionHas('message.text', 'You are not active for login.');
    }

    public function test_successful_web_otp_reencrypts_a_legacy_plaintext_secret(): void
    {
        $user = User::create([
            'name' => 'Legacy Web Two Factor Member',
            'phone_no' => '01000000000',
            'email' => 'legacy-web-two-factor@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);
        $secret = app('pragmarx.google2fa')->generateSecretKey();
        DB::table('users')->where('id', $user->id)->update(['google2fa_secret' => $secret]);
        $legacyFingerprint = hash('sha256', (string) DB::table('users')
            ->where('id', $user->id)
            ->value('google2fa_secret'));

        $challenge = $this->post(route('login2fa.perform'), [
            'email' => $user->email,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('login2fa.verify'))
            ->assertSessionHas('data.access_token');
        $accessToken = $challenge->getSession()->get('data.access_token');

        $this->post(route('login2fa.verify.perform'), [
            'access_token' => $accessToken,
            'code' => app('pragmarx.google2fa')->getCurrentOtp($secret),
        ])->assertRedirect(route('frontend.home'));

        $encryptedSecret = (string) DB::table('users')
            ->where('id', $user->id)
            ->value('google2fa_secret');
        $this->assertNotSame($legacyFingerprint, hash('sha256', $encryptedSecret));
        $this->assertSame(
            hash('sha256', $secret),
            hash('sha256', Crypt::decryptString($encryptedSecret))
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_existing_web_session_is_ended_before_password_change(): void
    {
        $this->assertIneligibleWebMemberCannotChangePassword(
            ['status' => 0],
            'inactive-web-session@example.test',
            '01000000001'
        );
    }

    public function test_rejected_existing_web_session_is_ended_before_password_change(): void
    {
        $this->assertIneligibleWebMemberCannotChangePassword(
            ['is_approved' => 2],
            'rejected-web-session@example.test',
            '01000000002'
        );
    }

    public function test_inactive_existing_web_session_is_ended_on_an_ordinary_public_page(): void
    {
        $this->assertIneligibleWebMemberCannotReachPublicPage(
            ['status' => 0],
            'inactive-public-page@example.test',
            '01000000003'
        );

        $middleware = app('router')->getMiddlewareGroups()['web'];
        $memberCheck = 'member.active:web';
        $this->assertContains(StartSession::class, $middleware);
        $this->assertContains($memberCheck, $middleware);
        $this->assertTrue(
            array_search(StartSession::class, $middleware, true)
                < array_search($memberCheck, $middleware, true)
        );
    }

    public function test_rejected_existing_web_session_is_ended_on_an_ordinary_public_page(): void
    {
        $this->assertIneligibleWebMemberCannotReachPublicPage(
            ['is_approved' => 2],
            'rejected-public-page@example.test',
            '01000000004'
        );
    }

    public function test_global_member_check_allows_guests_and_admin_only_sessions(): void
    {
        $this->get(route('showLogin'))->assertOk();

        $admin = Admin::create([
            'name' => 'Admin Only Session',
            'username' => 'admin-only-session',
            'email' => 'admin-only-session@example.test',
            'role' => 1,
            'status' => 1,
            'password' => Hash::make('Strong-Admin-Password!'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('showLogin'))
            ->assertOk();

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_rejecting_a_member_revokes_access_and_refresh_tokens(): void
    {
        $user = User::create([
            'name' => 'Token Member',
            'phone_no' => '01500000000',
            'email' => 'token-member@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);
        $user->forceFill(['remember_token' => 'known-remember-token'])->saveQuietly();
        [$accessTokenId, $refreshTokenId] = $this->issuePassportTokenPair($user);

        $user->update(['is_approved' => 2]);

        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $accessTokenId, 'revoked' => 1]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => $refreshTokenId, 'revoked' => 1]);
        $this->assertNotSame('known-remember-token', $user->fresh()->getRememberToken());
    }

    public function test_disabling_a_member_revokes_access_and_refresh_tokens(): void
    {
        $user = User::create([
            'name' => 'Disabled Token Member',
            'phone_no' => '01200000000',
            'email' => 'disabled-token-member@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);
        [$accessTokenId, $refreshTokenId] = $this->issuePassportTokenPair($user);

        $user->update(['status' => 0]);

        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $accessTokenId, 'revoked' => 1]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => $refreshTokenId, 'revoked' => 1]);
    }

    public function test_member_password_change_requires_strength_and_confirmation(): void
    {
        $user = User::create([
            'name' => 'Password Policy Member',
            'phone_no' => '01400000000',
            'email' => 'password-policy@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Original-Member-Password!'),
        ]);

        $this->actingAs($user)->post(route('change.password'), [
            'current_password' => 'Original-Member-Password!',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password'])
            ->assertSessionHas('message.type', 'error')
            ->assertSessionHas('message.text', 'Please correct the highlighted password fields.');

        $this->assertTrue(Hash::check('Original-Member-Password!', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_member_password_change_identifies_an_incorrect_current_password(): void
    {
        $user = User::create([
            'name' => 'Incorrect Current Password Member',
            'phone_no' => '01400000001',
            'email' => 'incorrect-current-password@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Original-Member-Password!'),
        ]);

        $this->actingAs($user)->post(route('change.password'), [
            'current_password' => 'Wrong-Member-Password!',
            'password' => 'Replacement-Member-Password!23',
            'password_confirmation' => 'Replacement-Member-Password!23',
        ])->assertSessionHasErrors([
            'current_password' => 'The current password is incorrect.',
        ])->assertSessionHas('message.text', 'Incorrect current password.');

        $this->assertTrue(Hash::check('Original-Member-Password!', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_valid_member_password_change_revokes_tokens_and_logs_out(): void
    {
        $user = User::create([
            'name' => 'Changing Password Member',
            'phone_no' => '01300000000',
            'email' => 'changing-password@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Original-Member-Password!'),
        ]);
        $user->forceFill(['remember_token' => 'known-remember-token'])->saveQuietly();
        [$accessTokenId, $refreshTokenId] = $this->issuePassportTokenPair($user);

        $replacementPassword = 'Replacement<em>Member-Password!23';
        $this->actingAs($user)->post(route('change.password'), [
            'current_password' => 'Original-Member-Password!',
            'password' => $replacementPassword,
            'password_confirmation' => $replacementPassword,
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.type', 'success');

        $freshUser = $user->fresh();
        $this->assertTrue(Hash::check($replacementPassword, $freshUser->password));
        $this->assertNotSame('known-remember-token', $freshUser->getRememberToken());
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $accessTokenId, 'revoked' => 1]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => $refreshTokenId, 'revoked' => 1]);
        $this->assertGuest();
    }

    public function test_secure_first_admin_command_builds_a_usable_menu_and_is_single_use(): void
    {
        $this->seed();
        $this->artisan('igf:provision-admin', [
            '--name' => 'Deployment Owner',
            '--username' => 'deployment-owner',
            '--email' => 'owner@example.test',
        ])->expectsQuestion(
            'Temporary password (12+ characters, mixed case, number and symbol)',
            'Temporary-Owner-Password!23'
        )->assertSuccessful();

        $admin = Admin::query()->where('username', 'deployment-owner')->firstOrFail();
        $this->assertTrue($admin->must_change_password);
        $this->assertNotSame('[]', $admin->roleModel()->first()?->serial ?? '[]');
        $admin->update(['must_change_password' => false]);
        $this->actingAs($admin, 'admin')->get(route('dashboard.index'))->assertOk()->assertSee('Dashboard');

        $this->artisan('igf:provision-admin', [
            '--name' => 'Second Owner',
            '--username' => 'second-owner',
            '--email' => 'second@example.test',
        ])->assertFailed();
        $this->assertSame(1, Admin::count());
    }

    /** @return array{string, string} */
    private function issuePassportTokenPair(User $user): array
    {
        $client = app(ClientRepository::class)->createPersonalAccessGrantClient('Member auth test', 'users');
        $accessTokenId = (string) Str::uuid();
        $refreshTokenId = (string) Str::uuid();

        Passport::token()->newQuery()->create([
            'id' => $accessTokenId,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'Member auth test token',
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
        Passport::refreshToken()->newQuery()->create([
            'id' => $refreshTokenId,
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ]);

        return [$accessTokenId, $refreshTokenId];
    }

    /** @param array<string, int> $ineligibleState */
    private function assertIneligibleWebMemberCannotChangePassword(
        array $ineligibleState,
        string $email,
        string $phoneNumber
    ): void {
        $user = User::create([
            'name' => 'Ineligible Web Member',
            'phone_no' => $phoneNumber,
            'email' => $email,
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Original-Member-Password!'),
        ]);
        $originalPasswordHash = $user->password;

        $this->actingAs($user)->withSession(['member-session-sentinel' => 'present']);
        $user->updateQuietly($ineligibleState);

        $this->post(route('change.password'), [
            'current_password' => 'Original-Member-Password!',
            'password' => 'Replacement-Member-Password!',
            'password_confirmation' => 'Replacement-Member-Password!',
        ])->assertRedirect(route('showLogin'))
            ->assertSessionHas('message.type', 'error')
            ->assertSessionMissing('member-session-sentinel');

        $this->assertSame($originalPasswordHash, $user->fresh()->password);
        $this->assertGuest();
    }

    /** @param array<string, int> $ineligibleState */
    private function assertIneligibleWebMemberCannotReachPublicPage(
        array $ineligibleState,
        string $email,
        string $phoneNumber
    ): void {
        $user = User::create([
            'name' => 'Ineligible Public Page Member',
            'phone_no' => $phoneNumber,
            'email' => $email,
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Original-Member-Password!'),
        ]);

        $this->actingAs($user)->withSession(['member-session-sentinel' => 'present']);
        $user->updateQuietly($ineligibleState);

        $this->get(route('frontend.home'))
            ->assertRedirect(route('showLogin'))
            ->assertSessionHas('message.type', 'error')
            ->assertSessionMissing('member-session-sentinel');

        $this->assertGuest('web');
        $this->get(route('showLogin'))->assertOk();
    }
}
