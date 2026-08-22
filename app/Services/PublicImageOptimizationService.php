<?php

namespace App\Services;

class PublicImageOptimizationService
{
    private const OPTIMIZED_FALLBACKS = [
        '/image/banner/slider-1.png' => '/image/banner/slider-1-1588.webp',
        '/image/banner/slider-2.png' => '/image/banner/slider-2-1588.webp',
    ];

    public function optimizedFallback(?string $value): string
    {
        $source = trim((string) $value);
        if ($source === '') {
            return '';
        }

        $parts = parse_url($source);
        if (!is_array($parts) || !$this->isRelativeOrSameOrigin($parts)) {
            return $source;
        }

        $path = $parts['path'] ?? null;
        if (!is_string($path)) {
            return $source;
        }

        return self::OPTIMIZED_FALLBACKS[$path] ?? $source;
    }

    public function replaceBundledReferences(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replaceBundledReferences($item), $value);
        }

        return is_string($value) ? $this->optimizedFallback($value) : $value;
    }

    /**
     * Only normalize references controlled by this application. An external URL
     * may coincidentally use the same path and must never be redirected locally.
     *
     * @param  array<string, int|string>  $parts
     */
    private function isRelativeOrSameOrigin(array $parts): bool
    {
        if (!isset($parts['scheme']) && !isset($parts['host'])) {
            return true;
        }

        $appOrigin = parse_url((string) config('app.url'));
        if (!is_array($appOrigin)
            || !isset($parts['scheme'], $parts['host'], $appOrigin['scheme'], $appOrigin['host'])) {
            return false;
        }

        return strtolower((string) $parts['scheme']) === strtolower((string) $appOrigin['scheme'])
            && strtolower((string) $parts['host']) === strtolower((string) $appOrigin['host'])
            && $this->effectivePort($parts) === $this->effectivePort($appOrigin);
    }

    /** @param array<string, int|string> $parts */
    private function effectivePort(array $parts): ?int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return match (strtolower((string) ($parts['scheme'] ?? ''))) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }
}
