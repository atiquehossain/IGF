<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyEditorClientUxIntegrityTest extends TestCase
{
    public function test_raw_css_is_kept_inside_an_advanced_panel_that_reopens_when_needed(): void
    {
        $views = [
            'category/add.blade.php' => "old('inline_css.'. \$lang)",
            'category/edit.blade.php' => "old('inline_css.'. \$lang, @\$category->inline_css)",
            'notice-board/add.blade.php' => "old('inline_css')",
            'notice-board/edit.blade.php' => "old('inline_css', \$notice_board->inline_css)",
        ];

        foreach ($views as $view => $preservedValueExpression) {
            $source = $this->adminView($view);

            $this->assertMatchesRegularExpression(
                '/<details\b[^>]*@if\(\$showAdvancedStyling\) open @endif>.*?'
                    .'Advanced developer styling.*?name="inline_css(?:\[[^\]]+\])?"/s',
                $source,
                $view
            );
            $this->assertStringContainsString(
                'Ordinary editors can leave this closed.',
                $source,
                $view
            );
            $this->assertStringContainsString("filled(\$inlineCssValue)", $source, $view);
            $this->assertStringContainsString("\$errors->has('inline_css", $source, $view);
            $this->assertStringContainsString($preservedValueExpression, $source, $view);
            $this->assertStringContainsString('{{ $inlineCssValue }}', $source, $view);
        }
    }

    public function test_display_priority_wording_matches_the_public_descending_order(): void
    {
        $formViews = [
            'notice-board/add.blade.php',
            'notice-board/edit.blade.php',
            'annual-report/add.blade.php',
            'annual-report/edit.blade.php',
        ];

        foreach ($formViews as $view) {
            $source = $this->adminView($view);

            $this->assertStringContainsString('>Display priority</label>', $source, $view);
            $this->assertStringContainsString(
                'Higher numbers appear first; leave blank for normal ordering.',
                $source,
                $view
            );
            $this->assertStringContainsString('name="order_by"', $source, $view);
            $this->assertStringContainsString('min="-2147483648"', $source, $view);
            $this->assertStringContainsString('max="2147483647"', $source, $view);
            $this->assertStringNotContainsString('Form->OrderBy', $source, $view);
        }

        foreach (['notice-board/index.blade.php', 'annual-report/index.blade.php'] as $view) {
            $source = $this->adminView($view);

            $this->assertStringContainsString('<strong>Display priority</strong>', $source, $view);
            $this->assertStringNotContainsString('Form->Order</strong>', $source, $view);
        }

        foreach (['NoticeBoardController.php', 'AnnualReportController.php'] as $controller) {
            $source = (string) file_get_contents(app_path('Http/Controllers/Vue/'.$controller));

            $this->assertTrue(
                str_contains($source, "->orderBy('order_by', 'desc')")
                    || str_contains($source, "->orderByDesc('order_by')"),
                $controller.' must keep display priority in descending order.'
            );
        }
    }

    public function test_tag_admin_describes_groups_and_hands_project_pages_to_content_hub(): void
    {
        $source = $this->adminView('tag/index.blade.php');

        $this->assertStringContainsString('Project groups organize project cards and pages.', $source);
        $this->assertStringContainsString("allows(\$currentAdmin, 'page.index')", $source);
        $this->assertStringContainsString("allows(\$currentAdmin, 'page.create')", $source);
        $this->assertStringContainsString("allows(\$currentAdmin, 'translations.index')", $source);
        $this->assertStringContainsString("route('page.index')", $source);
        $this->assertStringContainsString('Search project pages in Content Hub', $source);
        $this->assertStringContainsString("route('page.create')", $source);
        $this->assertStringContainsString('Create a project page', $source);
        $this->assertStringContainsString('Translate project groups', $source);
        $this->assertStringContainsString('Archive introduction', $source);
        $this->assertStringContainsString('name="description"', $source);
        $this->assertStringContainsString('Create project group', $source);
        $this->assertStringContainsString('Save project group', $source);
        $this->assertStringContainsString('aria-label="Search project groups"', $source);
        $this->assertStringContainsString('aria-label="Edit project group"', $source);
        $this->assertStringContainsString('aria-label="Delete project group"', $source);
        $this->assertStringContainsString('data-item-label="project group {{ $tag->name }}"', $source);

        $this->assertStringNotContainsString('Create project</button>', $source);
        $this->assertStringNotContainsString('Save project</button>', $source);
        $this->assertStringNotContainsString('Search projects', $source);
        $this->assertStringNotContainsString('aria-label="Edit project"', $source);
        $this->assertStringNotContainsString('aria-label="Delete project"', $source);
        $this->assertStringNotContainsString('data-item-label="project {{ $tag->name }}"', $source);
    }

    public function test_annual_report_language_is_explicit_in_the_list_and_editor_handoff(): void
    {
        $index = $this->adminView('annual-report/index.blade.php');
        $create = $this->adminView('annual-report/add.blade.php');
        $edit = $this->adminView('annual-report/edit.blade.php');

        $this->assertStringContainsString('Content language', $index);
        $this->assertStringContainsString('$annual_report->language', $index);
        $this->assertStringContainsString('Creating a {{ strtoupper(app()->getLocale()) }} report.', $create);
        $this->assertStringContainsString('switch the admin editing language from the globe menu', $create);
        $this->assertStringContainsString('Editing the {{ strtoupper((string) ($annual_report->language', $edit);
        $this->assertStringContainsString('use the Translation Center', $edit);
    }

    public function test_events_and_news_manager_explains_each_managed_record_to_ordinary_editors(): void
    {
        $index = $this->adminView('notice-board/index.blade.php');

        $this->assertStringContainsString('Add event or news', $index);
        $this->assertStringContainsString('Search events and news', $index);
        $this->assertStringContainsString('<strong>Content format</strong>', $index);
        $this->assertStringContainsString('<strong>Relevant date</strong>', $index);
        $this->assertStringContainsString('Scheduled event', $index);
        $this->assertStringContainsString('News / publication', $index);
        $this->assertStringContainsString('$notice_board->content_kind === \'event\'', $index);
        $this->assertStringContainsString('$notice_board->event_start_at', $index);
        $this->assertStringContainsString('Event starts', $index);
        $this->assertStringContainsString('Published', $index);
        $this->assertStringNotContainsString("date('M d, Y', strtotime(@\$notice_board->published_at))", $index);
    }

    private function adminView(string $path): string
    {
        return (string) file_get_contents(resource_path('views/admin/'.$path));
    }
}
