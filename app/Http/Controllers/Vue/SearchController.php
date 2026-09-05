<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Services\ContentSanitizer;
use App\Services\PublicSystemPageMetaService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PublicSystemPageMetaService $systemMeta,
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $locale = app()->getLocale();
        $pageMeta = $this->systemMeta->resolve(
            $request,
            'search_page.title',
            'search_page.introduction',
            [
                'title' => 'Search the site',
                'meta_title' => 'Search',
                'description' => 'Search published Ignite Global Foundation programs, projects, stories, reports, and pages.',
            ],
        );
        $title = $pageMeta['title'];
        $metaTag = $pageMeta['meta_tag'];
        $metaTag['robots'] = 'noindex,follow';
        $results = collect();

        $results = $results->concat(Page::query()
            ->publiclyAvailable()
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['name', 'sub_title', 'description']))
            ->get()
            ->map(fn (Page $page) => $this->result(
                'page', $page->id, $page->name, $page->sub_title, $page->description,
                $this->pageUrl($page->slug), (int) $page->order_by
            )));

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
            ->where('status', 1)
            ->where('language', $locale)
            ->when($search !== '', fn ($query) => $this->searchColumns($query, $search, ['name', 'description']))
            ->get()
            ->map(fn (Gallery $photo) => $this->result(
                'gallery', $photo->id, $photo->name, '', $photo->description,
                '/gallery', (int) $photo->order_by
            )));

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
            'meta_tag' => $metaTag,
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

    private function pageUrl(string $slug): string
    {
        return match ($slug) {
            'home' => '/',
            'about-us' => '/about-us',
            'zakat' => '/zakat',
            default => '/page/' . $slug,
        };
    }
}
