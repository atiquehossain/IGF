<?php

namespace App\Services;

use App\Data\SeoMetadataPayload;
use App\Models\Category;
use App\Models\AnnualReport;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SeoMetadataService
{
    /**
     * Return the deterministic, owner-editable social-card fallback without
     * hiding missing page-specific images in the SEO editor or health report.
     * The hierarchy reuses existing managed brand imagery and never calls an
     * external service or fabricates media.
     *
     * @return array{image: string, alt: string}
     */
    public function socialImageFallback(?string $locale = null): array
    {
        $settings = app(SiteSettingService::class)->values($locale ?: app()->getLocale(), true);
        $candidates = [
            data_get($settings, 'branding.social_share_image'),
            data_get($settings, 'sponsor_page.hero_image'),
            data_get($settings, 'volunteer_page.hero_image'),
            data_get($settings, 'branding.logo'),
        ];
        $image = collect($candidates)
            ->map(fn ($value) => $this->absolutePublicImageUrl(is_string($value) ? $value : ''))
            ->first(fn (string $value) => $value !== '') ?: '';

        return [
            'image' => $image,
            'alt' => trim((string) data_get(
                $settings,
                'branding.social_share_image_alt',
                data_get($settings, 'branding.logo_alt', config('app.name'))
            )),
        ];
    }

    public function updateForPage(Page $page, array $data): SeoMetadata
    {
        return $this->updateForModel($page, $data, $page->language ?: app()->getLocale());
    }

    public function updateForModel(
        Model $model,
        array $data,
        ?string $locale = null,
        ?SeoMetadata $lockedMetadata = null
    ): SeoMetadata
    {
        $data = SeoMetadataPayload::from($data)->attributes();
        $resolvedLocale = $locale ?: (string) ($model->getAttribute('language') ?: app()->getLocale());

        $seo = $lockedMetadata ?: SeoMetadata::withTrashed()->firstOrNew([
            'seoable_type' => $model::class,
            'seoable_id' => $model->getKey(),
            'locale' => $resolvedLocale,
        ]);
        $isNew = !$seo->exists;
        $wasTrashed = $seo->exists && $seo->trashed();

        if ($wasTrashed) {
            $seo->restore();
        }

        $seo->fill($data)->forceFill([
            // Ownership and audit values are derived, never accepted from a
            // nested request payload.
            'seoable_type' => $model::class,
            'seoable_id' => $model->getKey(),
            'route_name' => null,
            'route_path' => null,
            'locale' => $resolvedLocale,
            'updated_by' => auth('admin')->id(),
            'created_by' => $seo->exists ? $seo->created_by : auth('admin')->id(),
        ]);
        if ($isNew || $wasTrashed || $seo->isDirty(SeoMetadataPayload::WRITABLE_FIELDS)) {
            $seo->forceFill(['editor_version' => (int) $seo->editor_version + 1]);
        }
        $seo->save();

        return $seo;
    }

    public function metaForPage(Page $page): array
    {
        $fallback = [
            'meta_title' => $page->meta_title ?: $page->name,
            'meta_description' => $page->meta_description ?: strip_tags((string) $page->sub_title),
            'meta_keyword' => $page->meta_keyword,
            'meta_image' => $this->pageImageUrl($page->thumbnail),
        ];

        return $this->metaForModel($page, $fallback, null, $page->language ?: app()->getLocale());
    }

    public function publicUrlForPage(Page $page): string
    {
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $slug = (string) $page->slug;
        $landingOwner = app(CategoryLandingPageAliasService::class)->categoryForPage(
            $page,
            (string) ($page->language ?: app()->getLocale()),
        );
        if ($landingOwner && filled($landingOwner->slug)) {
            return (string) $this->localizedUrl(
                route('frontend.category', ['slug' => $landingOwner->slug]),
                (string) ($page->language ?: $defaultLocale),
                $defaultLocale,
            );
        }

        $routes = app(SeoRouteRegistry::class)->all();
        $definition = $routes->first(fn (array $candidate) => ($candidate['page_slug'] ?? null) === $slug);
        if (!$definition && filled($page->uuid)) {
            $source = Page::query()
                ->where('uuid', $page->uuid)
                ->where('language', $defaultLocale)
                ->first();
            if ($source) {
                $definition = $routes->first(
                    fn (array $candidate) => ($candidate['page_slug'] ?? null) === $source->slug
                );
            }
        }

        $url = $definition
            ? url((string) $definition['path'])
            : route('frontend.page', ['slug' => $slug]);

        return (string) $this->localizedUrl($url, (string) ($page->language ?: $defaultLocale), $defaultLocale);
    }

    public function metaForModel(Model $model, array $fallback, ?string $canonicalUrl = null, ?string $locale = null): array
    {
        $locale ??= (string) ($model->getAttribute('language') ?: app()->getLocale());
        $seo = SeoMetadata::where('seoable_type', $model::class)
            ->where('seoable_id', $model->getKey())
            ->where('locale', $locale)
            ->first();

        $meta = $seo ? $seo->toMetaArray($fallback) : array_merge($fallback, [
            'canonical_url' => null,
            'robots' => 'index,follow',
            'og_title' => $fallback['meta_title'],
            'og_description' => $fallback['meta_description'],
            'og_image' => $fallback['meta_image'],
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $fallback['meta_title'],
            'twitter_description' => $fallback['meta_description'],
            'twitter_image' => $fallback['meta_image'],
            'schema_markup' => null,
        ]);

        $meta['canonical_url'] = $meta['canonical_url'] ?: $canonicalUrl;
        if ($meta['canonical_url']) {
            $meta['canonical_url'] = $this->localizedUrl($meta['canonical_url'], $locale);
        }

        $meta['og_image'] = $this->absolutePublicImageUrl($meta['og_image'] ?? '');
        $meta['twitter_image'] = $this->absolutePublicImageUrl($meta['twitter_image'] ?? ($meta['og_image'] ?? ''));

        return $meta;
    }

    public function updateForRoute(
        string $routeName,
        string $routePath,
        string $locale,
        array $data,
        ?SeoMetadata $lockedMetadata = null
    ): SeoMetadata
    {
        $data = SeoMetadataPayload::from($data)->attributes();

        $seo = $lockedMetadata ?: SeoMetadata::withTrashed()->firstOrNew([
            'route_name' => $routeName,
            'locale' => $locale,
        ]);
        $isNew = !$seo->exists;
        $wasTrashed = $seo->exists && $seo->trashed();

        if ($wasTrashed) {
            $seo->restore();
        }

        $seo->fill($data)->forceFill([
            'seoable_type' => null,
            'seoable_id' => null,
            'route_name' => $routeName,
            'route_path' => $routePath,
            'locale' => $locale,
            'updated_by' => auth('admin')->id(),
            'created_by' => $seo->exists ? $seo->created_by : auth('admin')->id(),
        ]);
        if ($isNew || $wasTrashed || $seo->isDirty(SeoMetadataPayload::WRITABLE_FIELDS)) {
            $seo->forceFill(['editor_version' => (int) $seo->editor_version + 1]);
        }
        $seo->save();

        return $seo;
    }

    public function metaForRoute(?string $routeName, ?string $locale = null): array
    {
        if (!$routeName) {
            return [];
        }

        $locale ??= app()->getLocale();
        $seo = SeoMetadata::where('route_name', $routeName)
            ->where('locale', $locale)
            ->first();

        if (!$seo) {
            return [];
        }

        $meta = $seo->toMetaArray([
            'meta_title' => config('app.name'),
            'meta_description' => '',
            'meta_keyword' => '',
            'meta_image' => '',
        ]);
        $meta['canonical_url'] = $meta['canonical_url'] ?: $this->canonicalForPath($seo->route_path);
        if ($meta['canonical_url']) {
            $meta['canonical_url'] = $this->localizedUrl($meta['canonical_url'], $locale);
        }

        $meta['og_image'] = $this->absolutePublicImageUrl($meta['og_image'] ?? '');
        $meta['twitter_image'] = $this->absolutePublicImageUrl($meta['twitter_image'] ?? ($meta['og_image'] ?? ''));

        return $meta;
    }

    public function absolutePublicImageUrl(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === ''
            || str_starts_with($value, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme !== '') {
            return in_array($scheme, ['http', 'https'], true) ? $value : '';
        }

        return url('/' . ltrim($value, '/'));
    }

    public function localizedUrl(?string $url, string $locale, ?string $defaultLocale = null): ?string
    {
        if (!$url) {
            return $url;
        }

        $defaultLocale ??= (string) config('app.fallback_locale', 'en');
        $parameter = (string) config('seo.locale_query_parameter', 'lang');
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query[$parameter]);
        if ($locale !== $defaultLocale) {
            $query[$parameter] = $locale;
        }
        ksort($query);

        $authority = '';
        if (isset($parts['host'])) {
            $authority = ($parts['scheme'] ?? 'https') . '://';
            if (isset($parts['user'])) {
                $authority .= $parts['user'];
                if (isset($parts['pass'])) {
                    $authority .= ':' . $parts['pass'];
                }
                $authority .= '@';
            }
            $authority .= $parts['host'];
            if (isset($parts['port'])) {
                $authority .= ':' . $parts['port'];
            }
        }

        $path = $parts['path'] ?? ($authority === '' ? $url : '');
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $authority . $path . ($queryString === '' ? '' : '?' . $queryString);
    }

    /** @return array{links: array<int, array{locale: string, url: string}>, x_default: string} */
    public function alternateUrls(string $canonicalUrl, array $locales, ?string $defaultLocale = null): array
    {
        $defaultLocale ??= (string) config('app.fallback_locale', 'en');
        $locales = collect($locales)
            ->map(fn ($locale) => trim((string) $locale))
            ->filter()
            ->push($defaultLocale)
            ->unique()
            ->sortBy(fn (string $locale): string => ($locale === $defaultLocale ? '0' : '1') . $locale)
            ->values();

        $contentUrls = $this->contentAlternateUrls($locales, $defaultLocale);
        if ($contentUrls !== null) {
            return $this->alternatePayload($contentUrls, $canonicalUrl, $defaultLocale);
        }

        return [
            'links' => $locales->map(fn (string $locale) => [
                'locale' => $locale,
                'url' => (string) $this->localizedUrl($canonicalUrl, $locale, $defaultLocale),
            ])->all(),
            'x_default' => (string) $this->localizedUrl($canonicalUrl, $defaultLocale, $defaultLocale),
        ];
    }

    /**
     * Resolve content translations by a stable identity instead of assuming
     * that every enabled language uses the current record's slug.
     *
     * Returning null means the route is not content-owned and may safely use
     * the normal route-level locale cluster. Returning a collection means the
     * route is content-owned; only URLs in that collection are real variants.
     *
     * @param Collection<int, string> $locales
     * @return Collection<string, string>|null
     */
    private function contentAlternateUrls(Collection $locales, string $defaultLocale): ?Collection
    {
        if (!app()->bound('request') || !request()->route()) {
            return null;
        }

        $routeName = (string) request()->route()->getName();
        $slug = trim((string) request()->route('slug'));
        $currentLocale = (string) app()->getLocale();

        if ($routeName === 'frontend.page') {
            return $this->pageAlternateUrls($slug, $currentLocale, $locales, $defaultLocale);
        }

        if ($routeName === 'frontend.category') {
            return $this->categoryAlternateUrls($slug, $currentLocale, $locales, $defaultLocale);
        }

        if ($routeName === 'frontend.event') {
            return $this->eventAlternateUrls($slug, $currentLocale, $locales, $defaultLocale);
        }

        if ($routeName === 'frontend.annual_report.show') {
            return $this->annualReportAlternateUrls($slug, $currentLocale, $locales, $defaultLocale);
        }

        if ($routeName === 'frontend.project' && $slug !== '') {
            if (!Schema::hasTable('tags') || !Tag::query()->where('status', 1)->where('slug', $slug)->exists()) {
                return collect();
            }

            // Tags are deliberately global rather than translated records.
            return $locales->mapWithKeys(fn (string $locale) => [
                $locale => (string) $this->localizedUrl(
                    route('frontend.project', ['slug' => $slug]),
                    $locale,
                    $defaultLocale
                ),
            ]);
        }

        $definition = app(SeoRouteRegistry::class)->definition($routeName);
        if (is_array($definition) && !empty($definition['page_slug'])) {
            return $this->specialPageAlternateUrls($definition, $locales, $defaultLocale);
        }

        return null;
    }

    /** @param Collection<int, string> $locales @return Collection<string, string> */
    private function pageAlternateUrls(string $slug, string $currentLocale, Collection $locales, string $defaultLocale): Collection
    {
        if ($slug === '' || !Schema::hasTable('pages')) {
            return collect();
        }

        $page = $this->publicPages()
            ->where('language', $currentLocale)
            ->where('slug', $slug)
            ->first();
        if (!$page) {
            return collect();
        }

        $translations = filled($page->uuid)
            ? $this->publicPages()->where('uuid', $page->uuid)->whereIn('language', $locales->all())->get()
            : collect([$page]);

        return $translations->mapWithKeys(fn (Page $translation) => [
            (string) $translation->language => (string) $this->localizedUrl(
                route('frontend.page', ['slug' => $translation->slug]),
                (string) $translation->language,
                $defaultLocale
            ),
        ]);
    }

    /** @param Collection<int, string> $locales @return Collection<string, string> */
    private function categoryAlternateUrls(string $slug, string $currentLocale, Collection $locales, string $defaultLocale): Collection
    {
        if ($slug === '' || !Schema::hasTable('categories')) {
            return collect();
        }

        $category = Category::query()
            ->where('status', 1)
            ->where('language', $currentLocale)
            ->where('slug', $slug)
            ->first();
        if (!$category) {
            return collect();
        }

        $translations = filled($category->uuid)
            ? Category::query()->where('status', 1)->where('uuid', $category->uuid)->whereIn('language', $locales->all())->get()
            : collect([$category]);

        return $translations->mapWithKeys(fn (Category $translation) => [
            (string) $translation->language => (string) $this->localizedUrl(
                route('frontend.category', ['slug' => $translation->slug]),
                (string) $translation->language,
                $defaultLocale
            ),
        ]);
    }

    /** @param Collection<int, string> $locales @return Collection<string, string> */
    private function eventAlternateUrls(string $slug, string $currentLocale, Collection $locales, string $defaultLocale): Collection
    {
        if ($slug === '' || !Schema::hasTable('notice_boards')) {
            return collect();
        }

        $event = NoticeBoard::query()
            ->publiclyReleased()
            ->where('language', $currentLocale)
            ->where('slug', $slug)
            ->first();
        if (!$event) {
            return collect();
        }

        $translations = filled($event->translation_key)
            ? NoticeBoard::query()
                ->publiclyReleased()
                ->where('translation_key', $event->translation_key)
                ->whereIn('language', $locales->all())
                ->whereNotNull('slug')
                ->get()
            : collect([$event]);

        return $translations->mapWithKeys(fn (NoticeBoard $translation) => [
            (string) $translation->language => (string) $this->localizedUrl(
                route('frontend.event', ['slug' => $translation->slug]),
                (string) $translation->language,
                $defaultLocale
            ),
        ]);
    }

    /** @param Collection<int, string> $locales @return Collection<string, string> */
    private function annualReportAlternateUrls(
        string $slug,
        string $currentLocale,
        Collection $locales,
        string $defaultLocale
    ): Collection
    {
        if ($slug === '' || !Schema::hasTable('annual_reports')) {
            return collect();
        }

        $report = AnnualReport::query()
            ->publiclyReleased()
            ->where('language', $currentLocale)
            ->where('slug', $slug)
            ->first();
        if (!$report) {
            return collect();
        }

        $translations = filled($report->translation_key)
            ? AnnualReport::query()
                ->publiclyReleased()
                ->where('translation_key', $report->translation_key)
                ->whereIn('language', $locales->all())
                ->whereNotNull('slug')
                ->get()
            : collect([$report]);

        return $translations
            ->sortBy(fn (AnnualReport $translation): int => (int) $locales->search((string) $translation->language))
            ->mapWithKeys(fn (AnnualReport $translation) => [
            (string) $translation->language => (string) $this->localizedUrl(
                route('frontend.annual_report.show', ['slug' => $translation->slug]),
                (string) $translation->language,
                $defaultLocale
            ),
        ]);
    }

    /** @param array<string, mixed> $definition @param Collection<int, string> $locales @return Collection<string, string> */
    private function specialPageAlternateUrls(array $definition, Collection $locales, string $defaultLocale): Collection
    {
        if (!Schema::hasTable('pages')) {
            return collect();
        }

        $source = $this->publicPages()
            ->where('language', $defaultLocale)
            ->where('slug', (string) $definition['page_slug'])
            ->first();
        if (!$source) {
            return collect();
        }

        $translations = filled($source->uuid)
            ? $this->publicPages()
                ->where('uuid', $source->uuid)
                ->where('slug', (string) $definition['page_slug'])
                ->whereIn('language', $locales->all())
                ->get()
            : collect([$source]);
        $path = (string) ($definition['path'] ?? '/');

        return $translations->mapWithKeys(fn (Page $translation) => [
            (string) $translation->language => (string) $this->localizedUrl(
                url($path),
                (string) $translation->language,
                $defaultLocale
            ),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Page> */
    private function publicPages()
    {
        return Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @param Collection<string, string> $urls
     * @return array{links: array<int, array{locale: string, url: string}>, x_default: string}
     */
    private function alternatePayload(Collection $urls, string $canonicalUrl, string $defaultLocale): array
    {
        $currentLocale = (string) app()->getLocale();
        if ($urls->isEmpty()) {
            $urls->put($currentLocale, (string) $this->localizedUrl($canonicalUrl, $currentLocale, $defaultLocale));
        }
        $urls = $urls->sortBy(
            fn (string $url, string $locale): string => ($locale === $defaultLocale ? '0' : '1') . $locale
        );

        $xDefault = (string) ($urls->get($defaultLocale)
            ?: $urls->get($currentLocale)
            ?: $urls->first()
            ?: $this->localizedUrl($canonicalUrl, $currentLocale, $defaultLocale));

        return [
            'links' => $urls->map(fn (string $url, string $locale) => [
                'locale' => $locale,
                'url' => $url,
            ])->values()->all(),
            'x_default' => $xDefault,
        ];
    }

    public function isSameOrigin(string $url, ?string $origin = null): bool
    {
        $origin ??= url('/');
        $candidate = parse_url($url);
        $expected = parse_url($origin);
        if ($candidate === false || $expected === false || empty($candidate['host']) || empty($expected['host'])) {
            return false;
        }

        $candidateScheme = strtolower((string) ($candidate['scheme'] ?? 'https'));
        $expectedScheme = strtolower((string) ($expected['scheme'] ?? 'https'));
        $candidatePort = (int) ($candidate['port'] ?? ($candidateScheme === 'https' ? 443 : 80));
        $expectedPort = (int) ($expected['port'] ?? ($expectedScheme === 'https' ? 443 : 80));

        return $candidateScheme === $expectedScheme
            && strtolower((string) $candidate['host']) === strtolower((string) $expected['host'])
            && $candidatePort === $expectedPort;
    }

    /**
     * A special route is translated only when a published Page carries the
     * default Page's stable UUID and is reachable through the route's key.
     */
    public function hasPublishedSpecialPage(string $routeName, string $locale): ?bool
    {
        $definition = app(SeoRouteRegistry::class)->definition($routeName);
        if (!is_array($definition) || empty($definition['page_slug']) || !Schema::hasTable('pages')) {
            return null;
        }

        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $source = $this->publicPages()
            ->where('language', $defaultLocale)
            ->where('slug', (string) $definition['page_slug'])
            ->first();
        if (!$source) {
            return false;
        }
        if ($locale === $defaultLocale) {
            return true;
        }
        if (blank($source->uuid)) {
            return false;
        }

        return $this->publicPages()
            ->where('uuid', $source->uuid)
            ->where('language', $locale)
            ->where('slug', (string) $definition['page_slug'])
            ->exists();
    }

    private function canonicalForPath(?string $path): ?string
    {
        if (!$path || str_contains($path, '{')) {
            return null;
        }

        return url('/' . ltrim($path, '/'));
    }

    private function pageImageUrl(?string $value): string
    {
        $value = trim((string) $value);
        if ($value !== ''
            && !str_contains($value, '/')
            && parse_url($value, PHP_URL_SCHEME) === null) {
            $value = '/storage/photos/1/page/' . $value;
        }

        return $this->absolutePublicImageUrl($value);
    }
}
