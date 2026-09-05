<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\AnnualReport;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicSystemPageMetaService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AnnualReportController extends Controller
{
    public function __construct(
        private PublicArchiveSeoService $archiveSeo,
        private PublicSystemPageMetaService $systemMeta,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
        private SiteSettingService $siteSettings,
    ) {
    }

    public function index(Request $request)
    {
        $pageMeta = $this->systemMeta->resolve(
            $request,
            'reports_page.title',
            'reports_page.introduction',
            [
                'title' => 'Annual reports',
                'meta_title' => 'Annual Reports',
                'description' => 'Read Ignite Global Foundation annual reports and published records of programs, governance, and responsible stewardship.',
            ],
        );
        $title = $pageMeta['title'];
        $search = is_string($request->query('search'))
            ? trim((string) $request->query('search'))
            : '';
        $published_at = is_string($request->query('published_at'))
            ? trim((string) $request->query('published_at'))
            : '';
        if ($published_at !== '' && !$this->isIsoDate($published_at)) {
            $published_at = '';
        }

        $annualReports = AnnualReport::query()
            ->publiclyReleased()
            ->where('language', app()->getLocale())
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($published_at, function ($query, $published_at) {
                $query->whereDate('published_at', date('Y-m-d', strtotime($published_at)));
            })
            ->orderBy('order_by', 'desc')
            ->paginate(12)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($annualReports);

        $reportPageSettings = (array) data_get(
            $this->siteSettings->values((string) app()->getLocale(), true),
            'reports_page',
            [],
        );
        $summaryFallback = trim((string) ($reportPageSettings['detail_summary_fallback'] ?? ''));

        $annualReports->getCollection()->transform(function (AnnualReport $item) use ($summaryFallback): array {
            $publishedAt = $item->published_at;
            $imageUrl = $this->coverImageUrl($item);

            return [
                'id' => $item->id,
                'title' => (string) $item->title,
                'sub_title' => (string) $item->sub_title,
                'slug' => (string) $item->slug,
                'summary' => $this->summary($item, $summaryFallback),
                'publisher_name' => (string) $item->publisher_name,
                'published_at' => $publishedAt ? Carbon::parse($publishedAt)->toDateString() : null,
                'file_type' => 'application/pdf',
                'file_size' => is_numeric($item->file_size) ? (int) $item->file_size : null,
                'image_url' => $imageUrl,
                'landing_url' => route('frontend.annual_report.show', ['slug' => $item->slug]),
                'download_url' => route('frontend.annual_report.download', ['slug' => $item->slug]),
            ];
        });

        $meta_tag = $this->archiveSeo->apply(
            array_merge(
                $pageMeta['meta_tag'],
                (array) $request->attributes->get('route_seo', []),
            ),
            $request,
            $annualReports,
            route('frontend.annual_report.index'),
        );
        if (empty($meta_tag['schema_markup']) || $annualReports->currentPage() > 1) {
            $canonical = (string) $meta_tag['canonical_url'];
            $meta_tag['schema_markup'] = $this->structuredData->collection(
                $title,
                (string) $meta_tag['meta_description'],
                $canonical,
                $this->breadcrumbs($title, $canonical)
            );
        }

        return Inertia::render('annual-report')->with([
            'status' => true,
            'title' => $title,
            'meta_tag' => $meta_tag,
            'contentSeo' => $meta_tag,
            'seoAlternates' => $this->archiveSeo->alternateUrls((string) $meta_tag['canonical_url']),
            'properties' => [
                'current_page' => $annualReports->currentPage(),
                'per_page' => $annualReports->perPage(),
                'total_page' => $annualReports->lastPage(),
                'total' => $annualReports->total(),
            ],
            'data' => [
                'search' => $search,
                'published_at' => $published_at,
                'items' => $annualReports->items(),
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $report = AnnualReport::query()
            ->publiclyReleased()
            ->where('language', app()->getLocale())
            ->where('slug', $slug)
            ->firstOrFail();

        $canonical = (string) $this->seo->localizedUrl(
            route('frontend.annual_report.show', ['slug' => $report->slug]),
            (string) app()->getLocale()
        );
        $downloadUrl = route('frontend.annual_report.download', ['slug' => $report->slug]);
        $publicSettings = $this->siteSettings->values((string) app()->getLocale(), true);
        $summary = $this->summary(
            $report,
            trim((string) data_get($publicSettings, 'reports_page.detail_summary_fallback', '')),
        );
        $publisherName = trim((string) $report->publisher_name)
            ?: trim((string) data_get($publicSettings, 'branding.site_name', ''));
        $imageUrl = $this->coverImageUrl($report);
        $metaTag = $this->seo->metaForModel($report, array_merge(
            $this->systemMeta->forContent(
                (string) $report->title,
                str($summary)->limit(160)->toString(),
                $request,
            ),
            ['meta_image' => $imageUrl ?: ''],
        ), $canonical);
        if (empty($metaTag['schema_markup'])) {
            $metaTag['schema_markup'] = $this->structuredData->report(
                $report,
                $canonical,
                $downloadUrl,
                $imageUrl,
                $this->breadcrumbs(
                    (string) $report->title,
                    $canonical,
                    'Annual Reports',
                    route('frontend.annual_report.index')
                )
            );
        }

        $publishedAt = $report->published_at ? Carbon::parse($report->published_at) : null;
        $sourceUrl = $this->safeSourceUrl((string) $report->url);

        return Inertia::render('annual-report-detail')->with([
            'status' => true,
            'title' => (string) $report->title,
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'data' => [
                'report' => [
                    'id' => $report->id,
                    'title' => (string) $report->title,
                    'sub_title' => (string) $report->sub_title,
                    'slug' => (string) $report->slug,
                    'summary' => $summary,
                    'publisher_name' => $publisherName,
                    'published_at' => $publishedAt?->toDateString(),
                    'year' => $publishedAt?->year,
                    'file_type' => 'application/pdf',
                    'file_size' => is_numeric($report->file_size) ? (int) $report->file_size : null,
                    'image_url' => $imageUrl,
                    'download_url' => $downloadUrl,
                    'source_url' => $sourceUrl,
                ],
            ],
        ]);
    }

    public function download(string $slug)
    {
        $report = AnnualReport::query()
            ->publiclyReleased()
            ->where('language', app()->getLocale())
            ->where('slug', $slug)
            ->firstOrFail();

        $storedPath = 'annual-reports/' . basename((string) $report->image_path);
        abort_unless(Storage::disk('local')->exists($storedPath), 404);

        return response()->download(
            Storage::disk('local')->path($storedPath),
            str($report->title)->slug('-') . '.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
                'X-Download-Options' => 'noopen',
            ]
        );
    }

    private function isIsoDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return false;
        }

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function summary(AnnualReport $report, string $fallback): string
    {
        $value = $report->description ?: $report->sub_title;
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value);

        return $value !== ''
            ? mb_substr($value, 0, 3000)
            : (trim($fallback) ?: (string) $report->title);
    }

    private function coverImageUrl(AnnualReport $report): ?string
    {
        $coverPath = trim(str_replace('\\', '/', (string) $report->cover_image_path));
        $decodedSegments = explode('/', rawurldecode($coverPath));
        if ($coverPath !== ''
            && !str_starts_with($coverPath, '/')
            && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $coverPath)
            && !preg_match('/[\x00-\x1F\x7F]/', $coverPath)
            && !in_array('..', $decodedSegments, true)
            && Storage::disk('public')->exists($coverPath)) {
            return $this->sameOriginPublicPath(Storage::disk('public')->url($coverPath));
        }

        // Before cover_image_path existed, a small number of deployments used
        // image_path for public artwork. Retain that read-only fallback while
        // treating every PDF filename as the private document it is today.
        $legacyPath = trim((string) $report->getRawOriginal('image_path'));
        if ($legacyPath === '' || str_ends_with(strtolower($legacyPath), '.pdf')) {
            return null;
        }

        if (str_starts_with($legacyPath, '/')) {
            return preg_match('#^/(?!/)#', $legacyPath) === 1 ? $legacyPath : null;
        }
        if (preg_match('#^https?://#i', $legacyPath)) {
            return $this->sameOriginPublicPath($legacyPath);
        }

        return '/storage/photos/1/notice_board/' . basename($legacyPath);
    }

    private function sameOriginPublicPath(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('#^/(?!/)#', $value) === 1) {
            return $value;
        }
        if (!$this->seo->isSameOrigin($value)) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/(?!/)#', $path) === 1
            ? $path
            : null;
    }

    private function safeSourceUrl(string $value): ?string
    {
        $value = trim($value);
        $parts = parse_url($value);
        if ($value === ''
            || preg_match('/[\x00-\x1F\x7F]/', $value)
            || $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (!$this->seo->isSameOrigin($value) && $scheme !== 'https') {
            return null;
        }

        return $value;
    }

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumbs(
        string $currentName,
        string $currentUrl,
        ?string $parentName = null,
        ?string $parentUrl = null,
    ): array {
        $locale = (string) app()->getLocale();
        $items = [[
            'name' => 'Home',
            'url' => (string) $this->seo->localizedUrl(url('/'), $locale),
        ]];
        if ($parentName && $parentUrl) {
            $items[] = [
                'name' => $parentName,
                'url' => (string) $this->seo->localizedUrl($parentUrl, $locale),
            ];
        }
        $items[] = ['name' => $currentName, 'url' => $currentUrl];

        return $items;
    }
}
