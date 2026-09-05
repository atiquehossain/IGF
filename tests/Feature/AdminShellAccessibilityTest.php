<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminShellAccessibilityTest extends TestCase
{
    public function test_admin_shell_preserves_visible_keyboard_focus_and_shared_action_tokens(): void
    {
        $styles = file_get_contents(public_path('admin-assets/assets/css/style.css'));

        $this->assertStringNotContainsString('outline: none!important', $styles);
        $this->assertStringNotContainsString('outline: 0!important', $styles);
        $this->assertStringContainsString('[tabindex]):focus-visible', $styles);
        $this->assertStringContainsString('outline: 3px solid var(--igf-focus-ring) !important', $styles);
        $this->assertStringContainsString('.igf-btn-primary', $styles);
        $this->assertStringContainsString('.igf-btn-secondary', $styles);
        $this->assertStringContainsString('.igf-btn-tertiary', $styles);
        $this->assertStringContainsString('.igf-btn-danger', $styles);
        $this->assertStringContainsString('.igf-icon-btn', $styles);
        $this->assertStringContainsString('min-width: 44px', $styles);
        $this->assertStringContainsString('min-height: 44px', $styles);
    }

    public function test_global_navigation_names_mobile_actions_and_avoids_duplicate_shortcuts(): void
    {
        $navbar = file_get_contents(resource_path('views/admin/layouts/navbar.blade.php'));
        $sidebar = file_get_contents(resource_path('views/admin/layouts/sidebar.blade.php'));

        $this->assertStringContainsString("\$ui('shell.search_content')", $navbar);
        $this->assertStringContainsString("\$ui('shell.create_page')", $navbar);
        $this->assertStringContainsString("\$ui('shell.new_page')", $navbar);
        $this->assertStringContainsString("['page.index', 'page.create']", $navbar);
        $this->assertStringNotContainsString('igf-top-links', $navbar);
        $this->assertStringNotContainsString('Quick Create', $navbar);

        $this->assertStringContainsString("'label' => \$ui('sidebar.items.content_hub')", $sidebar);
        $this->assertStringContainsString("'label' => \$ui('sidebar.items.search_sharing')", $sidebar);
        $this->assertStringContainsString("'label' => \$ui('sidebar.items.administrators')", $sidebar);
        $this->assertStringContainsString('aria-current="page"', $sidebar);
        $this->assertStringNotContainsString('Search & Social Preview', $sidebar);
    }

    public function test_administrator_actions_use_outcome_labels_and_safe_visual_semantics(): void
    {
        $admin = file_get_contents(resource_path('views/admin/admin/index.blade.php'));

        $this->assertStringContainsString('Create administrator</button>', $admin);
        $this->assertStringContainsString('Save administrator</button>', $admin);
        $this->assertStringContainsString('igf-btn-secondary cancel', $admin);
        $this->assertStringContainsString('fa fa-times', $admin);
        $this->assertStringContainsString('igf-danger-action', $admin);
        $this->assertStringContainsString('igf-btn-danger trash', $admin);
        $this->assertStringNotContainsString('btn-danger cancel', $admin);
        $this->assertStringNotContainsString('fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}', $admin);
        $this->assertSame(1, substr_count($admin, 'id="edit_admin"'));
    }

    public function test_admin_cancel_controls_never_look_like_delete_actions(): void
    {
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'), \FilesystemIterator::SKIP_DOTS)
        );
        $cancelControls = 0;

        foreach ($views as $view) {
            if (!$view->isFile() || $view->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $view->getPathname());
            if (str_ends_with($relativePath, '/gallery/index.blade.php')) {
                continue;
            }

            $source = file_get_contents($view->getPathname());
            preg_match_all('/<(button|a)\b[^>]*>.*?<\/\1>/is', $source, $controls);

            foreach ($controls[0] as $control) {
                if (!preg_match('/Common->Cancel|\bCancel\b/i', $control)) {
                    continue;
                }

                $cancelControls++;
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(?:btn-danger|igf-btn-danger|igf-btn--danger)\b/i',
                    $control,
                    "Cancel must not use danger styling in {$relativePath}."
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/\bfa-trash(?:-o)?\b/i',
                    $control,
                    "Cancel must not use a trash icon in {$relativePath}."
                );
                $this->assertMatchesRegularExpression(
                    '/\bigf-btn-(?:secondary|tertiary)\b/i',
                    $control,
                    "Cancel must use a shared non-destructive action token in {$relativePath}."
                );
            }
        }

        $this->assertGreaterThan(20, $cancelControls, 'Expected the audit to cover the legacy admin cancel controls.');
    }

    public function test_admin_login_has_readable_static_labels_and_strong_focus_treatment(): void
    {
        $login = file_get_contents(resource_path('views/auth/admin-login.blade.php'));
        $styles = file_get_contents(public_path('admin-assets/assets/css/login.style.css'));

        $this->assertStringContainsString("admin_login.meta_description", $login);
        $this->assertStringContainsString("admin_login.heading", $login);
        $this->assertStringContainsString('class="admin-login-intro"', $login);
        $this->assertStringContainsString("admin_login.submit", $login);
        $this->assertStringContainsString('aria-required="true"', $login);
        $this->assertStringNotContainsString('jquery.min.js', $login);
        $this->assertStringContainsString('background-image:', $styles);
        $this->assertStringContainsString('height: 88px', $styles);
        $this->assertStringContainsString('.btn-larger:focus-visible', $styles);
        $this->assertStringContainsString('outline: 3px solid var(--igf-login-focus) !important', $styles);
    }
}
