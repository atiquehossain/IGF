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
     * Unfiltered pages are indexable and page 2+ self-canonicalize. Search,
     * filter, malformed-page, and unknown-query variants are crawlable for
     * discovery but cannot enter the search index as duplicate archive pages.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function apply(
        array $metadata,
        Request $request,
        LengthAwarePaginator $paginator,
        string $baseUrl,
    ): array {
        $page = $paginator->currentPage();
        $rawPage = $request->query('page');
        $pageIsCanonical = $rawPage === null
            || (is_string($rawPage) && preg_match('/^[1-9]\d*$/D', $rawPage) === 1);
        $hasVariantQuery = !$pageIsCanonical || $this->hasVariantQuery($request);

        $canonical = $this->preferredBaseCanonical($metadata, $baseUrl);
        if (!$hasVariantQuery && $page > 1) {
            $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $page;
            $title = trim((string) ($metadata['meta_title'] ?? ''));
            if ($title !== '') {
                $metadata['meta_title'] = $title . ' — Page ' . $page;
                $metadata['og_title'] = $metadata['meta_title'];
                $metadata['twitter_title'] = $metadata['meta_title'];
            }
        }

        $canonical = (string) $this->metadata->localizedUrl(
            $canonical,
            (string) app()->getLocale()
        );

        $metadata['canonical_url'] = $canonical;
        $metadata['robots'] = $hasVariantQuery ? 'noindex,follow' : 'index,follow';
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
    public function alternateUrls(string $canonicalUrl): array
    {
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $links = collect($this->localization->publicLocales())
            ->map(fn (string $locale): array => [
                'locale' => $locale,
                'url' => (string) $this->metadata->localizedUrl($canonicalUrl, $locale, $defaultLocale),
            ])
            ->values()
            ->all();

        $xDefault = data_get(collect($links)->firstWhere('locale', $defaultLocale), 'url')
            ?? ($links[0]['url'] ?? $canonicalUrl);

        return ['links' => $links, 'x_default' => $xDefault];
    }

    private function hasVariantQuery(Request $request): bool
    {
        $ignored = ['page', (string) config('seo.locale_query_parameter', 'lang')];

        foreach ($request->query() as $key => $value) {
            if (in_array((string) $key, $ignored, true)) {
                continue;
            }

            if (is_array($value)) {
                return true;
            }

            return true;
        }

        return false;
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
