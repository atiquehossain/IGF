<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminCtaPreviewParityRegressionTest extends TestCase
{
    public function test_both_admin_previews_use_a_dedicated_generic_cta_branch_without_swallowing_special_variants(): void
    {
        $sources = $this->previewSources();
        $branches = [
            'simple' => $this->sourceSegmentMatching(
                $sources['simple'],
                "/if\\s*\\(\\s*block\\.type\\s*===\\s*'cta'\\s*&&\\s*!\\s*\\[\\s*'campaign'\\s*,\\s*'campus-actions'\\s*\\]\\.includes\\(\\s*c\\.variant\\s*\\)\\s*\\)/",
                "/\\n\\s*if\\s*\\(\\s*block\\.type\\s*===\\s*'testimonials'/"
            ),
            'advanced' => $this->sourceSegment(
                $sources['advanced'],
                "if (block.type === 'cta' && !['campaign', 'campus-actions'].includes(content.variant))",
                "\n        if (block.type === 'spacer')"
            ),
        ];

        foreach ($branches as $name => $branch) {
            $this->assertStringContainsString('aria-hidden="true"', $branch, "The {$name} CTA preview must hide its decorative signal from assistive technology.");
            $this->assertStringContainsString('primary_label', $branch, "The {$name} CTA preview must render the primary action.");
            $this->assertStringContainsString('secondary_label', $branch, "The {$name} CTA preview must render the secondary action.");
        }

        $this->assertStringContainsString('return `<section ${previewSectionMeta(block)}>', $branches['simple']);
        $this->assertStringContainsString(
            "simple-preview-block--\${String(block.type).replaceAll('_','-')}",
            $sources['simple']
        );
        $this->assertStringContainsString('class="simple-cta-panel"', $branches['simple']);
        $this->assertStringContainsString('class="simple-cta-signal"', $branches['simple']);
        $this->assertStringContainsString('class="simple-cta-content"', $branches['simple']);
        $this->assertStringContainsString('class="simple-cta-actions"', $branches['simple']);
        $this->assertStringContainsString("'primary_label','main button text'", $branches['simple']);
        $this->assertStringContainsString("'secondary_label','second button text'", $branches['simple']);

        $this->assertStringContainsString('class="igf-preview-block igf-preview-block--cta', $branches['advanced']);
        $this->assertStringContainsString('class="igf-preview-cta-panel"', $branches['advanced']);
        $this->assertStringContainsString('class="igf-preview-cta-signal"', $branches['advanced']);
        $this->assertStringContainsString('class="igf-preview-cta-content"', $branches['advanced']);
        $this->assertStringContainsString('class="igf-preview-cta-actions"', $branches['advanced']);
        $this->assertStringContainsString('class="igf-preview-cta-button"', $branches['advanced']);
        $this->assertStringContainsString('igf-preview-cta-button igf-preview-cta-button--outline', $branches['advanced']);
    }

    public function test_both_admin_previews_omit_empty_action_regions_and_expand_a_single_action(): void
    {
        $sources = $this->previewSources();
        $branches = [
            'simple' => [
                'source' => $this->sourceSegmentMatching(
                    $sources['simple'],
                    "/if\\s*\\(\\s*block\\.type\\s*===\\s*'cta'\\s*&&\\s*!\\s*\\[\\s*'campaign'\\s*,\\s*'campus-actions'\\s*\\]\\.includes\\(\\s*c\\.variant\\s*\\)\\s*\\)/",
                    "/\\n\\s*if\\s*\\(\\s*block\\.type\\s*===\\s*'testimonials'/"
                ),
                'actions' => 'simple-cta-actions',
                'no_actions_selector' => '.simple-cta-panel[data-actions=false]',
                'only_child_selector' => '.simple-cta-actions>:only-child',
                'button_selector' => '.simple-cta-actions .simple-preview-button',
            ],
            'advanced' => [
                'source' => $this->sourceSegment(
                    $sources['advanced'],
                    "if (block.type === 'cta' && !['campaign', 'campus-actions'].includes(content.variant))",
                    "\n        if (block.type === 'spacer')"
                ),
                'actions' => 'igf-preview-cta-actions',
                'no_actions_selector' => '.igf-preview-cta-panel[data-actions="false"]',
                'only_child_selector' => '.igf-preview-cta-actions>:only-child',
                'button_selector' => '.igf-preview-cta-button',
            ],
        ];

        foreach ($branches as $name => $preview) {
            $branch = $preview['source'];
            $this->assertStringContainsString('hasActions', $branch, "The {$name} preview must detect whether an action exists.");
            $this->assertStringContainsString('data-actions="${hasActions}"', $branch, "The {$name} preview must expose its action state to the layout.");
            $this->assertMatchesRegularExpression(
                '/\$\{hasActions\s*\?\s*`<div class="' . preg_quote($preview['actions'], '/') . '">\$\{primary\}\$\{secondary\}<\/div>`\s*:\s*\'\'\}/',
                $branch,
                "The {$name} preview must not render an empty action container."
            );
            $this->assertCssDeclarations($sources[$name], $preview['no_actions_selector'], [
                'grid-template-columns' => '64px minmax(0,1fr)',
            ]);
            $this->assertCssDeclarations($sources[$name], $preview['only_child_selector'], [
                'grid-column' => '1/-1',
            ]);
            $this->assertCssDeclarations($sources[$name], $preview['button_selector'], [
                'color' => '#1c1e20',
                'line-height' => '1.25',
                'overflow-wrap' => 'anywhere',
            ]);
        }
    }

    public function test_simple_cta_preview_tracks_the_public_layout_at_embedded_viewports(): void
    {
        $this->assertResponsiveCtaPreview($this->previewSources()['simple'], [
            'container' => '.simple-preview',
            'block' => '.simple-preview-block--cta',
            'panel' => '.simple-cta-panel',
            'signal' => '.simple-cta-signal',
            'content' => '.simple-cta-content',
            'actions' => '.simple-cta-actions',
            'button' => '.simple-cta-actions .simple-preview-button',
        ]);
    }

    public function test_advanced_cta_preview_tracks_the_public_layout_at_embedded_viewports(): void
    {
        $this->assertResponsiveCtaPreview($this->previewSources()['advanced'], [
            'container' => '.igf-builder__preview',
            'block' => '.igf-preview-block--cta',
            'panel' => '.igf-preview-cta-panel',
            'signal' => '.igf-preview-cta-signal',
            'content' => '.igf-preview-cta-content',
            'actions' => '.igf-preview-cta-actions',
            'button' => '.igf-preview-cta-button',
        ]);
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

    private function sourceSegment(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($source, $startNeedle);
        $this->assertNotFalse($start, "Missing source marker: {$startNeedle}");
        $end = strpos($source, $endNeedle, $start + strlen($startNeedle));
        $this->assertNotFalse($end, "Missing source marker: {$endNeedle}");

        return substr($source, $start, $end - $start);
    }

    private function sourceSegmentMatching(string $source, string $startPattern, string $endPattern): string
    {
        $startMatched = preg_match($startPattern, $source, $startMatch, PREG_OFFSET_CAPTURE);
        $this->assertSame(1, $startMatched, "Missing source pattern: {$startPattern}");
        $start = $startMatch[0][1];

        $endMatched = preg_match(
            $endPattern,
            $source,
            $endMatch,
            PREG_OFFSET_CAPTURE,
            $start + strlen($startMatch[0][0])
        );
        $this->assertSame(1, $endMatched, "Missing source pattern: {$endPattern}");

        return substr($source, $start, $endMatch[0][1] - $start);
    }

    /**
     * @param  array{container:string, block:string, panel:string, signal:string, content:string, actions:string, button:string}  $selectors
     */
    private function assertResponsiveCtaPreview(string $source, array $selectors): void
    {
        $normalized = preg_replace('/\s+/', '', $source);
        $this->assertStringContainsString(
            $selectors['container'] . '{container-type:inline-size',
            $normalized,
            'CTA preview breakpoints must respond to the embedded canvas width.'
        );

        $this->assertCssDeclarations($source, $selectors['panel'], [
            'display' => 'grid',
            'grid-template-columns' => '64px minmax(0,1fr) minmax(205px,auto)',
            'overflow' => 'hidden',
        ]);
        $this->assertCssDeclarations($source, $selectors['signal'], [
            'width' => '64px',
            'height' => '64px',
        ]);
        $this->assertCssDeclarations($source, $selectors['content'], [
            'min-width' => '0',
        ]);
        $this->assertCssDeclarations($source, $selectors['actions'], [
            'display' => 'grid',
            'min-width' => '0',
            'gap' => '10px',
        ]);
        $this->assertCssDeclarations($source, $selectors['button'], [
            'min-width' => '0',
            'min-height' => '48px',
            'overflow-wrap' => 'anywhere',
        ]);

        $tabletStart = strpos($normalized, '@container(max-width:960px){');
        $mobileStart = strpos($normalized, '@container(max-width:520px){', $tabletStart === false ? 0 : $tabletStart);
        $this->assertNotFalse($tabletStart, 'The CTA preview needs a tablet-width container rule.');
        $this->assertNotFalse($mobileStart, 'The CTA preview needs a mobile-width container rule.');
        $tablet = substr($normalized, $tabletStart, $mobileStart - $tabletStart);
        $mobile = substr($normalized, $mobileStart, 1000);

        $this->assertStringContainsString(
            $selectors['panel'] . '{grid-template-columns:58pxminmax(0,1fr)',
            $tablet,
            'The tablet preview must move CTA actions below the copy without overflowing.'
        );
        $this->assertStringContainsString(
            $selectors['actions'] . '{grid-column:2;grid-template-columns:repeat(2,minmax(0,1fr))',
            $tablet,
            'The tablet preview must keep both actions visible side by side.'
        );
        $this->assertStringContainsString(
            $selectors['block'] . '{padding:30px14px',
            $mobile,
            'The mobile preview must reduce the section gutter.'
        );
        $this->assertStringContainsString(
            $selectors['panel'] . '{grid-template-columns:1fr',
            $mobile,
            'The mobile preview must use one content column.'
        );
        $this->assertStringContainsString(
            $selectors['actions'] . '{grid-column:auto;grid-template-columns:1fr',
            $mobile,
            'The mobile preview must stack both actions.'
        );
    }

    /**
     * @param  array<string, string>  $expected
     */
    private function assertCssDeclarations(string $source, string $selector, array $expected): void
    {
        $actual = $this->cssDeclarations($source, $selector);

        foreach ($expected as $property => $value) {
            $this->assertArrayHasKey($property, $actual, "{$selector} must define {$property}.");
            $this->assertSame(
                preg_replace('/\s+/', '', $value),
                preg_replace('/\s+/', '', $actual[$property]),
                "{$selector} must keep the public {$property} value."
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function cssDeclarations(string $source, string $selector): array
    {
        $matched = preg_match(
            '/(?:^|[{},])\s*' . preg_quote($selector, '/') . '\s*\{(?<body>[^}]*)\}/s',
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
}
