<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminInterfaceLocalizationTest extends TestCase
{
    public function test_high_use_admin_surfaces_use_the_safe_admin_ui_catalogue(): void
    {
        $files = [
            'layouts/header.blade.php' => 'shell.skip_to_content',
            'layouts/navbar.blade.php' => 'shell.search_content',
            'layouts/sidebar.blade.php' => 'sidebar.items.content_hub',
            'dashboard/index.blade.php' => 'dashboard.overview',
            'page/builder-simple.blade.php' => "AdminUi::section('builder')",
            'site-settings/index.blade.php' => "AdminUi::text('customizer.",
            'transactional-email-templates/edit.blade.php' => 'AdminUi::text($key',
            'page_menu/index.blade.php' => "AdminUi::section('navigation')",
            'translation-center/index.blade.php' => "AdminUi::section('translations')",
        ];

        foreach ($files as $file => $needle) {
            $source = file_get_contents(resource_path('views/admin/'.$file));

            $this->assertIsString($source);
            $this->assertStringContainsString($needle, $source, $file);
        }
    }

    public function test_admin_catalogues_keep_matching_top_level_sections(): void
    {
        $english = require resource_path('lang/en/admin_ui.php');
        $bangla = require resource_path('lang/bn/admin_ui.php');

        $this->assertSame(array_keys($english), array_keys($bangla));
        foreach (['common', 'shell', 'sidebar', 'dashboard', 'navigation', 'translations', 'builder', 'customizer', 'email_templates'] as $section) {
            $this->assertArrayHasKey($section, $english);
            $this->assertArrayHasKey($section, $bangla);
        }

        foreach (['builder', 'customizer', 'email_templates'] as $section) {
            $this->assertSame(
                $this->catalogKeyPaths($english[$section]),
                $this->catalogKeyPaths($bangla[$section]),
                "The {$section} admin catalogue must expose the same key paths in English and Bangla."
            );
        }
    }

    /**
     * @return list<string>
     */
    private function catalogKeyPaths(array $copy, string $prefix = ''): array
    {
        $paths = [];

        foreach ($copy as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $paths[] = $path;

            if (is_array($value)) {
                array_push($paths, ...$this->catalogKeyPaths($value, $path));
            }
        }

        sort($paths);

        return $paths;
    }
}
