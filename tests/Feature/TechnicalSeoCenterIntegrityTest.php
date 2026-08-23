<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoAuditIssue;
use App\Models\SeoAuditRun;
use App\Models\SeoNotFoundHit;
use App\Models\Tag;
use App\Services\SeoManagedDestinationService;
use App\Services\SeoRedirectService;
use App\Services\SeoRouteRegistry;
use App\Services\TechnicalSeoAuditService;
use App\Services\TechnicalSeoInternalFetcher;
use App\Services\TechnicalSeoPathNormalizer;
use App\Services\TechnicalSeoUrlPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TechnicalSeoCenterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_inbox_deduplicates_without_storing_visitor_identifiers_or_query_data(): void
    {
        $headers = ['Referer' => config('app.url') . '/contact-us?email=private@example.test'];
        $this->get('/lost/private@example.test?token=do-not-store', $headers)->assertNotFound();
        $this->get('/lost/private@example.test?another=secret', $headers)->assertNotFound();

        $hit = SeoNotFoundHit::query()->sole();
        $this->assertSame('/lost/[redacted]', $hit->path);
        $this->assertSame('/contact-us', $hit->referrer_path);
        $this->assertSame(2, $hit->hits);
        $serialized = json_encode($hit->getAttributes());
        $this->assertStringNotContainsString('do-not-store', $serialized);
        $this->assertStringNotContainsString('private@example.test', $serialized);
        $columns = Schema::getColumnListing('seo_not_found_hits');
        $this->assertSame([], array_values(array_intersect($columns, ['ip', 'ip_address', 'session_id', 'user_agent', 'email', 'phone'])));

        $hit->update(['resolved_at' => now()]);
        $this->get('/lost/private@example.test')->assertNotFound();
        $this->assertNull($hit->fresh()->resolved_at, 'A dismissed address reopens when it is reached again.');
        $this->assertSame(3, $hit->fresh()->hits);
    }

    public function test_404_inbox_discards_external_referrers_and_skips_private_admin_paths(): void
    {
        $this->get('/outside-referrer', ['Referer' => 'https://attacker.example/private?x=1'])->assertNotFound();
        $this->assertNull(SeoNotFoundHit::query()->sole()->referrer_path);

        $before = SeoNotFoundHit::count();
        $this->get('/admin/not-a-real-tool')->assertNotFound();
        $this->assertSame($before, SeoNotFoundHit::count());
    }

    public function test_url_policy_rejects_ssrf_and_sensitive_endpoints_before_fetching(): void
    {
        config(['app.url' => 'https://ignite.example']);
        DB::table('translation_locales')->where('locale', 'bn')->update(['is_enabled' => true]);
        $policy = app(TechnicalSeoUrlPolicy::class);

        $this->assertNull($policy->internalPath('http://169.254.169.254/latest/meta-data', '/'));
        $this->assertNull($policy->internalPath('https://ignite.example@169.254.169.254/secret', '/'));
        $this->assertNull($policy->internalPath('//169.254.169.254/secret', '/'));
        $this->assertNull($policy->internalPath('file:///etc/passwd', '/'));
        $this->assertNull($policy->internalPath('https://ignite.example/admin/users', '/'));
        $this->assertNull($policy->internalPath('/api/private', '/'));
        $this->assertSame('/about/team', $policy->internalPath('team?tracking=discarded', '/about/'));
        $this->assertSame('/contact-us', $policy->internalPath('https://ignite.example/contact-us#team', '/'));
        $this->assertSame('/events?page=2&lang=bn', $policy->internalAuditTarget('/events?tracking=x&lang=bn&page=2', '/'));
        $this->assertSame('/events?page=3&lang=bn', $policy->internalAuditTarget('?lang=bn&page=3', '/events?lang=bn'));
        $this->assertSame('/events', $policy->internalAuditTarget('/events?page=1&utm_source=test', '/'));
        $this->assertSame('/page/founder%27s-letter', $policy->internalAuditTarget('/page/founder%27s-letter', '/'));
        $this->assertSame('/page/founder%27s-letter', $policy->internalAuditTarget("/page/founder's-letter", '/'));
        $this->assertSame('/category/awards-%26-recognition', $policy->internalAuditTarget('/category/awards-&-recognition', '/'));
        $this->assertNull($policy->internalAuditTarget('/events?page[]=2', '/'));
        $this->assertNull($policy->internalAuditTarget('/events?lang=unsupported', '/'));
    }

    public function test_privacy_normalizer_redacts_tokens_phones_uuids_and_emails_before_hashing(): void
    {
        $paths = app(TechnicalSeoPathNormalizer::class);
        $this->assertSame('/member/[redacted]', $paths->normalize('/member/person@example.test'));
        $this->assertSame('/call/[redacted]', $paths->normalize('/call/+880-1712-345678'));
        $this->assertSame('/reset/[redacted]', $paths->normalize('/reset/550e8400-e29b-41d4-a716-446655440000'));
        $this->assertSame('/token/[redacted]', $paths->normalize('/token/AbCdEf0123456789AbCdEf0123456789'));
        $this->assertSame('/programs/disaster-response-and-resilience', $paths->normalize('/programs/disaster-response-and-resilience'));
    }

    public function test_redirect_page_findings_are_exposed_and_filterable_in_the_technical_center(): void
    {
        $viewMenu = AuthMenu::where('link', 'seo.technical.index')->firstOrFail();
        [$viewer, $viewerRole] = $this->makeAdmin('Redirect page viewer');
        $viewerRole->update(['permission' => (string) $viewMenu->id]);
        $run = SeoAuditRun::create([
            'status' => 'completed',
            'trigger' => 'command',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        SeoAuditIssue::create([
            'run_id' => $run->id,
            'fingerprint' => hash('sha256', 'redirect-page-filter'),
            'issue_type' => 'redirect_page',
            'severity' => 'medium',
            'source_path' => '/old-location',
            'target_path' => '/current-location',
            'message' => 'This scanned page redirects.',
        ]);
        SeoAuditIssue::create([
            'run_id' => $run->id,
            'fingerprint' => hash('sha256', 'broken-link-filter'),
            'issue_type' => 'broken_link',
            'severity' => 'high',
            'source_path' => '/another-page',
            'target_path' => '/missing-location',
            'message' => 'This link is broken.',
        ]);

        $response = $this->actingAs($viewer, 'admin')->get(route('seo.technical.index', [
            'issue_type' => 'redirect_page',
        ]));

        $response->assertOk()
            ->assertSee('value="redirect_page"', false)
            ->assertSee('Redirecting pages')
            ->assertSee('/old-location')
            ->assertDontSee('/another-page')
            ->assertViewHas('issues', fn ($issues): bool => $issues->count() === 1
                && $issues->first()?->issue_type === 'redirect_page');
    }

    public function test_audit_is_bounded_and_snapshots_structural_link_and_status_findings(): void
    {
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => [
                'test.a' => ['label' => 'A', 'path' => '/a'],
                'test.orphan' => ['label' => 'Orphan', 'path' => '/orphan'],
            ],
            'technical-seo.max_urls' => 8,
            'technical-seo.max_seconds' => 5,
            'technical-seo.max_response_bytes' => 10000,
            'technical-seo.max_links_per_page' => 10,
        ]);
        $calls = [];
        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $path, int $limit) use (&$calls): array {
            $calls[] = [$path, $limit];
            $responses = [
                '/sitemap-index.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><sitemapindex><sitemap><loc>http://localhost/sitemap-en.xml</loc></sitemap></sitemapindex>'),
                '/sitemap-en.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><urlset><url><loc>http://localhost/a</loc></url><url><loc>http://localhost/orphan</loc></url></urlset>'),
                '/' => $this->response(200, 'text/html', $this->healthyHtml('/', '<a href="/a">A</a>')),
                '/a' => $this->response(200, 'text/html', '<html><head><title>A</title><meta name="description" content="A"><link rel="canonical" href="/wrong"><script type="application/ld+json">{"@type":"WebPage"}</script></head><body><a href="/broken">Broken</a><a href="/redirect">Old</a><img src="/missing.png"></body></html>'),
                '/orphan' => $this->response(200, 'text/html', $this->healthyHtml('/wrong')),
                '/broken' => $this->response(404, 'text/html', 'missing'),
                '/redirect' => ['status' => 301, 'content_type' => 'text/html', 'body' => '', 'location' => '/a', 'too_large' => false],
                '/missing.png' => $this->response(404, 'image/png', ''),
                '/wrong' => $this->response(404, 'text/html', ''),
            ];

            return $responses[$path] ?? $this->response(404, 'text/html', '');
        });
        $service = new TechnicalSeoAuditService($fetcher, app(TechnicalSeoUrlPolicy::class), app(SeoRouteRegistry::class));
        $run = $service->run('command');

        $this->assertContains($run->status, ['completed', 'completed_limited']);
        $this->assertLessThanOrEqual(8, $run->urls_checked);
        $types = $run->issues()->pluck('issue_type')->all();
        foreach (['missing_h1', 'canonical_conflict', 'broken_link', 'broken_image', 'redirect_in_link', 'orphan_page', 'duplicate_canonical'] as $type) {
            $this->assertContains($type, $types, $type);
        }
        $this->assertLessThanOrEqual(10, count($calls), 'A bounded sitemap index/child pair plus at most eight URL fetches.');
    }

    public function test_audit_prioritizes_active_internal_redirect_destinations_and_flags_a_missing_target(): void
    {
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => [],
            'technical-seo.max_urls' => 1,
            'technical-seo.max_seconds' => 5,
            'technical-seo.max_response_bytes' => 64,
        ]);
        Page::create([
            'uuid' => (string) str()->uuid(),
            'name' => 'Draft redirect destination',
            'sub_title' => '',
            'slug' => 'draft-redirect-destination',
            'status' => 0,
            'publication_status' => 'draft',
            'visibility' => 'public',
            'language' => 'en',
        ]);
        app(SeoRedirectService::class)->create([
            'from_path' => '/legacy-campaign',
            'to_url' => '/page/draft-redirect-destination',
            'status_code' => 301,
            'is_active' => true,
            'locale' => 'en',
        ]);

        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldReceive('fetch')->with('/sitemap-index.xml', 64)->once()
            ->andReturn($this->response(404, 'text/html', ''));
        $fetcher->shouldReceive('fetch')->with('/sitemap.xml', 64)->once()
            ->andReturn($this->response(404, 'text/html', ''));
        $fetcher->shouldReceive('fetch')->with('/page/draft-redirect-destination', 64)->once()
            ->andReturn($this->response(404, 'text/html', 'missing'));

        $run = (new TechnicalSeoAuditService(
            $fetcher,
            app(TechnicalSeoUrlPolicy::class),
            app(SeoRouteRegistry::class)
        ))->run('command');

        $issue = $run->issues()
            ->where('issue_type', 'http_4xx')
            ->where('source_path', '/page/draft-redirect-destination')
            ->sole();
        $this->assertSame('completed_limited', $run->status);
        $this->assertSame('high', $issue->severity);
        $this->assertSame(404, $issue->http_status);
        $this->assertSame('An active redirect destination does not load.', $issue->message);
        $this->assertSame(['/legacy-campaign [en]'], $issue->evidence['redirect_sources']);
    }

    public function test_audit_keeps_english_and_bangla_sitemap_urls_as_distinct_crawl_identities(): void
    {
        DB::table('translation_locales')->where('locale', 'bn')->update(['is_enabled' => true]);
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => ['test.about' => ['label' => 'About', 'path' => '/about-us']],
            'technical-seo.max_urls' => 5,
            'technical-seo.max_seconds' => 5,
        ]);
        $calls = [];
        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $target, int $limit) use (&$calls): array {
            $calls[] = $target;
            $responses = [
                '/sitemap-index.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><sitemapindex><sitemap><loc>http://localhost/sitemap-en.xml</loc></sitemap><sitemap><loc>http://localhost/sitemap-bn.xml</loc></sitemap></sitemapindex>'),
                '/sitemap-en.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><urlset><url><loc>http://localhost/about-us</loc></url></urlset>'),
                '/sitemap-bn.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><urlset><url><loc>http://localhost/about-us?lang=bn</loc></url></urlset>'),
                '/' => $this->response(200, 'text/html', $this->healthyHtml('/')),
                '/about-us' => $this->response(200, 'text/html', $this->healthyHtml('/about-us')),
                '/about-us?lang=bn' => $this->response(200, 'text/html', $this->healthyHtml('/about-us?lang=bn')),
            ];

            return $responses[$target] ?? $this->response(404, 'text/html', '');
        });

        $service = new TechnicalSeoAuditService($fetcher, app(TechnicalSeoUrlPolicy::class), app(SeoRouteRegistry::class));
        $run = $service->run('command');

        $this->assertContains('/about-us', $calls);
        $this->assertContains('/about-us?lang=bn', $calls);
        $this->assertSame(3, $run->urls_checked);
        $this->assertFalse($run->issues()->where('issue_type', 'duplicate_canonical')->exists());
    }

    public function test_inertia_shells_do_not_create_false_h1_or_orphan_findings(): void
    {
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => [],
            'technical-seo.max_urls' => 5,
            'technical-seo.max_seconds' => 5,
        ]);
        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $target): array {
            $responses = [
                '/sitemap-index.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><sitemapindex><sitemap><loc>http://localhost/sitemap-en.xml</loc></sitemap></sitemapindex>'),
                '/sitemap-en.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><urlset><url><loc>http://localhost/linked</loc></url><url><loc>http://localhost/orphan</loc></url></urlset>'),
                '/' => $this->response(200, 'text/html', $this->inertiaShell('/', ['cta_url' => '/linked'])),
                '/linked' => $this->response(200, 'text/html', $this->inertiaShell('/linked')),
                '/orphan' => $this->response(200, 'text/html', $this->inertiaShell('/orphan')),
            ];

            return $responses[$target] ?? $this->response(404, 'text/html', '');
        });

        $run = (new TechnicalSeoAuditService(
            $fetcher,
            app(TechnicalSeoUrlPolicy::class),
            app(SeoRouteRegistry::class)
        ))->run('command');

        $this->assertFalse($run->issues()->where('issue_type', 'missing_h1')->exists());
        $this->assertFalse($run->issues()->where('issue_type', 'orphan_page')->where('source_path', '/linked')->exists());
        $this->assertTrue($run->issues()->where('issue_type', 'orphan_page')->where('source_path', '/orphan')->exists());
    }

    public function test_inertia_component_routes_are_counted_as_internal_links(): void
    {
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => [],
            'technical-seo.max_urls' => 17,
            'technical-seo.max_seconds' => 5,
        ]);
        $paths = [
            '/category-source', '/category-landing-source', '/events-source', '/project-source', '/zakat',
            '/page/program-one', '/about-us', '/event/event-one', '/page/project-one', '/donate/zakat',
        ];
        $sitemap = '<?xml version="1.0"?><urlset>'
            . collect($paths)->map(fn (string $path): string => '<url><loc>http://localhost' . $path . '</loc></url>')->implode('')
            . '</urlset>';

        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $calls = [];
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $target) use ($sitemap, &$calls): array {
            $calls[] = $target;
            $responses = [
                '/sitemap-index.xml' => $this->response(200, 'application/xml', '<?xml version="1.0"?><sitemapindex><sitemap><loc>http://localhost/sitemap-en.xml</loc></sitemap></sitemapindex>'),
                '/sitemap-en.xml' => $this->response(200, 'application/xml', $sitemap),
                '/' => $this->response(200, 'text/html', $this->inertiaShell('/')),
                '/category-source' => $this->response(200, 'text/html', $this->inertiaShell('/category-source', ['items' => [
                    ['slug' => 'program-one'],
                    ['slug' => 'about-us', 'public_url' => '/about-us'],
                ]], 'category')),
                '/category-landing-source' => $this->response(200, 'text/html', $this->inertiaShell('/category-landing-source', [
                    'items' => [['slug' => 'landing-alias']],
                    'landing_page' => ['visible_blocks' => [['type' => 'hero']]],
                ], 'category')),
                '/events-source' => $this->response(200, 'text/html', $this->inertiaShell('/events-source', ['items' => [['slug' => 'event-one']]], 'events')),
                '/project-source' => $this->response(200, 'text/html', $this->inertiaShell('/project-source', ['items' => [['slug' => 'project-one']]], 'project')),
                '/zakat' => $this->response(200, 'text/html', $this->inertiaShell('/zakat', [], 'zakat')),
            ];
            foreach (['/page/program-one', '/about-us', '/event/event-one', '/page/project-one', '/donate/zakat'] as $path) {
                $responses[$path] = $this->response(200, 'text/html', $this->inertiaShell($path));
            }

            return $responses[$target] ?? $this->response(404, 'text/html', '');
        });

        $run = (new TechnicalSeoAuditService(
            $fetcher,
            app(TechnicalSeoUrlPolicy::class),
            app(SeoRouteRegistry::class)
        ))->run('command');

        foreach (['/page/program-one', '/about-us', '/event/event-one', '/page/project-one', '/donate/zakat'] as $path) {
            $this->assertFalse(
                $run->issues()->where('issue_type', 'orphan_page')->where('source_path', $path)->exists(),
                $path . ' should inherit its client-rendered incoming link.'
            );
        }
        $this->assertNotContains('/page/about-us', $calls);
        $this->assertNotContains('/page/landing-alias', $calls);
    }

    public function test_oversized_response_is_stopped_and_recorded_without_body_evidence(): void
    {
        config([
            'app.url' => 'http://localhost',
            'seo.routes' => ['test.large' => ['label' => 'Large', 'path' => '/large']],
            'technical-seo.max_urls' => 1,
            'technical-seo.max_response_bytes' => 64,
        ]);
        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldReceive('fetch')->with('/sitemap-index.xml', 64)->once()->andReturn($this->response(404, 'text/html', ''));
        $fetcher->shouldReceive('fetch')->with('/sitemap.xml', 64)->once()->andReturn($this->response(404, 'text/html', ''));
        $fetcher->shouldReceive('fetch')->with('/', 64)->once()->andReturn([
            'status' => 413, 'content_type' => '', 'body' => '', 'location' => null, 'too_large' => true,
        ]);
        $service = new TechnicalSeoAuditService($fetcher, app(TechnicalSeoUrlPolicy::class), app(SeoRouteRegistry::class));
        $run = $service->run('command');

        $issue = $run->issues()->where('issue_type', 'response_too_large')->sole();
        $this->assertSame(['limit_bytes' => 64], $issue->evidence);
        $this->assertSame(1, $run->urls_checked);
    }

    public function test_concurrent_scan_is_rejected_before_any_fetch_or_snapshot_write(): void
    {
        $fetcher = Mockery::mock(TechnicalSeoInternalFetcher::class);
        $fetcher->shouldNotReceive('fetch');
        $service = new TechnicalSeoAuditService($fetcher, app(TechnicalSeoUrlPolicy::class), app(SeoRouteRegistry::class));
        $lock = Cache::lock('technical-seo:audit-running', 30);
        $this->assertTrue($lock->get());
        try {
            $service->run('admin');
            $this->fail('The second scan should not start.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scan', $exception->errors());
        } finally {
            $lock->release();
        }
        $this->assertDatabaseCount('seo_audit_runs', 0);
    }

    public function test_permissions_are_fail_closed_and_issue_output_is_escaped(): void
    {
        $viewMenu = AuthMenu::where('link', 'seo.technical.index')->firstOrFail();
        [$viewer, $viewerRole] = $this->makeAdmin('Technical viewer');
        $viewerRole->update(['permission' => (string) $viewMenu->id]);
        $run = SeoAuditRun::create(['status' => 'completed', 'trigger' => 'command', 'started_at' => now(), 'completed_at' => now()]);
        SeoAuditIssue::create([
            'run_id' => $run->id,
            'fingerprint' => hash('sha256', 'xss'),
            'issue_type' => 'broken_link',
            'severity' => 'high',
            'source_path' => '/<script>alert(1)</script>',
            'message' => '<img src=x onerror=alert(1)>',
        ]);

        $this->actingAs($viewer, 'admin')->get(route('seo.technical.index'))
            ->assertOk()
            ->assertViewHas('canViewMetadata', false)
            ->assertDontSee('Search &amp; Social Preview', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $metadataView = MenuAction::where('link', 'seo.metadata.view')->firstOrFail();
        $viewerRole->update(['actionPermission' => (string) $metadataView->id]);
        $this->get(route('seo.technical.index'))
            ->assertOk()
            ->assertViewHas('canViewMetadata', true)
            ->assertSee('Search &amp; Sharing', false);
        $this->post(route('seo.technical.scan'))->assertForbidden();
        $this->assertContains('throttle:2,1', Route::getRoutes()->getByName('seo.technical.scan')->gatherMiddleware());

        [$unprivileged] = $this->makeAdmin('No SEO permission');
        $this->actingAs($unprivileged, 'admin')->get(route('seo.technical.index'))->assertForbidden();
    }

    public function test_404_redirect_creation_accepts_only_server_managed_destinations(): void
    {
        [$admin, $role] = $this->makeAdmin('SEO owner');
        $menu = AuthMenu::where('link', 'seo.technical.index')->firstOrFail();
        $action = MenuAction::where('link', 'seo.technical.redirect')->firstOrFail();
        $role->update(['permission' => (string) $menu->id, 'actionPermission' => (string) $action->id]);
        $this->actingAs($admin, 'admin');

        $livePage = Page::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Live redirect source',
            'sub_title' => 'This content is already available.',
            'slug' => 'live-redirect-source',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
            'published_at' => now()->subDay(),
        ]);
        $livePath = '/page/' . $livePage->slug;
        $liveHit = SeoNotFoundHit::create([
            'scope_hash' => hash('sha256', 'en|' . $livePath),
            'path_hash' => hash('sha256', $livePath),
            'path' => $livePath,
            'locale' => 'en',
            'hits' => 2,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ]);

        try {
            app(SeoRedirectService::class)->create([
                'from_path' => $livePath,
                'to_url' => '/contact-us',
                'status_code' => 301,
                'is_active' => true,
                'locale' => 'en',
            ]);
            $this->fail('A manual redirect must never shadow live managed content.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('from_path', $exception->errors());
        }
        $this->assertDatabaseMissing('seo_redirects', ['from_path' => $livePath]);

        $inbox = $this->get(route('seo.technical.index'))->assertOk()->assertSee('Live again:');
        $this->assertTrue((bool) $inbox->viewData('liveNotFoundHits')->get($liveHit->id));
        $this->assertSame([], $inbox->viewData('suggestions')->get($liveHit->id));
        $this->post(route('seo.technical.not-found.redirect', $liveHit), [
            'destination' => '/contact-us',
            'status_code' => 301,
        ])->assertStatus(409);
        $this->assertNull($liveHit->fresh()->resolved_at);

        $hit = SeoNotFoundHit::create([
            'scope_hash' => hash('sha256', 'en|/old-about'),
            'path_hash' => hash('sha256', '/old-about'),
            'path' => '/old-about',
            'locale' => 'en',
            'hits' => 3,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')->post(route('seo.technical.not-found.redirect', $hit), [
            'destination' => 'https://attacker.example/phish',
            'status_code' => 301,
        ])->assertStatus(422);
        $this->assertDatabaseCount('seo_redirects', 0);

        $this->post(route('seo.technical.not-found.redirect', $hit), [
            'destination' => '/contact-us',
            'status_code' => 301,
        ])->assertRedirect();
        $this->assertDatabaseHas('seo_redirects', ['from_path' => '/old-about', 'to_url' => '/contact-us', 'status_code' => 301]);
        $this->assertNotNull($hit->fresh()->resolved_at);

        DB::table('translation_locales')->where('locale', 'bn')->update(['is_enabled' => true]);
        $banglaHit = SeoNotFoundHit::create([
            'scope_hash' => hash('sha256', 'bn|/bn-old-about'),
            'path_hash' => hash('sha256', '/bn-old-about'),
            'path' => '/bn-old-about',
            'locale' => 'bn',
            'hits' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        Page::create([
            'name' => 'English only destination', 'sub_title' => '', 'slug' => 'english-only-destination',
            'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'language' => 'en',
        ]);
        $this->post(route('seo.technical.not-found.redirect', $banglaHit), [
            'destination' => '/page/english-only-destination',
            'status_code' => 301,
        ])->assertStatus(422);
        $this->assertNull($banglaHit->fresh()->resolved_at);
        $this->post(route('seo.technical.not-found.redirect', $banglaHit), [
            'destination' => '/contact-us',
            'status_code' => 301,
        ])->assertRedirect();
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/bn-old-about',
            'locale' => 'bn',
            'to_url' => '/contact-us?lang=bn',
        ]);
    }

    public function test_redirect_suggestions_include_only_published_managed_dynamic_content(): void
    {
        DB::table('translation_locales')->where('locale', 'bn')->update(['is_enabled' => true]);
        Page::create(['name' => 'Public page', 'sub_title' => '', 'slug' => 'public-dynamic', 'status' => 1,
            'publication_status' => 'published', 'visibility' => 'public', 'language' => 'en']);
        Page::create(['name' => 'বাংলা পাতা', 'sub_title' => '', 'slug' => 'bangla-dynamic', 'status' => 1,
            'publication_status' => 'published', 'visibility' => 'public', 'language' => 'bn']);
        Page::create(['name' => 'Draft page', 'sub_title' => '', 'slug' => 'private-draft', 'status' => 0,
            'publication_status' => 'draft', 'visibility' => 'public', 'language' => 'en']);
        Page::create(['name' => 'Unlisted page', 'sub_title' => '', 'slug' => 'private-unlisted', 'status' => 1,
            'publication_status' => 'published', 'visibility' => 'unlisted', 'language' => 'en']);
        Category::create(['name' => 'Managed cause', 'slug' => 'managed-cause', 'status' => 1, 'language' => 'en']);
        NoticeBoard::create(['title' => 'Managed event', 'slug' => 'managed-event', 'status' => 1, 'language' => 'en', 'published_at' => now()]);
        Tag::create(['uuid' => (string) str()->uuid(), 'name' => 'Managed project', 'slug' => 'managed-project', 'status' => 1]);
        AnnualReport::create(['title' => 'Managed report', 'slug' => 'managed-report', 'status' => 1, 'language' => 'en', 'published_at' => now()]);

        $this->assertTrue(Page::query()->publiclyAvailable()->where('slug', 'public-dynamic')->exists(),
            json_encode(Page::where('slug', 'public-dynamic')->first()?->getAttributes()));
        $destinations = app(SeoManagedDestinationService::class)->all();
        foreach (['/page/public-dynamic', '/category/managed-cause', '/event/managed-event', '/projects/managed-project', '/annual-report/managed-report'] as $path) {
            $this->assertArrayHasKey($path, $destinations, $path . ' keys=' . implode(',', array_keys($destinations)));
        }
        $this->assertArrayNotHasKey('/page/private-draft', $destinations);
        $this->assertArrayNotHasKey('/page/private-unlisted', $destinations);
        $this->assertLessThanOrEqual(1000, count($destinations));
        $this->assertArrayHasKey('/page/public-dynamic', app(SeoManagedDestinationService::class)->all('en'));
        $this->assertArrayNotHasKey('/page/public-dynamic', app(SeoManagedDestinationService::class)->all('bn'));
        $this->assertArrayHasKey('/page/bangla-dynamic', app(SeoManagedDestinationService::class)->all('bn'));
    }

    /** @return array{0:Admin,1:Role} */
    private function makeAdmin(string $name): array
    {
        $role = Role::create(['name' => $name, 'permission' => '', 'actionPermission' => '', 'serial' => '[]', 'status' => 1]);
        $admin = Admin::create([
            'name' => $name,
            'username' => str($name)->slug() . '-' . uniqid(),
            'email' => str($name)->slug() . '-' . uniqid() . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);

        return [$admin, $role];
    }

    /** @return array{status:int,content_type:string,body:string,location:?string,too_large:bool} */
    private function response(int $status, string $type, string $body): array
    {
        return ['status' => $status, 'content_type' => $type, 'body' => $body, 'location' => null, 'too_large' => false];
    }

    private function healthyHtml(string $canonical, string $body = ''): string
    {
        return '<html><head><title>Healthy</title><meta name="description" content="Healthy description"><link rel="canonical" href="' . $canonical . '"><script type="application/ld+json">{"@type":"WebPage"}</script></head><body><h1>Healthy</h1>' . $body . '</body></html>';
    }

    private function inertiaShell(string $canonical, array $data = [], string $component = 'test'): string
    {
        $page = htmlspecialchars(json_encode([
            'component' => $component,
            'props' => ['data' => $data, 'appMenus' => [], 'appFooterMenus' => []],
            'url' => $canonical,
            'version' => null,
        ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<html><head><title>Healthy</title><meta name="description" content="Healthy description">'
            . '<link rel="canonical" href="' . $canonical . '"><script type="application/ld+json">{"@type":"WebPage"}</script>'
            . '</head><body><div id="app" data-page="' . $page . '"></div></body></html>';
    }
}
