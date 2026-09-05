<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoCodePublicTemplateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_template_settings_are_shared_with_donation_gallery_and_system_pages(): void
    {
        $this->putPublicSetting('donation_page', 'title', 'A donation heading changed without code');
        $this->putPublicSetting('gallery_page', 'title', 'A gallery heading changed without code');
        $this->putPublicSetting('system_pages', 'not_found_title', 'A helpful custom missing-page message');

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertSee('A donation heading changed without code');
        $this->get(route('frontend.gallery'))
            ->assertOk()
            ->assertSee('A gallery heading changed without code');
        $this->get('/this-public-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('A helpful custom missing-page message');
    }

    public function test_public_vue_templates_render_customizer_values_instead_of_fixed_headings(): void
    {
        $bindings = [
            'donate.vue' => ['settings.title', 'settings.value.submit_with_amount_label', 'settings.value.submit_label', 'settings.privacy_note', 'settings.amount_legend', 'settings.cause_field_label', 'settings.cause_content_accessible_label', 'settings.value.checkout_layout', 'settings.value[`amount_${index}`]', 'settings.gateway_note', 'settings.value.refund_link_url'],
            'contactUs.vue' => ['content.details_title', 'content.value.name_field_label', 'content.success_message'],
            'sponsor_child.vue' => ['settings.hero_cta_label', 'settings.value.children_field_label', 'settings.sending_label'],
            'volunteer-registration.vue' => ['settings.process_eyebrow', 'settings.value.cause_field_label', 'settings.causes_unavailable'],
            'search.vue' => ['settings.title', 'settings.placeholder', 'settings.empty_title'],
            'gallery.vue' => ['settings.title', 'settings.album_label', 'settings.empty_title'],
            'annual-report.vue' => ['settings.title', 'settings.library_title', 'settings.download_label'],
            'category.vue' => ['settings.category_eyebrow', 'settings.category_empty_title'],
            'project.vue' => ['settings.project_eyebrow', 'settings.project_empty_title'],
            'events.vue' => ['settings.events_eyebrow', 'settings.events_empty_title'],
            'event.vue' => ['archiveSettings.event_back_label', 'archiveSettings.event_footer_label'],
            'zakat.vue' => ['copy.assets_legend', 'copy.total_assets_label', 'copy.disclaimer', 'copy.nisab_basis_legend', 'copy.lunar_year_yes_label'],
            'auth/login.vue' => ['content.story_title', 'content.phone_label', 'content.security_note'],
            'errors-404.vue' => ['settings.not_found_title', 'settings.home_label'],
            'payment_success.vue' => ['settings.value.success_eyebrow', 'settings.value.success_title', 'settings.value.success_note', 'settings.another_donation_label'],
            'payment_fail.vue' => ['settings.failure_title', 'settings.try_again_label'],
        ];

        foreach ($bindings as $file => $needles) {
            $source = file_get_contents(resource_path('js/Pages/' . $file));
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $file . ' must use ' . $needle);
            }
        }
    }

    public function test_global_theme_tokens_override_legacy_component_color_variables(): void
    {
        $source = file_get_contents(resource_path('js/layouts/App.vue'));

        $this->assertStringContainsString("'--igf-primary'", $source);
        $this->assertStringContainsString("'--igf-accent'", $source);
        $this->assertStringContainsString("'--igf-ink'", $source);
        $this->assertStringContainsString("'--igf-surface'", $source);
        $this->assertStringContainsString('--orange: var(--igf-primary) !important', $source);
        $this->assertStringContainsString('--brown: var(--igf-accent) !important', $source);
        $this->assertStringContainsString('--ink: var(--igf-ink) !important', $source);
        $this->assertStringContainsString('--surface: var(--igf-surface) !important', $source);
    }

    public function test_safe_design_presets_are_mapped_to_responsive_public_tokens(): void
    {
        $layout = file_get_contents(resource_path('js/layouts/App.vue'));
        $header = file_get_contents(resource_path('js/layouts/AppHeader.vue'));
        $blocks = file_get_contents(resource_path('js/Shared/PageBlocks.vue'));
        $navigation = file_get_contents(resource_path('js/layouts/AppNav.vue'));
        $footer = file_get_contents(resource_path('js/layouts/AppFooter.vue'));
        $contentCard = file_get_contents(resource_path('js/Shared/category-item-card.vue'));
        $customizer = file_get_contents(resource_path('views/admin/site-settings/index.blade.php'));

        foreach ([
            '--igf-content-width', '--igf-heading-1', '--igf-heading-2', '--igf-body-size',
            '--igf-section-block', '--igf-hero-height', '--igf-card-media-height', '--igf-image-aspect', '--igf-testimonial-text-size',
            '--igf-card-columns', '--igf-card-radius', '--igf-card-shadow', '--igf-button-radius',
            '--igf-brand-font-size', '--igf-font-body', '--igf-font-heading', '--igf-radius-lg',
            '--igf-shadow-header', '--igf-shadow-floating', '--igf-header-position', '--igf-header-nav-height',
            '--igf-footer-bg', '--igf-footer-body-columns',
        ] as $token) {
            $this->assertStringContainsString($token, $layout, $token . ' must be mapped by the public layout');
        }

        $this->assertStringContainsString('var(--igf-section-block', $blocks);
        $this->assertStringContainsString('var(--igf-heading-1', $blocks);
        $this->assertStringContainsString('var(--igf-card-media-height', $blocks);
        $this->assertStringContainsString('repeat(var(--igf-card-columns,3)', $blocks);
        $this->assertStringContainsString('var(--igf-button-radius', $blocks);
        $this->assertStringContainsString('var(--igf-testimonial-text-size', $blocks);
        $this->assertStringContainsString('var(--igf-brand-font-size', $navigation);
        $this->assertStringContainsString('var(--igf-font-body', $header);
        $this->assertStringContainsString('var(--igf-header-nav-bg', $navigation);
        $this->assertStringContainsString('var(--igf-shadow-floating', $navigation);
        $this->assertStringContainsString('footer.about', $footer);
        $this->assertStringContainsString('footer.trustBadge', $footer);
        $this->assertStringContainsString('var(--igf-footer-bg', $footer);
        $this->assertStringContainsString('var(--igf-footer-body-columns', $footer);
        $this->assertStringContainsString('headerPreviewMaps', $customizer);
        $this->assertStringContainsString('footerPreviewMaps', $customizer);
        $this->assertStringContainsString('var(--igf-card-media-height', $contentCard);
    }

    public function test_public_shell_and_donation_templates_use_allowlisted_localized_schema_values(): void
    {
        $groups = config('site-settings.groups');

        $this->assertSame(['editorial', 'modern', 'classic'], array_keys($groups['design']['fields']['font_pairing']['options']));
        $this->assertSame(['square', 'soft', 'rounded'], array_keys($groups['design']['fields']['corner_radius']['options']));
        $this->assertSame(['flat', 'subtle', 'strong'], array_keys($groups['design']['fields']['shadow_density']['options']));
        $this->assertSame(['classic', 'minimal', 'soft'], array_keys($groups['header']['fields']['presentation']['options']));
        $this->assertSame(['compact', 'standard', 'spacious'], array_keys($groups['header']['fields']['density']['options']));
        $this->assertSame(['dark', 'light', 'warm'], array_keys($groups['footer']['fields']['presentation']['options']));
        $this->assertSame(['columns', 'stacked'], array_keys($groups['footer']['fields']['layout']['options']));

        foreach ([
            'detail_page_title_template',
            'detail_meta_title_template',
            'detail_meta_keywords_template',
            'detail_meta_description_template',
        ] as $key) {
            $field = $groups['donation_page']['fields'][$key];
            $this->assertTrue($field['localized']);
            $this->assertContains('{cause}', $field['required_placeholders']);
            $this->assertStringContainsString('{cause}', $field['default']);
            $this->assertStringContainsString('{cause}', $field['localized_defaults']['bn']);
        }

        $this->assertSame('হোম', $groups['donation_page']['fields']['home_breadcrumb_label']['localized_defaults']['bn']);
        $this->assertSame('দান করুন', $groups['donation_page']['fields']['donate_breadcrumb_label']['localized_defaults']['bn']);
    }

    private function putPublicSetting(string $group, string $key, string $value): void
    {
        SiteSetting::create([
            'group' => $group,
            'key' => $key,
            'locale' => 'en',
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }
}
