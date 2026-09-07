<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;

use App\Models\NoticeBoard;
use App\Services\ContentSanitizer;
use App\Services\LocalizationManager;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NoticeBoardController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PublicArchiveSeoService $archiveSeo,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
        private SiteSettingService $siteSettings,
        private LocalizationManager $localization,
    ) {
    }

    public function events(Request $request)
    {
        if ($redirect = $this->legacyArchiveRedirect($request)) {
            return $redirect;
        }

        return $this->archive($request, 'event', 'frontend.events');
    }

    public function news(Request $request)
    {
        return $this->archive($request, 'article', 'frontend.news');
    }

    private function archive(Request $request, string $kind, string $archiveRoute)
    {
        $presentation = $this->archivePresentation($kind, (string) app()->getLocale());
        $title = $presentation['title'];

        $events = NoticeBoard::select('notice_boards.*')
            ->publiclyReleased()
            ->where('language', app()->getLocale())
            ->where('content_kind', $kind)
            ->when(
                $kind === 'article',
                fn ($query) => $query->orderByDesc('published_at')->orderByDesc('id'),
                fn ($query) => $query->orderByDesc('order_by')->orderByDesc('published_at')->orderByDesc('id'),
            )
            ->paginate(12)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($events);

        $events->setCollection(
            $events->getCollection()->map(
                fn (NoticeBoard $event): array => $this->publicNoticePayload($event)
            )
        );

        $metadata = array_merge([
            'meta_keyword' => $presentation['meta_keyword'],
            'meta_title' => $title . ' | Ignite Global Foundation',
            'meta_description' => $presentation['meta_description'],
        ], (array) $request->attributes->get('route_seo', []));

        $meta_tag = $this->archiveSeo->apply(
            $metadata,
            $request,
            $events,
            route($archiveRoute),
        );
        if (empty($meta_tag['schema_markup']) || $events->currentPage() > 1) {
            $meta_tag['schema_markup'] = $this->structuredData->collection(
                (string) ($title ?: 'Events & News'),
                (string) $meta_tag['meta_description'],
                (string) $meta_tag['canonical_url'],
                $this->breadcrumbs((string) ($title ?: 'Events & News'), (string) $meta_tag['canonical_url'])
            );
        }

        return Inertia::render('events')->with([
            'status' => true,
            'title' => $title,
            'archive_kind' => $kind,
            'archive_route' => $archiveRoute,
            'archive_presentation' => $presentation,
            'meta_tag' => $meta_tag,
            'contentSeo' => $meta_tag,
            'seoAlternates' => $this->archiveSeo->alternateUrls(
                (string) $meta_tag['canonical_url'],
                $archiveRoute,
            ),
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

    private function legacyArchiveRedirect(Request $request)
    {
        $value = $request->query('kind');
        if (!is_string($value) || !in_array($value, ['event', 'article'], true)) {
            return null;
        }

        $query = [];
        $localeKey = (string) config('seo.locale_query_parameter', 'lang');
        $locale = $request->query($localeKey);
        if (is_string($locale) && in_array($locale, $this->localization->publicLocales(), true)) {
            $query[$localeKey] = $locale;
        }

        $page = $request->query('page');
        if (is_string($page) && preg_match('/^[1-9]\d*$/D', $page) === 1) {
            $query['page'] = $page;
        }

        return redirect()->route(
            $value === 'article' ? 'frontend.news' : 'frontend.events',
            $query,
            301,
        );
    }

    /** @return array{title: string, introduction: string, listing_label: string, empty_title: string, empty_body: string, card_eyebrow: string, meta_keyword: string, meta_description: string} */
    private function archivePresentation(?string $kind, string $locale): array
    {
        $bangla = $locale === 'bn';
        $settings = $this->siteSettings->values($locale, true)['content_archives'] ?? [];
        $copy = static function (string $key, string $fallback) use ($settings): string {
            $value = trim((string) ($settings[$key] ?? ''));

            return $value !== '' ? $value : $fallback;
        };

        return match ($kind) {
            'event' => [
                'title' => $copy('event_archive_title', $bangla ? 'ইভেন্ট' : 'Events'),
                'introduction' => $copy('event_archive_introduction', $bangla
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের প্রকাশিত ইভেন্ট ও কমিউনিটি কার্যক্রম দেখুন।'
                    : 'Explore published events and community activities from Ignite Global Foundation.'),
                'listing_label' => $copy('event_archive_listing_label', $bangla ? 'প্রকাশিত ইভেন্ট' : 'Published events'),
                'empty_title' => $copy('event_archive_empty_title', $bangla ? 'কোনো ইভেন্ট প্রকাশিত হয়নি' : 'No published events yet'),
                'empty_body' => $copy('event_archive_empty_body', $bangla
                    ? 'নতুন ইভেন্ট প্রকাশিত হলে এখানে দেখা যাবে।'
                    : 'New events will appear here after they are published.'),
                'card_eyebrow' => $copy('event_archive_card_eyebrow', $bangla ? 'ইভেন্ট' : 'Event'),
                'meta_keyword' => 'Ignite events, community events, nonprofit Bangladesh',
                'meta_description' => $copy('event_archive_introduction', $bangla
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের প্রকাশিত ইভেন্ট ও কমিউনিটি কার্যক্রম দেখুন।'
                    : 'Explore published events and community activities from Ignite Global Foundation.'),
            ],
            'article' => [
                'title' => $copy('news_archive_title', $bangla ? 'সর্বশেষ সংবাদ' : 'Latest news'),
                'introduction' => $copy('news_archive_introduction', $bangla
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের সর্বশেষ সংবাদ, গল্প ও কর্মসূচির আপডেট পড়ুন।'
                    : 'Read the latest news, stories, and program updates from Ignite Global Foundation.'),
                'listing_label' => $copy('news_archive_listing_label', $bangla ? 'প্রকাশিত সংবাদ' : 'Published news'),
                'empty_title' => $copy('news_archive_empty_title', $bangla ? 'কোনো সংবাদ প্রকাশিত হয়নি' : 'No published news yet'),
                'empty_body' => $copy('news_archive_empty_body', $bangla
                    ? 'নতুন সংবাদ প্রকাশিত হলে এখানে দেখা যাবে।'
                    : 'New stories will appear here after they are published.'),
                'card_eyebrow' => $copy('news_archive_card_eyebrow', $bangla ? 'সংবাদ' : 'News'),
                'meta_keyword' => 'Ignite news, nonprofit stories, community Bangladesh',
                'meta_description' => $copy('news_archive_introduction', $bangla
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের সর্বশেষ সংবাদ, গল্প ও কর্মসূচির আপডেট পড়ুন।'
                    : 'Read the latest news, stories, and program updates from Ignite Global Foundation.'),
            ],
            default => [
                'title' => $copy('events_default_title', $bangla ? 'ইভেন্ট ও সর্বশেষ সংবাদ' : 'Events & latest news'),
                'introduction' => $copy('events_introduction', $bangla
                    ? 'আমাদের লক্ষ্যকে এগিয়ে নেওয়া মানুষ, ধারণা ও কমিউনিটি-নেতৃত্বাধীন কাজের সঙ্গে পরিচিত হোন।'
                    : 'Meet the people, ideas, and community-led work moving our mission forward.'),
                'listing_label' => $copy('events_listing_label', $bangla ? 'ইভেন্ট ও সংবাদ' : 'Events and news'),
                'empty_title' => $copy('events_empty_title', $bangla ? 'এখনও কিছু প্রকাশিত হয়নি' : 'Nothing published yet'),
                'empty_body' => $copy('events_empty_body', $bangla
                    ? 'নতুন ইভেন্ট ও মাঠপর্যায়ের আপডেটের জন্য আবার দেখুন।'
                    : 'Check back for new events and field updates.'),
                'card_eyebrow' => $copy('event_card_eyebrow', $bangla ? 'ইভেন্ট বা গল্প' : 'Event or story'),
                'meta_keyword' => 'Ignite events, nonprofit news, community Bangladesh',
                'meta_description' => $copy('events_introduction', $bangla
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের ইভেন্ট ও কমিউনিটি-নেতৃত্বাধীন কর্মসূচির সর্বশেষ সংবাদ দেখুন।'
                    : 'Discover events and the latest community-led program news from Ignite Global Foundation.'),
            ],
        };
    }

    public function event(Request $request, $slug = '')
    {
        $locale = (string) app()->getLocale();
        $event = NoticeBoard::query()
            ->publiclyReleased()
            ->where('slug', $slug)
            ->where('language', $locale)
            ->firstOrFail();
        $archiveKind = $event->content_kind === 'event' ? 'event' : 'article';
        $archiveRoute = $archiveKind === 'event' ? 'frontend.events' : 'frontend.news';
        $archivePresentation = $this->archivePresentation($archiveKind, $locale);
        $publicEvent = $this->publicNoticePayload($event, true);

        $meta_tag = $this->seo->metaForModel($event, [
            'meta_keyword' => $event->title,
            'meta_title' => $event->title . ' | Ignite Global Foundation',
            'meta_description' => $event->sub_title ?: str($event->description)->stripTags()->limit(160)->toString(),
            'meta_image' => $publicEvent['image_url'],
        ], route('frontend.event', ['slug' => $event->slug]));
        if (empty($meta_tag['schema_markup'])) {
            $eventUrl = (string) $this->seo->localizedUrl(
                route('frontend.event', ['slug' => $event->slug]),
                (string) app()->getLocale()
            );
            $meta_tag['schema_markup'] = $this->structuredData->event(
                $event,
                $eventUrl,
                $publicEvent['image_url'],
                $this->breadcrumbs((string) $event->title, $eventUrl, $archivePresentation['title'], route($archiveRoute))
            );
        }

        return Inertia::render('event')->with([
            'status' => true,
            'title' => $event->title,
            'archive_route' => $archiveRoute,
            'archive_url' => (string) $this->seo->localizedUrl(route($archiveRoute), $locale),
            'archive_presentation' => $archivePresentation,
            'meta_tag' => $meta_tag,
            'contentSeo' => $meta_tag,
            'data' => [
                'event' => $publicEvent,
            ],
        ]);
    }

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumbs(string $currentName, string $currentUrl, ?string $parentName = null, ?string $parentUrl = null): array
    {
        $locale = (string) app()->getLocale();
        $items = [[
            'name' => $locale === 'bn' ? 'হোম' : 'Home',
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

    private function publicImageAlt(NoticeBoard $event): string
    {
        $value = html_entity_decode(strip_tags((string) $event->image_alt), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value !== '' ? $value : (string) $event->title;
    }

    /**
     * Return only the fields used by the public event/news interfaces.
     *
     * @return array<string, int|string|null>
     */
    private function publicNoticePayload(NoticeBoard $event, bool $includeBody = false): array
    {
        $publishedAt = $event->published_at;
        $payload = [
            'id' => (int) $event->getKey(),
            'uuid' => (string) $event->translation_key,
            'title' => (string) $event->title,
            'sub_title' => $event->sub_title,
            'slug' => (string) $event->slug,
            'content_kind' => (string) $event->content_kind,
            'location' => $event->location,
            'published_at' => $publishedAt ? Carbon::parse($publishedAt)->toDateString() : null,
            'event_start_at' => $event->event_start_at?->toISOString(),
            'event_end_at' => $event->event_end_at?->toISOString(),
            'event_status' => $event->event_status,
            'event_attendance_mode' => $event->event_attendance_mode,
            'image_url' => $this->publicImageUrl($event->getRawOriginal('image_path')),
            'image_alt' => $this->publicImageAlt($event),
        ];

        if ($includeBody) {
            $payload['description'] = $this->sanitizer->sanitizeHtml($event->description);
            $payload['inline_css'] = $this->sanitizer->sanitizeCss($event->inline_css);
        }

        return $payload;
    }
}
