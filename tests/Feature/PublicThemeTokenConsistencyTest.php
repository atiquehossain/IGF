<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicThemeTokenConsistencyTest extends TestCase
{
    public function test_the_audit_covers_every_public_page_and_layout_component(): void
    {
        $components = $this->publicComponents();

        $this->assertGreaterThanOrEqual(46, count($components));
        $this->assertArrayHasKey('Pages/Home/home.vue', $components);
        $this->assertArrayHasKey('Pages/about.vue', $components);
        $this->assertArrayHasKey('Pages/donate.vue', $components);
        $this->assertArrayHasKey('Pages/auth/login.vue', $components);
        $this->assertArrayHasKey('layouts/App.vue', $components);
        $this->assertArrayHasKey('layouts/AppNav.vue', $components);
        $this->assertArrayHasKey('layouts/GuestLayout.vue', $components);
    }

    public function test_public_components_do_not_redeclare_managed_palette_aliases_with_literal_colors(): void
    {
        $literalAlias = '/--(?:igf-(?:primary|accent|ink|surface)|orange|brown|ink|surface|muted|line|accent|primary|action(?:-orange)?(?:-hover)?|brand-(?:soft(?:-strong)?|border)|about-(?:primary|accent|ink|surface))\s*:\s*#[\da-f]{3,8}\b/i';

        foreach ($this->publicComponents() as $component => $path) {
            $source = File::get($path);

            $this->assertDoesNotMatchRegularExpression(
                $literalAlias,
                $source,
                $component . ' must inherit managed palette aliases instead of freezing a local hex color.'
            );
        }
    }

    public function test_canonical_palette_literals_only_appear_as_managed_fallbacks(): void
    {
        $canonicalColors = ['#ff7500', '#9c4500', '#191c1d', '#f8f9fa'];

        foreach ($this->publicComponents() as $component => $path) {
            $source = File::get($path);
            $withoutManagedFallbacks = preg_replace(
                '/var\(\s*--[\w-]+\s*,\s*#(?:ff7500|9c4500|191c1d|f8f9fa)\s*\)/i',
                '',
                $source
            );

            // The keyboard skip link intentionally uses fixed white on a fixed dark surface.
            if ($component === 'layouts/App.vue') {
                $withoutManagedFallbacks = str_replace('background: #191c1d;', '', $withoutManagedFallbacks);
            }

            foreach ($canonicalColors as $color) {
                $this->assertStringNotContainsString(
                    $color,
                    strtolower($withoutManagedFallbacks),
                    $component . ' contains a canonical palette literal outside a managed CSS-variable fallback.'
                );
            }
        }
    }

    public function test_legacy_brand_variants_are_absent_from_every_public_component(): void
    {
        $legacyColors = [
            '#e95d00', '#d85d00', '#a44906', '#9f4200', '#8f3f03', '#843a03', '#783300',
            '#ffad72', '#ffb070', '#ff9140', '#ff9a4b', '#ff7828', '#ff9b48', '#ffb174',
            '#ffc08c', '#ffad6a', '#ffd2af', '#592900', '#895126', '#8b3e08', '#7b3a08',
            '#6f2f00', '#b65a14', '#5b4a3f',
        ];

        foreach ($this->publicComponents() as $component => $path) {
            $source = strtolower(File::get($path));

            foreach ($legacyColors as $color) {
                $this->assertStringNotContainsString(
                    $color,
                    $source,
                    $component . ' must derive brand variants with color-mix() from a managed token.'
                );
            }

            $this->assertDoesNotMatchRegularExpression(
                '/rgba\(\s*(?:255\s*,\s*117\s*,\s*0|156\s*,\s*69\s*,\s*0)\s*,/i',
                $source,
                $component . ' contains an RGB brand shadow/tint that will not follow theme customization.'
            );
        }
    }

    public function test_managed_brand_backgrounds_use_their_computed_foreground_tokens(): void
    {
        $hardCodedForegroundOnManagedBackground = '/\{(?=[^{}]*background(?:-color)?\s*:\s*var\(--(?:igf-(?:primary|accent)|orange|brown|action(?:-orange)?(?:-hover)?|about-(?:primary|accent)|footer-primary)\b)(?=[^{}]*\bcolor\s*:\s*#(?:fff(?:fff)?|000(?:000)?)\b)[^{}]*\}/i';

        foreach ($this->publicComponents() as $component => $path) {
            $source = File::get($path);

            $this->assertDoesNotMatchRegularExpression(
                $hardCodedForegroundOnManagedBackground,
                $source,
                $component . ' hard-codes black/white on a managed brand background; use --igf-on-primary or --igf-on-accent.'
            );
        }
    }

    public function test_public_layouts_publish_computed_foregrounds_to_both_shells(): void
    {
        $utility = File::get(resource_path('js/Shared/utils/themeColors.js'));
        $app = File::get(resource_path('js/layouts/App.vue'));
        $guest = File::get(resource_path('js/layouts/GuestLayout.vue'));

        $this->assertStringContainsString("'--igf-on-primary': contrastForeground(primary)", $utility);
        $this->assertStringContainsString("'--igf-on-accent': contrastForeground(accent)", $utility);
        $this->assertStringContainsString('managedThemeTokens(inertiaPage.props?.siteSettings?.theme)', $app);
        $this->assertStringContainsString('managedThemeTokens(inertiaPage.props?.siteSettings?.theme)', $guest);
        $this->assertStringContainsString('color:var(--igf-on-primary)', $app);
    }

    /**
     * @return array<string, string>
     */
    private function publicComponents(): array
    {
        $resourceRoot = str_replace('\\', '/', resource_path('js')) . '/';
        $components = [];

        foreach ([resource_path('js/Pages'), resource_path('js/layouts')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (strtolower($file->getExtension()) !== 'vue') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $components[substr($path, strlen($resourceRoot))] = $file->getPathname();
            }
        }

        ksort($components);

        return $components;
    }
}
