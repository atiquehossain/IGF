<?php

namespace Tests\Feature;

use App\Contracts\SeoSearchPerformanceGateway;
use App\Contracts\SeoTrafficAnalyticsGateway;
use App\Models\Admin;
use App\Models\MenuAction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SeoPerformanceCenterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_performance_center_combines_first_party_search_and_organic_traffic_without_rank_or_ai_tools(): void
    {
        $search = new FakeSeoSearchPerformanceGateway(true);
        $analytics = new FakeSeoTrafficAnalyticsGateway(true);
        $this->app->instance(SeoSearchPerformanceGateway::class, $search);
        $this->app->instance(SeoTrafficAnalyticsGateway::class, $analytics);
        $admin = $this->seoAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('seo.performance.index', ['days' => 28]));

        $response->assertOk()
            ->assertSee('Search Performance')
            ->assertSee('1,250')
            ->assertSee('4.0%')
            ->assertSee('Organic landing pages')
            ->assertSee('Click-through opportunities')
            ->assertSee('clean water Bangladesh')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('does not use AI')
            ->assertSee('without rank tracking')
            ->assertDontSee('Average position');
        $this->assertSame([28], $search->daysFetched);
        $this->assertSame([28], $analytics->daysFetched);
    }

    public function test_disconnected_state_is_actionable_and_never_calls_external_gateways(): void
    {
        $search = new FakeSeoSearchPerformanceGateway(false);
        $analytics = new FakeSeoTrafficAnalyticsGateway(false);
        $this->app->instance(SeoSearchPerformanceGateway::class, $search);
        $this->app->instance(SeoTrafficAnalyticsGateway::class, $analytics);

        $this->actingAs($this->seoAdmin(), 'admin')->get(route('seo.performance.index'))
            ->assertOk()
            ->assertSee('Connect first-party performance data')
            ->assertSee('SEO_PERFORMANCE_ENABLED=true')
            ->assertSee('credentials are never displayed')
            ->assertSee('not configured');
        $this->assertSame([], $search->daysFetched);
        $this->assertSame([], $analytics->daysFetched);
    }

    public function test_refresh_is_validated_throttled_permission_checked_and_reports_source_failure_safely(): void
    {
        $search = new FakeSeoSearchPerformanceGateway(true, true);
        $analytics = new FakeSeoTrafficAnalyticsGateway(false);
        $this->app->instance(SeoSearchPerformanceGateway::class, $search);
        $this->app->instance(SeoTrafficAnalyticsGateway::class, $analytics);
        $admin = $this->seoAdmin();

        $this->actingAs($admin, 'admin')->post(route('seo.performance.refresh'), ['days' => 8])
            ->assertSessionHasErrors('days');
        $this->post(route('seo.performance.refresh'), ['days' => 7])
            ->assertRedirect(route('seo.performance.index', ['days' => 7]))
            ->assertSessionHas('alert-type', 'warning');
        $this->assertContains('throttle:3,1', Route::getRoutes()->getByName('seo.performance.refresh')->gatherMiddleware());

        $role = Role::create(['name' => 'No SEO', 'permission' => '', 'actionPermission' => '', 'serial' => '[]', 'status' => 1]);
        $unprivileged = Admin::create([
            'name' => 'No SEO', 'username' => 'no-seo', 'email' => 'no-seo@example.test',
            'role' => (string) $role->id, 'status' => 1, 'password' => bcrypt('test-password'), 'must_change_password' => false,
        ]);
        $this->actingAs($unprivileged, 'admin')->get(route('seo.performance.index'))->assertForbidden();
    }

    private function seoAdmin(): Admin
    {
        $permission = MenuAction::where('link', 'seo.metadata.view')->firstOrFail();
        $role = Role::create([
            'name' => 'Performance viewer ' . uniqid(),
            'permission' => '',
            'actionPermission' => (string) $permission->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Performance QA',
            'username' => 'performance-' . uniqid(),
            'email' => 'performance-' . uniqid() . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}

final class FakeSeoSearchPerformanceGateway implements SeoSearchPerformanceGateway
{
    /** @var list<int> */
    public array $daysFetched = [];

    public function __construct(private bool $isConfigured, private bool $fail = false)
    {
    }

    public function configured(): bool
    {
        return $this->isConfigured;
    }

    public function fetch(int $days): array
    {
        $this->daysFetched[] = $days;
        if ($this->fail) {
            throw new RuntimeException('A secret upstream detail that must never reach the view.');
        }

        return [
            'period' => ['start' => '2026-07-01', 'end' => '2026-07-28', 'days' => $days],
            'totals' => ['clicks' => 50, 'impressions' => 1250, 'ctr_percent' => 4.0],
            'trend' => [],
            'pages' => [],
            'queries' => [[
                'value' => 'clean water Bangladesh', 'path' => null, 'clicks' => 10, 'impressions' => 300, 'ctr_percent' => 3.3,
            ], [
                'value' => '<script>alert(1)</script>', 'path' => null, 'clicks' => 0, 'impressions' => 80, 'ctr_percent' => 0.0,
            ]],
            'opportunities' => [[
                'value' => 'https://example.test/page/clean-water', 'path' => '/page/clean-water', 'clicks' => 2, 'impressions' => 200, 'ctr_percent' => 1.0,
            ]],
            'sitemaps' => [[
                'path' => 'https://example.test/sitemap-index.xml', 'last_submitted' => null, 'pending' => false, 'warnings' => 0, 'errors' => 0,
            ]],
        ];
    }
}

final class FakeSeoTrafficAnalyticsGateway implements SeoTrafficAnalyticsGateway
{
    /** @var list<int> */
    public array $daysFetched = [];

    public function __construct(private bool $isConfigured)
    {
    }

    public function configured(): bool
    {
        return $this->isConfigured;
    }

    public function fetch(int $days): array
    {
        $this->daysFetched[] = $days;

        return [
            'totals' => ['sessions' => 120, 'engaged_sessions' => 90, 'page_views' => 180],
            'pages' => [[
                'path' => '/page/clean-water', 'sessions' => 70, 'engaged_sessions' => 55, 'page_views' => 100,
            ]],
        ];
    }
}
