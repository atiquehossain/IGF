<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyAdminAccessibilityTest extends TestCase
{
    public function test_legacy_admin_indexes_define_a_page_heading(): void
    {
        foreach ([
            'admin/admin/index.blade.php',
            'admin/album/index.blade.php',
            'admin/annual-report/index.blade.php',
            'admin/banner/index.blade.php',
            'admin/category/index.blade.php',
            'admin/content-trash/index.blade.php',
            'admin/donations/index.blade.php',
            'admin/editor-draft/index.blade.php',
            'admin/gallery/index.blade.php',
            'admin/menu/index.blade.php',
            'admin/page/trash.blade.php',
            'admin/role/index.blade.php',
            'admin/user/index.blade.php',
            'admin/youtube/index.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertSame(1, substr_count($source, '<h1'), $view . ' must define exactly one H1.');
        }
    }

    public function test_legacy_search_and_filter_controls_have_accessible_names(): void
    {
        foreach ([
            'admin/admin/index.blade.php',
            'admin/album/index.blade.php',
            'admin/annual-report/index.blade.php',
            'admin/banner/index.blade.php',
            'admin/category/index.blade.php',
            'admin/editor-draft/index.blade.php',
            'admin/gallery/index.blade.php',
            'admin/menu/index.blade.php',
            'admin/role/index.blade.php',
            'admin/user/index.blade.php',
            'admin/youtube/index.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $searchControls = [];
            preg_match_all('/<input\b[^>]*type="search"[^>]*>/i', $source, $searchControls);
            $this->assertNotEmpty($searchControls[0], $view . ' must contain its search control.');
            foreach ($searchControls[0] as $control) {
                $this->assertStringContainsString('aria-label=', $control, $view . ' has an unnamed search control.');
            }
        }

        $media = file_get_contents(resource_path('views/admin/media/index.blade.php'));
        $this->assertStringContainsString('for="media-library-search"', $media);
        $this->assertStringContainsString('for="media-library-type"', $media);
        $this->assertStringContainsString('id="media-library-type"', $media);
    }

    public function test_legacy_form_and_icon_actions_have_accessible_names(): void
    {
        foreach (['admin/admin/index.blade.php', 'admin/menu/index.blade.php', 'admin/role/index.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            preg_match_all('/<(?:input|select|textarea)\b(?![^>]*type="hidden")[^>]*>/i', $source, $controls);
            foreach ($controls[0] as $control) {
                $this->assertTrue(
                    str_contains($control, 'aria-label=') || str_contains($control, ' id='),
                    $view . ' has a form control without an explicit accessible-name hook: ' . $control,
                );
            }
        }

        $sharedActions = file_get_contents(app_path('Link.php'));
        $this->assertGreaterThanOrEqual(4, substr_count($sharedActions, 'aria-label='));

        $menu = file_get_contents(resource_path('views/admin/menu/index.blade.php'));
        $this->assertGreaterThanOrEqual(4, substr_count($menu, 'aria-label="'));

        foreach (['admin/banner/index.blade.php', 'admin/gallery/index.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertStringNotContainsString('<a href="javascript:void(0)"><img', $source);
        }
    }

    public function test_every_shared_legacy_action_call_supplies_a_human_readable_item_label(): void
    {
        $calls = [];

        foreach (glob(resource_path('views/admin/*/index.blade.php')) as $view) {
            preg_match_all('/Link::action\(([^\r\n]+)\)/', file_get_contents($view), $matches);
            array_push($calls, ...$matches[1]);
        }

        $this->assertCount(17, $calls);
        foreach ($calls as $arguments) {
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($arguments, ','),
                'Every Link::action call must provide an item-aware third argument.',
            );
        }

        $gallery = file_get_contents(resource_path('views/admin/gallery/index.blade.php'));
        $this->assertStringContainsString('igf-btn igf-btn-secondary igf-btn-compact', $gallery);
        $this->assertStringContainsString('igf-btn igf-btn-primary igf-btn-compact', $gallery);
        $this->assertStringContainsString('Add gallery item', $gallery);
        $this->assertStringNotContainsString('btn btn-info btn-sm', $gallery);
    }

    public function test_inline_cancel_controls_reset_only_their_own_form_and_preserve_hidden_fields(): void
    {
        foreach ([
            'admin/admin/index.blade.php',
            'admin/role/index.blade.php',
            'admin/menu/index.blade.php',
            'admin/menu-action/index.blade.php',
            'admin/tag/index.blade.php',
            'admin/testimonial/index.blade.php',
            'admin/district/index.blade.php',
            'admin/division/index.blade.php',
            'admin/upazila/index.blade.php',
            'admin/editor-draft/index.blade.php',
            'admin/event_calendar/index.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));

            $this->assertStringContainsString(
                'clear($(this).closest("form"));',
                $source,
                $view . ' must scope Cancel to the containing form.',
            );
            $this->assertStringContainsString(
                '$form.get(0).reset();',
                $source,
                $view . ' must use the browser form reset contract.',
            );
            $this->assertStringNotContainsString(
                '$("input").val("");',
                $source,
                $view . ' must never erase every input, including CSRF and method fields.',
            );
            $this->assertStringNotContainsString(
                '$("select").val("");',
                $source,
                $view . ' must never reset controls outside the selected form.',
            );
        }
    }

    public function test_report_printing_preserves_the_live_admin_dom_and_icon_only_view_links_are_named(): void
    {
        $scripts = file_get_contents(resource_path('views/admin/layouts/scripts.blade.php'));
        $this->assertStringContainsString("document.body.classList.add('igf-print-scope')", $scripts);
        $this->assertStringContainsString('window.print();', $scripts);
        $this->assertStringNotContainsString('document.body.innerHTML =', $scripts);
        $this->assertStringNotContainsString('window.close();', $scripts);

        $youtubeReport = file_get_contents(resource_path('views/admin/report/youtubemeta.blade.php'));
        $this->assertStringContainsString('Print report', $youtubeReport);

        $applications = file_get_contents(resource_path('views/admin/user-approval/index.blade.php'));
        $this->assertStringContainsString('aria-label="View application from {{ $user->name }}"', $applications);
        $this->assertStringContainsString('title="View application"', $applications);
    }
}
