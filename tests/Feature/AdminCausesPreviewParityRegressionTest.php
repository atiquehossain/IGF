<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminCausesPreviewParityRegressionTest extends TestCase
{
    public function test_both_admin_previews_default_missing_category_slugs_without_treating_empty_as_missing(): void
    {
        foreach ($this->previewSources() as $name => $source) {
            preg_match_all(
                "/if\\s*\\(source\\s*===\\s*'category'\\)\\s*\\{(?<body>.*?)\\n\\s*\\}/s",
                $source,
                $categoryFilters
            );

            $body = collect($categoryFilters['body'] ?? [])->first(
                fn (string $candidate): bool => str_contains($candidate, 'items = items.filter')
            ) ?? '';

            $this->assertNotSame(
                '',
                $body,
                "The {$name} preview must always scope category-backed cards."
            );
            $this->assertStringContainsString(
                "const categorySlug = String(block.content?.category_slug ?? 'our-causes').trim();",
                $body,
                "The {$name} preview must match the public resolver's our-causes default while preserving an explicit empty value."
            );
            $this->assertStringContainsString(
                'items = items.filter(item => item.category === categorySlug);',
                $body,
                "The {$name} preview must apply its normalized category instead of showing unrelated pages."
            );
        }
    }

    public function test_simple_focus_area_preview_tracks_public_card_scale_and_viewports(): void
    {
        $source = $this->previewSources()['simple'];

        $this->assertStringContainsString(
            '.simple-preview{container-type:inline-size',
            preg_replace('/\s+/', '', $source),
            'Container queries must respond to the embedded preview width.'
        );
        $this->assertCssDeclarations($source, '.simple-focus-grid', [
            'grid-template-columns' => 'repeat(3,minmax(0,1fr))',
            'gap' => '18px',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-tile', [
            'min-width' => '0',
            'min-height' => '390px',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-heading', [
            'container-type' => 'inline-size',
            'padding' => 'clamp(28px,4.4cqw,46px)',
            'border-radius' => '16px',
            'background' => 'var(--orange)',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-heading h2', [
            'font-size' => 'clamp(30px,10.5cqi,44px)',
            'line-height' => '1.08',
            'overflow-wrap' => 'anywhere',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-card', [
            'padding' => 'clamp(26px,3.6cqw,38px)',
            'border-radius' => '16px',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-card__visual', [
            'width' => '72px',
            'height' => '72px',
            'margin-bottom' => '28px',
            'border-radius' => '16px',
        ]);
        $heading = $this->cssDeclarations($source, '.simple-focus-card h3');
        $this->assertCssValues($heading, [
            'font' => "700 clamp(24px,2.95cqw,31px)/1.22 'Literata',Georgia,serif",
        ], '.simple-focus-card h3');
        $this->assertArrayNotHasKey('text-transform', $heading, 'Program names must retain their authored capitalization.');
        $this->assertCssDeclarations($source, '.simple-focus-card p', [
            'font-size' => '16px',
            'line-height' => '1.65',
        ]);
        $this->assertCssDeclarations($source, '.simple-focus-card__link', [
            'width' => 'fit-content',
            'margin-top' => 'auto',
            'padding' => '8px 14px',
            'border' => '1px dashed currentColor',
            'border-radius' => '999px',
        ]);
        $this->assertResponsiveContainerRules($source, '.simple-focus-grid', '.simple-focus-tile');
    }

    public function test_advanced_focus_area_preview_tracks_public_card_scale_and_viewports(): void
    {
        $source = $this->previewSources()['advanced'];

        $this->assertStringContainsString(
            '.igf-builder__preview{container-type:inline-size',
            preg_replace('/\s+/', '', $source),
            'Container queries must respond to the embedded preview width.'
        );
        $this->assertCssDeclarations($source, '.igf-preview-focus-grid', [
            'grid-template-columns' => 'repeat(3,minmax(0,1fr))',
            'gap' => '18px',
        ]);
        $this->assertCssDeclarations($source, '.igf-preview-focus-tile', [
            'min-width' => '0',
            'min-height' => '390px',
        ]);
        $this->assertCssDeclarations($source, '.igf-preview-focus-heading', [
            'container-type' => 'inline-size',
            'padding' => 'clamp(28px,4.4cqw,46px)',
            'border-radius' => '16px',
            'background' => 'var(--igf-orange)',
        ]);
        $this->assertCssDeclarations($source, '.igf-preview-focus-heading h2', [
            'font-size' => 'clamp(30px,10.5cqi,44px)',
            'line-height' => '1.08',
            'overflow-wrap' => 'anywhere',
        ]);
        $this->assertCssDeclarations($source, '.igf-preview-focus-card', [
            'padding' => 'clamp(26px,3.6cqw,38px)',
            'border-radius' => '16px',
        ]);
        foreach (['.igf-preview-focus-card img', '.igf-preview-focus-card>i'] as $visualSelector) {
            $this->assertCssDeclarations($source, $visualSelector, [
                'width' => '72px',
                'height' => '72px',
                'margin-bottom' => '28px',
                'border-radius' => '16px',
            ]);
        }
        $heading = $this->cssDeclarations($source, '.igf-preview-focus-card h3');
        $this->assertCssValues($heading, [
            'font' => "700 clamp(24px,2.95cqw,31px)/1.22 'Literata',serif",
        ], '.igf-preview-focus-card h3');
        $this->assertArrayNotHasKey('text-transform', $heading, 'Program names must retain their authored capitalization.');
        $this->assertCssDeclarations($source, '.igf-preview-focus-card p', [
            'font-size' => '16px',
            'line-height' => '1.65',
        ]);
        $this->assertCssDeclarations($source, '.igf-preview-focus-card span', [
            'width' => 'fit-content',
            'margin-top' => 'auto',
            'padding' => '8px 14px',
            'border' => '1px dashed currentColor',
            'border-radius' => '999px',
            'background' => 'transparent',
        ]);
        $this->assertResponsiveContainerRules($source, '.igf-preview-focus-grid', '.igf-preview-focus-tile');
    }

    /**
     * @return array{simple:string, advanced:string}
     */
    private function previewSources(): array
    {
        return [
            'simple' => (string) file_get_contents(resource_path('views/admin/page/builder-simple.blade.php')),
            'advanced' => (string) file_get_contents(resource_path('views/admin/page/builder.blade.php')),
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    private function assertCssDeclarations(string $source, string $selector, array $expected): void
    {
        $this->assertCssValues($this->cssDeclarations($source, $selector), $expected, $selector);
    }

    /**
     * @return array<string, string>
     */
    private function cssDeclarations(string $source, string $selector): array
    {
        $matched = preg_match(
            '/(?:^|[{},])\\s*' . preg_quote($selector, '/') . '\\s*\\{(?<body>[^}]*)\\}/s',
            $source,
            $rule
        );
        $this->assertSame(1, $matched, "Missing CSS rule for {$selector}.");

        $declarations = [];
        foreach (preg_split('/;/', $rule['body'] ?? '') ?: [] as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $declarations[trim($property)] = trim($value);
        }

        return $declarations;
    }

    /**
     * @param  array<string, string>  $actual
     * @param  array<string, string>  $expected
     */
    private function assertCssValues(array $actual, array $expected, string $selector): void
    {
        foreach ($expected as $property => $value) {
            $this->assertArrayHasKey($property, $actual, "{$selector} must define {$property}.");
            $this->assertSame(
                preg_replace('/\\s+/', '', $value),
                preg_replace('/\\s+/', '', $actual[$property]),
                "{$selector} must keep the public {$property} value."
            );
        }
    }

    private function assertResponsiveContainerRules(string $source, string $gridSelector, string $tileSelector): void
    {
        $normalized = preg_replace('/\s+/', '', $source);

        $tabletRule = '/@container\(max-width:960px\)\{' . preg_quote($gridSelector, '/')
            . '\{grid-template-columns:repeat\(2,minmax\(0,1fr\)\);?\}\}/';
        $mobileRule = '/@container\(max-width:560px\)\{' . preg_quote($gridSelector, '/')
            . '\{grid-template-columns:1fr;?\}' . preg_quote($tileSelector, '/') . '\{min-height:320px;?\}\}/';

        $this->assertTrue(
            preg_match($tabletRule, $normalized) === 1,
            'The preview must collapse to two columns based on its own width, not the browser window.'
        );
        $this->assertTrue(
            preg_match($mobileRule, $normalized) === 1,
            'The preview must collapse to one mobile-sized column based on its own width.'
        );
    }
}
