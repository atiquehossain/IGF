<?php

namespace Tests\Feature;

use App\Models\NoticeBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

class AdminFinalQaRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tinymce_keeps_required_textareas_synchronized_before_native_validation(): void
    {
        $source = file_get_contents(resource_path('views/admin/layouts/tinymce.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('var synchronizeTextarea = function()', $source);
        $this->assertStringContainsString('editor.save();', $source);
        $this->assertStringContainsString(
            "editor.on('init input change SetContent Undo Redo', synchronizeTextarea);",
            $source
        );
    }

    public function test_public_ziggy_payload_is_limited_to_routes_the_visitor_app_uses(): void
    {
        $appView = file_get_contents(resource_path('views/app.blade.php'));
        $routes = (new Ziggy('frontend'))->toArray()['routes'];

        $this->assertIsString($appView);
        $this->assertStringContainsString("@routes('frontend')", $appView);

        $literalClientRoutes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js')));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array($file->getExtension(), ['js', 'ts', 'vue'], true)) {
                continue;
            }
            preg_match_all('/(?:window\.)?route\(\s*[\'\"]([^\'\"]+)/', (string) file_get_contents($file->getPathname()), $matches);
            $literalClientRoutes = array_merge($literalClientRoutes, $matches[1]);
        }
        foreach (array_unique($literalClientRoutes) as $requiredRoute) {
            $this->assertArrayHasKey($requiredRoute, $routes, "The visitor bundle calls {$requiredRoute}, so it must exist in the public Ziggy group.");
        }

        foreach ([
            'frontend.home',
            'frontend.page',
            'frontend.donate.store',
            'api.frontend.comment',
            'login',
            'register',
            'showLogin',
            'search',
            'notice.download',
            'chat.bootstrap',
            'chat.conversations.store',
            'chat.messages.store',
        ] as $requiredRoute) {
            $this->assertArrayHasKey($requiredRoute, $routes, "The visitor app still needs {$requiredRoute}.");
        }

        foreach (array_keys($routes) as $routeName) {
            $this->assertFalse(
                str_starts_with($routeName, 'debugbar.')
                    || str_starts_with($routeName, 'ignition.')
                    || str_starts_with($routeName, 'cypress.')
                    || str_starts_with($routeName, 'passport.')
                    || str_starts_with($routeName, 'admin.'),
                "Private or development route {$routeName} must not be serialized publicly."
            );
        }

        $this->assertLessThan(100, count($routes));
        $this->assertLessThan(30_000, strlen(json_encode($routes, JSON_THROW_ON_ERROR)));
    }

    public function test_content_hub_keeps_filters_visible_without_burying_mobile_results(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('class="hub-active-filters" aria-label="Active content filters"', $source);
        $this->assertStringContainsString('{{ $activeFilterLabels->count() }} active', $source);
        $this->assertStringContainsString("if (mobileContentHub.matches) filterPanel.removeAttribute('open');", $source);
        $this->assertStringNotContainsString("filterPanel.dataset.activeFilters !== '1'", $source);
        $this->assertStringNotContainsString("filterPanel.dataset.activeFilters === '1'", $source);
    }

    public function test_legacy_notice_client_uses_a_real_filtered_public_endpoint(): void
    {
        NoticeBoard::create([
            'title' => 'Quarterly publication',
            'notice_type' => 'notice-board',
            'language' => 'en',
            'file_type' => 'pdf',
            'file_size' => '20 KB',
            'file_path' => 'quarterly.pdf',
            'published_at' => now()->subDay(),
            'status' => 1,
            'ip' => 'private-value',
        ]);
        NoticeBoard::create([
            'title' => 'Future publication',
            'notice_type' => 'notice-board',
            'language' => 'en',
            'file_type' => 'pdf',
            'file_path' => 'future.pdf',
            'published_at' => now()->addDay(),
            'status' => 1,
        ]);

        $response = $this->withHeader('locale', 'en')->getJson(route('api.frontend.notice', [
            'search' => 'Quarterly',
            'file_type' => 'pdf',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('properties.total_count', 1)
            ->assertJsonPath('data.items.0.title', 'Quarterly publication')
            ->assertJsonMissingPath('data.items.0.ip');
    }

    public function test_customizer_has_one_retryable_dirty_state_save_action_and_safe_hash_navigation(): void
    {
        $source = file_get_contents(resource_path('views/admin/site-settings/index.blade.php'));

        $this->assertIsString($source);
        preg_match_all('/\bid="customizer-save"/', $source, $saveIds);
        $this->assertCount(1, $saveIds[0]);
        $this->assertStringContainsString('form="customizer-form" disabled', $source);
        $this->assertStringContainsString('.customizer-save{position:sticky;z-index:50;top:73px', $source);
        $this->assertStringContainsString('let dirty = @json($errors->any())', $source);
        $this->assertStringContainsString('setDirty(dirty);', $source);
        $this->assertStringContainsString('document.getElementById(id)', $source);
        $this->assertStringNotContainsString('querySelector(hash)', $source);
        $this->assertStringNotContainsString('querySelector(window.location.hash)', $source);
    }

    public function test_builders_and_bulk_metadata_controls_expose_clear_save_and_field_contracts(): void
    {
        $advanced = file_get_contents(resource_path('views/admin/page/builder.blade.php'));
        $simple = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $bulkSeo = file_get_contents(resource_path('views/admin/seo/bulk.blade.php'));

        $this->assertIsString($advanced);
        $this->assertStringContainsString('id="save-page" disabled>Save page', $advanced);
        $this->assertStringContainsString('>Save section</button>', $advanced);
        $this->assertStringContainsString("savePageButton.disabled = !state.dirtyScopes.has('page');", $advanced);
        $this->assertStringContainsString('saveBlockButton.disabled = !state.dirtyScopes.has(blockScope());', $advanced);
        $this->assertStringContainsString('width:44px; height:44px;', $advanced);
        $this->assertStringContainsString('min-height:44px; border:1px solid #8c7163;', $advanced);

        $this->assertIsString($simple);
        $this->assertStringContainsString('role="textbox" aria-multiline="true" aria-labelledby=', $simple);
        $this->assertStringContainsString('<label for="${escapeHtml(id)}">${escapeHtml(label)}</label>', $simple);
        $this->assertStringContainsString('aria-label="Choose ${escapeHtml(label)} from existing pages"', $simple);
        $this->assertStringContainsString('.simple-page-settings summary,.simple-options summary{display:flex;min-height:44px;align-items:center}', $simple);

        $this->assertIsString($bulkSeo);
        foreach ([
            'aria-label="Metadata source for {{ $controlLabel }}"',
            'aria-label="Search title for {{ $controlLabel }}"',
            'aria-label="Search description for {{ $controlLabel }}"',
            'aria-label="Social image URL for {{ $controlLabel }}"',
            'aria-label="Show {{ $controlLabel }} in search"',
            'aria-label="Schema template for {{ $controlLabel }}"',
        ] as $accessibleName) {
            $this->assertStringContainsString($accessibleName, $bulkSeo);
        }
    }

    public function test_page_comment_screen_restores_cancelled_or_failed_toggles_and_names_its_controls(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/view.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('for="toggleCommentStatus"', $source);
        $this->assertStringContainsString('for="page-comments-order"', $source);
        $this->assertStringContainsString('for="page-comments-status"', $source);
        $this->assertStringContainsString('aria-labelledby="comment-publish-title"', $source);
        $this->assertStringContainsString('id="comment-publish-title"', $source);
        $this->assertStringContainsString('const restoreToggle = () =>', $source);
        $this->assertStringContainsString('if (!isConfirm)', $source);
        $this->assertStringContainsString('if (failed) restoreToggle();', $source);
        $this->assertStringNotContainsString('fa fa-magic', $source);
    }
}
