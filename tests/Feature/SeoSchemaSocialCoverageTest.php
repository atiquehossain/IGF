<?php

namespace Tests\Feature;

use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoSchemaSocialCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'production', 'seo.robots.indexing_enabled' => true]);
    }

    public function test_generic_public_page_gets_content_grounded_schema_and_managed_social_fallback(): void
    {
        $this->makePage('schema-fallback', [
            'name' => 'Community Health',
            'sub_title' => '<strong>Practical care</strong> led by local volunteers.',
            'thumbnail' => null,
        ]);

        $response = $this->get('/page/schema-fallback')->assertOk();
        $head = Str::before($response->getContent(), '</head>');
        $fallback = url('/image/banner/slider-2-1588.webp');

        $this->assertStringContainsString('property="og:image" content="' . $fallback . '"', $head);
        $this->assertStringContainsString('name="twitter:image" content="' . $fallback . '"', $head);
        $this->assertStringContainsString(
            'property="og:image:alt" content="Children learning together with community support"',
            $head
        );
        $this->assertSame(1, substr_count($head, 'property="og:image"'));
        $this->assertSame(1, substr_count($head, 'name="twitter:image"'));

        $schema = $this->schemaFrom($head);
        $graph = collect($schema['@graph']);
        $page = $graph->firstWhere('@type', 'WebPage');

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertNotNull($graph->firstWhere('@type', 'NGO'));
        $this->assertNotNull($graph->firstWhere('@type', 'WebSite'));
        $this->assertSame('Community Health', $page['name']);
        $this->assertSame('Practical care led by local volunteers.', $page['description']);
        $this->assertSame(url('/page/schema-fallback'), $page['url']);
        $this->assertSame($fallback, $page['primaryImageOfPage']['url']);
        // The bundled 67 x 61 logo is below Google's 112 x 112 minimum and is
        // deliberately omitted instead of emitted as an invalid logo claim.
        $this->assertArrayNotHasKey('logo', $graph->firstWhere('@type', 'NGO'));

        $response->assertInertia(fn ($inertia) => $inertia
            ->where('seoDefaults.og_image', $fallback)
            ->where('seoDefaults.twitter_image', $fallback)
            ->where('seoSchemaIdentity.@context', 'https://schema.org')
        );
    }

    public function test_page_specific_social_image_and_explicit_schema_remain_authoritative(): void
    {
        $page = $this->makePage('owned-schema', ['name' => 'Owned story']);
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
            'title' => 'Owned story title',
            'og_image' => 'https://cdn.example.org/owned-story.jpg',
            'twitter_image' => 'https://cdn.example.org/owned-story-x.jpg',
            'robots_index' => true,
            'robots_follow' => true,
            'schema_markup' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => 'Owned story title',
            ],
        ]);

        $head = Str::before($this->get('/page/owned-schema')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString('property="og:image" content="https://cdn.example.org/owned-story.jpg"', $head);
        $this->assertStringContainsString('name="twitter:image" content="https://cdn.example.org/owned-story-x.jpg"', $head);
        $this->assertStringNotContainsString('property="og:image:alt"', $head);

        $schema = $this->schemaFrom($head);
        $this->assertSame('Article', $schema['@type']);
        $this->assertSame('Owned story title', $schema['headline']);
        $this->assertArrayNotHasKey('@graph', $schema);
    }

    public function test_owner_can_replace_the_brand_fallback_without_code_changes(): void
    {
        SiteSetting::create([
            'group' => 'branding',
            'key' => 'social_share_image',
            'locale' => '*',
            'value' => '/storage/media/managed-share.jpg',
            'type' => 'url_or_path',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'branding',
            'key' => 'social_share_image_alt',
            'locale' => 'en',
            'value' => 'Ignite volunteers working with a community',
            'type' => 'text',
            'is_public' => true,
        ]);
        $this->makePage('managed-social', ['thumbnail' => null]);

        $head = Str::before($this->get('/page/managed-social')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString(
            'property="og:image" content="' . url('/storage/media/managed-share.jpg') . '"',
            $head
        );
        $this->assertStringContainsString(
            'property="og:image:alt" content="Ignite volunteers working with a community"',
            $head
        );
    }

    public function test_external_canonical_does_not_create_a_false_local_webpage_node(): void
    {
        $page = $this->makePage('external-canonical');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
            'canonical_url' => 'https://publisher.example.org/canonical-story',
            'robots_index' => false,
            'robots_follow' => true,
        ]);

        $head = Str::before($this->get('/page/external-canonical')->assertOk()->getContent(), '</head>');
        $graph = collect($this->schemaFrom($head)['@graph']);

        $this->assertNotNull($graph->firstWhere('@type', 'NGO'));
        $this->assertNotNull($graph->firstWhere('@type', 'WebSite'));
        $this->assertNull($graph->firstWhere('@type', 'WebPage'));
    }

    public function test_event_schema_uses_supplied_physical_address_but_incomplete_online_event_stays_webpage(): void
    {
        $physical = $this->makeNotice('community-clinic', [
            'title' => 'Community clinic',
            'content_kind' => 'event',
            'event_start_at' => '2026-09-10 09:00:00',
            'event_status' => 'scheduled',
            'event_attendance_mode' => 'offline',
            'location' => 'Bawnia Community Centre, Dhaka',
        ]);
        $physicalSchema = $this->get('/event/' . $physical->slug)
            ->assertOk()
            ->viewData('page')['props']['contentSeo']['schema_markup'];
        $event = collect($physicalSchema['@graph'])->firstWhere('@type', 'Event');

        $this->assertSame('Bawnia Community Centre, Dhaka', $event['location']['name']);
        $this->assertSame('PostalAddress', $event['location']['address']['@type']);
        $this->assertSame('Bawnia Community Centre, Dhaka', $event['location']['address']['streetAddress']);

        $online = $this->makeNotice('online-briefing', [
            'title' => 'Online briefing',
            'content_kind' => 'event',
            'event_start_at' => '2026-09-11 09:00:00',
            'event_status' => 'scheduled',
            'event_attendance_mode' => 'online',
            'location' => null,
        ]);
        $onlineSchema = $this->get('/event/' . $online->slug)
            ->assertOk()
            ->viewData('page')['props']['contentSeo']['schema_markup'];
        $onlineGraph = collect($onlineSchema['@graph']);

        $this->assertNotNull($onlineGraph->firstWhere('@type', 'WebPage'));
        $this->assertNull($onlineGraph->firstWhere('@type', 'Event'));
        $this->assertNull($onlineGraph->firstWhere('@type', 'Article'));
    }

    /** @return array<string, mixed> */
    private function schemaFrom(string $head): array
    {
        preg_match('#<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>#s', $head, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'The head must contain one JSON-LD document.');

        return json_decode((string) $matches[1], true, 64, JSON_THROW_ON_ERROR);
    }

    private function makePage(string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'A public community story.',
            'slug' => $slug,
            'status' => 1,
            'language' => 'en',
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function makeNotice(string $slug, array $overrides = []): NoticeBoard
    {
        return NoticeBoard::create(array_merge([
            'title' => Str::headline($slug),
            'sub_title' => 'A public update.',
            'slug' => $slug,
            'content_kind' => 'article',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'status' => 1,
        ], $overrides));
    }
}
