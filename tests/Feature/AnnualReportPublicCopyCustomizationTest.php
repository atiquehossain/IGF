<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnualReportPublicCopyCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_fallback_summary_and_publisher_follow_public_customizer_values(): void
    {
        $this->publicSetting('reports_page', 'detail_summary_fallback', 'A locally managed report summary.');
        $this->publicSetting('branding', 'site_name', 'Community Impact Trust');

        $report = AnnualReport::query()->create([
            'title' => 'Impact report 2025',
            'slug' => 'impact-report-2025',
            'description' => '',
            'sub_title' => '',
            'publisher_name' => '',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'file_type' => 'application/pdf',
            'file_size' => '2048',
            'status' => 1,
        ]);

        $this->get(route('frontend.annual_report.show', ['slug' => $report->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('annual-report-detail')
                ->where('data.report.summary', 'A locally managed report summary.')
                ->where('data.report.publisher_name', 'Community Impact Trust')
            );
    }

    public function test_detail_copy_fields_are_public_localized_and_preserve_the_cover_title_placeholder(): void
    {
        $fields = config('site-settings.groups.reports_page.fields');
        $localizedKeys = [
            'detail_summary_fallback',
            'detail_cover_alt_template',
            'detail_file_type_label',
            'detail_file_separator',
            'detail_file_unit_bytes',
            'detail_file_unit_kilobytes',
            'detail_file_unit_megabytes',
            'detail_file_unit_gigabytes',
        ];

        foreach ($localizedKeys as $key) {
            $this->assertTrue($fields[$key]['localized'], "{$key} must be localized");
            $this->assertTrue($fields[$key]['public'], "{$key} must be shared publicly");
            $this->assertNotSame('', trim((string) $fields[$key]['localized_defaults']['bn']), "{$key} needs Bangla copy");
        }

        $this->assertContains('{title}', $fields['detail_cover_alt_template']['required_placeholders']);
        $this->assertStringContainsString('{title}', $fields['detail_cover_alt_template']['default']);
        $this->assertStringContainsString('{title}', $fields['detail_cover_alt_template']['localized_defaults']['bn']);
    }

    private function publicSetting(string $group, string $key, string $value): void
    {
        SiteSetting::query()->create([
            'group' => $group,
            'key' => $key,
            'locale' => 'en',
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }
}
