<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Carbon\CarbonImmutable;
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
        $this->assertSame('info@ignite.org.bd', $values['contact']['email']);
        $this->assertSame('+8801972016221', $values['contact']['phone_primary']);
        $this->assertSame('', $values['contact']['phone_secondary']);
        $this->assertSame(
            'Madrasah Road, House-847, Level (A-1), East Kazi Para, Mirpur, Dhaka-1216',
            $values['contact']['address']
        );
        $this->assertSame("Let's start a conversation.", $values['contact_page']['title']);
        $this->assertSame('What is Ignite Global Foundation?', $values['contact_page']['faq_1_question']);
        $this->assertCount(5, $values['contact_page']['faqs']);
        $this->assertSame('What is Ignite Global Foundation?', $values['contact_page']['faqs'][0]['question']);
        $this->assertTrue($values['contact_page']['faqs'][0]['is_active']);
        $this->assertSame(1500, $values['sponsor_page']['monthly_amount']);
        $this->assertSame('Bring your time, skills, and heart.', $values['volunteer_page']['title']);
        $this->assertTrue($values['legal_status']['enabled']);
        $this->assertSame('Legal Status', $values['legal_status']['heading']);
        $this->assertSame('', $values['legal_status']['authority_1_label']);
        $this->assertSame('', $values['legal_status']['authority_1_registration']);
        $this->assertSame('', $values['legal_status']['authority_1_logo']);
        $this->assertSame('NGO Affairs Bureau Registration No.', $values['legal_status']['authority_2_label']);
        $this->assertSame('3461', $values['legal_status']['authority_2_registration']);
        $this->assertSame('Joint Stock & Firms Registration No.', $values['legal_status']['authority_3_label']);
        $this->assertSame('S-13907/2022', $values['legal_status']['authority_3_registration']);
        $this->assertSame('silver', $values['zakat_calculator']['nisab_default_basis']);
        $this->assertSame('standard_87_48_612_36', $values['zakat_calculator']['nisab_weight_standard']);
        $this->assertSame(22188.0, $values['zakat_calculator']['gold_price_per_gram']);
        $this->assertSame(475.0, $values['zakat_calculator']['silver_price_per_gram']);
        $this->assertSame(290871, $values['zakat_calculator']['nisab_amount']);
        $this->assertSame(
            'The calculation is paused until the administrator verifies the current metal prices and records the date checked.',
            $values['zakat_calculator']['stale_price_result_note']
        );
        $this->assertStringContainsString('approved {gold_weight}-gram gold / {silver_weight}-gram silver', $values['zakat_calculator']['methodology']);
        $this->assertStringContainsString('jewellery, shares, property, and debts', $values['zakat_calculator']['disclaimer']);
        $this->assertSame('View full details', $values['zakat_calculator']['impact_view_details_label']);
        $this->assertStringContainsString('urgent food needs', $values['zakat_calculator']['food_details']);
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

    public function test_dynamic_contact_faqs_preserve_legacy_customizations_until_the_new_list_is_saved(): void
    {
        SiteSetting::create([
            'group' => 'contact_page',
            'key' => 'faq_1_question',
            'locale' => 'en',
            'value' => 'A customized legacy question?',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'contact_page',
            'key' => 'faq_1_answer',
            'locale' => 'en',
            'value' => 'A customized legacy answer.',
            'type' => 'text',
            'is_public' => true,
        ]);

        $legacyValues = app(SiteSettingService::class)->values('en', true);

        $this->assertSame('A customized legacy question?', $legacyValues['contact_page']['faqs'][0]['question']);
        $this->assertSame('A customized legacy answer.', $legacyValues['contact_page']['faqs'][0]['answer']);

        SiteSetting::create([
            'group' => 'contact_page',
            'key' => 'faqs',
            'locale' => 'en',
            'value' => json_encode([
                ['question' => 'A dynamic question?', 'answer' => 'A dynamic answer.', 'is_active' => true],
                ['question' => 'A hidden question?', 'answer' => 'A hidden answer.', 'is_active' => false],
            ], JSON_THROW_ON_ERROR),
            'type' => 'json',
            'is_public' => true,
        ]);

        $dynamicValues = app(SiteSettingService::class)->values('en', true);

        $this->assertCount(2, $dynamicValues['contact_page']['faqs']);
        $this->assertSame('A dynamic question?', $dynamicValues['contact_page']['faqs'][0]['question']);
        $this->assertSame('A dynamic question?', $dynamicValues['contact_page']['faq_1_question']);
        $this->assertSame('', $dynamicValues['contact_page']['faq_2_question']);
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

    public function test_legal_status_labels_are_localized_while_official_identifiers_and_logos_are_global(): void
    {
        SiteSetting::create([
            'group' => 'legal_status',
            'key' => 'authority_2_label',
            'locale' => 'bn',
            'value' => 'এনজিও অ্যাফেয়ার্স ব্যুরো নিবন্ধন নম্বর',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'legal_status',
            'key' => 'authority_2_registration',
            'locale' => '*',
            'value' => '3461',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'legal_status',
            'key' => 'authority_2_logo',
            'locale' => '*',
            'value' => '/storage/media/legal/ngo-affairs.png',
            'type' => 'text',
            'is_public' => true,
        ]);

        $values = app(SiteSettingService::class)->values('bn', true)['legal_status'];

        $this->assertSame('এনজিও অ্যাফেয়ার্স ব্যুরো নিবন্ধন নম্বর', $values['authority_2_label']);
        $this->assertSame('3461', $values['authority_2_registration']);
        $this->assertSame('/storage/media/legal/ngo-affairs.png', $values['authority_2_logo']);

        $fields = config('site-settings.groups.legal_status.fields');
        $this->assertFalse($fields['enabled']['localized']);
        $this->assertTrue($fields['heading']['localized']);
        foreach ([1, 2, 3] as $position) {
            $this->assertTrue($fields["authority_{$position}_label"]['localized']);
            $this->assertFalse($fields["authority_{$position}_registration"]['localized']);
            $this->assertFalse($fields["authority_{$position}_logo"]['localized']);
            $this->assertTrue($fields["authority_{$position}_label"]['public']);
            $this->assertTrue($fields["authority_{$position}_registration"]['public']);
            $this->assertTrue($fields["authority_{$position}_logo"]['public']);
        }
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

    public function test_zakat_price_freshness_uses_the_server_calendar_and_fails_closed(): void
    {
        config()->set('app.timezone', 'Asia/Dhaka');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00:00', 'Asia/Dhaka'));

        try {
            config()->set('site-settings.groups.zakat_calculator.fields.nisab_price_updated_at.default', '2026-08-18');
            $this->assertTrue(
                app(SiteSettingService::class)->values('en', true)['zakat_calculator']['nisab_prices_current']
            );

            config()->set('site-settings.groups.zakat_calculator.fields.nisab_price_updated_at.default', '2026-08-17');
            $this->assertFalse(
                app(SiteSettingService::class)->values('en', true)['zakat_calculator']['nisab_prices_current']
            );

            config()->set('site-settings.groups.zakat_calculator.fields.nisab_price_updated_at.default', '2026-08-26');
            $this->assertFalse(
                app(SiteSettingService::class)->values('en', true)['zakat_calculator']['nisab_prices_current']
            );

            config()->set('site-settings.groups.zakat_calculator.fields.nisab_price_updated_at.default', '2026-02-30');
            $this->assertFalse(
                app(SiteSettingService::class)->values('en', true)['zakat_calculator']['nisab_prices_current']
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_fresh_zakat_date_cannot_mark_invalid_metal_prices_as_current(): void
    {
        config()->set('app.timezone', 'Asia/Dhaka');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00:00', 'Asia/Dhaka'));

        try {
            foreach ([
                'gold_price_per_gram' => '999',
                'silver_price_per_gram' => '100001',
            ] as $priceKey => $invalidValue) {
                SiteSetting::query()
                    ->where('group', 'zakat_calculator')
                    ->whereIn('key', [
                        'gold_price_per_gram',
                        'silver_price_per_gram',
                        'nisab_price_updated_at',
                    ])
                    ->forceDelete();
                SiteSetting::create([
                    'group' => 'zakat_calculator',
                    'key' => 'nisab_price_updated_at',
                    'locale' => '*',
                    'value' => '2026-08-25',
                    'type' => 'date',
                    'is_public' => true,
                ]);
                SiteSetting::create([
                    'group' => 'zakat_calculator',
                    'key' => $priceKey,
                    'locale' => '*',
                    'value' => $invalidValue,
                    'type' => 'float',
                    'is_public' => true,
                ]);

                $settings = app(SiteSettingService::class)->values('en', true)['zakat_calculator'];
                $this->assertFalse($settings['nisab_prices_current'], $priceKey);
                $this->assertGreaterThan(0, $settings['nisab_amount']);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }


    public function test_zakat_schema_separates_global_price_inputs_from_localized_public_guidance(): void
    {
        $fields = config('site-settings.groups.zakat_calculator.fields');

        foreach ([
            'nisab_default_basis', 'nisab_weight_standard',
            'gold_price_per_gram', 'silver_price_per_gram',
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
            'food_details', 'livelihood_details', 'education_details',
            'impact_view_details_label', 'impact_close_label', 'impact_dialog_eyebrow',
        ] as $key) {
            $this->assertTrue($fields[$key]['localized'], $key.' must be translatable.');
            $this->assertTrue($fields[$key]['public'], $key.' must be available to the calculator.');
        }

        $this->assertSame('date', $fields['nisab_price_updated_at']['type']);
        $this->assertSame('select', $fields['nisab_weight_standard']['type']);
        $this->assertSame(
            ['standard_87_48_612_36', 'standard_85_595'],
            array_keys($fields['nisab_weight_standard']['options'])
        );
        $this->assertSame('float', $fields['gold_price_per_gram']['type']);
        $this->assertSame('float', $fields['silver_price_per_gram']['type']);
        $this->assertSame('textarea', $fields['food_details']['type']);
        $this->assertSame('textarea', $fields['livelihood_details']['type']);
        $this->assertSame('textarea', $fields['education_details']['type']);
        $this->assertSame('text', $fields['impact_view_details_label']['type']);
        $this->assertSame('text', $fields['impact_close_label']['type']);
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
