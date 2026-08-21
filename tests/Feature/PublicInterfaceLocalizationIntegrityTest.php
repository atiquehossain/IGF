<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInterfaceLocalizationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessible_interface_defaults_are_public_and_localizable(): void
    {
        $values = app(SiteSettingService::class)->values('en', true);

        $this->assertSame('Featured highlights', $values['shared_blocks']['hero_carousel_label']);
        $this->assertSame('Show slide {current} of {total}', $values['shared_blocks']['hero_show_slide_label']);
        $this->assertSame('Embedded video', $values['shared_blocks']['video_embed_title']);
        $this->assertSame('View details for {name}', $values['shared_blocks']['team_view_details_for_label']);
        $this->assertSame('Open image: {name}', $values['shared_blocks']['gallery_open_image_label']);
        $this->assertSame('Close image', $values['shared_blocks']['gallery_close_image_label']);
        $this->assertSame('View all photos', $values['shared_blocks']['gallery_view_all_label']);
        $this->assertSame('Connect', $values['shared_blocks']['team_linkedin_label']);
        $this->assertSame('Contact details', $values['contact_page']['details_accessible_label']);
        $this->assertSame('Published stories', $values['content_archives']['category_listing_label']);
        $this->assertSame('Events and news', $values['content_archives']['events_listing_label']);
        $this->assertSame('Published projects', $values['content_archives']['project_listing_label']);
        $this->assertSame('Google', $values['member_area']['google_login_label']);
        $this->assertSame('000000', $values['member_area']['verification_code_placeholder']);
        $this->assertTrue($values['member_area']['registration_enabled']);
        $this->assertSame('Apply for member access', $values['member_area']['registration_title']);
        $this->assertSame('0', $values['zakat_calculator']['amount_placeholder']);

        SiteSetting::create([
            'group' => 'shared_blocks',
            'key' => 'hero_next_label',
            'locale' => 'bn',
            'value' => 'পরবর্তী গল্প',
            'type' => 'text',
            'is_public' => true,
        ]);

        $localized = app(SiteSettingService::class)->values('bn', true);
        $this->assertSame('পরবর্তী গল্প', $localized['shared_blocks']['hero_next_label']);
    }

    public function test_public_templates_bind_accessible_copy_and_custom_amount_visibility_to_settings(): void
    {
        $pageBlocks = file_get_contents(resource_path('js/Shared/PageBlocks.vue'));
        $contact = file_get_contents(resource_path('js/Pages/contactUs.vue'));
        $events = file_get_contents(resource_path('js/Pages/events.vue'));
        $category = file_get_contents(resource_path('js/Pages/category.vue'));
        $project = file_get_contents(resource_path('js/Pages/project.vue'));
        $login = file_get_contents(resource_path('js/Pages/auth/login.vue'));
        $verification = file_get_contents(resource_path('js/Pages/auth/login-2fa-verify.vue'));
        $zakat = file_get_contents(resource_path('js/Pages/zakat.vue'));

        $this->assertStringContainsString(':aria-label="shared.hero_controls_label"', $pageBlocks);
        $this->assertStringContainsString(':title="block.content.heading || shared.video_embed_title"', $pageBlocks);
        $this->assertStringContainsString("biography: 'team_biography_label'", $pageBlocks);
        $this->assertStringContainsString('v-if="showCampaignCustomAmount"', $pageBlocks);
        $this->assertStringContainsString(':aria-label="shared.gallery_close_image_label"', $pageBlocks);
        $this->assertStringContainsString('galleryOpenImageLabel(item, slideIndex * 5 + index)', $pageBlocks);
        $this->assertStringContainsString("blockLink(block, 'gallery')", $pageBlocks);
        $this->assertStringContainsString("return link.platform === 'linkedin' ? teamText('linkedin')", $pageBlocks);
        $this->assertStringNotContainsString('aria-label="Hero slides"', $pageBlocks);
        $this->assertStringNotContainsString('aria-label="Close image"', $pageBlocks);
        $this->assertStringNotContainsString('const teamCopy =', $pageBlocks);

        $this->assertStringContainsString(':aria-label="content.details_accessible_label"', $contact);
        $this->assertStringContainsString(':aria-label="settings.events_listing_label"', $events);
        $this->assertStringContainsString(':aria-label="settings.category_listing_label"', $category);
        $this->assertStringContainsString(':aria-label="settings.project_listing_label"', $project);
        $this->assertStringContainsString('content.google_login_label', $login);
        $this->assertStringContainsString(':placeholder="content.verification_code_placeholder"', $verification);
        $this->assertStringContainsString(':placeholder="copy.amount_placeholder"', $zakat);
    }
}
