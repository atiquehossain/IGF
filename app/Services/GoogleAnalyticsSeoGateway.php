<?php

namespace App\Services;

use App\Contracts\SeoTrafficAnalyticsGateway;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use RuntimeException;
use Spatie\Analytics\OrderBy;
use Spatie\Analytics\Period;

final class GoogleAnalyticsSeoGateway implements SeoTrafficAnalyticsGateway
{
    public function configured(): bool
    {
        return (bool) config('seo-performance.enabled')
            && trim((string) config('seo-performance.analytics.property_id')) !== ''
            && $this->credentialsExist();
    }

    /** @return array<string, mixed> */
    public function fetch(int $days): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('Google Analytics reporting is not configured.');
        }

        $days = max(7, min(90, $days));
        $period = Period::days($days);
        $filter = new FilterExpression([
            'filter' => new Filter([
                'field_name' => 'sessionDefaultChannelGroup',
                'string_filter' => new StringFilter([
                    'match_type' => MatchType::EXACT,
                    'value' => 'Organic Search',
                    'case_sensitive' => false,
                ]),
            ]),
        ]);
        /** @var \Spatie\Analytics\Analytics $analytics */
        $analytics = app(\Spatie\Analytics\Analytics::class);
        $analytics->setPropertyId(trim((string) config('seo-performance.analytics.property_id')));
        $metrics = ['sessions', 'engagedSessions', 'screenPageViews'];
        $total = $analytics->get($period, $metrics, [], 1, [], 0, $filter)->first() ?? [];
        $pages = $analytics->get(
            $period,
            $metrics,
            ['landingPagePlusQueryString'],
            50,
            [OrderBy::metric('sessions', true)],
            0,
            $filter,
        )->map(function (array $row): array {
            $landing = trim((string) ($row['landingPagePlusQueryString'] ?? ''));
            $path = str_starts_with($landing, '/') ? mb_substr($landing, 0, 1024) : '';

            return [
                'path' => $path,
                'sessions' => (int) ($row['sessions'] ?? 0),
                'engaged_sessions' => (int) ($row['engagedSessions'] ?? 0),
                'page_views' => (int) ($row['screenPageViews'] ?? 0),
            ];
        })->filter(fn (array $row): bool => $row['path'] !== '')->values()->all();

        return [
            'totals' => [
                'sessions' => (int) ($total['sessions'] ?? 0),
                'engaged_sessions' => (int) ($total['engagedSessions'] ?? 0),
                'page_views' => (int) ($total['screenPageViews'] ?? 0),
            ],
            'pages' => $pages,
        ];
    }

    private function credentialsExist(): bool
    {
        $value = config('seo-performance.analytics.credentials');
        if (is_array($value)) {
            return isset($value['client_email'], $value['private_key']);
        }
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        $path = trim($value);
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path)) {
            $path = base_path($path);
        }

        return is_file($path);
    }
}
