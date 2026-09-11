<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageBlock;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BlogController extends Controller
{
    public const CATEGORY_UUID = '61000000-0000-4000-8000-000000000007';

    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private PublicArchiveSeoService $archiveSeo,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
        private SiteSettingService $siteSettings,
    ) {
    }

    public function index(Request $request)
    {
        $locale = (string) app()->getLocale();
        $category = $this->categoryForLocale($locale);
        $presentation = $this->archivePresentation($locale, (string) $category->name);

        $pages = Page::query()
            ->publiclyListed()
            ->where('language', $locale)
            ->whereIn('category_id', $this->categoryKeys($category))
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('published_at')
            ->orderByDesc('last_published_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($pages);
        $pages->setCollection($pages->getCollection()->map(
            fn (Page $page): array => $this->archivePagePayload($page)
        ));

        $metadata = array_merge([
            'meta_keyword' => $locale === 'bn'
                ? 'ইগনাইট ব্লগ, অলাভজনক গল্প, বাংলাদেশ'
                : 'Ignite blog, nonprofit stories, Bangladesh',
            'meta_title' => $presentation['title'] . ' | Ignite Global Foundation',
            'meta_description' => $presentation['introduction'],
        ], (array) $request->attributes->get('route_seo', []));
        $metaTag = $this->archiveSeo->apply($metadata, $request, $pages, route('frontend.blog'));
        if (empty($metaTag['schema_markup']) || $pages->currentPage() > 1) {
            $metaTag['schema_markup'] = $this->structuredData->collection(
                $presentation['title'],
                (string) $metaTag['meta_description'],
                (string) $metaTag['canonical_url'],
                $this->breadcrumbs($presentation['title'], (string) $metaTag['canonical_url'])
            );
        }

        StaticUtil::ssr($metaTag);

        return Inertia::render('blog')->with([
            'status' => true,
            'title' => $presentation['title'],
            'archive_route' => 'frontend.blog',
            'archive_presentation' => $presentation,
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'seoAlternates' => $this->archiveSeo->alternateUrls(
                (string) $metaTag['canonical_url'],
                'frontend.blog',
            ),
            'properties' => [
                'page' => $pages->currentPage(),
                'total_page' => $pages->lastPage(),
                'total_count' => $pages->total(),
            ],
            'data' => [
                'category' => $this->categoryPayload($category),
                'items' => $pages->items(),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $locale = (string) app()->getLocale();
        $category = $this->categoryForLocale($locale);
        $page = Page::query()
            ->with(['visibleBlocks.reusableBlock'])
            ->publiclyAvailable()
            ->where('language', $locale)
            ->where('slug', $slug)
            ->whereIn('category_id', $this->categoryKeys($category))
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->firstOrFail();

        $publicUrl = (string) $this->seo->publicUrlForPage($page);
        $thumbnail = $this->pageImage($page->getRawOriginal('thumbnail'));
        $metaTag = $this->seo->metaForPage($page);
        $metaTag['canonical_url'] = $metaTag['canonical_url'] ?: $publicUrl;
        if ($page->visibility === 'unlisted') {
            $metaTag['robots'] = 'noindex,nofollow';
        }
        if (empty($metaTag['schema_markup'])) {
            $archiveUrl = (string) $this->seo->localizedUrl(route('frontend.blog'), $locale);
            $metaTag['schema_markup'] = $this->structuredData->article(
                $page,
                $publicUrl,
                $thumbnail,
                $this->breadcrumbs(
                    $this->plainText($page->name),
                    $publicUrl,
                    $this->archivePresentation($locale, (string) $category->name)['title'],
                    $archiveUrl,
                ),
            );
        }

        StaticUtil::ssr($metaTag);
        $presentation = $this->archivePresentation($locale, (string) $category->name);
        $archiveUrl = (string) $this->seo->localizedUrl(route('frontend.blog'), $locale);

        return Inertia::render('blog-post')->with([
            'status' => true,
            'title' => $this->plainText($page->name),
            'archive_presentation' => $presentation,
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'seoAlternates' => $this->seo->alternateUrls(
                (string) $metaTag['canonical_url'],
                (array) config('localization.public_locales', ['en', 'bn']),
                (string) config('app.fallback_locale', 'en'),
            ),
            'data' => [
                'page' => $this->detailPagePayload($page, $thumbnail, $publicUrl),
                'archive_url' => $archiveUrl,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    public static function presentationFromSettings(
        array $settings,
        string $locale,
        string $fallbackTitle = 'Blog'
    ): array {
        $bangla = $locale === 'bn';
        $copy = static function (string $key, string $fallback) use ($settings): string {
            $value = trim((string) ($settings[$key] ?? ''));

            return $value !== '' ? $value : $fallback;
        };

        return [
            'eyebrow' => $copy('blog_eyebrow', $bangla ? 'মাঠপর্যায়ের কথা' : 'From the field'),
            'title' => $copy('blog_archive_title', $fallbackTitle),
            'introduction' => $copy('blog_archive_introduction', $bangla
                ? 'আমাদের কমিউনিটি, কাজ ও শেখার গল্প পড়ুন।'
                : 'Read stories, ideas, and lessons from our communities and work.'),
            'listing_label' => $copy('blog_archive_listing_label', $bangla ? 'সর্বশেষ ব্লগ পোস্ট' : 'Latest blog posts'),
            'empty_title' => $copy('blog_archive_empty_title', $bangla ? 'এখনও কোনো লেখা প্রকাশিত হয়নি' : 'No posts published yet'),
            'empty_body' => $copy('blog_archive_empty_body', $bangla
                ? 'নতুন লেখা প্রকাশিত হলে এখানে দেখা যাবে।'
                : 'New posts will appear here after they are published.'),
            'featured_label' => $copy('blog_featured_label', $bangla ? 'নির্বাচিত লেখা' : 'Featured post'),
            'read_label' => $copy('blog_card_link_label', $bangla ? 'লেখাটি পড়ুন' : 'Read the post'),
            'back_label' => $copy('blog_back_label', $bangla ? 'ব্লগে ফিরুন' : 'Back to Blog'),
            'detail_eyebrow' => $copy('blog_detail_eyebrow', $bangla ? 'ব্লগ পোস্ট' : 'Blog post'),
            'footer_label' => $copy('blog_footer_label', $bangla ? 'সব লেখা দেখুন' : 'View all posts'),
            'pagination_label' => $copy('blog_pagination_label', $bangla ? 'ব্লগের পাতাসমূহ' : 'Blog pages'),
            'pagination_page_label' => $copy('blog_pagination_page_label', $bangla ? 'পৃষ্ঠা {0}-এ যান' : 'Go to page {0}'),
            'pagination_current_label' => $copy('blog_pagination_current_label', $bangla ? 'বর্তমান পৃষ্ঠা, পৃষ্ঠা {0}' : 'Current page, page {0}'),
            'pagination_previous_label' => $copy('blog_pagination_previous_label', $bangla ? 'আগের পৃষ্ঠা' : 'Previous page'),
            'pagination_next_label' => $copy('blog_pagination_next_label', $bangla ? 'পরের পৃষ্ঠা' : 'Next page'),
        ];
    }

    private function categoryForLocale(string $locale): Category
    {
        return Category::query()
            ->where('uuid', self::CATEGORY_UUID)
            ->where('language', $locale)
            ->where('status', 1)
            ->firstOrFail();
    }

    /** @return array<int, int|string> */
    private function categoryKeys(Category $category): array
    {
        return array_values(array_unique(array_filter([
            $category->getKey(),
            trim((string) $category->uuid),
        ], static fn ($value): bool => $value !== null && $value !== '')));
    }

    /** @return array<string, int|string|null> */
    private function archivePagePayload(Page $page): array
    {
        return [
            'id' => (int) $page->getKey(),
            'uuid' => (string) $page->uuid,
            'name' => $this->plainText($page->name),
            'slug' => (string) $page->slug,
            'sub_title' => $this->plainText($page->sub_title),
            'excerpt' => $this->excerpt($page),
            'thumbnail' => $this->pageImage($page->getRawOriginal('thumbnail')),
            'thumbnail_alt' => $this->plainText($page->name),
            'published_at' => $page->published_at?->toDateString(),
            'publish_by' => $this->plainText($page->publish_by),
            'public_url' => (string) $this->seo->publicUrlForPage($page),
        ];
    }

    /** @return array<string, mixed> */
    private function detailPagePayload(Page $page, ?string $thumbnail, string $publicUrl): array
    {
        return [
            'id' => (int) $page->getKey(),
            'uuid' => (string) $page->uuid,
            'name' => $this->plainText($page->name),
            'slug' => (string) $page->slug,
            'sub_title' => $this->plainText($page->sub_title),
            'description' => $this->sanitizer->sanitizeHtml($page->description),
            'inline_css' => $this->sanitizer->sanitizeCss($page->inline_css),
            'thumbnail' => $thumbnail,
            'thumbnail_alt' => $this->plainText($page->name),
            'published_at' => $page->published_at?->toDateString(),
            'publish_by' => $this->plainText($page->publish_by),
            'public_url' => $publicUrl,
            'visible_blocks' => $this->publicBlocks($page),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function publicBlocks(Page $page): array
    {
        return $page->visibleBlocks->map(function (PageBlock $block): array {
            return [
                'uuid' => (string) $block->uuid,
                'type' => (string) $block->type,
                'label' => $this->plainText($block->resolvedLabel()),
                'content' => $this->blockResolver->resolve($block),
                'settings' => $block->resolvedSettings(),
                'show_on_desktop' => (bool) $block->show_on_desktop,
                'show_on_mobile' => (bool) $block->show_on_mobile,
            ];
        })->values()->all();
    }

    /** @return array<string, int|string|null> */
    private function categoryPayload(Category $category): array
    {
        return [
            'id' => (int) $category->getKey(),
            'uuid' => (string) $category->uuid,
            'name' => $this->plainText($category->name),
            'slug' => (string) $category->slug,
            'description' => $this->sanitizer->sanitizeHtml($category->description),
            'image_url' => $this->categoryImage($category),
        ];
    }

    private function archivePresentation(string $locale, string $fallbackTitle): array
    {
        $settings = data_get($this->siteSettings->values($locale, true), 'content_archives', []);

        return self::presentationFromSettings(is_array($settings) ? $settings : [], $locale, $fallbackTitle);
    }

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumbs(
        string $currentName,
        string $currentUrl,
        ?string $parentName = null,
        ?string $parentUrl = null
    ): array
    {
        $locale = (string) app()->getLocale();
        $items = [[
            'name' => $locale === 'bn' ? 'হোম' : 'Home',
            'url' => (string) $this->seo->localizedUrl(url('/'), $locale),
        ]];
        if ($parentName && $parentUrl) {
            $items[] = ['name' => $parentName, 'url' => $parentUrl];
        }
        $items[] = ['name' => $currentName, 'url' => $currentUrl];

        return $items;
    }

    private function excerpt(Page $page): string
    {
        $source = $this->plainText($page->sub_title);
        if ($source === '') {
            $source = $this->plainText($page->description);
        }

        return Str::limit($source, 220);
    }

    private function pageImage(?string $value): ?string
    {
        return $this->publicImage($value, 'page');
    }

    private function categoryImage(Category $category): ?string
    {
        return $this->publicImage($category->path ?: $category->image, 'category');
    }

    private function publicImage(?string $value, string $legacyFolder): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $url = str_starts_with($value, '/') || preg_match('#^https?://#i', $value)
            ? $value
            : '/storage/photos/1/' . $legacyFolder . '/' . ltrim($value, '/');
        $url = $this->sanitizer->sanitizeUrl($url);

        return $url !== '' ? $url : null;
    }

    private function plainText(mixed $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
