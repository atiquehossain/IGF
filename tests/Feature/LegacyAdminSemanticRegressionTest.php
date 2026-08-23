<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyAdminSemanticRegressionTest extends TestCase
{
    /** @return array<int, string> */
    private function pageViews(): array
    {
        return [
            'admin/admin/change_password.blade.php',
            'admin/admin/reset_password.blade.php',
            'admin/comment/index.blade.php',
            'admin/contact_message/index.blade.php',
            'admin/district/index.blade.php',
            'admin/division/index.blade.php',
            'admin/document/index.blade.php',
            'admin/donationType/index.blade.php',
            'admin/editor-draft/index.blade.php',
            'admin/event_calendar/index.blade.php',
            'admin/members/index.blade.php',
            'admin/notice-board/add.blade.php',
            'admin/notice-board/edit.blade.php',
            'admin/notice-board/index.blade.php',
            'admin/report/submitwork.blade.php',
            'admin/report/youtubemeta.blade.php',
            'admin/role/permission.blade.php',
            'admin/service/add.blade.php',
            'admin/service/edit.blade.php',
            'admin/service/index.blade.php',
            'admin/splash_screen/index.blade.php',
            'admin/sponsorships/index.blade.php',
            'admin/subscriber/index.blade.php',
            'admin/submitwork/index.blade.php',
            'admin/submitwork/show.blade.php',
            'admin/tag/index.blade.php',
            'admin/testimonial/index.blade.php',
            'admin/upazila/index.blade.php',
            'admin/user-approval/index.blade.php',
            'admin/user-approval/view.blade.php',
            'admin/volunteer/index.blade.php',
            'admin/youtube/add.blade.php',
            'admin/youtube/edit.blade.php',
            'admin/youtube/index.blade.php',
        ];
    }

    /** @return array<int, string> */
    private function formViews(): array
    {
        return array_merge($this->pageViews(), [
            'admin/donationType/_destination-fields.blade.php',
            'admin/notice-board/_event_fields.blade.php',
        ]);
    }

    private function source(string $view): string
    {
        return file_get_contents(resource_path('views/'.$view));
    }

    private function markupOnly(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;
        $source = preg_replace('/<!--.*?-->/s', '', $source) ?? $source;
        $source = preg_replace('/<script\b.*?<\/script>/is', '', $source) ?? $source;
        $source = preg_replace('/<style\b.*?<\/style>/is', '', $source) ?? $source;
        // Blade property access contains a literal `>` that is not an HTML tag boundary.
        $source = str_replace('->', '__BLADE_PROPERTY__', $source);

        return $source;
    }

    public function test_every_assigned_legacy_page_has_one_semantic_h1(): void
    {
        foreach ($this->pageViews() as $view) {
            $source = $this->source($view);

            $this->assertSame(
                1,
                preg_match_all('/<h1\b/i', $source),
                $view.' must expose exactly one page-level H1.',
            );
        }
    }

    public function test_every_visible_legacy_form_control_has_a_programmatic_name_and_unique_id(): void
    {
        foreach ($this->formViews() as $view) {
            $source = $this->markupOnly($this->source($view));
            preg_match_all('/<(?:input|select|textarea)\b[^>]*>/is', $source, $matches);
            $ids = [];

            foreach ($matches[0] as $control) {
                if (preg_match('/\btype\s*=\s*["\'](?:hidden|submit|reset|button|image)["\']/i', $control)) {
                    continue;
                }

                $hasDirectName = preg_match('/\baria-(?:label|labelledby)\s*=/i', $control) === 1;
                preg_match('/\bid\s*=\s*(["\'])(.*?)\1/is', $control, $idMatch);
                $id = $idMatch[2] ?? '';

                $this->assertNotSame('', $id, $view.' contains a visible control without an ID: '.$control);
                $this->assertNotContains($id, $ids, $view.' repeats the visible control ID '.$id.'.');
                $ids[] = $id;

                $hasAssociatedLabel = $id !== ''
                    && preg_match('/<label\b[^>]*\bfor\s*=\s*(["\'])'.preg_quote($id, '/').'\1/is', $source) === 1;

                $this->assertTrue(
                    $hasDirectName || $hasAssociatedLabel,
                    $view.' contains a visible control without a programmatic label: '.$control,
                );
            }

            preg_match_all('/<label\b[^>]*\bfor\s*=\s*(["\'])(.*?)\1/is', $source, $labels);
            foreach ($labels[2] as $labelTarget) {
                $this->assertMatchesRegularExpression(
                    '/\bid\s*=\s*(["\'])'.preg_quote($labelTarget, '/').'\1/is',
                    $source,
                    $view.' has a label whose target does not exist: '.$labelTarget,
                );
            }
        }
    }

    public function test_legacy_modals_reference_real_unique_titles(): void
    {
        foreach ($this->formViews() as $view) {
            $source = $this->markupOnly($this->source($view));
            $this->assertStringNotContainsString('aria-labelledby="mediumModalLabel"', $source, $view);

            preg_match_all('/<div\b[^>]*class="modal fade[^"]*"[^>]*>/is', $source, $modals);
            foreach ($modals[0] as $modal) {
                preg_match('/\baria-labelledby="([^"]+)"/i', $modal, $labelMatch);
                $titleId = $labelMatch[1] ?? '';

                $this->assertNotSame('', $titleId, $view.' contains a modal without aria-labelledby.');
                $this->assertSame(
                    1,
                    preg_match_all('/\bid="'.preg_quote($titleId, '/').'"/i', $source),
                    $view.' modal title reference must resolve exactly once: '.$titleId,
                );
            }
        }
    }

    public function test_localized_legacy_tabs_use_scoped_relationships_and_truthful_selection_state(): void
    {
        foreach ([
            'admin/service/add.blade.php' => 'service-create-',
            'admin/service/edit.blade.php' => 'service-edit-',
            'admin/splash_screen/index.blade.php' => 'splash-screen-',
            'admin/testimonial/index.blade.php' => 'testimonial-',
            'admin/youtube/add.blade.php' => 'youtube-create-',
            'admin/youtube/edit.blade.php' => 'youtube-edit-',
        ] as $view => $prefix) {
            $source = $this->markupOnly($this->source($view));
            $this->assertStringContainsString('id="'.$prefix, $source, $view);
            $this->assertStringNotContainsString('aria-selected="true"', $source, $view.' must not mark every language selected.');

            preg_match_all('/<a\b[^>]*role="tab"[^>]*>/is', $source, $tabs);
            $this->assertNotEmpty($tabs[0], $view.' must define language tabs.');
            foreach ($tabs[0] as $tab) {
                $this->assertMatchesRegularExpression('/\baria-controls=/i', $tab, $view);
                $this->assertMatchesRegularExpression('/\baria-selected=/i', $tab, $view);
            }

            preg_match_all('/<div\b[^>]*role="tabpanel"[^>]*>/is', $source, $panels);
            $this->assertNotEmpty($panels[0], $view.' must define language panels.');
            foreach ($panels[0] as $panel) {
                $this->assertMatchesRegularExpression('/\baria-labelledby=/i', $panel, $view);
            }
        }
    }

    public function test_legacy_form_actions_use_explicit_outcomes_and_accurate_icons(): void
    {
        foreach ($this->formViews() as $view) {
            $source = $this->markupOnly($this->source($view));

            $this->assertDoesNotMatchRegularExpression('/fa-(?:lock|magic)\b/i', $source, $view);
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*\bbtn\s+btn-(?:primary|secondary|success|info|warning|danger|light|outline)/i',
                $source,
                $view.' bypasses the shared admin button hierarchy.',
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<button\b[^>]*type="submit"[^>]*>.*?\{\{\s*\$Lang->Common->Submit\s*\}\}.*?<\/button>/is',
                $source,
                $view.' has a generic submit action.',
            );
        }
    }
}
