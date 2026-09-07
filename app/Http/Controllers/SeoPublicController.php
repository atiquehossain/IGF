<?php

namespace App\Http\Controllers;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\JobPosting;
use App\Models\JobPostingTranslation;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\Workshop;
use App\Models\WorkshopTranslation;
use App\Services\CategoryLandingPageAliasService;
use App\Services\DonationDestinationService;
use App\Services\LocalizationManager;
use App\Services\SeoMetadataService;
use App\Services\SeoIndexingPolicy;
use App\Services\SeoRouteRegistry;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SeoPublicController extends Controller
{
    public function __construct(
        private SeoMetadataService $seo,
        private SeoIndexingPolicy $indexing,
        private SeoRouteRegistry $routes,
        private LocalizationManager $localization,
        private CategoryLandingPageAliasService $landingPageAliases,
        private DonationDestinationService $donationDestinations,
    ) {
    }

    /** Backward-compatible default-language sitemap. */
    public function sitemap(Request $request): Response
    {
        return $this->sitemapResponse($request, (string) config('app.fallback_locale', 'en'));
    }

    public function sitemapLocale(Request $request, string $locale): Response
    {
        abort_unless(in_array($locale, $this->localization->publicLocales(), true), 404);

        return $this->sitemapResponse($request, $locale);
    }

    public function sitemapIndex(Request $request): Response
    {
        $locales = $this->localization->publicLocales();

        return $this->xmlResponse($request, 'index:' . implode(',', $locales), function () use ($locales): string {
            $sitemaps = collect($locales)->map(fn (string $locale) => [
                'loc' => route('seo.sitemap.locale', ['locale' => $locale]),
                'lastmod' => $this->latestModification($locale),
            ]);

            return view('seo.sitemap-index', ['sitemaps' => $sitemaps])->render();
        });
    }

    public function robots(): Response
    {
        $indexingEnabled = $this->indexing->indexingAllowed();
        // Page-level noindex (and the matching HTTP header) is the actual
        // indexing control. Public crawling remains allowed so a crawler can
        // observe that directive and remove an already-known URL. A private
        // preview must use authentication/network access control instead.
        $directives = ($indexingEnabled ? '' : "# Indexing disabled by page-level noindex directives.\n")
            . "Allow: /\nDisallow: /admin";

        return response(
            "User-agent: *\n" . $directives . "\nSitemap: " . route('seo.sitemap.index')
                . "\nSitemap: " . route('seo.sitemap') . "\n",
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
            ]
        );
    }

    private function sitemapResponse(Request $request, string $locale): Response
    {
        app()->setLocale($locale);

        return $this->xmlResponse($request, 'locale:' . $locale, function () use ($locale): string {
            return view('seo.sitemap', ['entries' => $this->entries($locale)])->render();
        }, $locale);
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<int, array{locale: string, url: string}>}> */
    private function entries(string $locale): Collection
    {
        $routeSeo = SeoMetadata::query()
            ->whereIn('route_name', $this->routes->all()->keys())
            ->where('locale', $locale)
            ->get()
            ->keyBy('route_name');

        $specialPageSlugs = $this->routes->all()
            ->pluck('page_slug')
            ->filter()
            ->unique()
            ->values();
        $backingSlugs = $specialPageSlugs;
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $backingSources = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where('language', $defaultLocale)
            ->whereIn('slug', $backingSlugs)
            ->get()
            ->keyBy('slug');
        $backingUuids = $backingSources->pluck('uuid')->filter()->unique()->values();
        $landingPageUuids = $this->landingPageAliases->pageUuids();
        $backingPages = $this->routes->all()->mapWithKeys(function (array $definition, string $routeName) use ($backingSources, $locale) {
            $source = !empty($definition['page_slug'])
                ? $backingSources->get($definition['page_slug'])
                : null;
            if (!$source) {
                return [$routeName => null];
            }

            $translation = filled($source->uuid)
                ? Page::with('seo')
                    ->where('uuid', $source->uuid)
                    ->where('language', $locale)
                    ->where('slug', (string) $definition['page_slug'])
                    ->first()
                : ($locale === (string) $source->language ? $source->load('seo') : null);

            return [$routeName => $translation];
        });

        $staticEntries = $this->routes->all()->map(function (array $definition, string $routeName) use ($locale, $routeSeo, $backingPages) {
            if (in_array($routeName, ['frontend.events', 'frontend.news'], true)) {
                return null;
            }

            /** @var SeoMetadata|null $routeMetadata */
            $routeMetadata = $routeSeo->get($routeName);
            /** @var Page|null $page */
            $requiresBackingPage = isset($definition['page_slug']) && empty($definition['settings_backed']);
            $page = isset($definition['page_slug']) ? $backingPages->get($routeName) : null;
            if ($requiresBackingPage && !$page) {
                // A route-backed page is only a real localized URL when its
                // corresponding Page translation exists. Route metadata must
                // never fabricate a missing translation.
                return null;
            }
            $pageMetadata = $page?->seo;
            $effectiveMetadata = $pageMetadata ?: $routeMetadata;

            if (($page && !$this->isPublicPage($page)) || !$this->isIndexable($effectiveMetadata)) {
                return null;
            }

            $fallback = url($definition['path']);
            $candidate = $pageMetadata?->canonical_url ?: $routeMetadata?->canonical_url;

            return [
                'loc' => $this->sitemapLocation($candidate, $fallback, $locale),
                'lastmod' => $this->lastModified($page, $effectiveMetadata),
            ];
        })->filter()->values();

        $publicationArchiveEntries = collect([
            'frontend.events' => 'event',
            'frontend.news' => 'article',
        ])->map(function (string $kind, string $routeName) use ($locale, $routeSeo): ?array {
            /** @var SeoMetadata|null $routeMetadata */
            $routeMetadata = $routeSeo->get($routeName);
            if (!$this->isIndexable($routeMetadata)) {
                return null;
            }

            $latest = NoticeBoard::query()
                ->publiclyReleased()
                ->where('language', $locale)
                ->where('content_kind', $kind)
                ->latest('updated_at')
                ->first();

            return [
                'loc' => $this->publicationArchiveSitemapLocation($routeMetadata, $routeName, $locale),
                'lastmod' => $this->lastModified($latest, $routeMetadata),
                'alternates' => $this->publicationArchiveSitemapAlternates($routeName),
            ];
        })
            ->filter()
            ->values();

        $pages = Page::with('seo')
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('language', $locale)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->get()
            ->reject(fn (Page $page) => $specialPageSlugs->contains($page->slug)
                || (filled($page->uuid) && ($backingUuids->contains($page->uuid)
                    || $landingPageUuids->contains($page->uuid))))
            ->filter(fn (Page $page) => $this->isIndexable($page->seo))
            ->map(fn (Page $page) => [
                'loc' => $this->sitemapLocation($page->seo?->canonical_url, $this->pageUrl($page), $locale),
                'lastmod' => $this->lastModified($page, $page->seo),
            ]);

        $categories = Category::with('seo')
            ->where('status', 1)
            ->where('language', $locale)
            ->whereNotNull('slug')
            ->get()
            ->reject(fn (Category $category) => hash_equals('career', (string) $category->slug))
            ->filter(fn (Category $category) => $this->isIndexable($category->seo))
            ->map(fn (Category $category) => [
                'loc' => $this->sitemapLocation(
                    $category->seo?->canonical_url,
                    route('frontend.category', ['slug' => $category->slug]),
                    $locale
                ),
                'lastmod' => $this->lastModified($category, $category->seo),
            ]);

        $events = NoticeBoard::with('seo')
            ->publiclyReleased()
            ->where('language', $locale)
            ->whereNotNull('slug')
            ->get()
            ->filter(fn (NoticeBoard $event) => $this->isIndexable($event->seo))
            ->map(fn (NoticeBoard $event) => [
                'loc' => $this->sitemapLocation(
                    $event->seo?->canonical_url,
                    route('frontend.event', ['slug' => $event->slug]),
                    $locale
                ),
                'lastmod' => $this->lastModified($event, $event->seo),
                'alternates' => $this->eventSitemapAlternates($event),
            ]);

        $projects = Tag::with('seo')
            ->where('status', 1)
            ->whereNotNull('slug')
            ->get()
            ->filter(fn (Tag $tag) => $this->isIndexable($tag->seo))
            ->map(fn (Tag $tag) => [
                'loc' => $this->sitemapLocation(
                    $tag->seo?->canonical_url,
                    route('frontend.project', ['slug' => $tag->slug]),
                    $locale
                ),
                'lastmod' => $this->lastModified($tag, $tag->seo),
            ]);

        $reports = AnnualReport::with('seo')
            ->publiclyReleased()
            ->where('language', $locale)
            ->whereNotNull('slug')
            ->get()
            ->filter(fn (AnnualReport $report) => $this->isIndexable($report->seo))
            ->map(fn (AnnualReport $report) => [
                'loc' => $this->sitemapLocation(
                    $report->seo?->canonical_url,
                    route('frontend.annual_report.show', ['slug' => $report->slug]),
                    $locale
                ),
                'lastmod' => $this->lastModified($report, $report->seo),
                'alternates' => $this->annualReportSitemapAlternates($report),
            ]);

        $donationCauses = $this->donationDestinations
            ->activeCauses($locale)
            ->each(fn (DonationType $cause) => $cause->loadMissing('seo'))
            ->filter(fn (DonationType $cause) => $this->isIndexable($cause->seo))
            ->map(fn (DonationType $cause) => [
                'loc' => $this->sitemapLocation(
                    $cause->purpose_key === 'direct' ? null : $cause->seo?->canonical_url,
                    $cause->purpose_key === 'direct'
                        ? route('frontend.donate.direct')
                        : route('frontend.donate.cause', ['cause' => $cause->slug]),
                    $locale
                ),
                'lastmod' => $this->lastModified($cause, $cause->seo),
            ]);

        $jobs = JobPosting::query()
            ->publicDetail()
            ->whereHas('translations', fn ($query) => $query
                ->where('locale', $locale)
                ->whereNotNull('slug')
                ->where('slug', '!=', ''))
            ->with([
                'seo',
                'translations' => fn ($query) => $query
                    ->whereIn('locale', $this->localization->publicLocales())
                    ->whereNotNull('slug')
                    ->where('slug', '!=', ''),
            ])
            ->get()
            ->filter(fn (JobPosting $posting): bool => $this->isIndexable($posting->seo))
            ->map(function (JobPosting $posting) use ($locale): array {
                /** @var JobPostingTranslation $translation */
                $translation = $posting->translations->firstWhere('locale', $locale);

                return [
                    'loc' => $this->sitemapLocation(
                        $posting->seo?->canonical_url,
                        route('frontend.jobs.show', ['job' => $translation->slug]),
                        $locale
                    ),
                    'lastmod' => $this->lastModified($posting, $translation, $posting->seo),
                    'alternates' => $this->jobSitemapAlternates($posting),
                ];
            });

        $workshops = Workshop::query()
            ->publicDetail()
            ->whereHas('translations', fn ($query) => $query
                ->where('locale', $locale)
                ->whereNotNull('slug')
                ->where('slug', '!=', ''))
            ->with([
                'seo',
                'translations' => fn ($query) => $query
                    ->whereIn('locale', $this->localization->publicLocales())
                    ->whereNotNull('slug')
                    ->where('slug', '!=', ''),
            ])
            ->get()
            ->filter(fn (Workshop $workshop): bool => $this->isIndexable($workshop->seo))
            ->map(function (Workshop $workshop) use ($locale): array {
                /** @var WorkshopTranslation $translation */
                $translation = $workshop->translations->firstWhere('locale', $locale);

                return [
                    'loc' => $this->sitemapLocation(
                        $workshop->seo?->canonical_url,
                        route('frontend.workshops.show', ['workshop' => $translation->slug]),
                        $locale
                    ),
                    'lastmod' => $this->lastModified($workshop, $translation, $workshop->seo),
                    'alternates' => $this->workshopSitemapAlternates($workshop),
                ];
            });

        return $staticEntries
            ->concat($publicationArchiveEntries)
            ->concat($categories)
            ->concat($events)
            ->concat($projects)
            ->concat($reports)
            ->concat($donationCauses)
            ->concat($jobs)
            ->concat($workshops)
            ->concat($pages)
            ->filter(fn (array $entry) => $this->seo->isSameOrigin($entry['loc']))
            ->sortBy('loc')
            ->unique('loc')
            ->values();
    }

    /** @return array<int, array{locale: string, url: string}> */
    private function publicationArchiveSitemapAlternates(string $routeName): array
    {
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $locales = collect($this->localization->publicLocales());
        $routeMetadata = SeoMetadata::query()
            ->where('route_name', $routeName)
            ->whereIn('locale', $locales->all())
            ->get()
            ->keyBy('locale');
        $eligibleLocales = $this->seo->indexableRouteLocales($routeName, $locales->all());
        $links = collect($eligibleLocales)
            ->map(fn (string $locale): array => [
                'locale' => $locale,
                'url' => $this->publicationArchiveSitemapLocation($routeMetadata->get($locale), $routeName, $locale),
            ])
            ->values();

        $default = $links->firstWhere('locale', $defaultLocale);
        if ($default) {
            $links->push(['locale' => 'x-default', 'url' => $default['url']]);
        }

        return $links->all();
    }

    private function publicationArchiveSitemapLocation(?SeoMetadata $metadata, string $routeName, string $locale): string
    {
        $fallback = route($routeName);
        $candidate = trim((string) $metadata?->canonical_url);
        if ($candidate !== ''
            && $this->seo->isSameOrigin($candidate)
            && !preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            $parts = parse_url($candidate);
            if ($parts !== false && !isset($parts['user']) && !isset($parts['pass'])) {
                parse_str((string) ($parts['query'] ?? ''), $query);
                unset($query['page'], $query[(string) config('seo.locale_query_parameter', 'lang')]);
                if ($query === []) {
                    $fallback = url('/' . ltrim((string) ($parts['path'] ?? '/'), '/'));
                }
            }
        }

        return (string) $this->seo->localizedUrl($fallback, $locale);
    }

    private function isIndexable(?SeoMetadata $metadata): bool
    {
        if (!$metadata) {
            return true;
        }

        $canonical = trim((string) $metadata->canonical_url);

        return $metadata->robots_index
            && !$metadata->exclude_from_sitemap
            // A deliberately external canonical makes this local URL
            // non-canonical, so listing the local fallback would send search
            // engines two contradictory canonicalization signals.
            && ($canonical === '' || $this->seo->isSameOrigin($canonical));
    }

    /** @return array<int, array{locale: string, url: string}> */
    private function eventSitemapAlternates(NoticeBoard $event): array
    {
        if (blank($event->translation_key)) {
            return [];
        }

        $variants = NoticeBoard::with('seo')
            ->where('translation_key', $event->translation_key)
            ->where('status', 1)
            ->whereIn('language', $this->localization->publicLocales())
            ->whereNotNull('slug')
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->get()
            ->filter(fn (NoticeBoard $variant) => $this->isIndexable($variant->seo));

        if ($variants->count() < 2) {
            return [];
        }

        $links = $variants->map(fn (NoticeBoard $variant) => [
            'locale' => (string) $variant->language,
            'url' => $this->sitemapLocation(
                $variant->seo?->canonical_url,
                route('frontend.event', ['slug' => $variant->slug]),
                (string) $variant->language
            ),
        ])->values();
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $default = $links->firstWhere('locale', $defaultLocale);
        if ($default) {
            $links->push(['locale' => 'x-default', 'url' => $default['url']]);
        }

        return $links->all();
    }

    /** @return array<int, array{locale: string, url: string}> */
    private function annualReportSitemapAlternates(AnnualReport $report): array
    {
        if (blank($report->translation_key)) {
            return [];
        }

        $variants = AnnualReport::with('seo')
            ->publiclyReleased()
            ->where('translation_key', $report->translation_key)
            ->whereIn('language', $this->localization->publicLocales())
            ->whereNotNull('slug')
            ->get()
            ->filter(fn (AnnualReport $variant) => $this->isIndexable($variant->seo));

        if ($variants->count() < 2) {
            return [];
        }

        $links = $variants->map(fn (AnnualReport $variant) => [
            'locale' => (string) $variant->language,
            'url' => $this->sitemapLocation(
                $variant->seo?->canonical_url,
                route('frontend.annual_report.show', ['slug' => $variant->slug]),
                (string) $variant->language
            ),
        ])->values();
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $default = $links->firstWhere('locale', $defaultLocale);
        if ($default) {
            $links->push(['locale' => 'x-default', 'url' => $default['url']]);
        }

        return $links->all();
    }

    /** @return array<int, array{locale: string, url: string}> */
    private function jobSitemapAlternates(JobPosting $posting): array
    {
        return $this->opportunitySitemapAlternates(
            $posting->translations,
            'frontend.jobs.show',
            'job'
        );
    }

    /** @return array<int, array{locale: string, url: string}> */
    private function workshopSitemapAlternates(Workshop $workshop): array
    {
        return $this->opportunitySitemapAlternates(
            $workshop->translations,
            'frontend.workshops.show',
            'workshop'
        );
    }

    /**
     * @param Collection<int, JobPostingTranslation|WorkshopTranslation> $translations
     * @return array<int, array{locale: string, url: string}>
     */
    private function opportunitySitemapAlternates(
        Collection $translations,
        string $routeName,
        string $routeParameter
    ): array {
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $links = $translations
            ->filter(fn (JobPostingTranslation|WorkshopTranslation $translation) => filled($translation->slug))
            ->unique('locale')
            ->sortBy(fn (JobPostingTranslation|WorkshopTranslation $translation): string =>
                ((string) $translation->locale === $defaultLocale ? '0' : '1') . $translation->locale)
            ->map(fn (JobPostingTranslation|WorkshopTranslation $translation): array => [
                'locale' => (string) $translation->locale,
                'url' => $this->sitemapLocation(
                    null,
                    route($routeName, [$routeParameter => $translation->slug]),
                    (string) $translation->locale
                ),
            ])
            ->values();

        if ($links->count() < 2) {
            return [];
        }

        $default = $links->firstWhere('locale', $defaultLocale);
        if ($default) {
            $links->push(['locale' => 'x-default', 'url' => $default['url']]);
        }

        return $links->all();
    }

    private function isPublicPage(Page $page): bool
    {
        $published = $page->publication_status === 'published'
            || ($page->publication_status === 'scheduled'
                && $page->scheduled_for
                && $page->scheduled_for->isPast());
        $publicationDateReached = !$page->published_at || $page->published_at->isPast();

        return (bool) $page->status
            && $page->visibility === 'public'
            && $published
            && $publicationDateReached;
    }

    private function sitemapLocation(?string $canonical, string $fallback, string $locale): string
    {
        $url = $canonical && $this->seo->isSameOrigin($canonical) ? $canonical : $fallback;

        return (string) $this->seo->localizedUrl($url, $locale);
    }

    private function lastModified(?Model ...$records): ?string
    {
        return collect($records)
            ->map(fn (?Model $record) => $record?->updated_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())
            ->first()?->toAtomString();
    }

    private function latestModification(string $locale): ?string
    {
        $dates = collect([
            Page::where('language', $locale)->max('updated_at'),
            Category::where('language', $locale)->max('updated_at'),
            NoticeBoard::where('language', $locale)->max('updated_at'),
            AnnualReport::where('language', $locale)->max('updated_at'),
            Tag::max('updated_at'),
            DonationType::max('updated_at'),
            JobPosting::max('updated_at'),
            JobPostingTranslation::where('locale', $locale)->max('updated_at'),
            Workshop::max('updated_at'),
            WorkshopTranslation::where('locale', $locale)->max('updated_at'),
            SeoMetadata::where('locale', $locale)->max('updated_at'),
        ])->filter()->map(fn ($date) => \Illuminate\Support\Carbon::parse($date));

        return $dates->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())->first()?->toAtomString();
    }

    private function xmlResponse(
        Request $request,
        string $cacheKey,
        Closure $render,
        ?string $contentLanguage = null
    ): Response {
        $ttl = max(0, (int) config('seo.sitemap_cache_seconds', 300));
        $key = 'seo-xml:' . sha1((string) config('app.url') . '|' . $cacheKey);
        $content = app()->environment('testing') || $ttl === 0
            ? $render()
            : Cache::remember($key, now()->addSeconds($ttl), $render);
        $etag = '"' . hash('sha256', $content) . '"';
        $headers = [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=' . $ttl . ', stale-while-revalidate=' . ($ttl * 2),
            'ETag' => $etag,
        ];
        if ($contentLanguage) {
            $headers['Content-Language'] = $contentLanguage;
        }

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($content, 200, $headers);
    }

    private function pageUrl(Page $page): string
    {
        return match ($page->slug) {
            'home' => route('frontend.home'),
            'about-us' => route('frontend.about'),
            'zakat' => route('frontend.zakat'),
            'sponsor-a-child' => route('frontend.sponsor_child'),
            default => route('frontend.page', ['slug' => $page->slug]),
        };
    }
}
