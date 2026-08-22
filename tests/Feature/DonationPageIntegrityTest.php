<?php

namespace Tests\Feature;

use App\Models\DonationType;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DonationPageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_page_exposes_only_active_causes_and_useful_default_seo(): void
    {
        $active = DonationType::create(['name' => 'Education', 'status' => 1]);
        DonationType::create(['name' => 'Retired campaign', 'status' => 0]);

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('donate')
                ->where('title', 'Donate securely')
                ->where('meta_tag.meta_title', 'Donate Securely | Ignite Global Foundation')
                ->where('meta_tag.meta_description', fn ($value) => str_contains($value, 'secure donation'))
                ->has('data.donationTypes', 1)
                ->where('data.donationTypes.0.uuid', $active->uuid)
                ->where('data.donationTypes.0.name', 'Education')
                ->has('data.donationFrequencies', 4)
                ->where('data.donationFrequencies.0', ['key' => 'one_time', 'available' => true])
                ->where('data.donationFrequencies.1', ['key' => 'daily', 'available' => false])
                ->where('siteSettings.donation_page.checkout_layout', 'centered')
                ->where('siteSettings.donation_page.card_style', 'soft')
                ->where('siteSettings.donation_page.amount_button_count', '5')
                ->where('siteSettings.donation_page.amount_5', 10000)
                ->where('siteSettings.donation_page.amount_4_impact', 'Stronger support for an active community project')
                ->where('siteSettings.donation_page.frequency_label', 'One-time')
                ->where('siteSettings.donation_page.checkout_step_label', 'Details & payment')
                ->where('siteSettings.donation_page.submit_with_amount_label', 'Continue securely with {amount}')
                ->where('siteSettings.donation_page.terms_link_url', '/page/terms-conditions')
                ->where('siteSettings.donation_page.show_custom_amount', true)
                ->where('siteSettings.donation_page.show_gateway_note', true)
            );
    }

    public function test_admin_donation_builder_settings_control_the_public_checkout(): void
    {
        foreach ([
            ['key' => 'checkout_layout', 'value' => 'split', 'type' => 'text'],
            ['key' => 'card_style', 'value' => 'outlined', 'type' => 'text'],
            ['key' => 'amount_button_count', 'value' => '3', 'type' => 'text'],
            ['key' => 'amount_1', 'value' => '750', 'type' => 'integer'],
            ['key' => 'show_help_card', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'gateway_heading', 'value' => 'Payments handled securely', 'type' => 'text'],
        ] as $setting) {
            SiteSetting::create([
                'group' => 'donation_page',
                'key' => $setting['key'],
                'locale' => in_array($setting['key'], ['gateway_heading'], true) ? 'en' : '*',
                'value' => $setting['value'],
                'type' => $setting['type'],
                'is_public' => true,
            ]);
        }

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteSettings.donation_page.checkout_layout', 'split')
                ->where('siteSettings.donation_page.card_style', 'outlined')
                ->where('siteSettings.donation_page.amount_button_count', '3')
                ->where('siteSettings.donation_page.amount_1', 750)
                ->where('siteSettings.donation_page.show_help_card', false)
                ->where('siteSettings.donation_page.gateway_heading', 'Payments handled securely')
            );
    }

    public function test_admin_route_seo_overrides_donation_defaults(): void
    {
        SeoMetadata::create([
            'route_name' => 'frontend.donate.index',
            'route_path' => '/donate/{type?}',
            'locale' => 'en',
            'title' => 'Owner-controlled donation title',
            'description' => 'Owner-controlled donation description.',
            'robots_index' => false,
            'robots_follow' => false,
            'sitemap_priority' => .8,
        ]);

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('routeSeo.meta_title', 'Owner-controlled donation title')
                ->where('routeSeo.meta_description', 'Owner-controlled donation description.')
                ->where('routeSeo.robots', 'noindex,nofollow')
            );
    }
}
