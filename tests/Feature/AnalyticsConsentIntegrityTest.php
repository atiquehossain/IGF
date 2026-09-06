<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SiteSettingsController;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class AnalyticsConsentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_copy_is_managed_and_localized_for_non_technical_editors(): void
    {
        $fields = config('site-settings.groups.analytics.fields');

        $this->assertSame('url_or_path', $fields['privacy_url']['type']);
        $this->assertTrue($fields['consent_message']['localized']);
        $this->assertTrue($fields['accept_label']['localized']);
        $this->assertSame('analytics_id', $fields['google_analytics_id']['type']);
        $this->assertStringContainsString('Leave blank to disable analytics', $fields['google_analytics_id']['help']);

        $bangla = app(SiteSettingService::class)->values('bn', true)['analytics'];
        $this->assertSame('আপনার গোপনীয়তার পছন্দ', $bangla['consent_title']);
        $this->assertSame('অ্যানালিটিক্স গ্রহণ করুন', $bangla['accept_label']);
        $this->assertSame('/page/privacy-policy', $bangla['privacy_url']);
    }

    public function test_server_shell_never_loads_google_analytics_before_browser_consent(): void
    {
        $shell = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringNotContainsString('googletagmanager.com/gtag', $shell);
        $this->assertStringNotContainsString("gtag('config'", $shell);
        $this->assertStringContainsString('<AnalyticsConsent />', file_get_contents(resource_path('js/layouts/App.vue')));

        $app = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("import { trackAnalyticsPageView } from './Shared/analytics';", $app);
        $this->assertStringContainsString('trackAnalyticsPageView();', $app);
    }

    public function test_customizer_rejects_mistyped_tracking_ids_with_an_actionable_message(): void
    {
        $controller = app(SiteSettingsController::class);
        $method = new ReflectionMethod($controller, 'rulesFor');
        $rules = $method->invoke($controller, config('site-settings.groups.analytics.fields.google_analytics_id'));

        $invalid = Validator::make(['measurement' => 'UA-OLD-ID'], ['measurement' => $rules]);
        $this->assertTrue($invalid->fails());
        $this->assertSame(
            'Enter a GA4 measurement ID such as G-ABC123DEF4, or leave this field blank.',
            $invalid->errors()->first('measurement')
        );

        $this->assertFalse(Validator::make(['measurement' => 'G-ABC123DEF4'], ['measurement' => $rules])->fails());
        $this->assertFalse(Validator::make(['measurement' => ''], ['measurement' => $rules])->fails());
    }
}
