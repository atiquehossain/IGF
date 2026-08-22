<?php

namespace App\Services;

use App\Contracts\SeoSearchPerformanceGateway;
use Carbon\CarbonImmutable;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GoogleSearchConsoleGateway implements SeoSearchPerformanceGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private ?\Illuminate\Http\Client\PendingRequest $request = null;

    public function configured(): bool
    {
        return (bool) config('seo-performance.enabled')
            && $this->siteUrl() !== ''
            && $this->credentials() !== null;
    }

    /** @return array<string, mixed> */
    public function fetch(int $days): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('Search Console reporting is not configured.');
        }

        $days = max(7, min(90, $days));
        $end = CarbonImmutable::today()->subDay();
        $start = $end->subDays($days - 1);
        $base = [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'dataState' => 'final',
        ];

        $totals = $this->query($base + ['rowLimit' => 1]);
        $trend = $this->query($base + ['dimensions' => ['date'], 'rowLimit' => min(1000, $days + 5)]);
        $rowLimit = max(1, min(25000, (int) config('seo-performance.search_console.row_limit', 5000)));
        // Search Analytics returns top rows, not an exhaustive result set.
        // Querying page+query pairs and aggregating those truncated rows biases
        // both reports and excludes anonymized-query traffic from page totals.
        $pageDetail = $this->query($base + [
            'dimensions' => ['page'],
            'rowLimit' => $rowLimit,
        ]);
        $queryDetail = $this->query($base + [
            'dimensions' => ['query'],
            'rowLimit' => $rowLimit,
        ]);

        $pageRows = collect($pageDetail['rows'] ?? [])->filter(fn ($row): bool => is_array($row));
        $queryRows = collect($queryDetail['rows'] ?? [])->filter(fn ($row): bool => is_array($row));
        $pages = $this->aggregate($pageRows, 0, 'page');
        $queries = $this->aggregate($queryRows, 0, 'query');
        $lowCtr = (float) config('seo-performance.search_console.low_ctr_percent', 3);
        $minimumImpressions = (int) config('seo-performance.search_console.opportunity_min_impressions', 50);
        $opportunities = $pages->filter(fn (array $row): bool => $row['impressions'] >= $minimumImpressions && $row['ctr_percent'] < $lowCtr)
            ->sortByDesc('impressions')->take(15)->values();

        $total = (array) data_get($totals, 'rows.0', []);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => $days],
            'totals' => $this->metrics($total),
            'trend' => collect($trend['rows'] ?? [])->map(function ($row): array {
                $metrics = $this->metrics((array) $row);
                $metrics['date'] = (string) data_get($row, 'keys.0', '');

                return $metrics;
            })->filter(fn (array $row): bool => $row['date'] !== '')->values()->all(),
            'pages' => $pages->take(25)->values()->all(),
            'queries' => $queries->take(25)->values()->all(),
            'opportunities' => $opportunities->all(),
            'sitemaps' => $this->sitemaps(),
        ];
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function query(array $body): array
    {
        return $this->request()->post($this->analyticsUrl(), $body)->throw()->json();
    }

    /** @return list<array<string, mixed>> */
    private function sitemaps(): array
    {
        try {
            $payload = $this->request()->get($this->sitemapsUrl())->throw()->json();
        } catch (Throwable) {
            // Sitemap access can be restricted independently of Search
            // Analytics. Keep the useful performance report available.
            return [];
        }

        return collect($payload['sitemap'] ?? [])->filter(fn ($row): bool => is_array($row))->map(fn (array $row): array => [
            'path' => $this->safeDisplayUrl((string) ($row['path'] ?? '')),
            'last_submitted' => $row['lastSubmitted'] ?? null,
            'pending' => (bool) ($row['isPending'] ?? false),
            'warnings' => (int) ($row['warnings'] ?? 0),
            'errors' => (int) ($row['errors'] ?? 0),
        ])->filter(fn (array $row): bool => $row['path'] !== '')->take(20)->values()->all();
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        if ($this->request) {
            return $this->request;
        }
        $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentials());
        $token = $credentials->fetchAuthToken()['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Google did not provide an access token.');
        }

        return $this->request = Http::acceptJson()->asJson()->withToken($token)
            ->timeout((int) config('seo-performance.request_timeout_seconds', 12));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function aggregate(Collection $rows, int $keyIndex, string $kind): Collection
    {
        return $rows->groupBy(fn (array $row): string => (string) data_get($row, "keys.{$keyIndex}", ''))
            ->reject(fn (Collection $group, string $key): bool => trim($key) === '')
            ->map(function (Collection $group, string $key) use ($kind): array {
                $clicks = (int) round($group->sum(fn (array $row): float => (float) ($row['clicks'] ?? 0)));
                $impressions = (int) round($group->sum(fn (array $row): float => (float) ($row['impressions'] ?? 0)));
                $display = $kind === 'page' ? $this->safeDisplayUrl($key) : mb_substr(trim($key), 0, 300);

                return [
                    'value' => $display,
                    'path' => $kind === 'page' ? $this->pagePath($key) : null,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr_percent' => $impressions > 0 ? round(($clicks / $impressions) * 100, 1) : 0.0,
                ];
            })->filter(fn (array $row): bool => $row['value'] !== '')
            ->sortByDesc('impressions')->values();
    }

    /** @param array<string, mixed> $row @return array{clicks:int,impressions:int,ctr_percent:float} */
    private function metrics(array $row): array
    {
        $clicks = (int) round((float) ($row['clicks'] ?? 0));
        $impressions = (int) round((float) ($row['impressions'] ?? 0));

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr_percent' => $impressions > 0 ? round(($clicks / $impressions) * 100, 1) : 0.0,
        ];
    }

    private function analyticsUrl(): string
    {
        return 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($this->siteUrl()) . '/searchAnalytics/query';
    }

    private function sitemapsUrl(): string
    {
        return 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($this->siteUrl()) . '/sitemaps';
    }

    private function siteUrl(): string
    {
        $value = trim((string) config('seo-performance.search_console.site_url'));

        return str_starts_with($value, 'sc-domain:') || filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    /** @return string|array<string, mixed>|null */
    private function credentials(): string|array|null
    {
        $value = config('seo-performance.search_console.credentials');
        if (is_array($value) && isset($value['client_email'], $value['private_key'])) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $path = trim($value);
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path)) {
            $path = base_path($path);
        }

        return is_file($path) ? $path : null;
    }

    private function safeDisplayUrl(string $value): string
    {
        $value = trim($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return '';
        }

        $parts = parse_url($value);
        if ($parts === false || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return '';
        }

        return mb_substr($value, 0, 2000);
    }

    private function pagePath(string $value): string
    {
        $safe = $this->safeDisplayUrl($value);
        if ($safe === '') {
            return '';
        }
        $path = '/' . ltrim((string) parse_url($safe, PHP_URL_PATH), '/');

        return mb_substr($path === '//' ? '/' : $path, 0, 1024);
    }
}
