<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneNotFoundCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_view_uses_allowlisted_global_design_presets(): void
    {
        $this->globalSetting('theme', 'primary_color', '#336699', 'color');
        $this->globalSetting('design', 'font_pairing', 'classic');
        $this->globalSetting('design', 'content_width', 'wide');
        $this->globalSetting('design', 'heading_size', 'large');
        $this->globalSetting('design', 'body_text_size', 'large');
        $this->globalSetting('design', 'section_spacing', 'generous');
        $this->globalSetting('design', 'button_shape', 'square');
        $this->globalSetting('design', 'logo_size', 'large');
        $this->globalSetting('design', 'shadow_density', 'strong');

        $response = $this->get('/missing-page-for-design-preset-test')->assertNotFound();

        foreach ([
            '--primary:#336699',
            '--igf-font-body:Arial,Helvetica,sans-serif',
            "--igf-font-heading:Georgia,'Times New Roman',serif",
            '--igf-content-width:1400px',
            '--igf-heading-1:clamp(48px,7vw,88px)',
            '--igf-body-size:19px',
            '--igf-section-block:clamp(88px,11vw,144px)',
            '--igf-button-radius:4px',
            '--igf-not-found-logo-width:148px',
            '--igf-shadow-control:0 8px 22px rgba(255,117,0,.3)',
        ] as $token) {
            $response->assertSee($token, false);
        }
    }

    public function test_not_found_view_falls_back_when_database_contains_unapproved_css_values(): void
    {
        $payload = 'url(javascript:alert(1));position:fixed';
        $this->globalSetting('design', 'font_pairing', $payload);
        $this->globalSetting('theme', 'primary_color', '#fff;}body{display:none', 'color');

        $response = $this->get('/missing-page-for-safe-fallback-test')->assertNotFound();

        $response->assertSee("--igf-font-body:'Hanken Grotesk',Arial,sans-serif", false);
        $response->assertSee('--primary:#ff7500', false);
        $response->assertDontSee($payload, false);
        $response->assertDontSee('body{display:none', false);
    }

    private function globalSetting(string $group, string $key, string $value, string $type = 'text'): void
    {
        SiteSetting::query()->create([
            'group' => $group,
            'key' => $key,
            'locale' => '*',
            'value' => $value,
            'type' => $type,
            'is_public' => true,
        ]);
    }
}
