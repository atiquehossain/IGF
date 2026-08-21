<?php

namespace App\Services;

final class TechnicalSeoUrlPolicy
{
    public function __construct(private LocalizationManager $localization)
    {
    }

    /**
     * Convert a URL found in curated public HTML to an application path.
     * External, ambiguous, credentialed, sensitive, and non-HTTP URLs are
     * rejected before any fetch can happen.
     */
    public function internalPath(string $candidate, string $sourcePath = '/'): ?string
    {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate === '' || $candidate[0] === '#' || strlen($candidate) > 2048
            || str_contains($candidate, '\\') || preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return null;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || isset($parts['user'], $parts['pass'])) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $origin = $this->origin();
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            if (!in_array($scheme, ['http', 'https'], true)
                || !hash_equals($origin['host'], $host)
                || $origin['scheme'] !== $scheme
                || $origin['port'] !== $port) {
                return null;
            }
            $path = (string) ($parts['path'] ?? '/');
        } elseif (str_starts_with($candidate, '//')) {
            return null;
        } else {
            $path = (string) ($parts['path'] ?? '');
            if (!str_starts_with($path, '/')) {
                $sourceOnly = (string) (parse_url($sourcePath, PHP_URL_PATH) ?: '/');
                $base = str_ends_with($sourceOnly, '/')
                    ? rtrim($sourceOnly, '/')
                    : substr($sourceOnly, 0, (int) strrpos($sourceOnly, '/'));
                $path = ($base === '' ? '' : $base) . '/' . $path;
            }
        }

        $path = $this->collapsePath($path);
        if ($path === null || !$this->isPublicPath($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Normalize an auditable public URL while retaining only the two query
     * parameters that form stable crawl identities in this application:
     * pagination and the enabled public language. Search/filter/tracking
     * parameters are intentionally discarded so the crawler cannot expand an
     * attacker-controlled query space or persist query data.
     */
    public function internalAuditTarget(string $candidate, string $sourceTarget = '/'): ?string
    {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate === '' || strlen($candidate) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return null;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return null;
        }

        // A query-only link is relative to the current document, not its
        // directory. Preserve that path before applying the normal URL policy.
        $pathCandidate = $candidate;
        if (($parts['path'] ?? '') === '' && str_starts_with($candidate, '?')) {
            $pathCandidate = (string) (parse_url($sourceTarget, PHP_URL_PATH) ?: '/');
        }
        $path = $this->internalPath($pathCandidate, $sourceTarget);
        if ($path === null) {
            return null;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $stable = [];

        if (array_key_exists('page', $query)) {
            $page = $query['page'];
            if (!is_string($page) || preg_match('/^[1-9]\d{0,5}$/D', $page) !== 1) {
                return null;
            }
            if ((int) $page > 1) {
                $stable['page'] = (int) $page;
            }
        }

        $localeKey = (string) config('seo.locale_query_parameter', 'lang');
        if (array_key_exists($localeKey, $query)) {
            $locale = $query[$localeKey];
            if (!is_string($locale) || !in_array($locale, $this->localization->publicLocales(), true)) {
                return null;
            }
            if ($locale !== (string) config('app.fallback_locale', 'en')) {
                $stable[$localeKey] = $locale;
            }
        }

        return $stable === []
            ? $path
            : $path . '?' . http_build_query($stable, '', '&', PHP_QUERY_RFC3986);
    }

    public function isPublicPath(string $path): bool
    {
        if (!str_starts_with($path, '/') || strlen($path) > 1024) {
            return false;
        }

        foreach ((array) config('technical-seo.excluded_prefixes', []) as $prefix) {
            $prefix = '/' . trim((string) $prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    /** @return array{scheme:string,host:string,port:int} */
    public function origin(): array
    {
        $parts = parse_url((string) config('app.url'));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'], $parts['pass'])) {
            throw new \RuntimeException('APP_URL must be a valid HTTP(S) origin before a technical SEO audit can run.');
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
        ];
    }

    private function collapsePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return null;
        }

        $segments = [];
        foreach (explode('/', '/' . ltrim($path, '/')) as $segment) {
            $decoded = rawurldecode($segment);
            if (preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
                return null;
            }
            if ($decoded === '' || $decoded === '.') {
                continue;
            }
            if ($decoded === '..') {
                array_pop($segments);
                continue;
            }
            if (str_contains($decoded, '/') || str_contains($decoded, '\\')) {
                return null;
            }
            $segments[] = $segment;
        }

        $collapsed = '/' . implode('/', $segments);

        return $collapsed === '' ? '/' : (rtrim($collapsed, '/') ?: '/');
    }
}
