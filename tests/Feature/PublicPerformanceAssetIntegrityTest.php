<?php

namespace Tests\Feature;

use App\Models\PageBlock;
use App\Services\PageBlockContentResolver;
use App\Services\PublicImageOptimizationService;
use Tests\TestCase;

class PublicPerformanceAssetIntegrityTest extends TestCase
{
    public function test_fallback_banner_variants_are_present_and_materially_smaller_than_the_originals(): void
    {
        foreach (['slider-1', 'slider-2'] as $slide) {
            $original = public_path("image/banner/{$slide}.png");
            $this->assertFileExists($original);
            $this->assertGreaterThan(1_500_000, filesize($original));

            foreach ([640, 1024, 1588] as $width) {
                foreach (['avif', 'webp'] as $format) {
                    $variant = public_path("image/banner/{$slide}-{$width}.{$format}");
                    $this->assertFileExists($variant);
                    $this->assertGreaterThan(0, filesize($variant));
                    $this->assertLessThan(filesize($original), filesize($variant));
                }
            }
        }
    }

    public function test_live_homepage_variants_are_complete_and_mobile_candidates_stay_small(): void
    {
        $directory = storage_path('app/public/media/ignite-live');
        $variants = glob($directory . '/*-perf-*.webp') ?: [];
        $mobile = glob($directory . '/*-perf-640.webp') ?: [];

        $this->assertCount(32, $variants);
        $this->assertCount(9, $mobile);
        foreach ($variants as $variant) {
            $this->assertGreaterThan(0, filesize($variant), basename($variant));
        }
        foreach ($mobile as $variant) {
            $this->assertLessThan(80_000, filesize($variant), basename($variant));
        }
    }

    public function test_public_runtime_avoids_the_known_duplicate_and_unused_global_payloads(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $styles = file_get_contents(resource_path('scss/app.scss'));
        $vuetify = file_get_contents(resource_path('js/vuetify.js'));

        $this->assertStringNotContainsString('bootstrap-vue-next', $app);
        $this->assertStringNotContainsString("import 'bootstrap';", $app);
        $this->assertStringNotContainsString('vue3-datepicker', $app);
        $this->assertStringNotContainsString("from 'aos'", $app);
        $this->assertStringNotContainsString('vuetify/dist/vuetify.min.css', $styles);
        $this->assertStringNotContainsString('vue-toastification/dist/index.css', $styles);
        $this->assertStringNotContainsString("import * as components from 'vuetify/components'", $vuetify);
        $this->assertStringContainsString('VApp,', $vuetify);
        $this->assertStringContainsString('VTextarea,', $vuetify);
    }

    public function test_page_blocks_defer_inactive_hero_backgrounds_and_below_fold_images(): void
    {
        $source = file_get_contents(resource_path('js/Shared/PageBlocks.vue'));

        $this->assertStringContainsString(
            ':style="slideIndex === activeHeroIndex(block) ? heroBackgroundStyle(slide) : undefined"',
            $source
        );
        $this->assertStringContainsString('--igf-hero-image-set-small', $source);
        $this->assertStringContainsString('loading="lazy" decoding="async"', $source);
        $this->assertStringContainsString('responsiveImagePresentation', $source);
    }

    public function test_server_presentation_replaces_legacy_bundled_png_references(): void
    {
        config(['app.url' => 'https://ignite.test']);

        $images = app(PublicImageOptimizationService::class);
        $this->assertSame(
            '/image/banner/slider-1-1588.webp',
            $images->optimizedFallback('/image/banner/slider-1.png')
        );
        $this->assertSame(
            '/image/banner/slider-2-1588.webp',
            $images->replaceBundledReferences(['slide' => ['image' => '/image/banner/slider-2.png']])['slide']['image']
        );
        $this->assertSame(
            '/image/banner/slider-1-1588.webp',
            $images->optimizedFallback('https://ignite.test/image/banner/slider-1.png')
        );
        $this->assertSame(
            'https://cdn.example/image/banner/slider-1.png',
            $images->optimizedFallback('https://cdn.example/image/banner/slider-1.png')
        );
        $this->assertSame(
            '//ignite.test/image/banner/slider-1.png',
            $images->optimizedFallback('//ignite.test/image/banner/slider-1.png')
        );

        $block = new PageBlock([
            'type' => 'hero',
            'content' => [
                'image' => '/image/banner/slider-1.png',
                'slides' => [['image' => '/image/banner/slider-2.png']],
            ],
        ]);
        $resolved = app(PageBlockContentResolver::class)->resolve($block);

        $this->assertSame('/image/banner/slider-1-1588.webp', $resolved['image']);
        $this->assertSame('/image/banner/slider-2-1588.webp', $resolved['slides'][0]['image']);
        $this->assertSame('/image/banner/slider-2-1588.webp', config('site-settings.groups.branding.fields.social_share_image.default'));
    }
}
