<?php

namespace Tests\Unit;

use App\Services\GoogleSearchConsoleGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\TestCase;

class GoogleSearchConsoleGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_page_and_query_reports_use_independent_bounded_dimension_requests(): void
    {
        Carbon::setTestNow('2026-08-22 12:00:00');
        config([
            'seo-performance.enabled' => true,
            'seo-performance.search_console.site_url' => 'sc-domain:example.test',
            'seo-performance.search_console.credentials' => [
                'client_email' => 'seo@example.test',
                'private_key' => 'test-only',
            ],
            'seo-performance.search_console.row_limit' => 99999,
            'seo-performance.search_console.low_ctr_percent' => 3,
            'seo-performance.search_console.opportunity_min_impressions' => 50,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['sitemap' => []]);
            }

            return match ($request->data()['dimensions'] ?? []) {
                ['date'] => Http::response(['rows' => [[
                    'keys' => ['2026-08-21'],
                    'clicks' => 4,
                    'impressions' => 120,
                ]]]),
                ['page'] => Http::response(['rows' => [
                    ['keys' => ['https://example.test/a'], 'clicks' => 2, 'impressions' => 100],
                    ['keys' => ['https://example.test/b'], 'clicks' => 1, 'impressions' => 20],
                ]]),
                ['query'] => Http::response(['rows' => [
                    ['keys' => ['donate'], 'clicks' => 3, 'impressions' => 90],
                ]]),
                default => Http::response(['rows' => [[
                    'clicks' => 4,
                    'impressions' => 120,
                ]]]),
            };
        });

        $gateway = app(GoogleSearchConsoleGateway::class);
        $request = Http::acceptJson()->asJson();
        $property = new ReflectionProperty($gateway, 'request');
        $property->setValue($gateway, $request);

        $report = $gateway->fetch(28);

        $this->assertSame('https://example.test/a', $report['pages'][0]['value']);
        $this->assertSame('/a', $report['pages'][0]['path']);
        $this->assertSame('donate', $report['queries'][0]['value']);
        $this->assertSame('https://example.test/a', $report['opportunities'][0]['value']);

        $analyticsBodies = collect(Http::recorded())
            ->map(fn (array $pair): Request => $pair[0])
            ->filter(fn (Request $sent): bool => str_contains($sent->url(), '/searchAnalytics/query'))
            ->map(fn (Request $sent): array => $sent->data())
            ->values();

        $this->assertCount(4, $analyticsBodies);
        $this->assertSame(
            [[], ['date'], ['page'], ['query']],
            $analyticsBodies->map(fn (array $body): array => $body['dimensions'] ?? [])->all()
        );
        $this->assertSame(25000, $analyticsBodies[2]['rowLimit']);
        $this->assertSame(25000, $analyticsBodies[3]['rowLimit']);
    }
}
