<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    private const AWARDS_CATEGORY_UUID = '61000000-0000-4000-8000-000000000003';
    private const LEGACY_CAREER_SLUG = 'career';

    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private SeoMetadataService $seo,
        private SiteSettingService $siteSettings,
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
        $banner = $this->archiveBanner($category->banner, $category);
        $archiveDesign = $this->archiveDesign();

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

        $categoryImage = $category->path
            ?: $category->image
            ?: ($banner['image_url'] ?? null)
            ?: $landingPage?->thumbnail;

        return Inertia::render('category')->with([
            'status' => true,
            'title' => $category->name,
            'meta_tag' => $this->seo->metaForModel($category, [
                'meta_keyword' => $category->meta_keyword,
                'meta_title' => $category->meta_title ?: $category->name . ' | Ignite Global Foundation',
                'meta_description' => $category->meta_description ?: trim(strip_tags((string) $category->description)),
                'meta_image' => $categoryImage,
            ], route('frontend.category', ['slug' => $category->slug])),
            'properties' => [
                'page' => $pages->currentPage(),
                'total_page' => $pages->lastPage(),
                'total_count' => $pages->total(),
                'search' => $search,
            ],
            'data' => [
                'banner' => $banner,
                'category' => $category,
                'landing_page' => $landingPage,
                'items' => $pages->items(),
                'archive_design' => $archiveDesign,
                'is_awards_category' => hash_equals(self::AWARDS_CATEGORY_UUID, (string) $category->uuid),
            ],
        ]);
    }

    /**
     * Publish a deliberately small, allow-listed presentation contract. This
     * keeps Website Customizer values useful without allowing arbitrary class
     * names or layout values to leak into the public template.
     */
    private function archiveDesign(): array
    {
        $settings = data_get(
            $this->siteSettings->values(app()->getLocale(), true),
            'content_archives',
            []
        );
        $settings = is_array($settings) ? $settings : [];

        $requestedHeroLayout = $this->allowedSetting(
            $settings['category_hero_layout'] ?? null,
            ['compact', 'split'],
            'compact'
        );
        $showBanner = filter_var(
            $settings['category_show_banner'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        $cardEyebrow = $this->plainText($settings['category_card_eyebrow'] ?? '');
        $cardLinkLabel = $this->plainText($settings['category_card_link_label'] ?? 'Explore program');
        $bottomCta = [
            'enabled' => filter_var(
                $settings['category_bottom_cta_enabled'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            ),
            'title' => $this->plainText($settings['category_bottom_cta_title'] ?? ''),
            'body' => $this->plainText($settings['category_bottom_cta_body'] ?? ''),
            'label' => $this->plainText($settings['category_bottom_cta_label'] ?? ''),
            'url' => $this->sanitizer->sanitizeUrl($settings['category_bottom_cta_url'] ?? ''),
        ];
        $bottomCta['enabled'] = $bottomCta['enabled']
            && $bottomCta['title'] !== ''
            && $bottomCta['label'] !== ''
            && $bottomCta['url'] !== '';

        return [
            'hero_layout' => $requestedHeroLayout,
            'show_banner' => $showBanner,
            'card_columns' => $this->allowedSetting(
                $settings['category_card_columns'] ?? null,
                ['auto', '2', '3', '4'],
                'auto'
            ),
            'card_eyebrow' => $cardEyebrow,
            'show_card_eyebrow' => filter_var(
                $settings['category_card_show_eyebrow'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ) && $cardEyebrow !== '',
            'card_link_label' => $cardLinkLabel,
            'show_card_link' => filter_var(
                $settings['category_card_show_link'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            ) && $cardLinkLabel !== '',
            'bottom_cta' => $bottomCta,
        ];
    }

    private function archiveBanner(?Banner $banner, Category $category): ?array
    {
        if (!$banner) {
            return null;
        }

        $headline = $this->plainText($banner->headline);
        $subheadline = $this->plainText($banner->subheadline);
        $legacyName = trim((string) $banner->name);

        if ($headline === '' && preg_match('/<b[^>]*>(.*?)<\/b>/is', $legacyName, $matches)) {
            $headline = $this->plainText($matches[1] ?? '');
            if ($subheadline === '') {
                $subheadline = $this->plainText(str_replace($matches[0], '', $legacyName));
            }
        } elseif ($headline === '') {
            $headline = $this->plainText($legacyName);
        }

        $imageUrl = $this->sanitizer->sanitizeUrl($banner->image_url);
        $ctaUrl = $this->sanitizer->sanitizeUrl($banner->cta_url ?: $banner->url);

        return [
            'id' => $banner->getKey(),
            'uuid' => (string) $banner->uuid,
            'eyebrow' => $this->plainText($banner->eyebrow),
            'headline' => $headline,
            'subheadline' => $subheadline,
            'description' => $this->plainText($banner->description),
            'image_url' => $imageUrl,
            'image_alt' => $this->plainText($banner->image_alt) ?: (string) $category->name,
            'cta_label' => $this->plainText($banner->cta_label),
            'cta_url' => $ctaUrl,
        ];
    }

    private function allowedSetting(mixed $value, array $allowed, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function plainText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
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
