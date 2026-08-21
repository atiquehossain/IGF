<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Helper\StaticUtil;
use App\Models\Page;
use App\Models\Tag;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(
        private PublicArchiveSeoService $archiveSeo,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
    ) {
    }

    public function projects(Request $request, $slug = '')
    {
        $tag = $slug !== ''
            ? Tag::with('banner')->where('status', 1)->where('slug', $slug)->firstOrFail()
            : null;

        $pages = Page::publiclyAvailable()
            ->with(['pageTags.tag'])
            ->where('language', app()->getLocale())
            ->whereHas('pageTags.tag', function ($query) use ($tag) {
                $query->where('status', 1);
                if ($tag) {
                    $query->whereKey($tag->id);
                }
            })
            ->orderBy('order_by', 'desc')
            ->paginate(12)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($pages);

        $pages->getCollection()->transform(function (Page $page) {
            $thumbnail = trim((string) $page->getRawOriginal('thumbnail'));
            $page->setAttribute('thumbnail', $thumbnail === ''
                ? null
                : (str_starts_with($thumbnail, '/') || preg_match('#^https?://#i', $thumbnail)
                    ? $thumbnail
                    : '/storage/photos/1/page/' . $thumbnail));
            return $page;
        });

        $metaTag = $tag
            ? $this->seo->metaForModel($tag, [
                'meta_keyword' => $tag->name . ', Ignite Global Foundation projects',
                'meta_title' => $tag->name . ' | Ignite Global Foundation',
                'meta_description' => str($tag->description ?: 'Explore community-led Ignite Global Foundation projects and their impact.')->stripTags()->limit(160)->toString(),
                'meta_image' => $tag->banner?->path ?: $tag->banner?->image,
            ], route('frontend.project', ['slug' => $tag->slug]))
            : [
                'meta_title' => 'Our Projects | Ignite Global Foundation',
                'meta_description' => 'Explore community-led Ignite Global Foundation projects across Bangladesh.',
                'meta_keyword' => 'Ignite Global Foundation projects, community development Bangladesh',
                'meta_image' => '',
                'canonical_url' => route('frontend.project'),
                'robots' => 'index,follow',
                'og_title' => 'Our Projects | Ignite Global Foundation',
                'og_description' => 'Explore community-led Ignite Global Foundation projects across Bangladesh.',
                'og_image' => '',
                'twitter_card' => 'summary_large_image',
                'twitter_title' => 'Our Projects | Ignite Global Foundation',
                'twitter_description' => 'Explore community-led Ignite Global Foundation projects across Bangladesh.',
                'twitter_image' => '',
                'schema_markup' => null,
            ];
        $baseUrl = $tag
            ? route('frontend.project', ['slug' => $tag->slug])
            : route('frontend.project');
        if (!$tag) {
            $metaTag = array_merge($metaTag, (array) $request->attributes->get('route_seo', []));
        }
        $metaTag = $this->archiveSeo->apply($metaTag, $request, $pages, $baseUrl);
        if (empty($metaTag['schema_markup']) || $pages->currentPage() > 1) {
            $locale = (string) app()->getLocale();
            $canonical = (string) $metaTag['canonical_url'];
            $metaTag['schema_markup'] = $this->structuredData->collection(
                (string) ($tag?->name ?: 'Our projects'),
                (string) ($metaTag['meta_description'] ?? ''),
                $canonical,
                [
                    ['name' => 'Home', 'url' => (string) $this->seo->localizedUrl(url('/'), $locale)],
                    ['name' => (string) ($tag?->name ?: 'Our projects'), 'url' => $canonical],
                ]
            );
        }
        StaticUtil::ssr($metaTag);

        return Inertia::render('project')->with([
            'status' => true,
            'title' => $tag?->name ?: 'Our projects',
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'seoAlternates' => $this->archiveSeo->alternateUrls((string) $metaTag['canonical_url']),
            'properties' => [
                'page' => $pages->currentPage(),
                'total_page' => $pages->lastPage(),
                'total_count' => $pages->total(),
            ],
            'data' => [
                'tag' => $tag,
                'banner' => $tag?->banner,
                'items' => $pages->items(),
            ],
        ]);
    }
}
