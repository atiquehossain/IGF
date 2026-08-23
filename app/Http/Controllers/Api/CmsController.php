<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Page;
use App\Models\Banner;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\EventCalendar;
use App\Services\ContentSanitizer;
use Carbon\CarbonImmutable;
use Throwable;

class CmsController extends Controller
{
    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $locale = $this->locale($request);
        $title = 'Home';
        $meta_tag = [
            'meta_keyword' => '',
            'meta_title' => 'meta_title',
            'meta_description' => ''
        ];
        try {
            $ourSuccessStory = Category::select('categories.*')->with('page')
                ->where('status', 1)->where('slug', 'our-success-story')
                ->where('language', $locale)->first();

            $graduationStories = Category::select('categories.*')->with('page')
                ->where('status', 1)->where('slug', 'graduation-stories')
                ->where('language', $locale)->first();

            $graduationModel = Page::select('pages.*')
                ->publiclyAvailable()
                ->where('language', $locale)
                ->where('slug', 'graduation-model')->first();

            $publications = Page::select('pages.*')
                ->publiclyAvailable()
                ->where('language', $locale)
                ->where('slug', 'publications')->first();

            $ourGallery = Page::select('pages.*')
                ->publiclyAvailable()
                ->where('language', $locale)
                ->where('slug', 'our-gallery')->first();

            $aboutUs = Page::select('pages.*')
                ->publiclyAvailable()
                ->where('language', $locale)
                ->where('slug', 'about-us')->first();

            foreach ([$ourSuccessStory, $graduationStories] as $category) {
                if ($category) {
                    $this->sanitizeCategory($category);
                }
            }
            foreach ([$graduationModel, $publications, $ourGallery, $aboutUs] as $page) {
                if ($page) {
                    $this->sanitizePage($page);
                }
            }

            $response = [
                'status' => true,
                'title' => $title,
                'component' => "home",
                'meta_tag' => $meta_tag,
                'data' => [
                    'our_success_story' => $ourSuccessStory,
                    'graduation_stories' => $graduationStories,
                    'graduation_model' => $graduationModel,
                    'publications' => $publications,
                    'our_gallery' => $ourGallery,
                    'about_us' => $aboutUs,
                ]
            ];
            $response = array_merge($response, (array) $request->share);
            return response($response, 200);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function category(Request $request, $slug = NULL)
    {
        $title = '';
        try {

            $category = Category::select('categories.*')->with('page')
                ->where('status', 1)
                ->where('slug', $slug)
                ->where('language', $request->share->locale)->first();
            if (!empty($category)) {
                $this->sanitizeCategory($category);
                $title = @$category->name;
                $meta_tag = [
                    'meta_keyword' => @$category->meta_keyword,
                    'meta_title' => @$category->meta_title,
                    'meta_description' => @$category->meta_description
                ];

                $response = [
                    'status' => true,
                    'title' => $title,
                    'meta_tag' => $meta_tag,
                    'data' => $category
                ];

                return response($response, 200);
            }
            return response(['status' => false, 'message' => 'not found'], 404);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function page(Request $request, $slug = NULL)
    {
        $title = '';
        try {
            $page = Page::select('pages.*')->publiclyAvailable()
                ->where('slug', $slug)
                ->where('language', $request->share->locale)
                ->first();

            if (!empty($page)) {
                $this->sanitizePage($page);
                $title = @$page->name;
                $meta_tag = [
                    'meta_keyword' => $page->meta_keyword,
                    'meta_title' => $page->meta_title,
                    'meta_description' => $page->meta_description
                ];

                $response = [
                    'status' => true,
                    'title' => $title,
                    'component' => "page",
                    'meta_tag' => $meta_tag,
                    'data' => $page
                ];
                $response = array_merge($response, (array) $request->share);
                return response($response, 200);
            }
            return response(['status' => false, 'message' => 'not found'], 404);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function story(Request $request, $slug = null)
    {
        $locale = data_get($request, 'share.locale', app()->getLocale());
        $category = Category::query()
            ->with('page')
            ->where('status', 1)
            ->where('slug', $slug)
            ->where('language', $locale)
            ->first();

        if ($category === null) {
            return response(['status' => false, 'message' => 'not found'], 404);
        }

        $this->sanitizeCategory($category);

        return response([
            'status' => true,
            'title' => $category->name,
            'meta_tag' => [
                'meta_keyword' => $category->meta_keyword,
                'meta_title' => $category->meta_title ?: $category->name,
                'meta_description' => $category->meta_description,
            ],
            'data' => [
                'items' => [
                    'page' => $category->page,
                ],
            ],
        ]);
    }

    // public function resource(Request $request)
    // {
    //     try {

    //         $search = $request->search;
    //         $search_date = $request->search_date;
    //         $file_type = $request->file_type;
    //         $file_path = $request->file_path;
    //         $type = $request->type;

    //         $results = NoticeBoard::select('notice_boards.*')
    //             ->selectRaw('DATE_FORMAT(published_at, "%d-%m-%Y") as date_at')
    //             ->selectRaw('DATE_FORMAT(created_at, "%d-%m-%Y") as cdate_at')
    //             ->selectRaw('CONCAT("/storage/photos/1/' . $file_path . '/", notice_boards.file_path) as path')
    //             ->where('status', 1)
    //             ->where('notice_type', $type)
    //             ->where('language', app()->getLocale())
    //             ->where(function ($query) use ($search, $search_date, $file_type) {

    //                 if (!empty($search)) {
    //                     $query->where('title', 'like', '%' . $search . '%');
    //                 }

    //                 if (!empty($file_type)) {
    //                     $query->where('file_type', $file_type);
    //                 }

    //                 if (!empty($search_date)) {
    //                     $toDate = Date('Y-m-d', strtotime($search_date));
    //                     $query->where('published_at', $toDate . ' 00:00:00');
    //                 }
    //             })
    //             ->paginate(12);

    //         $response = [
    //             'status' => true,
    //             'properties' => [
    //                 'page' => $results->currentPage(),
    //                 'total_page' => $results->lastPage(),
    //             ],
    //             'data' => [
    //                 "items" => $results->items()
    //             ],
    //         ];
    //         return response($response, 200);
    //     } catch (Throwable $e) {
    //         return response(['status' => false, 'message' => 'not found'], 200);
    //     }
    // }

    public function recentPost(Request $request, $slug = null)
    {
        $locale = $this->locale($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'search_date_from' => ['nullable', 'date'],
            'search_date_to' => ['nullable', 'date', 'after_or_equal:search_date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $startAt = isset($validated['search_date_from'])
                ? CarbonImmutable::parse($validated['search_date_from'])->startOfDay()
                : CarbonImmutable::now()->startOfMonth();
            $endAt = isset($validated['search_date_to'])
                ? CarbonImmutable::parse($validated['search_date_to'])->endOfDay()
                : CarbonImmutable::now()->endOfMonth();
            $search = trim((string) ($validated['search'] ?? ''));
            $category = $slug === null ? null : Category::query()
                ->where('status', 1)
                ->where('slug', $slug)
                ->where('language', $locale)
                ->first();
            if ($slug !== null && $category === null) {
                return response(['status' => false, 'message' => 'not found'], 404);
            }

            $baseQuery = static fn () => Page::select('pages.*', 'categories.name as category_name')
                ->publiclyAvailable()
                ->join('categories', 'categories.id', '=', 'pages.category_id')
                ->withCount(['comment as total_commtnts' => fn ($query) => $query
                    ->where(fn ($deleted) => $deleted->whereNull('is_delete')->orWhere('is_delete', '!=', 1))])
                ->where('categories.status', 1)
                ->where('pages.language', $locale);

            $recentPost = $baseQuery()
                ->orderBy('pages.published_at', 'desc')
                ->limit(9)
                ->get()
                ->map(fn (Page $item) => $this->publicPagePayload($item));
            $page = $baseQuery()
                ->when($category !== null, fn ($query) => $query->where('pages.category_id', $category->id))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($matches) use ($search): void {
                        $matches->where('pages.name', 'like', '%' . $search . '%')
                            ->orWhere('pages.description', 'like', '%' . $search . '%');
                    });
                })
                ->whereBetween('pages.published_at', [$startAt, $endAt])
                ->orderBy('pages.published_at', 'desc')
                ->paginate(9);
            $page->setCollection($page->getCollection()->map(
                fn (Page $item) => $this->publicPagePayload($item)
            ));

            $response = [
                'status' => true,
                'properties' => [
                    'page' => $page->currentPage(),
                    'total_page' => $page->lastPage(),

                ],
                'data' => [
                    "search" => $search,
                    'recent_post' => $recentPost,
                    "items" => $page->items()
                ],
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function gallery(Request $request)
    {
        $title = 'Gallery';
        $locale = $this->locale($request);
        try {
            $search = $request->search;
            $album_id = $request->album_id;

            $results = Gallery::where('galleries.status', 1)
                  ->select('galleries.id', 'galleries.name', 'albums.name as album_name', 'galleries.grid_column', 'galleries.grid_row')
                  ->leftjoin('albums', 'albums.id', '=', 'galleries.album_id')
                  ->selectRaw('CONCAT("/storage/photos/1/gallery/", galleries.id, "/430X360/", galleries.path) as path')
                  ->selectRaw('CONCAT("/storage/photos/1/gallery/", galleries.id, "/main/", galleries.path) as main_path')
                  ->where('galleries.type', 'gallery')
                  ->where('galleries.name', 'like', '%' . $search . '%')
                  ->where(function ($query) use ($album_id) {
                    if (!empty($album_id)) {
                        $query->where('galleries.album_id', $album_id);
                    }
                  })
                  ->where('galleries.language', $locale)
                  ->orderBy('galleries.updated_at', 'desc')
                  ->paginate(12);

            $response = [
                'status' => true,
                'properties' => [
                    'page' => $results->currentPage(),
                    'total_page' => $results->lastPage(),
                ],
                'data' => [
                    "items" => $results->items()
                ],
            ];

            return response($response, 200);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function members(Request $request)
    {
        $locale = $this->locale($request);
        try {
            $search = $request->search;
            $ourMembers = LatestNews::select([
                    'latest_news.id',
                    'latest_news.name',
                    'latest_news.description',
                    'latest_news.url',
                    'latest_news.path',
                ])
                ->where('type', 'our-members')
                ->where('status', 1)
                ->where('name', 'like', '%' . $search . '%')
                ->where('language', $locale)
                ->orderBy('name', 'ASC')
                ->get()
                ->each(function (LatestNews $member): void {
                    $member->setAttribute('path', '/storage/photos/1/our_members/' . ltrim((string) $member->path, '/'));
                    $member->setAttribute('url', $this->sanitizer->sanitizeUrl($member->url));
                })
                ->chunk(4);

            $response = [
                'status' => true,
                'data' => [
                    'members' => $ourMembers,
                ],
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function events(Request $request)
    {
        $locale = $this->locale($request);

        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        try {
            $startAt = isset($validated['start'])
                ? CarbonImmutable::parse($validated['start'])->startOfDay()
                : CarbonImmutable::now()->startOfMonth();
            $endAt = isset($validated['end'])
                ? CarbonImmutable::parse($validated['end'])->endOfDay()
                : CarbonImmutable::now()->endOfMonth();

            // An event belongs in the range whenever the two intervals
            // overlap, including events that begin before the requested range.
            $eventCalendar = EventCalendar::query()
                ->where('start_date', '<=', $endAt)
                ->where('end_date', '>=', $startAt)
                ->where('language', $locale)
                ->where('status', 1)
                ->orderBy('start_date')
                ->get(['id', 'title', 'description', 'color', 'textColor', 'url', 'start_date', 'end_date'])
                ->map(static fn (EventCalendar $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'color' => $event->color,
                    'textColor' => $event->textColor,
                    'url' => (string) ($event->url ?? ''),
                    'start' => CarbonImmutable::parse($event->start_date)->format('Y-m-d H:i'),
                    'end' => CarbonImmutable::parse($event->end_date)->format('Y-m-d H:i'),
                ]);
            return response($eventCalendar, 200);
        } catch (Throwable $e) {
            return $this->serverFailure($e);
        }
    }

    public function notices(Request $request)
    {
        $locale = $this->locale($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'search_date' => ['nullable', 'date_format:Y-m-d'],
            'file_type' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $results = NoticeBoard::query()
                ->publiclyReleased()
                ->where('notice_type', 'notice-board')
                ->where('language', $locale)
                ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                    $query->where('title', 'like', '%'.trim($validated['search']).'%');
                })
                ->when(filled($validated['search_date'] ?? null), function ($query) use ($validated): void {
                    $query->whereDate('published_at', $validated['search_date']);
                })
                ->when(filled($validated['file_type'] ?? null), function ($query) use ($validated): void {
                    $query->where('file_type', strtolower($validated['file_type']));
                })
                ->orderByDesc('published_at')
                ->paginate(12);

            $results->getCollection()->transform(static fn (NoticeBoard $notice): array => [
                'id' => $notice->id,
                'title' => (string) $notice->title,
                'file_type' => (string) $notice->file_type,
                'file_size' => (string) $notice->file_size,
                'file_path' => (string) $notice->file_path,
                'image_path' => $notice->image_path,
                'url' => $notice->url,
                'date_at' => $notice->published_at
                    ? CarbonImmutable::parse($notice->published_at)->format('d-m-Y')
                    : null,
            ]);

            return response()->json([
                'status' => true,
                'properties' => [
                    'page' => $results->currentPage(),
                    'total_page' => $results->lastPage(),
                    'total_count' => $results->total(),
                ],
                'data' => ['items' => $results->items()],
            ]);
        } catch (Throwable $exception) {
            return $this->serverFailure($exception);
        }
    }

    private function locale(Request $request): string
    {
        return (string) data_get($request, 'share.locale', app()->getLocale());
    }

    private function serverFailure(Throwable $exception)
    {
        report($exception);

        return response([
            'status' => false,
            'message' => 'The requested content is temporarily unavailable.',
        ], 500);
    }

    private function sanitizeCategory(Category $category): void
    {
        $category->setAttribute('description', $this->sanitizer->sanitizeHtml($category->description));
        $category->setAttribute('inline_css', $this->sanitizer->sanitizeCss($category->inline_css));
        if ($category->relationLoaded('page')) {
            $category->page->each(fn (Page $page) => $this->sanitizePage($page));
        }
    }

    private function sanitizePage(Page $page): void
    {
        $page->setAttribute('description', $this->sanitizer->sanitizeHtml($page->description));
        $page->setAttribute('inline_css', $this->sanitizer->sanitizeCss($page->inline_css));
    }

    private function publicPagePayload(Page $page): array
    {
        $this->sanitizePage($page);
        $publishedAt = $page->published_at;
        $payload = $page->toArray();
        $payload['published_at'] = $publishedAt
            ? CarbonImmutable::parse($publishedAt)->format('d-m-Y')
            : null;

        return $payload;
    }
}
