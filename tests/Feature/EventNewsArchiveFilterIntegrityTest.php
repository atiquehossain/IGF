<?php

namespace Tests\Feature;

use App\Models\NoticeBoard;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventNewsArchiveFilterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_and_news_are_separate_honest_archives_and_legacy_kind_queries_redirect(): void
    {
        $event = $this->publication([
            'title' => 'Community gathering',
            'slug' => 'community-gathering-filter',
            'content_kind' => 'event',
            'event_start_at' => now()->addMonth(),
            'order_by' => 30,
        ]);
        $article = $this->publication([
            'title' => 'Education field update',
            'slug' => 'education-field-update-filter',
            'content_kind' => 'article',
            'order_by' => 20,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events')
                ->where('archive_kind', 'event')
                ->where('archive_route', 'frontend.events')
                ->where('title', 'Events')
                ->where('archive_presentation.listing_label', 'Published events')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $event->id)
                ->missing('data.items.1')
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'index,follow')
                ->where('contentSeo.meta_title', 'Events | Ignite Global Foundation')
            );

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'article')
                ->where('archive_route', 'frontend.news')
                ->where('title', 'Latest news')
                ->where('archive_presentation.card_eyebrow', 'News')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $article->id)
                ->missing('data.items.1')
                ->where('contentSeo.canonical_url', url('/news'))
                ->where('contentSeo.robots', 'index,follow')
                ->where('contentSeo.meta_title', 'Latest news | Ignite Global Foundation')
            );

        $this->get('/events?kind=article')
            ->assertMovedPermanently()
            ->assertRedirect('/news');
        $this->get('/events?kind=event')
            ->assertMovedPermanently()
            ->assertRedirect('/events');
    }

    public function test_invalid_kind_is_a_noindex_event_variant_while_valid_legacy_kind_discards_unrelated_queries(): void
    {
        $event = $this->publication([
            'title' => 'Public event',
            'slug' => 'public-event-invalid-kind',
            'content_kind' => 'event',
            'order_by' => 20,
        ]);
        $article = $this->publication([
            'title' => 'Public news',
            'slug' => 'public-news-invalid-kind',
            'content_kind' => 'article',
            'order_by' => 10,
        ]);

        $this->get('/events?kind=video')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $event->id)
                ->missing('data.items.1')
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'noindex,follow')
            );

        $this->get('/events?kind[]=event')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('properties.total_count', 1)
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'noindex,follow')
            );

        $this->get('/events?kind=event&preview=true')
            ->assertMovedPermanently()
            ->assertRedirect('/events');
    }

    public function test_public_archive_and_detail_expose_only_their_explicit_view_contracts(): void
    {
        $event = $this->publication([
            'title' => 'Public contract event',
            'slug' => 'public-contract-event',
            'content_kind' => 'event',
            'description' => '<p>Safe public body</p><script>alert(1)</script>',
            'inline_css' => '.safe{color:#123456} @import url(https://example.test/leak.css);',
            'image_path' => 'event.jpg',
            'file_path' => 'private-event.pdf',
            'url' => 'https://internal.example.test/source',
            'ip' => '192.0.2.50',
            'order_by' => 77,
        ]);

        $privateFields = [
            'translation_key', 'language', 'notice_type', 'file_type', 'file_size',
            'image_path', 'file_path', 'url', 'ip', 'order_by', 'status',
            'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at',
        ];

        $this->get('/events')
            ->assertOk()
            ->assertInertia(function ($page) use ($event, $privateFields): void {
                $page
                    ->where('data.items.0.id', $event->id)
                    ->where('data.items.0.uuid', $event->translation_key)
                    ->where('data.items.0.image_url', '/storage/photos/1/notice_board/event.jpg')
                    ->missing('data.items.0.description')
                    ->missing('data.items.0.inline_css');

                foreach ($privateFields as $field) {
                    $page->missing("data.items.0.{$field}");
                }
            });

        $this->get('/event/public-contract-event')
            ->assertOk()
            ->assertInertia(function ($page) use ($event, $privateFields): void {
                $page
                    ->where('data.event.id', $event->id)
                    ->where('data.event.uuid', $event->translation_key)
                    ->where('data.event.description', '<p>Safe public body</p>')
                    ->where('data.event.inline_css', '.safe{color:#123456}')
                    ->where('data.event.image_url', '/storage/photos/1/notice_board/event.jpg');

                foreach ($privateFields as $field) {
                    $page->missing("data.event.{$field}");
                }
            });
    }

    public function test_news_archive_is_latest_first_even_when_an_older_story_has_higher_manual_priority(): void
    {
        $older = $this->publication([
            'title' => 'Older promoted news',
            'slug' => 'older-promoted-news',
            'content_kind' => 'article',
            'published_at' => now()->subMonths(2),
            'order_by' => 999,
        ]);
        $newer = $this->publication([
            'title' => 'Newest published news',
            'slug' => 'newest-published-news',
            'content_kind' => 'article',
            'published_at' => now()->subHour(),
            'order_by' => 1,
        ]);

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('data.items.0.id', $newer->id)
                ->where('data.items.1.id', $older->id)
            );
    }

    public function test_news_archive_wording_is_localized_and_editable_without_code(): void
    {
        foreach ([
            'news_archive_title' => 'Stories selected by our team',
            'news_archive_introduction' => 'Fresh reporting from every program.',
            'news_archive_listing_label' => 'Selected public stories',
            'news_archive_empty_title' => 'No selected stories',
            'news_archive_empty_body' => 'Please return after our next update.',
            'news_archive_card_eyebrow' => 'From the field',
        ] as $key => $value) {
            SiteSetting::create([
                'group' => 'content_archives',
                'key' => $key,
                'locale' => 'en',
                'value' => $value,
                'type' => str_contains($key, 'introduction') ? 'textarea' : 'text',
                'is_public' => true,
            ]);
        }

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('title', 'Stories selected by our team')
                ->where('archive_presentation.introduction', 'Fresh reporting from every program.')
                ->where('archive_presentation.listing_label', 'Selected public stories')
                ->where('archive_presentation.empty_title', 'No selected stories')
                ->where('archive_presentation.empty_body', 'Please return after our next update.')
                ->where('archive_presentation.card_eyebrow', 'From the field')
                ->where('contentSeo.meta_description', 'Fresh reporting from every program.')
            );
    }

    public function test_bangla_archive_and_detail_schema_use_localized_breadcrumb_names(): void
    {
        TranslationLocale::query()->updateOrCreate(['locale' => 'en'], [
            'name' => 'English',
            'native_name' => 'English',
            'is_default' => true,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        TranslationLocale::query()->updateOrCreate(['locale' => 'bn'], [
            'name' => 'Bangla',
            'native_name' => 'বাংলা',
            'is_default' => false,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $article = $this->publication([
            'title' => 'মাঠের সংবাদ',
            'slug' => 'bangla-schema-story',
            'content_kind' => 'article',
            'language' => 'bn',
        ]);

        $archiveResponse = $this->get('/news?lang=bn')->assertOk();
        $archiveGraph = collect($archiveResponse->viewData('page')['props']['contentSeo']['schema_markup']['@graph']);
        $this->assertSame(
            ['হোম', 'সর্বশেষ সংবাদ'],
            collect($archiveGraph->firstWhere('@type', 'BreadcrumbList')['itemListElement'])
                ->pluck('name')
                ->all(),
        );

        $detailResponse = $this->get('/event/' . $article->slug . '?lang=bn')->assertOk();
        $detailGraph = collect($detailResponse->viewData('page')['props']['contentSeo']['schema_markup']['@graph']);
        $this->assertSame(
            ['হোম', 'সর্বশেষ সংবাদ', 'মাঠের সংবাদ'],
            collect($detailGraph->firstWhere('@type', 'BreadcrumbList')['itemListElement'])
                ->pluck('name')
                ->all(),
        );
    }

    public function test_event_archive_keeps_pagination_canonical_and_language_alternates(): void
    {
        foreach (range(1, 13) as $index) {
            $this->publication([
                'title' => "Filtered event {$index}",
                'slug' => "filtered-event-{$index}",
                'content_kind' => 'event',
                'order_by' => $index,
            ]);
        }
        $this->publication([
            'title' => 'News outside the event archive',
            'slug' => 'news-outside-event-archive',
            'content_kind' => 'article',
            'order_by' => 100,
        ]);

        $this->get('/events?page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('properties.events', 2)
                ->where('properties.total_page', 2)
                ->where('properties.total_count', 13)
                ->has('data.items', 1)
                ->where('contentSeo.canonical_url', url('/events?page=2'))
                ->where('contentSeo.robots', 'index,follow')
                ->where('contentSeo.meta_title', 'Events | Ignite Global Foundation — Page 2')
                ->where('seoAlternates.links.0.url', url('/events?page=2'))
            );
    }

    public function test_bangla_event_pagination_uses_a_localized_page_title_suffix(): void
    {
        $this->enableEnglishAndBangla();
        foreach (range(1, 13) as $index) {
            $this->publication([
                'title' => "বাংলা ইভেন্ট {$index}",
                'slug' => "bangla-filtered-event-{$index}",
                'content_kind' => 'event',
                'language' => 'bn',
                'order_by' => $index,
            ]);
        }

        $this->get('/events?lang=bn&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('properties.events', 2)
                ->where('contentSeo.canonical_url', url('/events?lang=bn&page=2'))
                ->where('contentSeo.meta_title', 'ইভেন্ট | Ignite Global Foundation — পৃষ্ঠা ২')
                ->where('contentSeo.og_title', 'ইভেন্ট | Ignite Global Foundation — পৃষ্ঠা ২')
                ->where('contentSeo.twitter_title', 'ইভেন্ট | Ignite Global Foundation — পৃষ্ঠা ২')
                ->where('contentSeo.robots', 'index,follow')
            );
    }

    public function test_unsupported_or_array_language_values_are_noindex_variants_of_the_event_archive(): void
    {
        $event = $this->publication([
            'title' => 'Language safety event',
            'slug' => 'language-safety-event',
            'content_kind' => 'event',
        ]);

        foreach (['/events?lang=zz', '/events?lang[]=bn'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('archive_kind', 'event')
                    ->where('properties.total_count', 1)
                    ->where('data.items.0.id', $event->id)
                    ->where('contentSeo.canonical_url', url('/events'))
                    ->where('contentSeo.robots', 'noindex,follow')
                );
        }
    }

    public function test_event_archive_keeps_locale_and_public_release_boundaries(): void
    {
        TranslationLocale::query()->updateOrCreate(['locale' => 'en'], [
            'name' => 'English',
            'native_name' => 'English',
            'is_default' => true,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        TranslationLocale::query()->updateOrCreate(['locale' => 'bn'], [
            'name' => 'Bangla',
            'native_name' => 'বাংলা',
            'is_default' => false,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $english = $this->publication([
            'title' => 'English event',
            'slug' => 'english-event-scope',
            'content_kind' => 'event',
            'language' => 'en',
            'order_by' => 50,
        ]);
        $bangla = $this->publication([
            'title' => 'বাংলা ইভেন্ট',
            'slug' => 'bangla-event-scope',
            'content_kind' => 'event',
            'language' => 'bn',
            'order_by' => 40,
        ]);
        $this->publication([
            'title' => 'Draft event',
            'slug' => 'draft-event-scope',
            'content_kind' => 'event',
            'language' => 'en',
            'status' => 0,
            'order_by' => 30,
        ]);
        $this->publication([
            'title' => 'Future release event',
            'slug' => 'future-release-event-scope',
            'content_kind' => 'event',
            'language' => 'en',
            'published_at' => now()->addDay(),
            'order_by' => 20,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $english->id)
            );

        $this->get('/events?lang=bn')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('title', 'ইভেন্ট')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $bangla->id)
                ->where('contentSeo.canonical_url', url('/events?lang=bn'))
                ->where('contentSeo.robots', 'index,follow')
            );
    }

    public function test_locale_sitemaps_advertise_both_indexable_fixed_archives(): void
    {
        $this->enableEnglishAndBangla();
        $this->publication([
            'title' => 'Sitemap event',
            'slug' => 'sitemap-filter-event',
            'content_kind' => 'event',
            'language' => 'en',
        ]);
        $this->publication([
            'title' => 'Sitemap news',
            'slug' => 'sitemap-filter-news',
            'content_kind' => 'article',
            'language' => 'en',
        ]);

        $english = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . url('/events') . '</loc>', $english);
        $this->assertStringContainsString('<loc>' . url('/news') . '</loc>', $english);
        $this->assertStringContainsString(
            'hreflang="bn" href="' . e(url('/events?lang=bn')) . '"',
            $english,
        );
        $this->assertSame(1, substr_count($english, '<loc>' . url('/events') . '</loc>'));
        $this->assertSame(1, substr_count($english, '<loc>' . url('/news') . '</loc>'));

        $bangla = $this->get('/sitemap-bn.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . e(url('/events?lang=bn')) . '</loc>', $bangla);
        $this->assertStringContainsString('<loc>' . e(url('/news?lang=bn')) . '</loc>', $bangla);

        $banglaRouteMetadata = SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'bn',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => false,
        ]);
        foreach ([
            ['robots_index' => false, 'exclude_from_sitemap' => false, 'canonical_url' => null],
            ['robots_index' => true, 'exclude_from_sitemap' => true, 'canonical_url' => null],
            ['robots_index' => true, 'exclude_from_sitemap' => false, 'canonical_url' => 'https://external.example/events'],
        ] as $visibility) {
            $banglaRouteMetadata->update($visibility);
            $englishWithoutBanglaAlternate = $this->get('/sitemap-en.xml')->assertOk()->getContent();
            $this->assertStringContainsString('<loc>' . url('/events') . '</loc>', $englishWithoutBanglaAlternate);
            $this->assertStringNotContainsString(
                'hreflang="bn" href="' . e(url('/events?lang=bn')) . '"',
                $englishWithoutBanglaAlternate,
            );
        }

        SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'en',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => true,
        ]);
        $excluded = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('<loc>' . url('/events') . '</loc>', $excluded);
        $this->assertStringContainsString('<loc>' . url('/news') . '</loc>', $excluded);
        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'noindex,follow')
            );
    }

    public function test_event_archive_sitemap_and_response_share_a_safe_custom_route_canonical(): void
    {
        $this->publication([
            'title' => 'Custom canonical event',
            'slug' => 'custom-canonical-event',
            'content_kind' => 'event',
        ]);
        SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'en',
            'canonical_url' => url('/community-updates'),
            'robots_index' => true,
            'robots_follow' => true,
            'exclude_from_sitemap' => false,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/community-updates'))
                ->where('contentSeo.robots', 'index,follow')
            );

        $sitemap = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringContainsString(
            '<loc>' . url('/community-updates') . '</loc>',
            $sitemap,
        );
        $this->assertStringNotContainsString('<loc>' . url('/events') . '</loc>', $sitemap);
    }

    public function test_event_archive_preserves_route_owned_robots_on_clean_and_variant_queries(): void
    {
        $this->publication([
            'title' => 'Robots policy event',
            'slug' => 'robots-policy-event',
            'content_kind' => 'event',
        ]);
        $metadata = SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'en',
            'robots_index' => false,
            'robots_follow' => false,
            'exclude_from_sitemap' => false,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contentSeo.robots', 'noindex,nofollow'));
        $this->get('/events?preview=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contentSeo.robots', 'noindex,nofollow'));

        $metadata->update(['robots_index' => true]);
        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contentSeo.robots', 'index,nofollow'));
        $this->get('/events?preview=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contentSeo.robots', 'noindex,nofollow'));
    }

    public function test_event_archive_props_and_initial_head_omit_ineligible_route_locales(): void
    {
        $this->enableEnglishAndBangla();
        $this->publication([
            'title' => 'English hreflang event',
            'slug' => 'english-hreflang-event',
            'content_kind' => 'event',
            'language' => 'en',
        ]);
        $this->publication([
            'title' => 'বাংলা ভাষার ইভেন্ট',
            'slug' => 'bangla-hreflang-event',
            'content_kind' => 'event',
            'language' => 'bn',
        ]);
        $banglaMetadata = SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'bn',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => false,
        ]);

        foreach ([
            ['robots_index' => false, 'exclude_from_sitemap' => false, 'canonical_url' => null],
            ['robots_index' => true, 'exclude_from_sitemap' => true, 'canonical_url' => null],
            ['robots_index' => true, 'exclude_from_sitemap' => false, 'canonical_url' => 'https://external.example/events'],
        ] as $visibility) {
            $banglaMetadata->update($visibility);
            $response = $this->get('/events')->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('seoAlternates.links', [[
                    'locale' => 'en',
                    'url' => url('/events'),
                ]])
                ->where('seoAlternates.x_default', url('/events'))
            );

            $head = Str::before($response->getContent(), '</head>');
            $this->assertStringContainsString(
                'hreflang="en" href="' . url('/events') . '"',
                $head,
            );
            $this->assertStringNotContainsString('hreflang="bn"', $head);
            $this->assertStringContainsString(
                'hreflang="x-default" href="' . url('/events') . '"',
                $head,
            );
        }

        $banglaMetadata->update([
            'robots_index' => true,
            'exclude_from_sitemap' => false,
            'canonical_url' => null,
        ]);
        $eligibleResponse = $this->get('/events')->assertOk();
        $eligibleResponse->assertInertia(fn ($page) => $page->where('seoAlternates.links', [
            ['locale' => 'en', 'url' => url('/events')],
            ['locale' => 'bn', 'url' => url('/events?lang=bn')],
        ]));
        $this->assertStringContainsString(
            'hreflang="bn" href="' . e(url('/events?lang=bn')) . '"',
            Str::before($eligibleResponse->getContent(), '</head>'),
        );

        SeoMetadata::query()->create([
            'route_name' => 'frontend.events',
            'route_path' => '/events',
            'locale' => 'en',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => false,
        ]);
        $defaultExcludedResponse = $this->get('/events')->assertOk();
        $defaultExcludedResponse->assertInertia(fn ($page) => $page
            ->where('seoAlternates.links', [[
                'locale' => 'bn',
                'url' => url('/events?lang=bn'),
            ]])
            ->where('seoAlternates.x_default', url('/events?lang=bn'))
        );
        $defaultExcludedHead = Str::before($defaultExcludedResponse->getContent(), '</head>');
        $this->assertStringNotContainsString('hreflang="en"', $defaultExcludedHead);
        $this->assertStringContainsString(
            'hreflang="bn" href="' . e(url('/events?lang=bn')) . '"',
            $defaultExcludedHead,
        );
        $this->assertStringContainsString(
            'hreflang="x-default" href="' . e(url('/events?lang=bn')) . '"',
            $defaultExcludedHead,
        );
    }

    public function test_kind_is_not_a_first_class_filter_for_unrelated_archives(): void
    {
        foreach (['/gallery', '/annual-report', '/projects'] as $path) {
            $this->get($path . '?kind=event')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('contentSeo.canonical_url', url($path))
                    ->where('contentSeo.robots', 'noindex,follow')
                );
        }
    }

    public function test_homepage_block_defaults_and_legacy_snapshot_destinations_resolve_to_distinct_archives(): void
    {
        $defaults = config('page-builder.default_content.events_news');
        $this->assertSame('/events', $defaults['events_view_all_url']);
        $this->assertSame('/news', $defaults['news_view_all_url']);

        $snapshot = json_decode(
            file_get_contents(database_path('seeders/seed-data/cms-content.snapshot.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($snapshot['tables'] as $table => $records) {
            $actual = hash('sha256', json_encode(
                $records,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
            $this->assertSame($snapshot['checksums'][$table], $actual, "Snapshot checksum failed for {$table}.");
        }

        $blocks = collect($snapshot['tables']['page_blocks']);
        $this->assertCount(1, $blocks->where('uuid', '44444444-4444-4444-8444-000000000007'));
        $eventsNews = $blocks->where('type', 'events_news')->values();
        $this->assertCount(2, $eventsNews);
        $eventsNews->each(function (array $block): void {
            $content = json_decode($block['content'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertContains($content['events_view_all_url'], ['/events', '/events?kind=event']);
            $this->assertContains($content['news_view_all_url'], ['/news', '/events?kind=article']);

            if ($content['events_view_all_url'] !== '/events') {
                $this->get($content['events_view_all_url'])
                    ->assertMovedPermanently()
                    ->assertRedirect('/events');
            }
            if ($content['news_view_all_url'] !== '/news') {
                $this->get($content['news_view_all_url'])
                    ->assertMovedPermanently()
                    ->assertRedirect('/news');
            }
        });
    }

    private function publication(array $overrides): NoticeBoard
    {
        return NoticeBoard::create(array_replace([
            'title' => 'Published update',
            'slug' => 'published-update-' . uniqid(),
            'description' => 'A public update.',
            'content_kind' => 'article',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'order_by' => 1,
            'status' => 1,
        ], $overrides));
    }

    private function enableEnglishAndBangla(): void
    {
        TranslationLocale::query()->updateOrCreate(['locale' => 'en'], [
            'name' => 'English',
            'native_name' => 'English',
            'is_default' => true,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        TranslationLocale::query()->updateOrCreate(['locale' => 'bn'], [
            'name' => 'Bangla',
            'native_name' => 'বাংলা',
            'is_default' => false,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }
}
