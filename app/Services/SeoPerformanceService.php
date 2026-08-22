<?php

namespace App\Services;

use App\Contracts\SeoSearchPerformanceGateway;
use App\Contracts\SeoTrafficAnalyticsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SeoPerformanceService
{
    public function __construct(
        private SeoSearchPerformanceGateway $search,
        private SeoTrafficAnalyticsGateway $analytics,
    ) {
    }

    /** @return array<string, mixed> */
    public function report(int $days, bool $force = false): array
    {
        $days = $this->days($days);
        $key = "seo-performance:v1:{$days}";
        if ($force) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes((int) config('seo-performance.cache_minutes', 360)), function () use ($days): array {
            return [
                'days' => $days,
                'search_console' => $this->source($this->search, $days, 'Search Console'),
                'analytics' => $this->source($this->analytics, $days, 'Google Analytics'),
                'fetched_at' => now(),
                'uses_ai' => false,
                'tracks_rankings' => false,
            ];
        });
    }

    /** @param SeoSearchPerformanceGateway|SeoTrafficAnalyticsGateway $gateway @return array<string, mixed> */
    private function source(object $gateway, int $days, string $label): array
    {
        if (!$gateway->configured()) {
            return [
                'status' => 'not_configured',
                'message' => "{$label} is not connected yet.",
                'data' => [],
            ];
        }

        try {
            return [
                'status' => 'connected',
                'message' => "{$label} data loaded.",
                'data' => $gateway->fetch($days),
            ];
        } catch (Throwable $exception) {
            Log::warning('SEO performance source could not be loaded.', [
                'source' => $label,
                'exception' => $exception::class,
            ]);

            return [
                'status' => 'unavailable',
                'message' => "{$label} is configured but could not be loaded. Verify the property access and credentials, then refresh.",
                'data' => [],
            ];
        }
    }

    private function days(int $days): int
    {
        return in_array($days, [7, 28, 90], true) ? $days : 28;
    }
}
