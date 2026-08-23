<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLegacyButtonAdoptionTest extends TestCase
{
    public function test_shared_admin_button_tokens_and_legacy_fallback_meet_touch_target_size(): void
    {
        $css = file_get_contents(public_path('admin-assets/assets/css/style.css'));

        $this->assertStringContainsString('.igf-btn {', $css);
        $this->assertMatchesRegularExpression('/\.igf-btn\s*\{[^}]*min-height:\s*44px/s', $css);
        $this->assertMatchesRegularExpression('/\.igf-icon-btn\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px/s', $css);
        $this->assertMatchesRegularExpression('/body\.layout-wrapper \.right-panel :where\(\.btn-sm, \.btn-sm1\)\s*\{[^}]*min-height:\s*44px/s', $css);
    }

    public function test_core_administration_tables_use_semantic_visible_action_tokens(): void
    {
        $views = [
            resource_path('views/admin/menu/index.blade.php'),
            resource_path('views/admin/menu-action/index.blade.php'),
            resource_path('views/admin/role/index.blade.php'),
            resource_path('views/admin/tag/index.blade.php'),
            resource_path('views/admin/testimonial/index.blade.php'),
        ];

        foreach ($views as $view) {
            $markup = file_get_contents($view);

            $this->assertStringNotContainsString('btn-sm1', $markup, $view);
            $this->assertStringContainsString('igf-btn-secondary', $markup, $view);
            $this->assertStringContainsString('igf-btn-danger', $markup, $view);
            $this->assertStringContainsString('igf-btn-compact', $markup, $view);
            $this->assertMatchesRegularExpression('/<\/i>\s*Edit\s*<\/button>/s', $markup, $view);
            $this->assertMatchesRegularExpression('/<\/i>\s*Delete\s*<\/button>/s', $markup, $view);
        }
    }

    public function test_save_and_continue_is_distinct_from_the_primary_save_action(): void
    {
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'), \FilesystemIterator::SKIP_DOTS)
        );
        $controls = 0;

        foreach ($views as $view) {
            if (!$view->isFile() || $view->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($view->getPathname());
            preg_match_all('/<button\b[^>]*name="save_and_update"[^>]*>.*?<\/button>/is', $source, $matches);

            foreach ($matches[0] as $control) {
                $controls++;
                $this->assertStringContainsString('igf-btn-secondary', $control, $view->getPathname());
                $this->assertStringContainsString('Save and continue editing', $control, $view->getPathname());
                $this->assertStringNotContainsString('btn-success', $control, $view->getPathname());
            }
        }

        $this->assertSame(19, $controls);
    }
}
