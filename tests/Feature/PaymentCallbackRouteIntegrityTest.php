<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class PaymentCallbackRouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_donation_post_callback_matches_the_csrf_allowlist(): void
    {
        $reflection = new ReflectionClass(VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);
        $patterns = $property->getValue(app(VerifyCsrfToken::class));

        $this->assertContains('donation/payment/*', $patterns);
        $this->assertContains('donate/payment/*', $patterns);

        $callbackUris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('POST', $route->methods(), true))
            ->filter(fn ($route) => str_contains($route->uri(), 'payment/'))
            ->pluck('uri');

        foreach ($callbackUris as $uri) {
            $matches = collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $uri));
            $this->assertTrue($matches, "Callback {$uri} is missing a CSRF exemption.");
        }
    }

    public function test_unsigned_browser_callbacks_reach_controller_instead_of_returning_419(): void
    {
        foreach (['success', 'fail', 'cancel'] as $callback) {
            $this->withHeader('X-Inertia', 'true')
                ->post('/donation/payment/' . $callback, ['tran_id' => 'unknown'])
                ->assertStatus(200);
        }
    }
}
