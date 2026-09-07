<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PublicArchiveSeoService
{
    public function __construct(
        private SeoMetadataService $metadata,
        private LocalizationManager $localization,
    ) {
    }

    /**
     * Apply a deterministic SEO policy to a paginated public archive.
     *
     * Unfiltered pages and explicitly declared first-class sub-archives are
     * indexable, and page 2+ self-canonicalizes. Search, undeclared filters,
     * malformed-page, and unknown-query variants are crawlable for discovery
     * but cannot enter the search index as duplicate archive pages.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, scalar> $canonicalQuery Stable, validated query
     *        values that identify a first-class archive (for example, an
     *        event/news content-kind archive) rather than a search variant.
     * @return array<string, mixed>
     */
    public function apply(
        array $metadata,
        Request $request,
        LengthAwarePaginator $paginator,
        string $baseUrl,
        array $canonicalQuery = [],
    ): array {
        $page = $paginator->currentPage();
        $rawPage = $request->query('page');
        $pageIsCanonical = $rawPage === null
            || (is_string($rawPage) && preg_match('/^[1-9]\d*$/D', $rawPage) === 1);
        $hasVariantQuery = !$pageIsCanonical
            || $this->hasVariantQuery($request, $canonicalQuery);

        $canonical = $this->preferredBaseCanonical($metadata, $baseUrl);
        $canonical = $this->withQuery($canonical, $canonicalQuery);
        if (!$hasVariantQuery && $page > 1) {
            $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $page;
            $title = trim((string) ($metadata['meta_title'] ?? ''));
            if ($title !== '') {
                $metadata['meta_title'] = $title . $this->pageTitleSuffix($page);
                $metadata['og_title'] = $metadata['meta_title'];
                $metadata['twitter_title'] = $metadata['meta_title'];
            }
        }

        $canonical = (string) $this->metadata->localizedUrl(
            $canonical,
            (string) app()->getLocale()
        );

        $metadata['canonical_url'] = $canonical;
        $metadata['robots'] = $this->robotsDirective($metadata['robots'] ?? null, $hasVariantQuery);
        $metadata['og_url'] = $canonical;

        return $metadata;
    }

    public function abortIfOutOfRange(LengthAwarePaginator $paginator): void
    {
        if ($paginator->currentPage() > 1 && $paginator->currentPage() > $paginator->lastPage()) {
            abort(404);
        }
    }

    /** @return array{links: array<int, array{locale: string, url: string}>, x_default: string} */
    public function alternateUrls(string $canonicalUrl, ?string $eligibilityRouteName = null): array
    {
        $defaultLocale = (string) config('app.fallback_locale', 'en');

        return $this->metadata->alternateUrls(
            $canonicalUrl,
            $this->localization->publicLocales(),
            $defaultLocale,
            $eligibilityRouteName,
        );
    }

    /** @param array<string, scalar> $canonicalQuery */
    private function hasVariantQuery(Request $request, array $canonicalQuery = []): bool
    {
        $localeKey = (string) config('seo.locale_query_parameter', 'lang');
        if ($request->query->has($localeKey)) {
            $locale = $request->query($localeKey);
            if (!is_string($locale)
                || !in_array($locale, $this->localization->publicLocales(), true)) {
                return true;
            }
        }

        $ignored = ['page', $localeKey];

        foreach ($request->query() as $key => $value) {
            if (in_array((string) $key, $ignored, true)) {
                continue;
            }

            if (array_key_exists((string) $key, $canonicalQuery)) {
                if (is_array($value) || (string) $value !== (string) $canonicalQuery[(string) $key]) {
                    return true;
                }

                continue;
            }

            return true;
        }

        foreach ($canonicalQuery as $key => $value) {
            $requestValue = $request->query((string) $key);
            if (is_array($requestValue) || (string) $requestValue !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function pageTitleSuffix(int $page): string
    {
        if ((string) app()->getLocale() !== 'bn') {
            return ' — Page ' . $page;
        }

        $localizedPage = strtr((string) $page, [
            '0' => '০',
            '1' => '১',
            '2' => '২',
            '3' => '৩',
            '4' => '৪',
            '5' => '৫',
            '6' => '৬',
            '7' => '৭',
            '8' => '৮',
            '9' => '৯',
        ]);

        return ' — পৃষ্ঠা ' . $localizedPage;
    }

    private function robotsDirective(mixed $configured, bool $hasVariantQuery): string
    {
        $configured = is_string($configured) ? strtolower(trim($configured)) : '';
        if (preg_match('/^(index|noindex),(follow|nofollow)$/D', $configured, $matches) !== 1) {
            $matches = [null, 'index', 'follow'];
        }

        $index = $hasVariantQuery ? 'noindex' : $matches[1];

        return $index . ',' . $matches[2];
    }

    /** @param array<string, scalar> $query */
    private function withQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        parse_str((string) ($parts['query'] ?? ''), $existing);
        foreach ($query as $key => $value) {
            $existing[(string) $key] = $value;
        }
        ksort($existing);

        $authority = '';
        if (isset($parts['host'])) {
            $authority = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $authority .= ':' . $parts['port'];
            }
        }

        $path = $parts['path'] ?? '/';
        $queryString = http_build_query($existing, '', '&', PHP_QUERY_RFC3986);

        return $authority . $path . ($queryString === '' ? '' : '?' . $queryString);
    }

    /** @param array<string, mixed> $metadata */
    private function preferredBaseCanonical(array $metadata, string $fallback): string
    {
        $candidate = trim((string) ($metadata['canonical_url'] ?? ''));
        if ($candidate === ''
            || !$this->metadata->isSameOrigin($candidate)
            || preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return $fallback;
        }

        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['page'], $query[(string) config('seo.locale_query_parameter', 'lang')]);
        if ($query !== []) {
            return $fallback;
        }

        return url('/' . ltrim((string) ($parts['path'] ?? '/'), '/'));
    }
}
