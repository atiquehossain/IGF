<?php

namespace Tests\Feature;

use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use App\Services\DonationDestinationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DonationPageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_catalog_exposes_only_active_canonical_cause_links_without_checkout_secrets(): void
    {
        DonationType::query()->forceDelete();
        $first = DonationType::create([
            'name' => 'Where it is needed most',
            'description' => 'Flexible support.',
            'status' => 1,
            'display_order' => 10,
            'icon_key' => 'hands-heart',
        ]);
        $active = DonationType::create([
            'name' => 'Education',
            'description' => 'Learning support.',
            'status' => 1,
            'display_order' => 20,
            'icon_key' => 'graduation-cap',
        ]);
        DonationType::create(['name' => 'Retired campaign', 'status' => 0]);
        DonationType::create([
            'name' => 'Broken project',
            'description' => 'This linked project is not publicly available.',
            'status' => 1,
            'destination_type' => 'page',
            'destination_page_uuid' => '33333333-3333-4333-8333-333333333333',
        ]);
        SiteSetting::create([
            'group' => 'donation_page',
            'key' => 'show_cause_gallery',
            'locale' => '*',
            'value' => '0',
            'type' => 'boolean',
            'is_public' => true,
        ]);

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('donate')
                ->where('data.pageMode', 'catalog')
                ->where('data.catalogUrl', route('frontend.donate.index'))
                ->where('title', 'Donate securely')
                ->where('meta_tag.meta_title', 'Donate Securely | Ignite Global Foundation')
                ->where('meta_tag.meta_description', fn ($value) => str_contains($value, 'secure donation'))
                ->has('data.donationTypes', 2)
                ->where('data.donationTypes.0.uuid', $first->uuid)
                ->where('data.donationTypes.0.name', 'Where it is needed most')
                ->where('data.donationTypes.0.icon_key', 'hands-heart')
                ->where('data.donationTypes.0.url', route('frontend.donate.cause', ['cause' => $first->slug]))
                ->where('data.donationTypes.1.uuid', $active->uuid)
                ->where('data.donationTypes.1.name', 'Education')
                ->where('data.donationTypes.1.icon_key', 'graduation-cap')
                ->where('data.donationTypes.1.url', route('frontend.donate.cause', ['cause' => $active->slug]))
                ->where('data.selectedUUID', null)
                ->where('data.selectedCauseSlug', null)
                ->where('data.selectedDestination', null)
                ->where('data.paymentMethods', [])
                ->where('data.donationFrequencies', [])
                ->where('data.checkout_key', null)
                ->missing('siteSettings.donation_page.show_cause_gallery')
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

        $donationFields = config('site-settings.groups.donation_page.fields');
        foreach ([
            'show_cause_gallery',
            'show_intro_panel',
            'cause_card_selected_label',
            'cause_card_selected_cta_label',
            'aside_eyebrow',
            'aside_title',
            'aside_body',
        ] as $obsoleteControl) {
            $this->assertArrayNotHasKey($obsoleteControl, $donationFields, "{$obsoleteControl} must not appear as a no-op admin control.");
        }
    }

    public function test_dedicated_cause_page_exposes_one_locked_checkout_and_cause_specific_metadata(): void
    {
        DonationType::query()->forceDelete();
        DonationType::create([
            'name' => 'Another active cause',
            'description' => 'Another reviewed purpose.',
            'status' => 1,
        ]);
        $cause = DonationType::create([
            'name' => 'Education',
            'description' => 'Help children access learning materials and school support.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Education Fund',
            'status' => 1,
        ]);

        $response = $this->get(route('frontend.donate.cause', ['cause' => $cause->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('donate')
                ->where('data.pageMode', 'detail')
                ->where('data.catalogUrl', route('frontend.donate.index'))
                ->has('data.donationTypes', 1)
                ->where('data.donationTypes.0.uuid', $cause->uuid)
                ->where('data.donationTypes.0.slug', $cause->slug)
                ->where('data.donationTypes.0.url', route('frontend.donate.cause', ['cause' => $cause->slug]))
                ->where('data.selectedUUID', $cause->uuid)
                ->where('data.selectedCauseSlug', $cause->slug)
                ->where('data.selectedDestination.type', 'restricted_fund')
                ->where('data.selectedDestination.name', 'Education Fund')
                ->has('data.donationFrequencies', 4)
                ->where('data.donationFrequencies.0', ['key' => 'one_time', 'available' => true])
                ->where('data.donationFrequencies.1', ['key' => 'daily', 'available' => false])
                ->where('data.checkout_key', fn ($value) => is_string($value) && $value !== '')
                ->where('title', 'Donate to Education')
                ->where('meta_tag.meta_title', 'Donate to Education | Ignite Global Foundation')
                ->where('meta_tag.meta_description', fn ($value) => str_contains($value, 'learning materials'))
            );

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_make_a_donation_route_uses_the_admin_designated_direct_cause_without_a_catalog(): void
    {
        DonationType::query()->forceDelete();
        DonationType::create([
            'name' => 'Education catalog option',
            'description' => 'A separate active cause that must not appear on the direct page.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Education Fund',
            'status' => 1,
        ]);
        $direct = DonationType::create([
            'name' => 'Flexible community support',
            'description' => 'The administrator-managed direct donation destination.',
            'purpose_key' => 'direct',
            'destination_type' => 'unrestricted',
            'status' => 1,
        ]);
        SeoMetadata::create([
            'seoable_type' => DonationType::class,
            'seoable_id' => $direct->id,
            'locale' => 'en',
            'canonical_url' => route('frontend.donate.cause', ['cause' => $direct->slug]),
        ]);

        $response = $this->get(route('frontend.donate.direct'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('donate')
                ->where('data.pageMode', 'detail')
                ->has('data.donationTypes', 1)
                ->has('data.donationGroups', 0)
                ->where('data.donationTypes.0.uuid', $direct->uuid)
                ->where('data.donationTypes.0.url', route('frontend.donate.direct'))
                ->where('data.selectedUUID', $direct->uuid)
                ->where('data.selectedCauseSlug', $direct->slug)
                ->where('data.selectedDestination.type', 'unrestricted')
                ->where('data.selectedDestination.name', 'Where it is needed most')
                ->has('data.paymentMethods')
                ->where('data.checkout_key', fn ($value) => is_string($value) && $value !== '')
                ->where('title', 'Donate to Flexible community support')
                ->where('contentSeo.canonical_url', route('frontend.donate.direct'))
            );

        $this->assertSame('/make-a-donation', route('frontend.donate.direct', absolute: false));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));

        $this->get(route('frontend.donate.cause', ['cause' => $direct->slug]) . '?amount=2500')
            ->assertRedirect(route('frontend.donate.direct') . '?amount=2500')
            ->assertStatus(301);
        $this->get(route('frontend.donate.index') . '?cause=' . $direct->slug . '&amount=2500')
            ->assertRedirect(route('frontend.donate.direct') . '?amount=2500');
    }

    public function test_make_a_donation_route_fails_closed_without_an_active_operational_direct_cause(): void
    {
        DonationType::query()->forceDelete();
        DonationType::create([
            'name' => 'Inactive direct destination',
            'description' => 'This cause must not issue a checkout while unpublished.',
            'purpose_key' => 'direct',
            'destination_type' => 'unrestricted',
            'status' => 0,
        ]);
        DonationType::create([
            'name' => 'Unrelated active cause',
            'description' => 'The route must not silently fall back to another cause.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Other Fund',
            'status' => 1,
        ]);

        $this->get(route('frontend.donate.direct'))->assertStatus(503);
    }

    public function test_legacy_cause_queries_redirect_to_the_canonical_slug_and_preserve_checkout_options(): void
    {
        DonationType::query()->forceDelete();
        $cause = DonationType::create([
            'name' => 'Education',
            'description' => 'Reviewed education support.',
            'status' => 1,
        ]);
        $projectUuid = '12345678-1234-4234-8234-123456789012';
        $expected = route('frontend.donate.cause', ['cause' => $cause->slug])
            . '?amount=1000&project=' . $projectUuid;

        $this->get('/donate?' . http_build_query([
            'cause' => $cause->slug,
            'amount' => 1000,
            'project' => $projectUuid,
        ]))->assertRedirect($expected);
        $this->get('/donate?' . http_build_query([
            'cause' => $cause->uuid,
            'amount' => 1000,
            'project' => $projectUuid,
        ]))->assertRedirect($expected);

        $this->get('/donate?cause=unavailable-cause')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.pageMode', 'catalog')
                ->where('data.selectedUUID', null)
            );
    }

    public function test_static_checkout_key_route_wins_and_unknown_cause_pages_fail_closed(): void
    {
        $this->get(route('frontend.donate.checkout-key'))
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJson(fn ($json) => $json
                ->whereType('checkout_key', 'string')
                ->etc()
            );

        $this->get('/donate/not-a-real-cause')->assertNotFound();
    }

    public function test_admin_donation_builder_settings_control_the_public_checkout(): void
    {
        DonationType::query()->forceDelete();
        $cause = DonationType::create([
            'name' => 'Education',
            'description' => 'Reviewed education support.',
            'status' => 1,
        ]);
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

        $this->get(route('frontend.donate.cause', ['cause' => $cause->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.pageMode', 'detail')
                ->where('siteSettings.donation_page.checkout_layout', 'split')
                ->where('siteSettings.donation_page.card_style', 'outlined')
                ->where('siteSettings.donation_page.amount_button_count', '3')
                ->where('siteSettings.donation_page.amount_1', 750)
                ->where('siteSettings.donation_page.show_help_card', false)
                ->where('siteSettings.donation_page.gateway_heading', 'Payments handled securely')
            );
    }

    public function test_catalog_resolves_seventeen_legacy_media_images_with_one_batched_lookup(): void
    {
        DonationType::query()->forceDelete();
        MediaAsset::query()->forceDelete();
        $expectedUrls = [];

        foreach (range(1, 17) as $index) {
            $asset = MediaAsset::create([
                'uuid' => (string) Str::uuid(),
                'disk' => 'public',
                'path' => "media/catalog-cause-{$index}.png",
                'original_name' => "catalog-cause-{$index}.png",
                'mime_type' => 'image/png',
                'extension' => 'png',
                'bytes' => 100 + $index,
            ]);
            DonationType::create([
                'name' => "Catalog cause {$index}",
                'description' => "Reviewed catalog cause {$index}.",
                'destination_type' => 'restricted_fund',
                'destination_name' => "Catalog Fund {$index}",
                'image_media_uuid' => null,
                'image' => 'https://former-host.example/storage/' . $asset->path,
                'display_order' => $index * 10,
                'status' => 1,
            ]);
            $expectedUrls[] = $asset->url;
        }

        $mediaQueries = [];
        DB::listen(function ($query) use (&$mediaQueries): void {
            if (str_contains(strtolower($query->sql), 'media_assets')) {
                $mediaQueries[] = $query->sql;
            }
        });

        $destinations = app(DonationDestinationService::class);
        $causes = $destinations->activeCauses('en');
        $options = $destinations->publicOptions($causes, 'en');

        $this->assertCount(17, $causes);
        $this->assertCount(17, $options);
        $this->assertSame($expectedUrls, $options->pluck('image')->all());
        $this->assertCount(
            1,
            $mediaQueries,
            'Legacy catalog images must resolve through one targeted media query, not one full-table query per cause.'
        );
        $this->assertStringContainsString('where', strtolower($mediaQueries[0]));
    }

    public function test_empty_catalog_preserves_a_safe_mode_and_localized_unavailable_message(): void
    {
        DonationType::query()->forceDelete();

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('donate')
                ->where('data.pageMode', 'catalog')
                ->has('data.donationTypes', 0)
                ->where('data.selectedUUID', null)
                ->where('data.checkout_key', null)
                ->where('siteSettings.donation_page.causes_unavailable_message', fn ($message) => is_string($message) && $message !== '')
            );
    }

    public function test_admin_route_seo_overrides_donation_defaults(): void
    {
        SeoMetadata::create([
            'route_name' => 'frontend.donate.index',
            'route_path' => '/donate',
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
                ->where('data.pageMode', 'catalog')
                ->where('routeSeo.meta_title', 'Owner-controlled donation title')
                ->where('routeSeo.meta_description', 'Owner-controlled donation description.')
                ->where('routeSeo.robots', 'noindex,nofollow')
            );
    }
}
