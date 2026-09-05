<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\PublicSystemPageMetaService;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    private const AWARDS_CATEGORY_UUID = '61000000-0000-4000-8000-000000000003';
    private const LEGACY_CAREER_SLUG = 'career';

    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private PublicSystemPageMetaService $systemMeta,
        private SeoMetadataService $seo,
    ) {
    }

    public function category(Request $request, $slug = '')
    {
        if (hash_equals(self::LEGACY_CAREER_SLUG, (string) $slug)) {
            $url = route('frontend.jobs.index');
            $localeParameter = (string) config('seo.locale_query_parameter', 'lang');
            $requestedLocale = trim((string) $request->query($localeParameter, ''));
            if ($requestedLocale !== '') {
                $url .= '?' . http_build_query([$localeParameter => $requestedLocale]);
            }

            return redirect()->to($url, 301);
        }

        $search = trim((string) $request->query('search', ''));
        $category = Category::select('categories.*')
            ->with(['banner'])
            ->where('status', 1)
            ->where('slug', $slug)
            ->where('language', app()->getLocale())
            ->firstOrFail();

        $category->setAttribute('description', $this->sanitizer->sanitizeHtml($category->description));
        $category->setAttribute('inline_css', $this->sanitizer->sanitizeCss($category->inline_css));
        $landingPage = $this->resolveLandingPage($category);
        $archivePage = $this->systemMeta->resolve(
            $request,
            'content_archives.category_default_title',
            'content_archives.category_search_description',
            [
                'title' => 'Community programs',
                'description' => 'Explore community-led programs and published impact stories from Ignite Global Foundation.',
            ],
        );

        $pages = Page::select('pages.*', 'categories.name as category_name')
            ->publiclyAvailable()
            ->leftJoin('categories', function ($join) {
                $join->on('categories.uuid', '=', 'pages.category_id')
                    ->orOn('categories.id', '=', 'pages.category_id');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('pages.name', 'like', '%' . $search . '%')
                        ->orWhere('pages.sub_title', 'like', '%' . $search . '%');
                });
            })
            ->whereIn('pages.category_id', array_values(array_filter([$category->id, $category->uuid])))
            ->orderBy('pages.order_by', 'desc')
            ->where('pages.language', app()->getLocale())
            ->paginate(12)
            ->withQueryString();

        $pages->getCollection()->transform(function (Page $page) {
            $page->setAttribute('published_at', $page->published_at?->format('d F, Y'));
            $thumbnail = trim((string) $page->getRawOriginal('thumbnail'));
            $page->setAttribute('thumbnail', $thumbnail === ''
                ? null
                : (str_starts_with($thumbnail, '/') || preg_match('#^https?://#i', $thumbnail)
                    ? $thumbnail
                    : '/storage/photos/1/page/' . $thumbnail));
            $page->setAttribute('public_url', $this->seo->publicUrlForPage($page));
            return $page;
        });

        $categoryImage = $category->path ?: $category->image ?: $landingPage?->thumbnail;
        $fallbackMeta = $this->systemMeta->forContent(
            (string) $category->name,
            (string) ($category->meta_description
                ?: trim(strip_tags((string) $category->description))
                ?: $archivePage['meta_tag']['meta_description']),
            $request,
        );
        $fallbackMeta['meta_keyword'] = $category->meta_keyword ?: $fallbackMeta['meta_keyword'];
        $fallbackMeta['meta_title'] = $category->meta_title ?: $fallbackMeta['meta_title'];
        $fallbackMeta['meta_image'] = $categoryImage;

        return Inertia::render('category')->with([
            'status' => true,
            'title' => $category->name,
            'meta_tag' => $this->seo->metaForModel(
                $category,
                $fallbackMeta,
                route('frontend.category', ['slug' => $category->slug]),
            ),
            'properties' => [
                'page' => $pages->currentPage(),
                'total_page' => $pages->lastPage(),
                'total_count' => $pages->total(),
                'search' => $search,
            ],
            'data' => [
                'banner' => $category->banner,
                'category' => $category,
                'landing_page' => $landingPage,
                'items' => $pages->items(),
                'is_awards_category' => hash_equals(self::AWARDS_CATEGORY_UUID, (string) $category->uuid),
            ],
        ]);
    }

    private function resolveLandingPage(Category $category): ?Page
    {
        if ($category->display_mode !== 'landing_page' || blank($category->landing_page_uuid)) {
            return null;
        }

        $page = Page::query()
            ->with(['banner', 'visibleBlocks.reusableBlock'])
            ->publiclyAvailable()
            ->where('pages.uuid', $category->landing_page_uuid)
            ->where('pages.language', app()->getLocale())
            ->whereIn('pages.category_id', array_values(array_filter([$category->id, $category->uuid])))
            ->first();

        if (!$page) {
            return null;
        }

        $page->setAttribute('description', $this->sanitizer->sanitizeHtml($page->description));
        $page->setAttribute('inline_css', $this->sanitizer->sanitizeCss($page->inline_css));
        $page->setAttribute('thumbnail', $this->publicPageThumbnail($page->getRawOriginal('thumbnail')));
        $page->visibleBlocks->each(function ($block): void {
            $block->setAttribute('content', $this->blockResolver->resolve($block));
            $block->setAttribute('settings', $block->resolvedSettings());
            $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
            $block->unsetRelation('reusableBlock');
        });

        return $page;
    }

    private function publicPageThumbnail(?string $value): ?string
    {
        $thumbnail = trim((string) $value);
        if ($thumbnail === '') {
            return null;
        }

        return str_starts_with($thumbnail, '/') || preg_match('#^https?://#i', $thumbnail)
            ? $thumbnail
            : '/storage/photos/1/page/' . $thumbnail;
    }
}
