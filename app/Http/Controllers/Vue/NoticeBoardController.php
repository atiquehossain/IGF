<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;

use App\Models\NoticeBoard;
use App\Services\ContentSanitizer;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicSystemPageMetaService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NoticeBoardController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PublicArchiveSeoService $archiveSeo,
        private PublicSystemPageMetaService $systemMeta,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
    ) {
    }

    public function events(Request $request)
    {
        $pageMeta = $this->systemMeta->resolve(
            $request,
            'content_archives.events_default_title',
            'content_archives.events_introduction',
            [
                'title' => 'Events & latest news',
                'description' => 'Discover upcoming events and the latest community-led program news from Ignite Global Foundation.',
            ],
        );
        $title = $pageMeta['title'];

        $events = NoticeBoard::select('notice_boards.*')
            ->publiclyReleased()
            ->where('language', app()->getLocale())
            ->orderBy('order_by', 'desc')
            ->paginate(12)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($events);

        $events->getCollection()->transform(function (NoticeBoard $event) {
            $publishedAt = $event->published_at;
            $event->setAttribute('published_at', $publishedAt ? Carbon::parse($publishedAt)->toDateString() : null);
            $event->setAttribute('image_url', $this->publicImageUrl($event->getRawOriginal('image_path')));
            return $event;
        });

        $meta_tag = $this->archiveSeo->apply(
            array_merge(
                $pageMeta['meta_tag'],
                (array) $request->attributes->get('route_seo', []),
            ),
            $request,
            $events,
            route('frontend.events'),
        );
        if (empty($meta_tag['schema_markup']) || $events->currentPage() > 1) {
            $meta_tag['schema_markup'] = $this->structuredData->collection(
                (string) $title,
                (string) $meta_tag['meta_description'],
                (string) $meta_tag['canonical_url'],
                $this->breadcrumbs((string) $title, (string) $meta_tag['canonical_url'])
            );
        }

        return Inertia::render('events')->with([
            'status' => true,
            'title' => $title,
            'meta_tag' => $meta_tag,
            'contentSeo' => $meta_tag,
            'seoAlternates' => $this->archiveSeo->alternateUrls((string) $meta_tag['canonical_url']),
            'properties' => [
                'events' => $events->currentPage(),
                'total_page' => $events->lastPage(),
                'total_count' => $events->total(),
            ],
            'data' => [
                'items' => $events->items(),
            ],
        ]);
    }

    public function event(Request $request, $slug = '')
    {
        $archivePage = $this->systemMeta->resolve(
            $request,
            'content_archives.events_default_title',
            'content_archives.events_introduction',
            [
                'title' => 'Events & latest news',
                'description' => 'Discover upcoming events and the latest community-led program news from Ignite Global Foundation.',
            ],
        );
        $event = NoticeBoard::query()
            ->publiclyReleased()
            ->where('slug', $slug)
            ->where('language', app()->getLocale())
            ->firstOrFail();
        $publishedAt = $event->published_at;
        $event->setAttribute('published_at', $publishedAt ? Carbon::parse($publishedAt)->toDateString() : null);
        $event->setAttribute('image_url', $this->publicImageUrl($event->getRawOriginal('image_path')));
        $event->setAttribute('description', $this->sanitizer->sanitizeHtml($event->description));
        $event->setAttribute('inline_css', $this->sanitizer->sanitizeCss($event->inline_css));

        $meta_tag = $this->seo->metaForModel($event, array_merge(
            $this->systemMeta->forContent(
                (string) $event->title,
                (string) ($event->sub_title
                    ?: str($event->description)->stripTags()->limit(160)->toString()
                    ?: $archivePage['meta_tag']['meta_description']),
                $request,
            ),
            ['meta_image' => $event->image_url],
        ), route('frontend.event', ['slug' => $event->slug]));
        if (empty($meta_tag['schema_markup'])) {
            $eventUrl = (string) $this->seo->localizedUrl(
                route('frontend.event', ['slug' => $event->slug]),
                (string) app()->getLocale()
            );
            $meta_tag['schema_markup'] = $this->structuredData->event(
                $event,
                $eventUrl,
                $event->image_url,
                $this->breadcrumbs((string) $event->title, $eventUrl, $archivePage['title'], route('frontend.events'))
            );
        }

        return Inertia::render('event')->with([
            'status' => true,
            'title' => $event->title,
            'meta_tag' => $meta_tag,
            'contentSeo' => $meta_tag,
            'data' => [
                'event' => $event,
            ],
        ]);
    }

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumbs(string $currentName, string $currentUrl, ?string $parentName = null, ?string $parentUrl = null): array
    {
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

    private function publicImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return str_starts_with($path, '/') || preg_match('#^https?://#i', $path)
            ? $path
            : '/storage/photos/1/notice_board/' . $path;
    }
}
