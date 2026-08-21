<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\SeoMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoMetadataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_seo_has_safe_fallbacks_and_can_be_fully_overridden(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Clean Water',
            'sub_title' => '<strong>Safe water</strong> for every community.',
            'slug' => 'clean-water',
            'meta_keyword' => 'water, community',
            'status' => 1,
            'language' => 'en',
        ]);
        $service = app(SeoMetadataService::class);

        $fallback = $service->metaForPage($page);
        $this->assertSame('Clean Water', $fallback['meta_title']);
        $this->assertSame('Safe water for every community.', $fallback['meta_description']);
        $this->assertSame('index,follow', $fallback['robots']);

        $service->updateForPage($page, [
            'title' => 'Clean Water Projects | Ignite Global Foundation',
            'description' => 'See how Ignite delivers safe water projects.',
            'focus_keyword' => 'clean water projects',
            'canonical_url' => 'https://ignite.test/clean-water',
            'robots_index' => false,
            'robots_follow' => true,
            'og_title' => 'Water changes everything',
            'twitter_card' => 'summary_large_image',
            'schema_markup' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
            ], JSON_THROW_ON_ERROR),
        ]);

        $meta = $service->metaForPage($page->fresh());
        $this->assertSame('Clean Water Projects | Ignite Global Foundation', $meta['meta_title']);
        $this->assertSame('noindex,follow', $meta['robots']);
        $this->assertSame('Water changes everything', $meta['og_title']);
        $this->assertSame('WebPage', $meta['schema_markup']['@type']);
    }
}
