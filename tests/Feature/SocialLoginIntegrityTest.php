<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Tests\TestCase;

class SocialLoginIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_social_registration_awaits_approval_without_dumping_provider_data(): void
    {
        $providerUser = (object) [
            'id' => 'github-user-awaiting-approval',
            'name' => 'Community Member',
            'email' => 'member@example.test',
            'avatar' => 'https://images.example.test/member.jpg',
            'token' => 'must-never-be-rendered',
        ];

        Socialite::shouldReceive('driver')->once()->with('github')->andReturn(
            new class ($providerUser) {
                public function __construct(private object $providerUser)
                {
                }

                public function user(): object
                {
                    return $this->providerUser;
                }
            }
        );

        $response = $this->get('/login/github/callback')
            ->assertRedirect(route('showLogin'))
            ->assertSessionHas('message.type', 'info');

        $response->assertDontSee('must-never-be-rendered', false);
        $user = User::query()->where('social_id', 'github-user-awaiting-approval')->firstOrFail();
        $this->assertGuest();
        $this->assertTrue((bool) $user->status);
        $this->assertSame(0, (int) $user->is_approved);
        $this->assertNotSame('github-user-awaiting-approval', $user->password);
    }

    public function test_github_callback_uses_the_real_home_route(): void
    {
        config()->set('security.social_registration_auto_approve', true);

        $providerUser = (object) [
            'id' => 'github-user-123',
            'name' => 'Open Source Member',
            'email' => 'github@example.test',
            'avatar' => null,
        ];

        Socialite::shouldReceive('driver')->once()->with('github')->andReturn(
            new class ($providerUser) {
                public function __construct(private object $providerUser)
                {
                }

                public function user(): object
                {
                    return $this->providerUser;
                }
            }
        );

        $this->get('/login/github/callback')
            ->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.type', 'success');
    }

    public function test_provider_failure_has_a_generic_response_and_sanitized_log_context(): void
    {
        Log::spy();
        $driver = new class {
            public function user(): object
            {
                throw new RuntimeException('secret-oauth-code-and-token');
            }
        };
        Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver);

        $response = $this->get('/login/github/callback')
            ->assertRedirect(route('showLogin'))
            ->assertSessionHas('message', [
                'type' => 'error',
                'text' => 'Social sign-in could not be completed. Please try again.',
            ]);

        $response->assertDontSee('secret-oauth-code-and-token', false);
        Log::shouldHaveReceived('warning')->once()->with(
            'Social login callback failed.',
            [
                'provider' => 'github',
                'exception_class' => RuntimeException::class,
            ]
        );
    }

    public function test_social_callback_routes_are_throttled(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'login/github/callback');

        $this->assertNotNull($route);
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
    }

    public function test_google_and_facebook_web_login_entry_points_are_removed(): void
    {
        $this->assertFalse(Route::has('login.google'));
        $this->assertFalse(Route::has('login.facebook'));

        foreach (['google', 'facebook'] as $provider) {
            $this->get("/login/{$provider}")->assertNotFound();
            $this->get("/login/{$provider}/callback")->assertNotFound();
        }
    }

    public function test_social_redirects_are_environment_configured(): void
    {
        $services = file_get_contents(config_path('services.php'));

        $this->assertStringContainsString("env('GOOGLE_CLIENT_REDIRECT')", $services);
        $this->assertStringContainsString("env('FACEBOOK_CLIENT_REDIRECT')", $services);
        $this->assertStringContainsString("env('GITHUB_CLIENT_REDIRECT')", $services);
    }

    public function test_disabled_social_account_cannot_authenticate_again(): void
    {
        User::create([
            'name' => 'Disabled Member',
            'email' => 'disabled-social@example.test',
            'provider_type' => 'github',
            'social_id' => 'disabled-github-id',
            'status' => 0,
            'is_approved' => 1,
            'password' => bcrypt('unusable-local-password'),
        ]);
        $providerUser = (object) [
            'id' => 'disabled-github-id',
            'name' => 'Disabled Member',
            'email' => 'disabled-social@example.test',
            'avatar' => null,
        ];
        Socialite::shouldReceive('driver')->once()->with('github')->andReturn(
            new class ($providerUser) {
                public function __construct(private object $providerUser) {}
                public function user(): object { return $this->providerUser; }
            }
        );

        $this->get('/login/github/callback')
            ->assertRedirect(route('showLogin'))
            ->assertSessionHas('message.type', 'error');
        $this->assertGuest();
    }
}
