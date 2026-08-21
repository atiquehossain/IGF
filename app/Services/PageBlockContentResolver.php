<?php

namespace App\Services;

use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

class PageBlockContentResolver
{
    private const DEFAULT_SOURCES = [
        'causes' => 'category',
        'events' => 'events',
        'testimonials' => 'testimonials',
        'team' => 'team',
        'gallery' => 'gallery',
    ];

    private const SOCIAL_PLATFORM_LABELS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'twitter' => 'X / Twitter',
        'x' => 'X',
        'youtube' => 'YouTube',
        'website' => 'Website',
    ];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private TranslationCenterService $translations,
        private DonationDestinationService $destinations,
        private SiteSettingService $siteSettings
    ) {
    }

    public function resolve(PageBlock $block): array
    {
        $content = $block->resolvedContent();
        if ($block->type === 'ways_to_give') {
            $content['items'] = $this->waysToGiveItems($content);

            return $this->sanitizer->sanitizeBlockContent($content);
        }

        $source = $this->contentSource($block->type, $content);
        $limit = min(12, max(1, (int) ($content['limit'] ?? 3)));

        // A manual source deliberately preserves the editor-owned item list.
        if ($source === 'manual') {
            return $this->sanitizer->sanitizeBlockContent($content);
        }

        if (($block->type === 'cards' && in_array($source, ['projects', 'category'], true))
            || ($block->type === 'causes' && $source === 'category')) {
            $content['items'] = $this->pageItems($source, $content, $limit);
        } elseif ($block->type === 'events' && $source === 'events') {
            $content['items'] = $this->eventItems($content, $limit);
        } elseif ($block->type === 'testimonials' && $source === 'testimonials') {
            $content['items'] = $this->testimonialItems($content, $limit);
        } elseif ($block->type === 'team' && $source === 'team') {
            $content['items'] = $this->teamItems($content, $limit);
        } elseif ($block->type === 'gallery' && $source === 'gallery') {
            $content['items'] = $this->galleryItems($content, $limit);
        }

        return $this->sanitizer->sanitizeBlockContent($content);
    }

    private function waysToGiveItems(array $content): array
    {
        $manual = ($content['selection_mode'] ?? 'automatic') === 'manual';
        $selectedTokens = collect($content['selected_items'] ?? [])
            ->filter(fn ($token) => is_string($token))
            ->map(fn (string $token) => trim($token))
            ->filter()
            ->unique()
            ->values();

        $allOperationalCauses = $this->destinations->activeCauses(app()->getLocale());
        $operationalCauses = $allOperationalCauses
            ->whereNull('purpose_key')
            ->values();
        if ($manual) {
            $selectedCauseUuids = $selectedTokens
                ->filter(fn (string $token) => str_starts_with($token, 'cause:'))
                ->map(fn (string $token) => substr($token, strlen('cause:')))
                ->values();
            $causes = $selectedCauseUuids->isEmpty()
                ? collect()
                : $operationalCauses
                    ->filter(fn (DonationType $cause) => $selectedCauseUuids->contains((string) $cause->uuid))
                    ->values();
        } else {
            $causes = $operationalCauses;
            $selectedTokens = $causes
                ->map(fn (DonationType $cause) => 'cause:' . $cause->uuid)
                ->concat(['zakat', 'sponsor'])
                ->values();
        }

        $causesByUuid = $causes->keyBy(fn (DonationType $cause) => (string) $cause->uuid);
        $project = $this->waysToGiveProject($content);
        $specialPages = collect([
            'zakat' => $this->specialPage('zakat'),
            'sponsor-a-child' => $this->specialPage('sponsor-a-child'),
        ]);
        $zakatOperational = $specialPages->get('zakat') !== null
            && $allOperationalCauses->firstWhere('purpose_key', 'zakat') !== null;
        $settings = $this->siteSettings->values(app()->getLocale(), true);

        return $selectedTokens->map(function (string $token) use ($causesByUuid, $project, $specialPages, $zakatOperational, $content, $settings): ?array {
            if ($token === 'zakat') {
                if (!$zakatOperational) {
                    return null;
                }

                return $this->specialGivingItem(
                    $specialPages->get('zakat'),
                    'zakat',
                    '/zakat',
                    (array) ($settings['zakat_calculator'] ?? [])
                );
            }

            if ($token === 'sponsor') {
                return $this->specialGivingItem(
                    $specialPages->get('sponsor-a-child'),
                    'sponsor',
                    '/sponsor-child',
                    (array) ($settings['sponsor_page'] ?? [])
                );
            }

            if (!str_starts_with($token, 'cause:')) {
                return null;
            }

            $cause = $causesByUuid->get(substr($token, strlen('cause:')));
            if (!$cause) {
                // Draft blocks may retain a cause that an administrator later
                // unpublished. Never leak it back onto the public website.
                return null;
            }

            $name = $this->translations->localizedContentValue(
                'donation_cause',
                (string) $cause->uuid,
                'name',
                (string) $cause->name,
                app()->getLocale()
            );
            $description = $this->translations->localizedContentValue(
                'donation_cause',
                (string) $cause->uuid,
                'description',
                (string) $cause->description,
                app()->getLocale()
            );
            $destination = match ($cause->destination_type) {
                'unrestricted' => $name,
                'restricted_fund' => $this->translations->localizedContentValue(
                    'donation_cause',
                    (string) $cause->uuid,
                    'destination_name',
                    $this->destinations->destinationName($cause, app()->getLocale()),
                    app()->getLocale()
                ),
                default => $this->destinations->destinationName($cause, app()->getLocale()),
            };
            $url = '/donate?cause=' . rawurlencode((string) ($cause->slug ?: $cause->uuid));
            if ($project && $this->causeAcceptsProject($cause, $project)) {
                $url .= '&project=' . rawurlencode((string) $project->uuid);
            }

            return [
                'key' => $token,
                'kind' => 'cause',
                'heading' => $name,
                'body' => $description,
                'image' => $this->managedCauseImage($this->destinations->causeImageUrl($cause)),
                'image_alt' => $name,
                'url' => $url,
                'link_label' => trim((string) ($content['link_label'] ?? '')) ?: 'Give now',
                'destination' => $destination,
            ];
        })->filter()->values()->all();
    }

    private function waysToGiveProject(array $content): ?Page
    {
        $uuid = trim((string) ($content['project_uuid'] ?? ''));
        if ($uuid === ''
            || ($content['selection_mode'] ?? '') !== 'manual'
            || !in_array($content['layout'] ?? '', ['single_cta', 'banner'], true)
            || count($content['selected_items'] ?? []) !== 1) {
            return null;
        }

        return $this->destinations->preferredFundingPublicPage($uuid, app()->getLocale());
    }

    private function causeAcceptsProject(DonationType $cause, Page $project): bool
    {
        return $this->destinations
            ->selectablePages($cause, app()->getLocale())
            ->contains(fn (Page $candidate) => (string) $candidate->uuid === (string) $project->uuid);
    }

    private function specialPage(string $slug): ?Page
    {
        $uuid = Page::query()->where('slug', $slug)->value('uuid');

        return $uuid
            ? $this->destinations->preferredPublicPage((string) $uuid, app()->getLocale())?->load('banner')
            : null;
    }

    private function specialGivingItem(
        ?Page $page,
        string $kind,
        string $url,
        array $settings
    ): array {
        $fallbackHeading = $kind === 'zakat' ? 'Zakat calculator & donation' : 'Sponsor a Child';
        $fallbackBody = $kind === 'zakat'
            ? 'Calculate your Zakat and continue to the managed Zakat donation cause.'
            : 'Support a child through the foundation’s managed sponsorship program.';
        $heading = trim((string) ($settings['title'] ?? '')) ?: trim((string) ($page?->name ?: $fallbackHeading));
        $body = trim((string) ($settings['introduction'] ?? ''))
            ?: trim((string) ($page?->sub_title ?: str($page?->description ?? '')->stripTags()->limit(180)->toString()));
        $image = $kind === 'sponsor' ? trim((string) ($settings['hero_image'] ?? '')) : '';
        if ($image === '' && $page) {
            $image = $this->publicImage($page->getRawOriginal('thumbnail'), 'page');
        }
        if ($image === '' && $page?->banner) {
            $image = (string) ($page->banner->image_url ?? '');
        }

        $destination = $kind === 'sponsor'
            ? $this->sponsorContributionLabel($settings)
            : (trim((string) ($settings['eyebrow'] ?? '')) ?: $fallbackHeading);

        return [
            'key' => $kind,
            'kind' => $kind,
            'heading' => $heading,
            'body' => $body ?: $fallbackBody,
            'image' => $image,
            'image_alt' => $heading,
            'url' => $url,
            'link_label' => trim((string) ($settings[$kind === 'zakat' ? 'donate_label' : 'hero_cta_label'] ?? ''))
                ?: ($kind === 'zakat' ? 'Calculate Zakat' : 'Sponsor a child'),
            'destination' => $destination,
        ];
    }

    private function sponsorContributionLabel(array $settings): string
    {
        $monthlyAmount = max(1, (int) ($settings['monthly_amount'] ?? 1500));
        $period = trim((string) ($settings['monthly_period_label'] ?? ''))
            ?: 'per child, each month';

        return 'BDT ' . number_format($monthlyAmount) . ' · ' . $period;
    }

    private function managedCauseImage(string $value): string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $normalized = '/' . ltrim($path, '/');

        return preg_match('#^/storage/media/[a-z0-9/_-]+\.(?:avif|gif|jpe?g|png|webp)$#i', $normalized)
            ? $normalized
            : '';
    }

    private function contentSource(string $blockType, array $content): string
    {
        if (isset($content['content_source']) && is_string($content['content_source'])) {
            return $content['content_source'];
        }

        // Existing card grids and hand-picked galleries predate content_source.
        // Treat them as manual so an upgrade never replaces editor-owned cards.
        if ($blockType === 'cards' || ($blockType === 'gallery' && !empty($content['items']))) {
            return 'manual';
        }

        return self::DEFAULT_SOURCES[$blockType] ?? 'manual';
    }

    private function pageItems(string $source, array $content, int $limit): array
    {
        $query = Page::query()
            ->publiclyAvailable()
            ->where('language', app()->getLocale())
            ->with(['category', 'pageTags.tag']);

        if ($source === 'projects') {
            $tagSlug = trim((string) ($content['tag_slug'] ?? ''));
            $query->whereHas('pageTags.tag', function (Builder $tagQuery) use ($tagSlug): void {
                $tagQuery->where('status', 1);
                if ($tagSlug !== '') {
                    $tagQuery->where('slug', $tagSlug);
                }
            });
        } else {
            $categorySlug = trim((string) ($content['category_slug'] ?? 'our-causes'));
            $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                ->where('slug', $categorySlug)
                ->where('status', 1));
        }

        $pages = $this->records($query, $content, $limit, 'uuid', 'name', 'published_at');
        $itemLinkLabel = trim((string) ($content['item_link_label'] ?? ''));
        $requestedTag = trim((string) ($content['tag_slug'] ?? ''));

        return $pages->map(function (Page $page) use ($source, $itemLinkLabel, $requestedTag): array {
            $projectTag = $source === 'projects'
                ? ($requestedTag !== ''
                    ? $page->pageTags->pluck('tag')->firstWhere('slug', $requestedTag)
                    : $page->pageTags->pluck('tag')->first(fn ($tag) => (bool) $tag?->status))
                : null;

            return [
                'status' => $projectTag?->name ?: '',
                'heading' => $page->name,
                'body' => $page->sub_title ?: str($page->description)->stripTags()->limit(140)->toString(),
                'image' => $this->publicImage($page->getRawOriginal('thumbnail'), 'page'),
                'image_alt' => $page->name,
                'url' => '/page/' . $page->slug,
                'link_label' => $itemLinkLabel,
            ];
        })->values()->all();
    }

    private function eventItems(array $content, int $limit): array
    {
        $query = NoticeBoard::query()
            ->publiclyReleased()
            ->where('language', app()->getLocale());
        $events = $this->records($query, $content, $limit, 'id', 'title', 'published_at');
        $itemLinkLabel = trim((string) ($content['item_link_label'] ?? ''));

        return $events->map(fn (NoticeBoard $event) => [
            'heading' => $event->title,
            'body' => $event->sub_title ?: str($event->description)->stripTags()->limit(140)->toString(),
            'image' => $this->publicImage($event->getRawOriginal('image_path'), 'notice_board'),
            'image_alt' => $event->title,
            'published_at' => $event->published_at ? Carbon::parse($event->published_at)->toDateString() : '',
            'url' => '/event/' . $event->slug,
            'link_label' => $itemLinkLabel,
        ])->values()->all();
    }

    private function testimonialItems(array $content, int $limit): array
    {
        $query = Testimonial::query()
            ->where('status', 1)
            ->where('language', app()->getLocale());
        $testimonials = $this->records($query, $content, $limit, 'uuid', 'name');

        return $testimonials->map(fn (Testimonial $testimonial) => [
            'name' => $testimonial->name,
            'designation' => $testimonial->designation,
            'quote' => $testimonial->testimonial,
            'photo' => $this->publicImage($testimonial->getRawOriginal('photo'), 'testimonial'),
        ])->values()->all();
    }

    private function teamItems(array $content, int $limit): array
    {
        $locale = app()->getLocale();
        $query = LatestNews::query()
            ->where('type', 'our-members')
            ->where('status', 1)
            ->where('language', $locale);

        // Older installations may not yet have separate Bangla team rows. In
        // that case the translation overlay keeps the managed English record as
        // the source instead of making the whole section disappear.
        if ($locale !== 'en' && !(clone $query)->exists()) {
            $query = LatestNews::query()
                ->where('type', 'our-members')
                ->where('status', 1)
                ->where('language', 'en');
        }

        $members = $this->records($query, $content, $limit, 'id', 'name');
        $itemLinkLabel = trim((string) ($content['item_link_label'] ?? ''));

        return $members->map(function (LatestNews $member) use ($locale, $itemLinkLabel): array {
            $name = $this->translations->localizedContentValue('team_member', (string) $member->id, 'name', (string) $member->name, $locale);
            $description = $this->translations->localizedContentValue('team_member', (string) $member->id, 'description', (string) $member->description, $locale);
            $biography = $this->translations->localizedContentValue('team_member', (string) $member->id, 'biography', (string) $member->biography, $locale);
            $qualification = $this->translations->localizedContentValue('team_member', (string) $member->id, 'qualification', (string) $member->qualification, $locale);
            $legacyUrl = $this->sanitizer->sanitizeUrl($member->url ?: '');

            return [
                'id' => $member->id,
                'heading' => $name,
                'designation' => $description,
                'body' => $description,
                'biography' => $biography,
                'qualification' => $qualification,
                'image' => $this->publicImage($member->path ?: $member->image, 'our_members'),
                'image_alt' => $name,
                'url' => $legacyUrl,
                'social_links' => $this->normalizedSocialLinks($member->social_links, $legacyUrl),
                'link_label' => $itemLinkLabel,
            ];
        })->values()->all();
    }

    private function galleryItems(array $content, int $limit): array
    {
        $query = Gallery::query()
            ->where('status', 1)
            ->where('language', app()->getLocale());
        $photos = $this->records($query, $content, $limit, 'uuid', 'name');

        return $photos->map(function (Gallery $photo): array {
            $libraryImage = $this->safeMediaLibraryUrl((string) $photo->url);
            $alt = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $photo->description)));

            return [
                'heading' => $photo->name,
                'body' => '',
                'image' => $libraryImage ?: $this->legacyGalleryImage($photo),
                'image_alt' => $alt ?: $photo->name,
                // A media-library URL is the image itself, not a click target.
                'url' => $libraryImage ? '' : $this->sanitizer->sanitizeUrl($photo->url ?: ''),
            ];
        })->values()->all();
    }

    private function records(
        Builder $query,
        array $content,
        int $limit,
        string $identityColumn,
        string $titleColumn,
        string $dateColumn = 'created_at'
    ): EloquentCollection {
        $selected = collect($content['selected_items'] ?? [])
            ->filter(fn ($value) => is_string($value) || is_int($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();
        $manualSelection = ($content['selection_mode'] ?? 'automatic') === 'manual';

        if ($manualSelection) {
            // Choosing “specific items” with nothing selected is an intentional
            // empty state. Falling back to automatic results surprises editors
            // and can publish unrelated content.
            if ($selected->isEmpty()) {
                return new EloquentCollection();
            }

            $rank = $selected->flip();
            $records = $query->whereIn($identityColumn, $selected->all())->get();

            return new EloquentCollection($records
                ->sortBy(fn ($model) => $rank[(string) $model->{$identityColumn}] ?? PHP_INT_MAX)
                ->take($limit)
                ->values()
                ->all());
        }

        $sort = (string) ($content['sort'] ?? 'featured');
        match ($sort) {
            'newest' => $query->orderByDesc($dateColumn)->orderByDesc('id'),
            'oldest' => $query->orderBy($dateColumn)->orderBy('id'),
            'title' => $query->orderBy($titleColumn)->orderBy('id'),
            default => $query->orderByDesc('order_by')->orderByDesc('id'),
        };

        return $query->limit($limit)->get();
    }

    private function publicImage(?string $value, string $legacyDirectory): string
    {
        $image = trim((string) $value);
        if ($image === '' || str_starts_with($image, '/') || preg_match('#^https?://#i', $image)) {
            return $image;
        }

        return '/storage/photos/1/' . $legacyDirectory . '/' . ltrim($image, '/');
    }

    private function safeMediaLibraryUrl(string $value): ?string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/storage/media/[a-z0-9/_-]+\.(?:avif|gif|jpe?g|png|webp)$#i', $path)) {
            return null;
        }

        return $path;
    }

    private function legacyGalleryImage(Gallery $photo): string
    {
        $source = trim((string) ($photo->path ?: $photo->image));
        if ($source === '' || str_starts_with($source, '/') || preg_match('#^https?://#i', $source)) {
            return $source;
        }

        $filename = rawurlencode(basename(str_replace('\\', '/', $source)));

        return '/storage/photos/1/gallery/' . $photo->id . '/430X360/' . $filename;
    }

    private function normalizedSocialLinks(mixed $links, string $legacyUrl): array
    {
        if (is_string($links)) {
            $decoded = json_decode($links, true);
            $links = is_array($decoded) ? $decoded : [];
        }

        $normalized = [];
        $seen = [];

        foreach (is_array($links) ? $links : [] as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = $this->sanitizer->sanitizeUrl($link['url'] ?? '');
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ($url === '' || !in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $dedupeKey = strtolower(rtrim($url, '/'));
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $platform = strtolower(trim((string) ($link['platform'] ?? '')));
            $platform = trim((string) preg_replace('/[^a-z0-9]+/', '-', $platform), '-');
            $platform = mb_substr($platform ?: 'website', 0, 50);
            $label = trim((string) ($link['label'] ?? ''));

            $normalized[] = [
                'platform' => $platform,
                'label' => mb_substr($label ?: (self::SOCIAL_PLATFORM_LABELS[$platform] ?? ucwords(str_replace('-', ' ', $platform))), 0, 80),
                'url' => $url,
            ];
            $seen[$dedupeKey] = true;
        }

        if ($normalized === [] && $legacyUrl !== '') {
            $normalized[] = [
                'platform' => 'website',
                'label' => 'View profile',
                'url' => $legacyUrl,
            ];
        }

        return $normalized;
    }
}
