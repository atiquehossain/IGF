<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\SiteSettingVersionService;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteCustomizerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sslcommerz.store_id', 'customizer-test-store');
        config()->set('sslcommerz.store_password', 'customizer-test-password');
        config()->set('sslcommerz.payment_methods.bkash.enabled', true);
        config()->set('sslcommerz.payment_methods.bkash.gateway_filter', 'bkash');
        config()->set('sslcommerz.payment_methods.nagad.enabled', false);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', null);
        config()->set('sslcommerz.payment_methods.card.enabled', true);
        config()->set('sslcommerz.payment_methods.card.gateway_filter', 'visacard,amexcard');
    }

    public function test_non_technical_editor_gets_one_guided_customizer_with_media_selection_and_preview(): void
    {
        $admin = $this->makePageEditor();
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'customizer/ignite-logo.png',
            'original_name' => 'Ignite logo.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'bytes' => 2048,
            'alt_text' => 'Ignite logo',
            'locale' => 'en',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Website Customizer')
            ->assertSee('New here? Follow these five simple steps')
            ->assertSee('Content Hub')
            ->assertSee('Design &amp; layout', false)
            ->assertSee('Resize headings, images and cards')
            ->assertSee('Donation experience')
            ->assertSee('Layout, amounts, wording and help')
            ->assertSee('Navigation')
            ->assertSee('Media Library')
            ->assertSee('Search &amp; Sharing', false)
            ->assertSee('Search previews, URLs and sharing')
            ->assertSee('Find a setting')
            ->assertSee('Website preview')
            ->assertSee('Page to preview')
            ->assertSee('Member sign in')
            ->assertSee('Desktop preview')
            ->assertSee('Mobile preview')
            ->assertSee('Design presets preview immediately')
            ->assertSee('Website name &amp; logo size', false)
            ->assertSee('Heading size')
            ->assertSee('Card image size')
            ->assertSee('Cards per row')
            ->assertSee('Larger')
            ->assertSee('No-code donation builder')
            ->assertSee('Manage donation causes')
            ->assertSee('Checkout layout')
            ->assertSee('Suggested amount 5 (BDT)')
            ->assertSee('Choose a payment method')
            ->assertSee('Offer bKash')
            ->assertSee('Offer Nagad')
            ->assertSee('Offer card payment')
            ->assertSee('Payment provider status')
            ->assertSee('Read-only checks from the protected payment configuration')
            ->assertSee('The payment account and this channel are configured.')
            ->assertSee('This channel is disabled in the protected payment configuration.')
            ->assertSee('Not ready')
            ->assertSee('min="1" max="8333"', false)
            ->assertSee('min="10" max="500000"', false)
            ->assertSee('payment verification stay protected')
            ->assertSee('Choose image')
            ->assertSee('FAQ questions and answers')
            ->assertSee('Add FAQ')
            ->assertSee('Show publicly')
            ->assertSee('data-faq-move="up"', false)
            ->assertSee('data-faq-remove', false)
            ->assertSee('Save website changes')
            ->assertSee('Reset to default')
            ->assertSee($asset->url, false)
            ->assertDontSee('Header logo URL')
            ->assertDontSee('bKash option label')
            ->assertDontSee('Nagad option label')
            ->assertDontSee('Card option label')
            ->assertDontSee('Accepted-card label')
            ->assertDontSee('customizer-test-store')
            ->assertDontSee('customizer-test-password')
            ->assertDontSee('visacard,amexcard')
            ->assertDontSee('Save The Children')
            ->assertDontSee('savethechildren.org');

        $donationFields = config('site-settings.groups.donation_page.fields');
        $this->assertArrayNotHasKey('bkash_label', $donationFields);
        $this->assertArrayNotHasKey('nagad_label', $donationFields);
        $this->assertArrayNotHasKey('card_label', $donationFields);
        $this->assertArrayNotHasKey('card_networks_label', $donationFields);
    }

    public function test_admin_can_publish_dynamic_office_contact_details(): void
    {
        $admin = $this->makePageEditor();

        $this->actingAs($admin, 'admin')
            ->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Office contact details')
            ->assertSee('Cell number')
            ->assertSee('Office address')
            ->assertDontSee('Footer contact heading');

        $settings = $this->defaultSettingsPayload();
        $settings['contact'] = array_replace($settings['contact'], [
            'email' => 'office-contact@example.test',
            'phone_primary' => '+8801712345678',
            'phone_secondary' => '',
            'address' => 'Admin-managed office address',
            'footer_address_label' => 'Office',
            'footer_phone_label' => 'Mobile',
            'footer_secondary_phone_label' => 'Alternate',
            'footer_email_label' => 'Inbox',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        foreach ($settings['contact'] as $key => $value) {
            $locale = config("site-settings.groups.contact.fields.{$key}.localized") ? 'en' : '*';

            $this->assertDatabaseHas('site_settings', [
                'group' => 'contact',
                'key' => $key,
                'locale' => $locale,
                'value' => $value,
                'is_public' => true,
                'updated_by' => $admin->id,
            ]);
        }

        $publicContact = app(SiteSettingService::class)->values('en', true)['contact'];

        foreach ($settings['contact'] as $key => $value) {
            $this->assertSame($value, $publicContact[$key]);
        }
    }


    public function test_contact_page_faq_editor_saves_an_ordered_dynamic_list_safely(): void
    {
        $admin = $this->makePageEditor();
        $settings = $this->defaultSettingsPayload();
        $settings['contact_page']['faqs'] = [
            ['question' => 'First <b>question</b>?', 'answer' => 'First <em>answer</em>.', 'is_active' => true],
            ['question' => 'Second question?', 'answer' => 'Second answer.', 'is_active' => false],
            ['question' => 'Third question?', 'answer' => 'Third answer.', 'is_active' => true],
            ['question' => 'Fourth question?', 'answer' => 'Fourth answer.', 'is_active' => true],
            ['question' => 'Fifth question?', 'answer' => 'Fifth answer.', 'is_active' => true],
            ['question' => 'Sixth question?', 'answer' => 'Sixth answer.', 'is_active' => true],
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $stored = SiteSetting::query()
            ->where('group', 'contact_page')
            ->where('key', 'faqs')
            ->where('locale', 'en')
            ->firstOrFail();

        $this->assertSame('json', $stored->type);
        $this->assertCount(6, $stored->typed_value);
        $this->assertSame('First question?', $stored->typed_value[0]['question']);
        $this->assertSame('First answer.', $stored->typed_value[0]['answer']);
        $this->assertFalse($stored->typed_value[1]['is_active']);

        $public = app(SiteSettingService::class)->values('en', true)['contact_page'];
        $this->assertCount(6, $public['faqs']);
        $this->assertSame('Sixth question?', $public['faqs'][5]['question']);
        $this->assertSame('', $public['faq_2_question']);
    }

    public function test_footer_legal_status_fields_are_editable_sanitized_and_published(): void
    {
        $admin = $this->makePageEditor();

        $this->actingAs($admin, 'admin')
            ->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Footer legal status')
            ->assertSee('Replace Donor Support with legal status')
            ->assertSee('NGO Affairs Bureau Registration No.')
            ->assertSee('3461')
            ->assertSee('Joint Stock &amp; Firms Registration No.', false)
            ->assertSee('S-13907/2022')
            ->assertDontSee('Microcredit Regulatory Authority Reg. No.')
            ->assertDontSee('00176-00059-00018')
            ->assertSee('Authority 1 logo')
            ->assertSee('Choose image');

        $settings = $this->defaultSettingsPayload();
        $settings['legal_status'] = [
            'enabled' => true,
            'heading' => 'Official <strong>registrations</strong>',
            'authority_1_label' => 'First <em>authority</em>',
            'authority_1_registration' => 'REG-001',
            'authority_1_logo' => '/storage/media/legal/first-authority.png',
            'authority_2_label' => 'Second authority',
            'authority_2_registration' => 'REG-002',
            'authority_2_logo' => '',
            'authority_3_label' => 'Third authority',
            'authority_3_registration' => 'REG-003',
            'authority_3_logo' => '/storage/media/legal/third-authority.png',
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $this->assertDatabaseHas('site_settings', [
            'group' => 'legal_status',
            'key' => 'enabled',
            'locale' => '*',
            'value' => '1',
            'type' => 'boolean',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'legal_status',
            'key' => 'heading',
            'locale' => 'en',
            'value' => 'Official registrations',
            'type' => 'text',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'legal_status',
            'key' => 'authority_1_registration',
            'locale' => '*',
            'value' => 'REG-001',
            'type' => 'text',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'legal_status',
            'key' => 'authority_1_logo',
            'locale' => '*',
            'value' => '/storage/media/legal/first-authority.png',
            'type' => 'text',
            'is_public' => true,
        ]);

        $public = app(SiteSettingService::class)->values('en', true)['legal_status'];
        $this->assertTrue($public['enabled']);
        $this->assertSame('Official registrations', $public['heading']);
        $this->assertSame('First authority', $public['authority_1_label']);
        $this->assertSame('REG-001', $public['authority_1_registration']);
        $this->assertSame('/storage/media/legal/first-authority.png', $public['authority_1_logo']);
        $this->assertSame('REG-002', $public['authority_2_registration']);
        $this->assertSame('REG-003', $public['authority_3_registration']);
        $this->assertSame('/storage/media/legal/third-authority.png', $public['authority_3_logo']);
    }

    public function test_view_only_customizer_hides_mutations_and_unauthorized_admin_shortcuts(): void
    {
        $admin = $this->makePageEditor(['site.settings.index'], []);

        $response = $this->actingAs($admin, 'admin')->get(route('site.settings.index'));
        $response
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee('cannot save or reset website settings')
            ->assertDontSee('Save website changes')
            ->assertDontSee('Reset to default')
            ->assertDontSee('data-media-open="', false);

        $customizer = $this->customizerMarkup($response->getContent());
        foreach ([
            route('page.index'),
            route('page.menu.index'),
            route('media.index'),
            route('reusable-blocks.index'),
            route('seo.index'),
            route('content.trash.index'),
            route('donationType.index'),
        ] as $forbiddenUrl) {
            $this->assertStringNotContainsString('href="'.$forbiddenUrl.'"', $customizer);
        }
    }

    public function test_update_and_reset_controls_follow_their_independent_capabilities(): void
    {
        $updateAdmin = $this->makePageEditor(['site.settings.index'], ['site.settings.edit']);

        $this->actingAs($updateAdmin, 'admin')->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Save website changes')
            ->assertDontSee('Reset to default');

        $resetAdmin = $this->makePageEditor(['site.settings.index'], ['site.settings.destroy']);
        $this->actingAs($resetAdmin, 'admin')->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Limited settings access')
            ->assertSee('Reset to default')
            ->assertDontSee('Save website changes');
    }

    public function test_selected_media_url_is_saved_through_the_safe_settings_contract(): void
    {
        $admin = $this->makePageEditor();
        $settings = [];
        foreach (config('site-settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'];
            }
        }
        $settings['branding']['logo'] = '/storage/media/customizer/new-logo.png';
        $settings['design']['heading_size'] = 'large';
        $settings['design']['image_size'] = 'large';
        $settings['design']['card_columns'] = '4';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $this->assertSame('/storage/media/customizer/new-logo.png', SiteSetting::query()
            ->where('group', 'branding')
            ->where('key', 'logo')
            ->where('locale', '*')
            ->value('value'));
        $this->assertSame('large', SiteSetting::query()
            ->where('group', 'design')
            ->where('key', 'heading_size')
            ->where('locale', '*')
            ->value('value'));
        $this->assertSame('large', SiteSetting::query()
            ->where('group', 'design')
            ->where('key', 'image_size')
            ->where('locale', '*')
            ->value('value'));
        $this->assertSame('4', SiteSetting::query()
            ->where('group', 'design')
            ->where('key', 'card_columns')
            ->where('locale', '*')
            ->value('value'));
    }

    public function test_design_presets_reject_unsafe_or_unknown_values(): void
    {
        $admin = $this->makePageEditor();
        $settings = [];
        foreach (config('site-settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'];
            }
        }
        $settings['design']['heading_size'] = '144px;position:fixed';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.design.heading_size');

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'design',
            'key' => 'heading_size',
            'value' => '144px;position:fixed',
        ]);
    }

    public function test_donation_builder_cannot_disable_every_configured_payment_method(): void
    {
        $admin = $this->makePageEditor();
        $settings = [];
        foreach (config('site-settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'];
            }
        }

        $settings['donation_page']['enable_bkash'] = false;
        $settings['donation_page']['enable_card'] = false;
        $settings['donation_page']['enable_nagad'] = true;
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', null);

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.donation_page.enable_bkash');

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'donation_page',
            'key' => 'enable_bkash',
            'value' => '0',
        ]);
    }

    public function test_donation_builder_cannot_enable_only_gateway_methods_that_are_operationally_unavailable(): void
    {
        $admin = $this->makePageEditor();
        $settings = [];
        foreach (config('site-settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'];
            }
        }

        $settings['donation_page']['enable_bkash'] = true;
        $settings['donation_page']['enable_card'] = true;
        $settings['donation_page']['enable_nagad'] = true;
        config()->set('sslcommerz.payment_methods.bkash.enabled', false);
        config()->set('sslcommerz.payment_methods.card.enabled', false);
        config()->set('sslcommerz.payment_methods.nagad.enabled', true);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', 'nagad;unsafe');

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.donation_page.enable_bkash');

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'donation_page',
            'key' => 'enable_bkash',
            'value' => '1',
        ]);
    }

    public function test_payment_provider_status_and_save_validation_fail_closed_without_credentials(): void
    {
        $admin = $this->makePageEditor();
        config()->set('sslcommerz.store_id', '');
        config()->set('sslcommerz.store_password', '   ');

        $this->actingAs($admin, 'admin')
            ->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('SSLCommerz account credentials have not been configured by the payment administrator.')
            ->assertSee('Not ready');

        $settings = $this->defaultSettingsPayload();

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.donation_page.enable_bkash');

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'donation_page',
            'key' => 'enable_bkash',
        ]);
    }

    public function test_validation_failure_returns_to_customizer_even_when_public_preview_was_previous_page(): void
    {
        $admin = $this->makePageEditor();
        config()->set('sslcommerz.store_id', '');
        config()->set('sslcommerz.store_password', '');

        $response = $this->actingAs($admin, 'admin')
            ->from(route('frontend.home'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($this->defaultSettingsPayload()));

        $response
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.donation_page.enable_bkash');

        $this->get(route('site.settings.index'))
            ->assertOk()
            ->assertSee('Website changes were not saved.')
            ->assertSee('Keep at least one payment method enabled that is marked Ready in Payment provider status.');

        $this->assertStringContainsString(
            '$errors->has("settings.$groupKey.*")',
            file_get_contents(resource_path('views/admin/site-settings/index.blade.php'))
        );
    }

    public function test_zakat_settings_offer_an_approved_methodology_with_guided_prices_and_no_editable_rate_or_fixed_threshold(): void
    {
        $admin = $this->makePageEditor();

        $response = $this->actingAs($admin, 'admin')->get(route('site.settings.index'));

        $response
            ->assertOk()
            ->assertSee('Keep the Nisab trustworthy')
            ->assertSee('Approved Nisab weight standard')
            ->assertSee('87.48g gold / 612.36g silver')
            ->assertSee('85g gold / 595g silver (alternate common standard)')
            ->assertSee('Gold reference price per gram (BDT)')
            ->assertSee('current per-gram reference price approved by your Shariah adviser')
            ->assertSee('Silver reference price per gram (BDT)')
            ->assertSee('Prices checked on')
            ->assertSee('Price source name')
            ->assertSee('Price source web address')
            ->assertSee('Food card full details')
            ->assertSee('Livelihood card full details')
            ->assertSee('Education card full details')
            ->assertSee('Impact-card details button')
            ->assertSee('Impact-details close button')
            ->assertSee('type="date"', false)
            ->assertSee('max="'.now()->toDateString().'"', false)
            ->assertSee('min="1000" max="1000000" step="0.01"', false)
            ->assertSee('min="10" max="100000" step="0.01"', false)
            ->assertDontSee('Nisab amount (BDT)')
            ->assertDontSee('setting-zakat_calculator-nisab_amount', false)
            ->assertDontSee('Zakat rate (%)');

        $zakatFields = config('site-settings.groups.zakat_calculator.fields');
        $this->assertArrayHasKey('nisab_weight_standard', $zakatFields);
        $this->assertSame('select', $zakatFields['nisab_weight_standard']['type']);
        $this->assertSame(
            ['standard_87_48_612_36', 'standard_85_595'],
            array_keys($zakatFields['nisab_weight_standard']['options'])
        );
        $this->assertArrayNotHasKey('nisab_amount', $zakatFields);
        $this->assertArrayNotHasKey('investment_property_label', $zakatFields);
        $this->assertArrayNotHasKey('debts_label', $zakatFields);
        $this->assertArrayNotHasKey('nisab_label', $zakatFields);
        $this->assertArrayNotHasKey('zakat_rate', $zakatFields);
        $this->assertArrayNotHasKey('rate', $zakatFields);
    }

    public function test_zakat_impact_full_details_are_localized_admin_content_and_publicly_available(): void
    {
        $admin = $this->makePageEditor();
        $settings = $this->defaultSettingsPayload();
        $settings['zakat_calculator']['food_details'] = 'Admin food details for eligible families.';
        $settings['zakat_calculator']['livelihood_details'] = 'Admin livelihood details for eligible families.';
        $settings['zakat_calculator']['education_details'] = 'Admin education details for eligible families.';
        $settings['zakat_calculator']['impact_view_details_label'] = 'Read full details';
        $settings['zakat_calculator']['impact_close_label'] = 'Close impact details';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $this->assertDatabaseHas('site_settings', [
            'group' => 'zakat_calculator',
            'key' => 'food_details',
            'locale' => 'en',
            'value' => 'Admin food details for eligible families.',
            'type' => 'text',
            'is_public' => true,
        ]);

        $public = app(SiteSettingService::class)->values('en', true)['zakat_calculator'];
        $this->assertSame('Admin food details for eligible families.', $public['food_details']);
        $this->assertSame('Admin livelihood details for eligible families.', $public['livelihood_details']);
        $this->assertSame('Admin education details for eligible families.', $public['education_details']);
        $this->assertSame('Read full details', $public['impact_view_details_label']);
        $this->assertSame('Close impact details', $public['impact_close_label']);
    }


    public function test_zakat_prices_source_and_date_save_safely_and_publish_a_calculated_legacy_threshold(): void
    {
        $admin = $this->makePageEditor();
        $settings = $this->defaultSettingsPayload();
        $settings['zakat_calculator']['nisab_default_basis'] = 'gold';
        $settings['zakat_calculator']['nisab_weight_standard'] = 'standard_85_595';
        $settings['zakat_calculator']['gold_price_per_gram'] = 20000;
        $settings['zakat_calculator']['silver_price_per_gram'] = 400;
        $settings['zakat_calculator']['nisab_price_updated_at'] = now()->subDay()->toDateString();
        $settings['zakat_calculator']['nisab_source_label'] = 'Verified market bulletin';
        $settings['zakat_calculator']['nisab_source_url'] = 'https://example.test/metals';

        SiteSetting::create([
            'group' => 'zakat_calculator',
            'key' => 'nisab_amount',
            'locale' => '*',
            'value' => '74000',
            'type' => 'integer',
            'is_public' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $this->assertDatabaseHas('site_settings', [
            'group' => 'zakat_calculator',
            'key' => 'nisab_weight_standard',
            'locale' => '*',
            'value' => 'standard_85_595',
            'type' => 'text',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'group' => 'zakat_calculator',
            'key' => 'gold_price_per_gram',
            'locale' => '*',
            'value' => '20000',
            'type' => 'float',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'zakat_calculator',
            'key' => 'nisab_price_updated_at',
            'locale' => '*',
            'value' => now()->subDay()->toDateString(),
            'type' => 'text',
        ]);

        $public = app(SiteSettingService::class)->values('en', true)['zakat_calculator'];
        $this->assertSame(1700000, $public['nisab_amount']);
        $this->assertSame('gold', $public['nisab_default_basis']);
        $this->assertSame('standard_85_595', $public['nisab_weight_standard']);
        $this->assertTrue($public['nisab_prices_current']);
        $this->assertSame('https://example.test/metals', $public['nisab_source_url']);
        $this->assertNotSame(74000, $public['nisab_amount']);
    }

    public function test_zakat_price_inputs_reject_invalid_bounds_future_dates_and_unsafe_sources(): void
    {
        $admin = $this->makePageEditor();
        $settings = $this->defaultSettingsPayload();
        $settings['zakat_calculator']['nisab_weight_standard'] = 'unapproved_custom_formula';
        $settings['zakat_calculator']['gold_price_per_gram'] = 999;
        $settings['zakat_calculator']['silver_price_per_gram'] = 100001;
        $settings['zakat_calculator']['nisab_price_updated_at'] = now()->addDay()->toDateString();
        $settings['zakat_calculator']['nisab_source_url'] = 'javascript:alert(1)';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors([
                'settings.zakat_calculator.nisab_weight_standard',
                'settings.zakat_calculator.gold_price_per_gram',
                'settings.zakat_calculator.silver_price_per_gram',
                'settings.zakat_calculator.nisab_price_updated_at',
                'settings.zakat_calculator.nisab_source_url',
            ]);

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'zakat_calculator',
            'key' => 'gold_price_per_gram',
        ]);

        $settings = $this->defaultSettingsPayload();
        $settings['zakat_calculator']['nisab_price_updated_at'] = '2026-02-30';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), $this->settingsUpdatePayload($settings))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.zakat_calculator.nisab_price_updated_at');
    }

    public function test_stale_editor_cannot_overwrite_newer_global_settings(): void
    {
        $admin = $this->makePageEditor();
        $initialVersion = app(SiteSettingVersionService::class)->current();

        $firstSettings = $this->defaultSettingsPayload();
        $firstSettings['branding']['logo'] = '/storage/media/first-editor-logo.png';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => $initialVersion,
                'settings' => $firstSettings,
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $this->assertNotSame($initialVersion, app(SiteSettingVersionService::class)->current());

        $staleSettings = $this->defaultSettingsPayload();
        $staleSettings['branding']['logo'] = '/storage/media/stale-editor-logo.png';

        $this->from(route('site.settings.index'))
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => $initialVersion,
                'settings' => $staleSettings,
            ])
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('global_settings_version');

        $this->assertDatabaseHas('site_settings', [
            'group' => 'branding',
            'key' => 'logo',
            'locale' => '*',
            'value' => '/storage/media/first-editor-logo.png',
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'group' => 'branding',
            'key' => 'logo',
            'value' => '/storage/media/stale-editor-logo.png',
        ]);
    }

    private function settingsUpdatePayload(array $settings, string $locale = 'en'): array
    {
        return [
            'locale' => $locale,
            'global_settings_version' => app(SiteSettingVersionService::class)->current(),
            'settings' => $settings,
        ];
    }
    private function defaultSettingsPayload(): array
    {

        $settings = [];
        foreach (config('site-settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'];
            }
        }

        return $settings;
    }

    private function makePageEditor(?array $menuLinks = null, ?array $actionLinks = null): Admin
    {
        $suffix = Str::lower(Str::random(8));
        $menuLinks ??= [
            'site.settings.index',
            'page.index',
            'page.menu.index',
            'media.index',
            'reusable-blocks.index',
            'content.trash.index',
            'donationType.index',
        ];
        $actionLinks ??= ['site.settings.edit', 'site.settings.destroy', 'seo.metadata.edit'];
        $menus = AuthMenu::query()->whereIn('link', $menuLinks)->get();
        $actions = MenuAction::query()->whereIn('link', $actionLinks)->get();
        $this->assertCount(count(array_unique($menuLinks)), $menus);
        $this->assertCount(count(array_unique($actionLinks)), $actions);
        $role = Role::create([
            'name' => 'Website editor',
            'permission' => $menus->pluck('id')->implode(','),
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Customizer Editor',
            'username' => 'customizer-'.$suffix,
            'email' => 'customizer-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function customizerMarkup(string $html): string
    {
        $this->assertSame(1, preg_match('/<main class="igf-customizer">.*?<\/main>/s', $html, $matches));

        return $matches[0];
    }
}
