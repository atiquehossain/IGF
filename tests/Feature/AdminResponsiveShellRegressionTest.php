<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminResponsiveShellRegressionTest extends TestCase
{
    public function test_shared_shell_contains_wide_content_and_keeps_global_controls_touch_sized(): void
    {
        $styles = file_get_contents(public_path('admin-assets/assets/css/style.css'));
        $header = file_get_contents(resource_path('views/admin/layouts/header.blade.php'));

        $this->assertStringContainsString('--igf-focus-ring: #9c4500;', $styles);
        $this->assertStringNotContainsString('--igf-focus-ring: #ff7500;', $styles);
        $this->assertStringContainsString('.pagination .page-link', $styles);
        $this->assertStringContainsString('.modal .close', $styles);
        $this->assertStringContainsString('min-width: 44px;', $styles);
        $this->assertStringContainsString('min-height: 44px;', $styles);
        $this->assertStringContainsString('.table-stats, .table-responsive, .dataTables_wrapper', $styles);
        $this->assertStringContainsString('overflow-x: auto !important;', $styles);
        $this->assertStringContainsString('flex-wrap: wrap !important;', $styles);

        $this->assertStringContainsString('max-width:calc(100vw - var(--igf-sidebar))', $header);
        $this->assertStringContainsString('max-width:calc(100vw - 83px)', $header);
        $this->assertStringContainsString('max-width:100vw; margin-left:0!important', $header);
        $this->assertStringContainsString('.igf-mobile-search button { min-width:44px; min-height:44px;', $header);
    }

    public function test_compact_sidebar_controls_keep_names_and_only_the_most_specific_route_is_current(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/layouts/sidebar.blade.php'));

        $this->assertStringContainsString('$activeNavRoute = collect($navGroups)', $sidebar);
        $this->assertStringContainsString('->sortByDesc(function (array $item): int', $sidebar);
        $this->assertStringContainsString('$item[\'route\'] === $activeNavRoute', $sidebar);
        $this->assertStringContainsString('aria-label="Dashboard" title="Dashboard"', $sidebar);
        $this->assertStringContainsString('aria-label="{{ $group[\'label\'] }} navigation"', $sidebar);
        $this->assertStringContainsString('aria-label="{{ $item[\'label\'] }}"', $sidebar);
        $this->assertStringContainsString('aria-label="Advanced and legacy tools navigation"', $sidebar);
        $this->assertStringContainsString('aria-label="Visit public website"', $sidebar);
        $this->assertStringContainsString('aria-label="Website Customizer"', $sidebar);
        $this->assertStringContainsString('aria-label="Log out"', $sidebar);
    }

    public function test_navigation_translation_and_chat_actions_have_44_pixel_targets_and_mobile_containment(): void
    {
        $navigation = file_get_contents(resource_path('views/admin/page_menu/index.blade.php'));
        $translations = file_get_contents(resource_path('views/admin/translation-center/index.blade.php'));
        $chat = file_get_contents(resource_path('views/admin/chat/index.blade.php'));

        $this->assertStringContainsString('.igf-menu-drag,.igf-menu-action{width:44px;min-width:44px;height:44px;min-height:44px}', $navigation);
        $this->assertStringContainsString('.igf-menu-settings-actions{flex-wrap:wrap}', $navigation);
        $this->assertStringContainsString('.igf-menu-tree{padding:14px 10px 18px}', $navigation);

        $this->assertStringContainsString('.tc-copy{width:44px;min-width:44px;height:44px;min-height:44px}', $translations);
        $this->assertStringContainsString('.tc-pagination .page-link{width:44px;min-width:44px;height:44px;min-height:44px}', $translations);
        $this->assertStringContainsString('.tc-tools input,.tc-tools select,.tc-filter-button,.tc-reset{width:100%}', $translations);
        $this->assertStringContainsString('class="tc-sheet-wrap table-responsive"', $translations);

        $this->assertStringContainsString('.igf-chat-field input,.igf-chat-field select,.igf-chat-button,.igf-chat-tab{min-height:44px}', $chat);
        $this->assertStringContainsString('.igf-chat-table-wrap{overscroll-behavior-inline:contain;', $chat);
        $this->assertStringContainsString('.igf-chat-head>.igf-chat-button,.igf-chat-filter .igf-chat-button,.igf-chat-clear-form .igf-chat-button{width:100%}', $chat);
        $this->assertStringContainsString('class="igf-chat-table-wrap table-responsive"', $chat);
    }

    public function test_navigation_trash_and_reusable_sections_use_labeled_local_table_scrollers(): void
    {
        $navigationTrash = file_get_contents(resource_path('views/admin/page_menu/trash.blade.php'));
        $reusable = file_get_contents(resource_path('views/admin/reusable-blocks/index.blade.php'));

        $this->assertStringContainsString('for="navigation-trash-search"', $navigationTrash);
        $this->assertStringContainsString('id="navigation-trash-search" type="search"', $navigationTrash);
        $this->assertStringContainsString('aria-label="Scrollable trashed navigation records" tabindex="0"', $navigationTrash);
        $this->assertStringContainsString('.igf-table-wrap{max-width:100%;overflow-x:auto', $navigationTrash);
        $this->assertStringContainsString('class="igf-table-wrap table-responsive"', $navigationTrash);

        $this->assertStringContainsString('for="reusable-section-search"', $reusable);
        $this->assertStringContainsString('id="reusable-section-search" type="search"', $reusable);
        $this->assertStringContainsString('aria-label="Scrollable reusable section records" tabindex="0"', $reusable);
        $this->assertStringContainsString('.igf-table-wrap{max-width:100%;overflow-x:auto', $reusable);
        $this->assertStringContainsString('class="igf-table-wrap table-responsive"', $reusable);
    }
}
