<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AnnualReport;
use App\Models\Gallery;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageTagModule;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\TranslationLocale;
use App\Services\PublicStructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PublicSeoEnrichmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unfiltered_archives_self_canonicalize_real_pages_without_a_duplicate_page_one_url(): void
    {
        $this->makeNotices(13);
        $this->makeProjects(13);
        $this->makeGalleryItems(13);
        $this->makeReports(13);

        foreach (['/events', '/projects', '/gallery', '/annual-report'] as $path) {
            $pageOne = $this->get($path . '?page=1')->assertOk();
            $pageOne->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url($path))
                ->where('contentSeo.robots', 'index,follow')
            );

            $pageTwo = $this->get($path . '?page=2')->assertOk();
            $pageTwo->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url($path) . '?page=2')
                ->where('contentSeo.robots', 'index,follow')
                ->where('seoAlternates.links.0.url', url($path) . '?page=2')
            );
            $this->assertStringContainsString(
                'rel="canonical" href="' . url($path) . '?page=2"',
                $pageTwo->getContent()
            );
        }

        $this->get('/events?page=99')->assertNotFound();
    }

    public function test_search_filter_and_unknown_query_variants_are_noindex_and_canonicalize_to_the_clean_archive(): void
    {
        $this->makeGalleryItems(2);
        $this->makeReports(2);

        $this->get('/gallery?search=Photo')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/gallery'))
                ->where('contentSeo.robots', 'noindex,follow')
            );

        $this->get('/annual-report?published_at=' . now()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/annual-report'))
                ->where('contentSeo.robots', 'noindex,follow')
            );

        $this->get('/events?preview=true')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'noindex,follow')
            );

        $this->get('/events?utm_source=')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contentSeo.canonical_url', url('/events'))
                ->where('contentSeo.robots', 'noindex,follow')
            );
    }

    public function test_page_two_language_alternates_keep_the_same_page_number(): void
    {
        TranslationLocale::whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        $this->makeNotices(13, 'bn');

        $response = $this->get('/events?page=2&lang=bn')->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('contentSeo.canonical_url', url('/events?lang=bn&page=2'))
            ->where('seoAlternates.links', [
                ['locale' => 'en', 'url' => url('/events?page=2')],
                ['locale' => 'bn', 'url' => url('/events?lang=bn&page=2')],
            ])
        );
    }

    public function test_generated_event_schema_uses_managed_identity_breadcrumbs_and_rich_event_fields(): void
    {
        SiteSetting::create([
            'group' => 'branding', 'key' => 'site_name', 'locale' => 'en',
            'value' => 'Client Managed Foundation', 'type' => 'text', 'is_public' => true,
        ]);
        $event = NoticeBoard::create([
            'title' => 'Community Day',
            'sub_title' => 'A day led by local volunteers.',
            'slug' => 'community-day',
            'description' => '<p>Meet the community.</p>',
            'location' => 'Dhaka Community Centre',
            'publisher_name' => 'Ignite Team',
            'published_at' => '2026-08-20 08:00:00',
            'content_kind' => 'event',
            'event_start_at' => '2026-08-25 10:00:00',
            'event_end_at' => '2026-08-25 14:00:00',
            'event_status' => 'scheduled',
            'event_attendance_mode' => 'offline',
            'language' => 'en',
            'status' => 1,
        ]);

        $response = $this->get('/event/' . $event->slug)->assertOk();
        $schema = $response->viewData('page')['props']['contentSeo']['schema_markup'];
        $graph = collect($schema['@graph']);

        $this->assertSame('Client Managed Foundation', $graph->firstWhere('@type', 'NGO')['name']);
        $this->assertSame('WebSite', $graph->firstWhere('@type', 'WebSite')['@type']);
        $this->assertCount(3, $graph->firstWhere('@type', 'BreadcrumbList')['itemListElement']);
        $eventNode = $graph->firstWhere('@type', 'Event');
        $this->assertSame('Community Day', $eventNode['name']);
        $this->assertSame('Dhaka Community Centre', $eventNode['location']['name']);
        $this->assertSame('https://schema.org/EventScheduled', $eventNode['eventStatus']);
        $this->assertSame('https://schema.org/OfflineEventAttendanceMode', $eventNode['eventAttendanceMode']);
        $this->assertStringContainsString('2026-08-25T10:00:00', $eventNode['startDate']);
        $this->assertStringContainsString('2026-08-25T14:00:00', $eventNode['endDate']);
        $this->assertStringNotContainsString('2026-08-20T08:00:00', $eventNode['startDate']);
        $this->assertSame(url('/#organization'), $eventNode['organizer']['@id']);
    }

    public function test_report_landing_page_has_safe_payload_report_schema_download_and_sitemap_discovery(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('annual-reports/impact.pdf', "%PDF-1.7\nreport");
        $report = AnnualReport::create([
            'title' => 'Impact and Accountability 2025',
            'sub_title' => 'A year of community-led outcomes.',
            'description' => '<p>Programs, governance, and audited stewardship.</p>',
            'slug' => 'impact-accountability-2025',
            'publisher_name' => 'Ignite Global Foundation',
            'published_at' => '2025-12-31 00:00:00',
            'language' => 'en',
            'image_path' => 'impact.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 2048,
            'ip' => '192.0.2.12',
            'status' => 1,
        ]);

        $response = $this->get('/annual-report/' . $report->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('annual-report-detail')
                ->where('data.report.title', 'Impact and Accountability 2025')
                ->where('data.report.year', 2025)
                ->where('data.report.publisher_name', 'Ignite Global Foundation')
                ->where('data.report.download_url', route('frontend.annual_report.download', ['slug' => $report->slug]))
                ->missing('data.report.ip')
                ->missing('data.report.created_by')
            );
        $schema = $response->viewData('page')['props']['contentSeo']['schema_markup'];
        $reportNode = collect($schema['@graph'])->firstWhere('@type', 'Report');
        $this->assertSame('Impact and Accountability 2025', $reportNode['headline']);
        $this->assertSame('application/pdf', $reportNode['encoding']['encodingFormat']);
        $this->assertSame(route('frontend.annual_report.download', ['slug' => $report->slug]), $reportNode['encoding']['contentUrl']);

        $this->get('/annual-report/download/' . $report->slug)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Download-Options', 'noopen');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/annual-report/' . $report->slug), false)
            ->assertDontSee('/annual-report/download/' . $report->slug, false);
    }

    public function test_news_updates_use_article_dates_author_and_publisher_instead_of_false_event_semantics(): void
    {
        $article = NoticeBoard::create([
            'title' => 'Field update',
            'slug' => 'field-update',
            'description' => 'A published community update.',
            'notice_type' => 'notice-board',
            'location' => 'Dhaka office',
            'publisher_name' => 'Impact Team',
            'published_at' => '2026-08-18 09:00:00',
            'language' => 'en',
            'status' => 1,
        ]);

        $response = $this->get('/event/' . $article->slug)->assertOk();
        $schema = $response->viewData('page')['props']['contentSeo']['schema_markup'];
        $node = collect($schema['@graph'])->firstWhere('@type', 'Article');

        $this->assertNotNull($node);
        $this->assertSame('Field update', $node['headline']);
        $this->assertNotEmpty($node['datePublished']);
        $this->assertNotEmpty($node['dateModified']);
        $this->assertSame('Impact Team', $node['author']['name']);
        $this->assertSame(url('/#organization'), $node['publisher']['@id']);
        $this->assertNull(collect($schema['@graph'])->firstWhere('@type', 'Event'));
    }

    public function test_event_semantics_and_annual_report_actions_have_managed_accessible_contracts(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_20_170000_add_managed_event_semantics_to_notice_boards.php'));
        $form = file_get_contents(resource_path('views/admin/notice-board/_event_fields.blade.php'));
        $detail = file_get_contents(resource_path('js/Pages/annual-report-detail.vue'));
        $listing = file_get_contents(resource_path('js/Pages/annual-report.vue'));

        $this->assertStringContainsString("'event_start_at'", $migration);
        $this->assertStringContainsString("'event_attendance_mode'", $migration);
        $this->assertStringContainsString('name="content_kind"', $form);
        $this->assertStringContainsString('name="event_start_at"', $form);
        $this->assertStringContainsString('name="event_status"', $form);
        $this->assertStringContainsString('name="event_attendance_mode"', $form);

        $this->assertStringContainsString('--action:var(--brown)', $detail);
        $this->assertStringContainsString('--action-hover:var(--brown)', $detail);
        $this->assertStringContainsString('background:var(--action)', $detail);
        $this->assertStringContainsString('color:var(--igf-on-accent,#fff)', $detail);
        $this->assertStringContainsString('.igf-download:focus-visible', $detail);
        $this->assertStringContainsString('class="igf-report-card__primary"', $listing);
        $this->assertStringContainsString(':aria-label="actionLabel(settings.view_label, item.title)"', $listing);
        $this->assertStringContainsString('.igf-report-card__actions a:focus-visible', $listing);
    }

    public function test_reports_do_not_invent_cross_language_hreflang_without_translation_identity(): void
    {
        TranslationLocale::whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        $report = $this->makeReport(1, 'en');
        $this->makeReport(1, 'bn');

        $response = $this->get('/annual-report/' . $report->slug)->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('seoAlternates.links', [[
                'locale' => 'en',
                'url' => url('/annual-report/' . $report->slug),
            ]])
            ->where('seoAlternates.x_default', url('/annual-report/' . $report->slug))
        );
        $head = Str::before($response->getContent(), '</head>');
        $this->assertStringContainsString('hreflang="en"', $head);
        $this->assertStringNotContainsString('hreflang="bn"', $head);
    }

    public function test_report_translation_identity_survives_independent_permalink_changes_and_drives_hreflang(): void
    {
        TranslationLocale::whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        $english = $this->makeReport(7, 'en');
        $bangla = $this->makeReport(8, 'bn');
        $bangla->update(['translation_key' => $english->translation_key]);

        $response = $this->get('/annual-report/' . $english->slug)->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('seoAlternates.links', [
                ['locale' => 'en', 'url' => url('/annual-report/' . $english->slug)],
                ['locale' => 'bn', 'url' => url('/annual-report/' . $bangla->slug . '?lang=bn')],
            ])
            ->where('seoAlternates.x_default', url('/annual-report/' . $english->slug))
        );

        $head = Str::before($response->getContent(), '</head>');
        $this->assertStringContainsString('hreflang="bn" href="' . url('/annual-report/' . $bangla->slug . '?lang=bn') . '"', $head);
        $this->get('/sitemap-en.xml')
            ->assertOk()
            ->assertSee('hreflang="bn"', false)
            ->assertSee(url('/annual-report/' . $bangla->slug . '?lang=bn'), false);
    }

    public function test_structured_data_semantic_validation_rejects_unsafe_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PublicStructuredDataService::class)->validate([
            '@context' => 'https://schema.org',
            '@graph' => [[
                '@type' => 'WebPage',
                'name' => 'Unsafe page',
                'url' => 'javascript:alert(1)',
            ]],
        ]);
    }

    private function makeNotices(int $count, string $locale = 'en'): void
    {
        foreach (range(1, $count) as $index) {
            NoticeBoard::create([
                'title' => "Event {$locale} {$index}",
                'slug' => "event-{$locale}-{$index}",
                'language' => $locale,
                'published_at' => now()->subDays($index),
                'order_by' => $index,
                'status' => 1,
            ]);
        }
    }

    private function makeProjects(int $count): void
    {
        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Projects',
            'slug' => 'projects',
            'status' => 1,
        ]);
        foreach (range(1, $count) as $index) {
            $page = Page::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Project {$index}",
                'sub_title' => 'A public community project.',
                'slug' => "project-{$index}",
                'language' => 'en',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'published_at' => now()->subDay(),
                'order_by' => $index,
            ]);
            PageTagModule::create([
                'uuid' => (string) Str::uuid(),
                'page_id' => $page->id,
                'tag_id' => $tag->id,
            ]);
        }
    }

    private function makeGalleryItems(int $count): void
    {
        $album = Album::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community',
            'language' => 'en',
            'status' => 1,
        ]);
        foreach (range(1, $count) as $index) {
            Gallery::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Photo {$index}",
                'type' => 'gallery',
                'description' => 'Community photo',
                'path' => "photo-{$index}.jpg",
                'language' => 'en',
                'album_id' => $album->id,
                'order_by' => $index,
                'status' => 1,
            ]);
        }
    }

    private function makeReports(int $count, string $locale = 'en'): void
    {
        foreach (range(1, $count) as $index) {
            $this->makeReport($index, $locale);
        }
    }

    private function makeReport(int $index, string $locale = 'en'): AnnualReport
    {
        return AnnualReport::create([
            'title' => "Report {$locale} {$index}",
            'slug' => "report-{$locale}-{$index}",
            'description' => 'A public accountability report.',
            'language' => $locale,
            'image_path' => "report-{$locale}-{$index}.pdf",
            'published_at' => now()->subDays($index),
            'order_by' => $index,
            'status' => 1,
        ]);
    }
}
