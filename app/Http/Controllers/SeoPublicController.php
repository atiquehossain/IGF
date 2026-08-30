<?php

namespace App\Http\Controllers;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tag;
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

        $backingSlugs = $this->routes->all()->pluck('page_slug')->filter()->unique()->values();
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
            $source = !empty($definition['page_slug']) ? $backingSources->get($definition['page_slug']) : null;
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
            /** @var SeoMetadata|null $routeMetadata */
            $routeMetadata = $routeSeo->get($routeName);
            /** @var Page|null $page */
            $page = isset($definition['page_slug']) ? $backingPages->get($routeName) : null;
            if (isset($definition['page_slug']) && !$page) {
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

        $pages = Page::with('seo')
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('language', $locale)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->get()
            ->reject(fn (Page $page) => $backingSlugs->contains($page->slug)
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

        return $staticEntries
            ->concat($categories)
            ->concat($events)
            ->concat($projects)
            ->concat($reports)
            ->concat($donationCauses)
            ->concat($pages)
            ->filter(fn (array $entry) => $this->seo->isSameOrigin($entry['loc']))
            ->sortBy('loc')
            ->unique('loc')
            ->values();
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

    private function lastModified(?Model $content, ?SeoMetadata ...$metadata): ?string
    {
        return collect([$content?->updated_at, ...array_map(fn (?SeoMetadata $seo) => $seo?->updated_at, $metadata)])
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
