<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SponsorshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsorship_request_persists_without_broken_payment_callbacks(): void
    {
        Mail::fake();

        $this->postJson('/sponsorship/store', [
            'name' => 'Community Sponsor',
            'email' => 'sponsor@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'number_of_children' => 2,
            'contribution_interval' => 'monthly',
            'sponsorshipAmount' => 2000,
        ])->assertOk()->assertJson(['status' => true]);

        $sponsorship = \App\Models\Sponsorship::firstOrFail();
        $this->assertStringStartsWith('REQUEST-', $sponsorship->transaction_id);
        $this->assertSame('Request', $sponsorship->payment_status);
        $this->assertSame(3000.0, (float) $sponsorship->sponsorship_amount);

        $callbackRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'sponsorship/payment/'));
        $this->assertCount(0, $callbackRoutes);
    }

    public function test_sponsorship_total_is_recalculated_from_admin_settings_instead_of_trusting_the_browser(): void
    {
        Mail::fake();
        \App\Models\SiteSetting::create([
            'group' => 'sponsor_page',
            'key' => 'monthly_amount',
            'locale' => '*',
            'value' => '1750',
            'type' => 'integer',
            'is_public' => true,
        ]);

        $this->postJson('/sponsorship/store', [
            'name' => 'Careful Sponsor',
            'email' => 'careful@example.test',
            'number_of_children' => 2,
            'contribution_interval' => 'quarterly',
            'sponsorshipAmount' => 1,
        ])->assertOk();

        $this->assertSame(10500.0, (float) \App\Models\Sponsorship::firstOrFail()->sponsorship_amount);
    }
}
