<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Page;
use App\Models\Category;
use App\Models\Banner;
use App\Helper\StaticUtil;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use Exception;

class HomeController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private PublicStructuredDataService $structuredData,
    ) {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Home;
        try {
            $category = Category::select('categories.*')
                ->with(['page' => function ($query) {
                    $query->publiclyAvailable()->where('language', app()->getLocale());
                }])
                ->where('status', 1)
                ->where('slug', 'home')
                ->where('language', app()->getLocale())
                ->first();

            $homePage = Page::with(['visibleBlocks.reusableBlock'])
                ->publiclyAvailable()
                ->where('slug', 'home')
                ->where('language', app()->getLocale())
                ->first();

            if ($homePage) {
                $homePage->setAttribute('description', $this->sanitizer->sanitizeHtml($homePage->description));
                $homePage->setAttribute('inline_css', $this->sanitizer->sanitizeCss($homePage->inline_css));
                $homePage->visibleBlocks->each(function ($block) {
                    $block->setAttribute('content', $this->blockResolver->resolve($block));
                    $block->setAttribute('settings', $block->resolvedSettings());
                    $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
                    $block->unsetRelation('reusableBlock');
                });
            }

            $sliders =  Banner::select('banners.*')
                ->where('type', 'banner-home')
                ->where('status', 1)
                ->where('language', app()->getLocale())
                ->orderBy('order_by', 'ASC')
                ->get();
            $sliders->each(function (Banner $banner) {
                $image = (string) $banner->getRawOriginal('image');
                $banner->setAttribute('image', $image === '' || str_starts_with($image, '/')
                    ? $image
                    : '/storage/photos/1/banner/' . $image);
            });

            $meta_tag = $homePage
                ? app(SeoMetadataService::class)->metaForPage($homePage)
                : [
                    'meta_keyword' => @$category->meta_keyword,
                    'meta_title' => @$category->meta_title ?? $title,
                    'meta_description' => @$category->meta_description,
                ];
            $meta_tag['canonical_url'] = $meta_tag['canonical_url'] ?? url('/');
            if ($homePage?->visibility === 'unlisted') {
                $meta_tag['robots'] = 'noindex,nofollow';
            }
            if (empty($meta_tag['schema_markup'])) {
                $meta_tag['schema_markup'] = $this->structuredData->website(
                    (string) ($meta_tag['meta_title'] ?: $title ?: 'Ignite Global Foundation'),
                    (string) ($meta_tag['meta_description'] ?? ''),
                    (string) app(SeoMetadataService::class)->localizedUrl(url('/'), (string) app()->getLocale())
                );
            }
            $contentSeo = $homePage ? $meta_tag : [];

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $meta_tag,
                'contentSeo' => $contentSeo,
                'data' => [
                    'category' => $category,
                    'homePage' => $homePage,
                    'sliders' => $sliders,
                ],
                'properties' => [
                    'page' => 1,
                    'total_page' => 1,
                ],
            ];

            StaticUtil::ssr($meta_tag);
            return Inertia::render('Home/home')->with($response);
        } catch (Exception $e) {
            report($e);
            throw $e;
        }
    }

    public function search(Request $request)
    {
        $title = '';
        try {
            $search = $request->q;
            $page = Page::select('pages.*')
                ->publiclyAvailable()
                ->selectRaw('(SELECT count(id) FROM comments where (comments.page_id = pages.id and comments.is_delete != 1 and comments.status = 1)) as total_comments')
                ->selectRaw('DATE_FORMAT(pages.updated_at, "%M,%d,%Y") as date_at')
                ->leftjoin('categories', 'categories.id', '=', 'pages.category_id')
                ->where(function ($query) use ($search) {
                    $query->where('pages.name', 'like', '%' . $search . '%');
                    $query->orWhere('pages.description', 'like', '%' . $search . '%');
                    $query->orWhere('categories.name', 'like', '%' . $search . '%');
                    $query->orWhere('categories.description', 'like', '%' . $search . '%');
                })
                ->orderBy('pages.order_by', 'ASC')
                ->where('pages.language', app()->getLocale())
                ->paginate(12);

            $title = 'search';
            $meta_tag = [
                'meta_keyword' => '',
                'meta_title' => '',
                'meta_description' => '',
            ];

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $meta_tag,
                'properties' => [
                    'page' => $page->currentPage(),
                    'total_page' => $page->lastPage(),
                ],
                'data' => [
                    "search" => $search,
                    "items" => $page->items(),
                ],
            ];
            return Inertia::render('search')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function language(string $language = 'en')
    {
        abort_unless(in_array($language, app(\App\Services\LocalizationManager::class)->publicLocales(), true), 404);
        Session()->put('locale', $language);
        app()->setLocale($language);

        return redirect()->back();
    }
}
