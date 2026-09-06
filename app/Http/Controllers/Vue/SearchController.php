<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\JobPosting;
use App\Models\JobPostingTranslation;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\Workshop;
use App\Models\WorkshopTranslation;
use App\Services\ContentSanitizer;
use App\Services\SeoMetadataService;
use App\Services\TranslationCenterService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private SeoMetadataService $seoMetadata,
        private TranslationCenterService $translations,
    ) {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Common->Search;
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $locale = app()->getLocale();
        $results = collect();

        $pages = Page::query()
            ->publiclyListed()
            ->where('language', $locale)
            ->with(['blocks' => fn ($query) => $query->visible()->with('reusableBlock')])
            ->get()
            ->map(function (Page $page) use ($search): ?array {
                $blockText = $this->pageBlockText($page);
                if (!$this->matches($search, $page->name, $page->sub_title, $page->description, $blockText)) {
                    return null;
                }

                return $this->result(
                    'page',
                    $page->id,
                    $page->name,
                    $page->sub_title,
                    $blockText !== '' ? $blockText : $page->description,
                    $this->relativeUrl($this->seoMetadata->publicUrlForPage($page)),
                    (int) $page->order_by
                );
            })
            ->filter();
        $results = $results->concat($pages);

        $results = $results->concat(Category::query()
            ->where('status', 1)
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['name', 'description']))
            ->get()
            ->map(fn (Category $category) => $this->result(
                'program', $category->id, $category->name, '', $category->description,
                '/category/' . $category->slug, 0
            )));

        $results = $results->concat(NoticeBoard::query()
            ->publiclyReleased()
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['title', 'sub_title', 'description']))
            ->get()
            ->map(fn (NoticeBoard $event) => $this->result(
                'event', $event->id, $event->title, $event->sub_title, $event->description,
                '/event/' . $event->slug, (int) $event->order_by
            )));

        $results = $results->concat(AnnualReport::query()
            ->publiclyReleased()
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['title', 'sub_title', 'description']))
            ->get()
            ->map(fn (AnnualReport $report) => $this->result(
                'report', $report->id, $report->title, $report->sub_title, $report->description,
                '/annual-report/' . $report->slug, (int) $report->order_by
            )));

        $results = $results->concat(Gallery::query()
            ->publiclyAvailable()
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['name', 'description']))
            ->get()
            ->map(fn (Gallery $photo) => $this->result(
                'gallery', $photo->id, $photo->name, '', $photo->description,
                '/gallery', (int) $photo->order_by
            )));

        $causes = DonationType::query()
            ->active()
            ->orderBy('display_order')
            ->get();
        $localizedCauses = $this->translations->localizedContentValues(
            'donation_cause',
            $causes->mapWithKeys(fn (DonationType $cause): array => [
                (string) $cause->uuid => [
                    'name' => (string) $cause->name,
                    'description' => (string) $cause->description,
                ],
            ])->all(),
            $locale,
        );
        $results = $results->concat($causes
            ->map(function (DonationType $cause) use ($localizedCauses, $search): ?array {
                $localized = $localizedCauses[(string) $cause->uuid] ?? [];
                $name = (string) ($localized['name'] ?? $cause->name);
                $description = (string) ($localized['description'] ?? $cause->description);
                if (!$this->matches($search, $name, $description, $cause->destination_name)) {
                    return null;
                }

                return $this->result(
                    'donation',
                    $cause->id,
                    $name,
                    $cause->destination_name,
                    $description,
                    '/donate/' . rawurlencode((string) ($cause->slug ?: $cause->uuid)),
                    0,
                );
            })
            ->filter());

        $results = $results->concat(JobPosting::query()
            ->publicDetail()
            ->with(['translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([$locale, 'en'])))])
            ->get()
            ->map(function (JobPosting $job) use ($locale, $search): ?array {
                /** @var JobPostingTranslation|null $translation */
                $translation = $job->translations->firstWhere('locale', $locale)
                    ?? $job->translations->firstWhere('locale', 'en');
                if (!$translation || !$this->matches(
                    $search,
                    $translation->title,
                    $translation->department,
                    $translation->location,
                    $translation->summary,
                    $translation->description,
                    $translation->responsibilities,
                    $translation->requirements,
                )) {
                    return null;
                }

                return $this->result(
                    'job',
                    $job->id,
                    $translation->title,
                    $translation->department ?: $translation->location,
                    $translation->summary ?: $translation->description,
                    '/careers/' . rawurlencode((string) $translation->slug),
                    0,
                );
            })
            ->filter());

        $results = $results->concat(Workshop::query()
            ->publicDetail()
            ->with(['translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([$locale, 'en'])))])
            ->get()
            ->map(function (Workshop $workshop) use ($locale, $search): ?array {
                /** @var WorkshopTranslation|null $translation */
                $translation = $workshop->translations->firstWhere('locale', $locale)
                    ?? $workshop->translations->firstWhere('locale', 'en');
                if (!$translation || !$this->matches(
                    $search,
                    $translation->title,
                    $translation->summary,
                    $translation->description,
                    $translation->facilitator_name,
                    $translation->venue_name,
                    $translation->venue_address,
                    $translation->registration_instructions,
                )) {
                    return null;
                }

                return $this->result(
                    'workshop',
                    $workshop->id,
                    $translation->title,
                    $translation->venue_name,
                    $translation->summary ?: $translation->description,
                    '/workshops/' . rawurlencode((string) $translation->slug),
                    0,
                );
            })
            ->filter());

        $results = $results
            ->unique(fn (array $item) => $item['view_type'] . ':' . $item['id'])
            ->sortByDesc('order_by')
            ->values();

        $pageNumber = LengthAwarePaginator::resolveCurrentPage();
        $page = new LengthAwarePaginator(
            $results->forPage($pageNumber, 9)->values(),
            $results->count(),
            9,
            $pageNumber,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('search')->with([
            'status' => true,
            'title' => $title,
            'meta_tag' => [
                'meta_title' => $search === '' ? 'Search' : 'Search results for ' . $search,
                'meta_description' => 'Search published Ignite Global Foundation programs, projects, stories, reports, and pages.',
                'robots' => 'noindex,follow',
            ],
            'properties' => [
                'page' => $page->currentPage(),
                'total_page' => $page->lastPage(),
                'total_count' => $page->total(),
                'search' => $search,
            ],
            'data' => ['pages' => $page->items()],
        ]);
    }

    private function searchColumns($query, string $search, array $columns): void
    {
        $query->where(function ($nested) use ($columns, $search) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $nested->{$method}($column, 'like', '%' . $search . '%');
            }
        });
    }

    private function result(string $type, int $id, string $name, ?string $subtitle, ?string $description, string $url, int $order): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'sub_title' => $subtitle,
            'description' => trim(strip_tags($this->sanitizer->sanitizeHtml($description))),
            'view_type' => $type,
            'slug' => basename((string) parse_url($url, PHP_URL_PATH)),
            'result_url' => $url,
            'order_by' => $order,
        ];
    }

    private function pageBlockText(Page $page): string
    {
        return $this->plainText($page->blocks
            ->flatMap(fn ($block) => $this->textValues($block->resolvedContent()))
            ->implode(' '));
    }

    /** @return list<string> */
    private function textValues(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($nested) => $this->textValues($nested))
                ->values()
                ->all();
        }

        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ($value === '' || preg_match('#^(?:https?://|/storage/|data:)#i', $value)) {
            return [];
        }

        return [$value];
    }

    private function matches(string $search, mixed ...$values): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = $this->plainText(collect($values)
            ->filter(fn ($value) => is_scalar($value))
            ->implode(' '));

        return mb_stripos($haystack, $search) !== false;
    }

    private function plainText(?string $value): string
    {
        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            strip_tags($this->sanitizer->sanitizeHtml((string) $value))
        ));
    }

    private function relativeUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path . ($query ? '?' . $query : '');
    }
}
