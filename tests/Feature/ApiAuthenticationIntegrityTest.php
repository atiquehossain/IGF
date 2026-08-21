<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveApprovedMember;
use App\Models\User;
use App\Services\TwoFactorChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class ApiAuthenticationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_social_google_alias_is_not_registered(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(
            fn ($route): bool => in_array('POST', $route->methods(), true)
                && $route->uri() === 'api/v1/auth/social-google'
        ));

        $this->postJson('/api/v1/auth/social-google', [
            'provider' => 'google',
            'access_token' => 'invalid',
        ])->assertNotFound();
    }

    public function test_active_local_user_reaches_password_validation(): void
    {
        User::create([
            'name' => 'Active API User',
            'email' => 'active-api@example.test',
            'phone_no' => '01700000000',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_no' => '01700000000',
            'password' => 'wrong-password',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => false,
                'message' => 'password mismatch. please try again',
            ]);
    }

    public function test_inactive_local_user_is_rejected_before_password_or_token_issue(): void
    {
        User::create([
            'name' => 'Inactive API User',
            'email' => 'inactive-api@example.test',
            'phone_no' => '01800000000',
            'provider_type' => 'local',
            'status' => 0,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_no' => '01800000000',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => false,
                'message' => 'You are not active for login.',
            ]);
    }

    public function test_two_factor_challenge_is_opaque_password_free_and_single_use(): void
    {
        $secret = app('pragmarx.google2fa')->generateSecretKey();
        User::create([
            'name' => 'Two Factor API User',
            'email' => 'two-factor-api@example.test',
            'phone_no' => '01900000000',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
            'google2fa_secret' => $secret,
        ]);

        $challengeResponse = $this->postJson('/api/v1/auth/login-2fa', [
            'phone_no' => '01900000000',
            'password' => 'correct-password',
        ]);

        $challengeResponse->assertOk()
            ->assertJson([
                'status' => true,
                'enrollment_required' => false,
                'qr_image' => null,
            ])
            ->assertJsonMissingPath('secret')
            ->assertJsonMissingPath('password');

        $token = $challengeResponse->json('access_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertStringNotContainsString('correct-password', $token);

        $cached = Cache::get('auth:two-factor-challenge:' . hash('sha256', $token));
        $this->assertSame(['user_id', 'pending_secret'], array_keys($cached));
        $this->assertNull($cached['pending_secret']);

        $currentCode = app('pragmarx.google2fa')->getCurrentOtp($secret);
        $wrongCode = str_pad((string) (((int) $currentCode + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->postJson('/api/v1/auth/verify2fa', [
            'access_token' => $token,
            'code' => $wrongCode,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Verification code mismatch. Start a new login challenge.');

        $this->postJson('/api/v1/auth/verify2fa', [
            'access_token' => $token,
            'code' => $currentCode,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The verification challenge is invalid or expired.');
    }

    public function test_pending_enrollment_secret_is_encrypted_at_rest_and_consumed_under_a_lock(): void
    {
        $user = User::create([
            'name' => 'Pending Enrollment User',
            'email' => 'pending-enrollment@example.test',
            'phone_no' => '01900000001',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
        ]);
        $secret = app('pragmarx.google2fa')->generateSecretKey();
        $challenges = app(TwoFactorChallengeService::class);
        $token = $challenges->create($user, $secret);
        $hash = hash('sha256', $token);
        $cacheKey = 'auth:two-factor-challenge:' . $hash;
        $lock = Cache::lock('auth:two-factor-challenge-lock:' . $hash, 10);

        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
        $this->assertIsString($cached['pending_secret']);
        $this->assertNotSame($secret, $cached['pending_secret']);
        $this->assertStringNotContainsString($secret, serialize($cached));

        $this->assertTrue($lock->get());
        try {
            $this->assertNull($challenges->consume($token));
            $this->assertNotNull(Cache::get($cacheKey));
        } finally {
            $lock->release();
        }

        $this->assertSame([
            'user_id' => $user->id,
            'pending_secret' => $secret,
        ], $challenges->consume($token));
        $this->assertNull($challenges->consume($token));
    }

    public function test_enrolled_user_cannot_exchange_only_a_password_for_an_api_token(): void
    {
        $user = User::create([
            'name' => 'Enrolled API User',
            'email' => 'enrolled-api@example.test',
            'phone_no' => '01600000000',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
            'google2fa_secret' => app('pragmarx.google2fa')->generateSecretKey(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone_no' => $user->phone_no,
            'password' => 'correct-password',
        ])->assertOk()->assertJson([
            'status' => false,
            'requires_2fa' => true,
        ])->assertJsonMissingPath('token');

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_legacy_plaintext_two_factor_secret_remains_readable_and_enforced(): void
    {
        $user = User::create([
            'name' => 'Legacy Two Factor User',
            'email' => 'legacy-two-factor@example.test',
            'phone_no' => '01100000000',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
        ]);
        $legacySecret = app('pragmarx.google2fa')->generateSecretKey();
        DB::table('users')->where('id', $user->id)->update(['google2fa_secret' => $legacySecret]);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->postJson('/api/v1/auth/login', [
            'phone_no' => $user->phone_no,
            'password' => 'correct-password',
        ])->assertOk()->assertJson([
            'status' => false,
            'requires_2fa' => true,
        ])->assertJsonMissingPath('token');
    }

    public function test_protected_api_rechecks_member_eligibility(): void
    {
        $user = User::create([
            'name' => 'Revoked API User',
            'email' => 'revoked-api@example.test',
            'phone_no' => '01500000000',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('correct-password'),
        ]);

        $user->updateQuietly(['is_approved' => 2]);
        $request = Request::create('/api/v1/user/profile', 'GET');
        $request->setUserResolver(fn () => $user->fresh());
        $response = app(EnsureActiveApprovedMember::class)->handle(
            $request,
            fn () => response()->json(['status' => true])
        );

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'status' => false,
            'message' => 'This account is inactive or awaiting approval.',
        ], $response->getData(true));
        $this->assertContains(
            'member.active',
            app('router')->getRoutes()->getByName('api.pictureUpload')->gatherMiddleware()
        );
    }

    public function test_inactive_social_account_cannot_receive_an_api_token(): void
    {
        User::create([
            'name' => 'Disabled Social API User',
            'email' => 'disabled-api-social@example.test',
            'provider_type' => 'facebook',
            'social_id' => 'disabled-api-social-id',
            'status' => 0,
            'is_approved' => 1,
            'password' => Hash::make('unusable-local-password'),
        ]);
        $providerUser = new class {
            public function getId(): string { return 'disabled-api-social-id'; }
            public function getEmail(): string { return 'disabled-api-social@example.test'; }
            public function getName(): string { return 'Disabled Social API User'; }
            public function getAvatar(): ?string { return null; }
        };
        $driver = new class ($providerUser) {
            public function __construct(private object $providerUser) {}
            public function stateless(): self { return $this; }
            public function userFromToken(string $token): object { return $this->providerUser; }
        };
        Socialite::shouldReceive('with')->once()->with('facebook')->andReturn($driver);

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'facebook',
            'access_token' => 'opaque-provider-token',
        ])->assertForbidden()->assertJson([
            'status' => false,
            'message' => 'This member account is inactive.',
        ])->assertJsonMissingPath('token');
    }
}
