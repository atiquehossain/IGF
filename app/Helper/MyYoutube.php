<?php

namespace App\Helper;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MyYoutube
{
    private const API_ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';
    private const CIRCUIT_KEY = 'youtube-api:circuit-open';
    private const FAILURE_KEY = 'youtube-api:recent-failures';

    public static function _singleVideo($id)
    {
        $videoId = self::normalizeVideoId($id);
        $apiKey = (string) config('services.youtube.api_key');

        if ($videoId === null || $apiKey === '' || Cache::get(self::CIRCUIT_KEY, false)) {
            return (object) ['items' => []];
        }

        $cacheKey = 'youtube-api:video:' . sha1($videoId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return json_decode(json_encode($cached));
        }

        try {
            $response = self::request(['part' => 'snippet', 'id' => $videoId], $apiKey);
            if (!$response->successful()) {
                self::recordFailure();
                return (object) ['items' => []];
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                self::recordFailure();
                return (object) ['items' => []];
            }

            self::recordSuccess();
            Cache::put($cacheKey, $payload, now()->addHours(6));

            return json_decode(json_encode($payload));
        } catch (Throwable $exception) {
            self::logProviderFailure($exception);
            self::recordFailure();

            return (object) ['items' => []];
        }
    }

    /**
     * Verify local records through YouTube's 50-ID batch API. A null result
     * means the provider is unavailable; callers should keep serving their
     * already-approved local records rather than taking the page down.
     *
     * @param array<int, mixed> $ids
     * @return array<int, string>|null
     */
    public static function existingVideoIds(array $ids): ?array
    {
        $videoIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => self::normalizeVideoId($id),
            $ids
        ))));

        if ($videoIds === []) {
            return [];
        }

        $apiKey = (string) config('services.youtube.api_key');
        if ($apiKey === '' || Cache::get(self::CIRCUIT_KEY, false)) {
            return null;
        }

        $existence = [];
        $missing = [];
        foreach ($videoIds as $videoId) {
            $key = self::existenceCacheKey($videoId);
            if (Cache::has($key)) {
                $existence[$videoId] = (bool) Cache::get($key);
            } else {
                $missing[] = $videoId;
            }
        }

        try {
            foreach (array_chunk($missing, 50) as $chunk) {
                $response = self::request([
                    'part' => 'id',
                    'id' => implode(',', $chunk),
                ], $apiKey);

                if (!$response->successful()) {
                    self::recordFailure();
                    return null;
                }

                $returnedIds = collect($response->json('items', []))
                    ->pluck('id')
                    ->filter(static fn ($id) => is_string($id))
                    ->all();

                foreach ($chunk as $videoId) {
                    $exists = in_array($videoId, $returnedIds, true);
                    $existence[$videoId] = $exists;
                    Cache::put(
                        self::existenceCacheKey($videoId),
                        $exists,
                        $exists ? now()->addHours(6) : now()->addMinutes(30)
                    );
                }
            }
        } catch (Throwable $exception) {
            self::logProviderFailure($exception);
            self::recordFailure();
            return null;
        }

        self::recordSuccess();

        return array_values(array_filter(
            $videoIds,
            static fn (string $videoId): bool => (bool) ($existence[$videoId] ?? false)
        ));
    }

    public static function _ytExists($id)
    {
        $videoId = self::normalizeVideoId($id);
        if ($videoId === null) {
            return false;
        }

        $existing = self::existingVideoIds([$videoId]);

        // Fail open only for records an administrator already approved.
        return $existing === null || in_array($videoId, $existing, true);
    }

    public function filter_array($array, $term)
    {
        $matches = [];
        foreach ($array as $item) {
            if (($item['name'] ?? null) === $term) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    private static function request(array $query, string $apiKey): Response
    {
        return Http::acceptJson()
            ->withHeaders(['X-Goog-Api-Key' => $apiKey])
            ->connectTimeout(2)
            ->timeout(5)
            ->retry(2, 100, throw: false)
            ->get(self::API_ENDPOINT, $query);
    }

    private static function logProviderFailure(Throwable $exception): void
    {
        // Exception messages and traces from HTTP clients can contain the full
        // request URL. Never pass them to the application logger because a
        // provider credential may be present in legacy URLs or middleware.
        Log::warning('YouTube provider request failed.', [
            'exception_class' => $exception::class,
        ]);
    }

    private static function normalizeVideoId(mixed $id): ?string
    {
        $id = trim((string) $id);

        return preg_match('/\A[A-Za-z0-9_-]{6,20}\z/', $id) === 1 ? $id : null;
    }

    private static function existenceCacheKey(string $videoId): string
    {
        return 'youtube-api:exists:' . sha1($videoId);
    }

    private static function recordFailure(): void
    {
        Cache::add(self::FAILURE_KEY, 0, now()->addMinutes(5));
        $failures = (int) Cache::increment(self::FAILURE_KEY);
        if ($failures >= 3) {
            Cache::put(self::CIRCUIT_KEY, true, now()->addMinutes(2));
            Cache::forget(self::FAILURE_KEY);
        }
    }

    private static function recordSuccess(): void
    {
        Cache::forget(self::FAILURE_KEY);
        Cache::forget(self::CIRCUIT_KEY);
    }
}
