<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Helper\StaticUtil;
use App\Models\Banner;
use App\Models\Page;
use App\Services\CategoryLandingPageAliasService;
use App\Services\SeoMetadataService;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private CategoryLandingPageAliasService $landingPageAliases,
        private SeoMetadataService $seo,
    ) {
    }

    public function page(Request $request, $slug = '')
    {
        $specializedRoutes = [
            'home' => 'frontend.home',
            'about-us' => 'frontend.about',
            'zakat' => 'frontend.zakat',
            'sponsor-a-child' => 'frontend.sponsor_child',
        ];

        if (isset($specializedRoutes[$slug])) {
            return redirect()->route($specializedRoutes[$slug], [], 301);
        }

        $page = Page::select(
                'pages.*',
                'categories.name as category_name',
                'categories.slug as category_slug',
                'categories.status as category_status',
                'categories.deleted_at as category_deleted_at',
            )
            ->leftjoin('categories', 'categories.id', '=', 'pages.category_id')
            ->with(['banner', 'visibleBlocks.reusableBlock'])
            ->publiclyAvailable()
            ->where('pages.slug', $slug)
            ->where('pages.language', app()->getLocale())
            ->firstOrFail();

        if ($category = $this->landingPageAliases->categoryForPage($page)) {
            abort_if(blank($category->slug), 404);

            $categoryUrl = route('frontend.category', ['slug' => $category->slug]);

            return redirect()->to(
                (string) $this->seo->localizedUrl($categoryUrl, (string) $category->language),
                301
            );
        }

        $page->setAttribute('description', $this->sanitizer->sanitizeHtml($page->description));
        $page->setAttribute('inline_css', $this->sanitizer->sanitizeCss($page->inline_css));
        $page->visibleBlocks->each(function ($block) {
            $block->setAttribute('content', $this->blockResolver->resolve($block));
            $block->setAttribute('settings', $block->resolvedSettings());
            $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
            $block->unsetRelation('reusableBlock');
        });

        $categorySlug = trim((string) $page->getAttribute('category_slug'));
        if ($categorySlug !== ''
            && (bool) $page->getAttribute('category_status')
            && blank($page->getAttribute('category_deleted_at'))) {
            $page->setAttribute('category_url', $this->seo->localizedUrl(
                route('frontend.category', ['slug' => $categorySlug]),
                (string) $page->language,
            ));
        }

        $meta_tag = $this->seo->metaForPage($page);
        if ($page->visibility === 'unlisted') {
            $meta_tag['robots'] = 'noindex,nofollow';
        }
        $meta_tag['canonical_url'] = $meta_tag['canonical_url'] ?: url()->current();
        StaticUtil::ssr($meta_tag);

        return Inertia::render('page')->with([
            'status' => true,
            'title' => $page->name,
            'meta_tag' => $meta_tag,
            'data' => [
                'page' => $page,
                'banner' => $page->banner,
            ],
        ]);
    }

    /**
     * Render the real visitor component for an authenticated admin, even when
     * the page is still a draft or private. This route is deliberately kept
     * inside the admin middleware group and always carries no-index metadata.
     */
    public function preview(Request $request, string $uuid)
    {
        $locale = (string) $request->query('locale', app()->getLocale());
        $page = Page::with(['banner', 'visibleBlocks.reusableBlock'])
            ->where('uuid', $uuid)
            ->where('language', $locale)
            ->firstOrFail();

        app()->setLocale($locale);

        if ($page->slug === 'sponsor-a-child') {
            return redirect()->route('frontend.sponsor_child');
        }

        $this->prepareForPresentation($page);

        $metaTag = $this->seo->metaForPage($page);
        $metaTag['robots'] = 'noindex,nofollow';
        $metaTag['canonical_url'] = null;

        $shared = [
            'status' => true,
            'title' => 'Preview: ' . $page->name,
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
        ];

        if ($page->slug === 'home') {
            $sliders = Banner::where('type', 'banner-home')
                ->where('status', 1)
                ->where('language', $locale)
                ->orderBy('order_by')
                ->get();
            $sliders->each(function (Banner $banner) {
                $image = (string) $banner->getRawOriginal('image');
                $banner->setAttribute('image', $image === '' || str_starts_with($image, '/')
                    ? $image
                    : '/storage/photos/1/banner/' . $image);
            });

            return Inertia::render('Home/home')->with($shared + [
                'data' => ['homePage' => $page, 'sliders' => $sliders, 'category' => null],
            ]);
        }

        if ($page->slug === 'about-us') {
            $foundersLetter = Page::where('language', $locale)
                ->where('slug', "founder's-letter")
                ->first();
            if ($foundersLetter) {
                $foundersLetter->setAttribute(
                    'description',
                    $this->sanitizer->sanitizeHtml($foundersLetter->description)
                );
            }

            return Inertia::render('about')->with($shared + [
                'data' => [
                    'banner' => $page->banner,
                    'founders_letter' => $foundersLetter,
                    'about_us' => $page,
                ],
            ]);
        }

        if ($page->slug === 'zakat') {
            return Inertia::render('zakat')->with($shared + [
                'data' => ['banner' => $page->banner, 'zakat' => $page],
            ]);
        }

        return Inertia::render('page')->with($shared + [
            'data' => ['page' => $page, 'banner' => $page->banner],
        ]);
    }

    private function prepareForPresentation(Page $page): void
    {
        $page->setAttribute('description', $this->sanitizer->sanitizeHtml($page->description));
        $page->setAttribute('inline_css', $this->sanitizer->sanitizeCss($page->inline_css));
        $page->visibleBlocks->each(function ($block) {
            $block->setAttribute('content', $this->blockResolver->resolve($block));
            $block->setAttribute('settings', $block->resolvedSettings());
            $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
            $block->unsetRelation('reusableBlock');
        });
    }
}
