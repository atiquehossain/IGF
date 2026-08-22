<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\NoticeBoard;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

class PublicStructuredDataService
{
    private const CONTEXT = 'https://schema.org';

    private const TYPES = [
        'NGO', 'Organization', 'WebSite', 'SearchAction', 'EntryPoint',
        'BreadcrumbList', 'ListItem', 'CollectionPage', 'WebPage',
        'Event', 'Place', 'VirtualLocation', 'PostalAddress', 'ImageObject', 'Report',
        'Article', 'MediaObject', 'DonateAction', 'MonetaryAmount',
    ];

    public function __construct(
        private SiteSettingService $settings,
        private SeoMetadataService $metadata,
    ) {
    }

    /**
     * Server-owned organization and website identity reused by the raw and
     * hydrated public heads. Page-specific nodes are added only after all SEO
     * metadata authority layers have been resolved.
     *
     * @return array<string, mixed>
     */
    public function identityDocument(): array
    {
        return $this->validate([
            '@context' => self::CONTEXT,
            '@graph' => $this->identityNodes(),
        ]);
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs */
    public function collection(
        string $name,
        string $description,
        string $url,
        array $breadcrumbs = [],
    ): array {
        return $this->document([
            $this->breadcrumbNode($breadcrumbs),
            $this->pageNode('CollectionPage', $name, $description, $url),
        ]);
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs */
    public function event(
        NoticeBoard $event,
        string $url,
        ?string $image = null,
        array $breadcrumbs = [],
    ): array {
        $description = $this->text($event->sub_title ?: $event->description, 1000);
        $startDate = $this->date($event->event_start_at);
        $endDate = $this->date($event->event_end_at);
        $statusMap = [
            'scheduled' => 'https://schema.org/EventScheduled',
            'postponed' => 'https://schema.org/EventPostponed',
            'cancelled' => 'https://schema.org/EventCancelled',
            'rescheduled' => 'https://schema.org/EventRescheduled',
            'moved-online' => 'https://schema.org/EventMovedOnline',
        ];
        $attendanceMap = [
            'offline' => 'https://schema.org/OfflineEventAttendanceMode',
            'online' => 'https://schema.org/OnlineEventAttendanceMode',
            'mixed' => 'https://schema.org/MixedEventAttendanceMode',
        ];
        $status = $statusMap[(string) $event->event_status] ?? null;
        $attendance = $attendanceMap[(string) $event->event_attendance_mode] ?? null;
        // Google event eligibility requires a physical Place with an address.
        // Online-only or incomplete event records remain honest WebPage nodes;
        // they must never be mislabeled as an Article merely to gain a richer
        // search appearance.
        $isEvent = $event->content_kind === 'event'
            && $startDate !== ''
            && $status !== null
            && $attendance !== null
            && in_array($event->event_attendance_mode, ['offline', 'mixed'], true)
            && filled($event->location);
        $isArticle = $event->content_kind !== 'event';
        $node = $this->pageNode(
            $isEvent ? 'Event' : ($isArticle ? 'Article' : 'WebPage'),
            (string) $event->title,
            $description,
            $url
        );
        if ($isEvent) {
            $node += [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'eventStatus' => $status,
                'eventAttendanceMode' => $attendance,
                'organizer' => ['@id' => $this->organizationId()],
            ];
        } elseif ($isArticle) {
            $node += [
                'headline' => $this->text($event->title, 300),
                'datePublished' => $this->date($event->published_at),
                'dateModified' => $this->date($event->updated_at),
                'author' => filled($event->publisher_name)
                    ? ['@type' => 'Organization', 'name' => $this->text($event->publisher_name, 200)]
                    : ['@id' => $this->organizationId()],
                'publisher' => ['@id' => $this->organizationId()],
            ];
        }

        if ($isEvent) {
            $place = [
                '@type' => 'Place',
                'name' => $this->text($event->location, 300),
                // The managed location field is the only address assertion the
                // editor has supplied. Preserve it verbatim as a PostalAddress
                // instead of inventing locality, region, postcode, or country.
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $this->text($event->location, 500),
                ],
            ];
            $node['location'] = $event->event_attendance_mode === 'mixed'
                ? [$place, [
                    '@type' => 'VirtualLocation',
                    'url' => $this->safeUrl($url),
                ]]
                : $place;
        }
        if ($safeImage = $this->imageUrl($image)) {
            $node['image'] = [$safeImage];
        }
        if ($modified = $this->date($event->updated_at)) {
            $node['dateModified'] = $modified;
        }

        return $this->document([$this->breadcrumbNode($breadcrumbs), $this->compact($node)]);
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs */
    public function report(
        AnnualReport $report,
        string $url,
        string $downloadUrl,
        ?string $image = null,
        array $breadcrumbs = [],
    ): array {
        $description = $this->text(
            $report->description ?: $report->sub_title ?: ('Annual report published by ' . ($report->publisher_name ?: $this->organizationName())),
            1000
        );
        $node = $this->pageNode('Report', (string) $report->title, $description, $url) + [
            'headline' => $this->text($report->title, 300),
            'datePublished' => $this->date($report->published_at),
            'dateModified' => $this->date($report->updated_at),
            'author' => filled($report->publisher_name)
                ? ['@type' => 'Organization', 'name' => $this->text($report->publisher_name, 200)]
                : ['@id' => $this->organizationId()],
            'publisher' => ['@id' => $this->organizationId()],
            'encoding' => [
                '@type' => 'MediaObject',
                'contentUrl' => $this->safeUrl($downloadUrl),
                'encodingFormat' => 'application/pdf',
            ],
        ];
        if ($safeImage = $this->imageUrl($image)) {
            $node['image'] = $safeImage;
        }

        return $this->document([$this->breadcrumbNode($breadcrumbs), $this->compact($node)]);
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs */
    public function donation(
        string $name,
        string $description,
        string $url,
        array $breadcrumbs = [],
    ): array {
        $node = [
            '@type' => 'DonateAction',
            '@id' => $this->safeUrl($url) . '#donate-action',
            'name' => $this->text($name, 300),
            'description' => $this->text($description, 1000),
            'recipient' => ['@id' => $this->organizationId()],
            'object' => [
                '@type' => 'MonetaryAmount',
                'currency' => 'BDT',
            ],
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $this->safeUrl($url),
                'actionPlatform' => [
                    'https://schema.org/DesktopWebPlatform',
                    'https://schema.org/MobileWebPlatform',
                ],
            ],
        ];

        return $this->document([$this->breadcrumbNode($breadcrumbs), $node]);
    }

    public function webpage(
        string $name,
        string $description,
        string $url,
        ?string $image = null,
    ): array
    {
        $node = $this->pageNode('WebPage', $name, $description, $url);
        if ($safeImage = $this->imageUrl($image)) {
            $node['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                '@id' => $safeImage . '#primaryimage',
                'url' => $safeImage,
            ];
        }

        return $this->document([$node]);
    }

    /** Backward-compatible name retained for the home controller. */
    public function website(string $name, string $description, string $url): array
    {
        return $this->webpage($name, $description, $url);
    }

    /**
     * Compose the generic page fallback from final, already-merged metadata.
     * An explicit page/route schema always bypasses this method.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $identity
     * @return array<string, mixed>
     */
    public function fallbackForMetadata(array $metadata, ?array $identity = null): array
    {
        $identity ??= $this->identityDocument();
        if ($this->semanticErrors($identity) !== []) {
            $identity = $this->identityDocument();
        }

        $url = $this->safeUrl((string) ($metadata['canonical_url'] ?? ''));
        $name = $this->text($metadata['meta_title'] ?? '', 300);
        if ($url === '' || $name === '') {
            return $identity;
        }

        $node = $this->pageNode(
            'WebPage',
            $name,
            $this->text($metadata['meta_description'] ?? '', 1000),
            $url
        );
        $image = $metadata['og_image'] ?? $metadata['twitter_image'] ?? null;
        if ($safeImage = $this->imageUrl(is_string($image) ? $image : null)) {
            $node['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                '@id' => $safeImage . '#primaryimage',
                'url' => $safeImage,
            ];
        }

        $identityNodes = collect($identity['@graph'] ?? [])
            ->filter(fn ($candidate) => is_array($candidate)
                && in_array($candidate['@type'] ?? null, ['NGO', 'Organization', 'WebSite'], true))
            ->values()
            ->all();

        return $this->validate([
            '@context' => self::CONTEXT,
            '@graph' => [...$identityNodes, $node],
        ]);
    }

    /**
     * Validate a server-produced JSON-LD document before it reaches either the
     * initial Blade response or Inertia navigation. Unsafe URLs and malformed
     * semantic nodes fail closed instead of being serialized into the head.
     *
     * @return array<string, mixed>
     */
    public function validate(array $document): array
    {
        $errors = $this->semanticErrors($document);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid structured data: ' . implode('; ', $errors));
        }

        return $document;
    }

    /** @return array<int, string> */
    public function semanticErrors(array $document): array
    {
        $errors = [];
        if (($document['@context'] ?? null) !== self::CONTEXT) {
            $errors[] = 'The schema context must be https://schema.org.';
        }
        $graph = $document['@graph'] ?? null;
        if (!is_array($graph) || $graph === []) {
            $errors[] = 'The schema graph must contain at least one node.';
            return $errors;
        }

        foreach ($graph as $index => $node) {
            if (!is_array($node)) {
                $errors[] = "Graph node {$index} must be an object.";
                continue;
            }
            $this->validateNode($node, "Graph node {$index}", $errors);
        }

        return array_values(array_unique($errors));
    }

    /** @param array<int, array<string, mixed>> $pageNodes */
    private function document(array $pageNodes): array
    {
        $nodes = array_merge($this->identityNodes(), array_values(array_filter($pageNodes)));

        return $this->validate([
            '@context' => self::CONTEXT,
            '@graph' => $nodes,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function identityNodes(): array
    {
        $settings = $this->settings->values(app()->getLocale(), true);
        $branding = $settings['branding'] ?? [];
        $contact = $settings['contact'] ?? [];
        $social = $settings['social'] ?? [];
        $root = url('/');
        $logo = $this->qualifiedLogoUrl((string) ($branding['logo'] ?? ''));

        $organization = [
            '@type' => 'NGO',
            '@id' => $this->organizationId(),
            'name' => $this->organizationName(),
            'url' => $root,
            'description' => $this->text($branding['tagline'] ?? '', 500),
            'email' => $this->email($contact['email'] ?? ''),
            'telephone' => $this->text($contact['phone_primary'] ?? '', 100),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $this->text($contact['address'] ?? '', 500),
                'addressCountry' => 'BD',
            ],
            'sameAs' => collect($social)
                ->map(fn ($value) => $this->safeUrl((string) $value, true))
                ->filter()
                ->values()
                ->all(),
        ];
        if ($logo) {
            $organization['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo,
            ];
            $organization['image'] = $logo;
        }

        $website = [
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
            'url' => $root,
            'name' => $this->organizationName(),
            'description' => $this->text($branding['tagline'] ?? '', 500),
            'publisher' => ['@id' => $this->organizationId()],
            'inLanguage' => (string) app()->getLocale(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/search') . '?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return [$this->compact($organization), $this->compact($website)];
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs */
    private function breadcrumbNode(array $breadcrumbs): array
    {
        $items = [];
        foreach ($breadcrumbs as $index => $breadcrumb) {
            $name = $this->text($breadcrumb['name'] ?? '', 200);
            $url = $this->safeUrl((string) ($breadcrumb['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $name,
                'item' => $url,
            ];
        }

        return $items === [] ? [] : [
            '@type' => 'BreadcrumbList',
            '@id' => ((string) end($items)['item']) . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    private function pageNode(string $type, string $name, string $description, string $url): array
    {
        $safeUrl = $this->safeUrl($url);

        return $this->compact([
            '@type' => $type,
            '@id' => $safeUrl . '#webpage',
            'url' => $safeUrl,
            'name' => $this->text($name, 300),
            'description' => $this->text($description, 1000),
            'isPartOf' => ['@id' => $this->websiteId()],
            'about' => ['@id' => $this->organizationId()],
            'inLanguage' => (string) app()->getLocale(),
        ]);
    }

    /** @param array<string, mixed> $node @param array<int, string> $errors */
    private function validateNode(array $node, string $path, array &$errors): void
    {
        $type = $node['@type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $errors[] = "{$path} has an unsupported @type.";
        }

        foreach ($node as $key => $value) {
            if (in_array($key, ['@id', 'url', 'contentUrl', 'urlTemplate', 'item', 'eventStatus', 'eventAttendanceMode'], true)) {
                if (!is_string($value) || $this->safeUrl($value, true) === '') {
                    $errors[] = "{$path}.{$key} must be a safe HTTP(S) URL.";
                }
            }
            if (in_array($key, ['startDate', 'endDate', 'datePublished', 'dateModified'], true)
                && $value !== '' && $this->date($value) === '') {
                $errors[] = "{$path}.{$key} must be an ISO date.";
            }
            if (is_array($value)) {
                if (isset($value['@type'])) {
                    $this->validateNode($value, "{$path}.{$key}", $errors);
                } else {
                    foreach ($value as $childIndex => $child) {
                        if (is_array($child) && isset($child['@type'])) {
                            $this->validateNode($child, "{$path}.{$key}.{$childIndex}", $errors);
                        } elseif (is_string($child)
                            && in_array($key, ['sameAs', 'actionPlatform'], true)
                            && $this->safeUrl($child, true) === '') {
                            $errors[] = "{$path}.{$key}.{$childIndex} must be a safe HTTP(S) URL.";
                        }
                    }
                }
            }
        }

        if (in_array($type, ['NGO', 'Organization', 'WebSite', 'CollectionPage', 'WebPage', 'Event', 'Report', 'Article', 'DonateAction'], true)
            && trim((string) ($node['name'] ?? $node['headline'] ?? '')) === '') {
            $errors[] = "{$path} must have a name.";
        }
        if ($type === 'Event' && trim((string) ($node['startDate'] ?? '')) === '') {
            $errors[] = "{$path} must have a startDate.";
        }
        if ($type === 'BreadcrumbList') {
            foreach (($node['itemListElement'] ?? []) as $index => $item) {
                if (($item['position'] ?? null) !== $index + 1) {
                    $errors[] = "{$path} breadcrumb positions must be sequential.";
                }
            }
        }
    }

    private function safeUrl(string $value, bool $allowExternal = false): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return '';
        }
        if (str_starts_with($value, '/')) {
            $value = url($value);
        }
        $parts = parse_url($value);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        if (!$allowExternal && !$this->metadata->isSameOrigin($value)) {
            return '';
        }
        if ($allowExternal && !$this->metadata->isSameOrigin($value) && $scheme !== 'https') {
            return '';
        }

        return $value;
    }

    private function imageUrl(?string $value): string
    {
        $absolute = $this->metadata->absolutePublicImageUrl($value);

        return $this->safeUrl($absolute, true);
    }

    /**
     * Google requires Organization logos to be at least 112 x 112. Local
     * assets can be verified exactly; remote owner-supplied HTTPS assets are
     * retained because their bytes are intentionally not fetched per request.
     */
    private function qualifiedLogoUrl(?string $value): string
    {
        $url = $this->imageUrl($value);
        if ($url === '' || !$this->metadata->isSameOrigin($url)) {
            return $url;
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $file = public_path(ltrim(str_replace('\\', '/', $path), '/'));
        if (!is_file($file)) {
            return '';
        }
        $dimensions = @getimagesize($file);
        if (!is_array($dimensions) || (int) $dimensions[0] < 112 || (int) $dimensions[1] < 112) {
            return '';
        }

        return $url;
    }

    private function text(mixed $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $limit);
    }

    private function email(mixed $value): string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    private function date(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->toAtomString();
        } catch (Throwable) {
            return '';
        }
    }

    private function organizationName(): string
    {
        $settings = $this->settings->values(app()->getLocale(), true);

        return $this->text($settings['branding']['site_name'] ?? config('app.name'), 200);
    }

    private function organizationId(): string
    {
        return url('/#organization');
    }

    private function websiteId(): string
    {
        return url('/#website');
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function compact(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== '' && $value !== null && $value !== []);
    }
}
