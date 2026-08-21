<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_available_before_an_admin_customizes_them(): void
    {
        $values = app(SiteSettingService::class)->values('en', true);

        $this->assertSame('Ignite Global Foundation', $values['branding']['site_name']);
        $this->assertSame('/donate', $values['header']['donate_url']);
        $this->assertSame('#ff7500', $values['theme']['primary_color']);
        $this->assertSame("Let's start a conversation.", $values['contact_page']['title']);
        $this->assertSame('What is Ignite Global Foundation?', $values['contact_page']['faq_1_question']);
        $this->assertSame(1500, $values['sponsor_page']['monthly_amount']);
        $this->assertSame('Bring your time, skills, and heart.', $values['volunteer_page']['title']);
        $this->assertSame('silver', $values['zakat_calculator']['nisab_default_basis']);
        $this->assertSame(22188.0, $values['zakat_calculator']['gold_price_per_gram']);
        $this->assertSame(475.0, $values['zakat_calculator']['silver_price_per_gram']);
        $this->assertSame(290871, $values['zakat_calculator']['nisab_amount']);
        $this->assertSame(
            'The calculation is paused until the administrator verifies the current metal prices and records the date checked.',
            $values['zakat_calculator']['stale_price_result_note']
        );
        $this->assertStringContainsString('another common standard uses 85 grams of gold or 595 grams of silver', $values['zakat_calculator']['methodology']);
        $this->assertStringContainsString('jewellery, shares, property, and debts', $values['zakat_calculator']['disclaimer']);
    }

    public function test_locale_specific_values_override_global_values_without_leaking_private_settings(): void
    {
        SiteSetting::create([
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'en',
            'value' => 'Ignite for Everyone',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'header',
            'key' => 'announcement_enabled',
            'locale' => '*',
            'value' => '1',
            'type' => 'boolean',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'analytics',
            'key' => 'google_analytics_id',
            'locale' => '*',
            'value' => 'private-test-value',
            'type' => 'text',
            'is_public' => false,
        ]);

        $values = app(SiteSettingService::class)->values('en', true);

        $this->assertSame('Ignite for Everyone', $values['branding']['site_name']);
        $this->assertTrue($values['header']['announcement_enabled']);
        $this->assertSame('', $values['analytics']['google_analytics_id']);
    }

    public function test_public_values_sanitize_legacy_stored_urls_at_read_time(): void
    {
        SiteSetting::create([
            'group' => 'header',
            'key' => 'announcement_url',
            'locale' => 'en',
            'value' => 'javascript:alert(1)',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'social',
            'key' => 'facebook',
            'locale' => '*',
            'value' => 'javascript:alert(2)',
            'type' => 'text',
            'is_public' => true,
        ]);

        $values = app(SiteSettingService::class)->values('en', true);

        $this->assertSame('', $values['header']['announcement_url']);
        $this->assertSame('', $values['social']['facebook']);
        $this->assertSame('/donate', $values['header']['donate_url']);
    }

    public function test_legacy_property_wording_cannot_reintroduce_full_investment_property_value(): void
    {
        SiteSetting::create([
            'group' => 'zakat_calculator',
            'key' => 'investment_property_label',
            'locale' => 'en',
            'value' => 'Investment property value',
            'type' => 'text',
            'is_public' => true,
        ]);

        $values = app(SiteSettingService::class)->values('en', true);

        $this->assertSame(
            'Property bought with the intention to resell',
            $values['zakat_calculator']['property_for_resale_label']
        );
    }

    public function test_zakat_schema_separates_global_price_inputs_from_localized_public_guidance(): void
    {
        $fields = config('site-settings.groups.zakat_calculator.fields');

        foreach ([
            'nisab_default_basis', 'gold_price_per_gram', 'silver_price_per_gram',
            'nisab_price_updated_at', 'nisab_source_url',
        ] as $key) {
            $this->assertFalse($fields[$key]['localized'], $key.' must be the same in every language.');
            $this->assertTrue($fields[$key]['public'], $key.' must be available to the calculator.');
        }

        foreach ([
            'nisab_source_label', 'nisab_method_label', 'nisab_basis_help',
            'gold_method_label', 'silver_method_label', 'calculated_nisab_label',
            'stale_price_notice', 'stale_price_result_note', 'lunar_year_question', 'lunar_year_help',
            'property_for_resale_label', 'resale_property_help',
            'net_rental_income_label', 'retained_rental_income_help',
            'exclusions_note', 'immediate_debt_label', 'immediate_debt_help',
            'methodology', 'disclaimer',
        ] as $key) {
            $this->assertTrue($fields[$key]['localized'], $key.' must be translatable.');
            $this->assertTrue($fields[$key]['public'], $key.' must be available to the calculator.');
        }

        $this->assertSame('date', $fields['nisab_price_updated_at']['type']);
        $this->assertSame('float', $fields['gold_price_per_gram']['type']);
        $this->assertSame('float', $fields['silver_price_per_gram']['type']);
        $this->assertSame(0.01, $fields['gold_price_per_gram']['step']);
        $this->assertSame(0.01, $fields['silver_price_per_gram']['step']);
        $this->assertSame(1000, $fields['gold_price_per_gram']['min']);
        $this->assertSame(1000000, $fields['gold_price_per_gram']['max']);
        $this->assertSame(10, $fields['silver_price_per_gram']['min']);
        $this->assertSame(100000, $fields['silver_price_per_gram']['max']);
        $this->assertArrayNotHasKey('nisab_amount', $fields);
        $this->assertArrayNotHasKey('investment_property_label', $fields);
        $this->assertArrayNotHasKey('debts_label', $fields);
        $this->assertArrayNotHasKey('nisab_label', $fields);
        $this->assertArrayNotHasKey('rate', $fields);
        $this->assertArrayNotHasKey('zakat_rate', $fields);
    }

}
