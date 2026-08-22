<?php

namespace App\Services;

use App\Models\SeoAuditIssue;
use App\Models\SeoAuditRun;
use App\Models\SeoRedirect;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TechnicalSeoAuditService
{
    public function __construct(
        private TechnicalSeoInternalFetcher $fetcher,
        private TechnicalSeoUrlPolicy $urls,
        private SeoRouteRegistry $routes,
        private ?TechnicalSeoAlertService $alerts = null,
    ) {
    }

    public function run(string $trigger = 'admin', ?int $actorId = null): SeoAuditRun
    {
        $lockSeconds = max(15, min(150, (int) config('technical-seo.max_seconds', 20) + 10));
        $lock = Cache::lock('technical-seo:audit-running', $lockSeconds);
        if (!$lock->get()) {
            throw ValidationException::withMessages(['scan' => 'A technical SEO scan is already running. Wait for it to finish before starting another.']);
        }

        try {
            $run = SeoAuditRun::query()->create([
                'status' => 'running',
                'trigger' => in_array($trigger, ['admin', 'command'], true) ? $trigger : 'admin',
                'triggered_by' => $actorId,
                'started_at' => now(),
            ]);

            try {
                $result = $this->crawl();
                foreach ($result['issues'] as $issue) {
                    SeoAuditIssue::query()->create($issue + ['run_id' => $run->id]);
                }
                $summary = collect($result['issues'])->countBy('issue_type')->sortKeys()->all();
                $run->update([
                    'status' => $result['truncated'] ? 'completed_limited' : 'completed',
                    'completed_at' => now(),
                    'urls_checked' => $result['urls_checked'],
                    'issues_found' => count($result['issues']),
                    'summary' => $summary,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $run->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'failure_message' => 'The audit stopped safely. Check the application log, then run it again.',
                ]);
            }

            try {
                // Alert delivery is deliberately isolated from the scan. A
                // missing mail transport or an alert-table deployment lag can
                // never turn a safely completed crawl into a failed crawl.
                ($this->alerts ?? app(TechnicalSeoAlertService::class))->recordFor($run->fresh());
            } catch (Throwable $exception) {
                report($exception);
            }

            $this->pruneSnapshots();

            return $run->fresh('issues');
        } finally {
            $lock->release();
        }
    }

    private function pruneSnapshots(): void
    {
        $keep = max(5, min(100, (int) config('technical-seo.snapshot_retention', 20)));
        $oldestKeptId = SeoAuditRun::query()->latest('id')->skip($keep - 1)->value('id');
        if ($oldestKeptId) {
            SeoAuditRun::query()->where('id', '<', $oldestKeptId)->where('status', '!=', 'running')->delete();
        }
    }

    /**
     * @return array{issues:list<array<string,mixed>>,urls_checked:int,truncated:bool}
     */
    private function crawl(): array
    {
        $maxUrls = max(1, min(500, (int) config('technical-seo.max_urls', 150)));
        $maxSeconds = max(1, min(120, (int) config('technical-seo.max_seconds', 20)));
        $maxBytes = max(64, min(5242880, (int) config('technical-seo.max_response_bytes', 1048576)));
        $maxLinks = max(1, min(1000, (int) config('technical-seo.max_links_per_page', 250)));
        $maxIssues = min(5000, $maxUrls * 20);
        $started = hrtime(true);

        $redirectTargetInventory = $this->activeRedirectTargets($maxUrls);
        $redirectTargets = $redirectTargetInventory['targets'];
        $redirectSeeds = array_keys($redirectTargets);
        $ordinarySeeds = array_values(array_unique(array_merge(
            $this->routeSeeds(),
            $this->sitemapTargets($maxBytes)
        )));
        $seedCandidates = array_values(array_unique(array_merge($redirectSeeds, $ordinarySeeds)));
        // Reserve part of the budget for the links and images discovered on
        // sitemap pages. Active redirect targets come first and may expand the
        // seed share because a redirect to a missing destination is a live
        // traffic failure that must not depend on the target appearing in a
        // sitemap or page link.
        $discoverySeedBudget = $maxUrls === 1 ? 1 : max(1, (int) floor($maxUrls * 0.7));
        $seedBudget = min($maxUrls, max($discoverySeedBudget, count($redirectSeeds)));
        $seeds = array_slice($seedCandidates, 0, $seedBudget);
        $seedSet = array_fill_keys($seeds, true);

        $queue = $seeds;
        $queued = $seedSet;
        $responses = [];
        $edges = [];
        $incoming = array_fill_keys($seeds, 0);
        $analyzedSeeds = [];
        $pageCanonicals = [];
        $issues = [];
        $truncated = $redirectTargetInventory['truncated'] || count($seedCandidates) > count($seeds);

        while ($queue !== [] && count($responses) < $maxUrls) {
            if ((hrtime(true) - $started) / 1_000_000_000 >= $maxSeconds) {
                $truncated = true;
                break;
            }

            $path = array_shift($queue);
            $response = $this->fetcher->fetch($path, $maxBytes);
            $responses[$path] = $response;

            if ($response['too_large']) {
                $this->addIssue($issues, $maxIssues, 'response_too_large', 'medium', $path, null, 413,
                    'The response exceeded the safe audit size limit.', ['limit_bytes' => $maxBytes]);
                continue;
            }
            if ($response['status'] >= 500) {
                $isRedirectTarget = isset($redirectTargets[$path]);
                $this->addIssue($issues, $maxIssues, 'http_5xx', 'high', $path, null, $response['status'],
                    $isRedirectTarget
                        ? 'An active redirect destination returned a server error.'
                        : 'This public URL returned a server error.',
                    $isRedirectTarget ? ['redirect_sources' => $redirectTargets[$path]] : []);
                continue;
            }
            if ($response['status'] >= 400) {
                $isRedirectTarget = isset($redirectTargets[$path]);
                $this->addIssue($issues, $maxIssues, 'http_4xx', 'high', $path, null, $response['status'],
                    $isRedirectTarget
                        ? 'An active redirect destination does not load.'
                        : 'This managed public URL returned an error.',
                    $isRedirectTarget ? ['redirect_sources' => $redirectTargets[$path]] : []);
                continue;
            }
            if ($response['status'] >= 300) {
                $isRedirectTarget = isset($redirectTargets[$path]);
                $this->addIssue($issues, $maxIssues, 'redirect_page', 'medium', $path, null, $response['status'],
                    $isRedirectTarget
                        ? 'An active redirect destination redirects again. Point the rule directly to its final address.'
                        : 'A sitemap or managed page redirects instead of loading directly.',
                    $isRedirectTarget ? ['redirect_sources' => $redirectTargets[$path]] : []);
                continue;
            }
            if (!str_contains(strtolower($response['content_type']), 'text/html')) {
                continue;
            }

            if (isset($seedSet[$path])) {
                $analyzedSeeds[$path] = true;
            }
            $analysis = $this->analyzeHtml($path, $response['body'], $maxLinks, $issues, $maxIssues);
            $pageCanonicals[$path] = $analysis['canonical'];
            foreach ($analysis['resources'] as $resource) {
                $target = $resource['target'];
                $edges[] = ['source' => $path, 'target' => $target, 'kind' => $resource['kind']];
                if ($resource['kind'] === 'link' && $target !== $path && isset($incoming[$target])) {
                    $incoming[$target]++;
                }
                if (!isset($queued[$target]) && count($queued) < $maxUrls) {
                    $queued[$target] = true;
                    $queue[] = $target;
                } elseif (!isset($queued[$target])) {
                    $truncated = true;
                }
            }
        }

        if ($queue !== []) {
            $truncated = true;
        }

        foreach ($edges as $edge) {
            $response = $responses[$edge['target']] ?? null;
            if (!$response) {
                continue;
            }
            if ($response['status'] >= 400 && $response['status'] !== 413) {
                $type = $edge['kind'] === 'image' ? 'broken_image' : 'broken_link';
                $this->addIssue($issues, $maxIssues, $type, 'high', $edge['source'], $edge['target'], $response['status'],
                    $edge['kind'] === 'image' ? 'An image on this page does not load.' : 'An internal link on this page is broken.');
            } elseif ($response['status'] >= 300 && $response['status'] < 400) {
                $this->addIssue($issues, $maxIssues, 'redirect_in_link', 'medium', $edge['source'], $edge['target'], $response['status'],
                    'This internal link points to a redirect. Link directly to its final managed address.');
            }
        }

        foreach ($incoming as $path => $count) {
            if ($path !== '/' && $count === 0 && isset($analyzedSeeds[$path])) {
                $this->addIssue($issues, $maxIssues, 'orphan_page', 'medium', $path, null, null,
                    'No audited public page links to this managed page.');
            }
        }

        $canonicalOwners = [];
        foreach ($pageCanonicals as $source => $canonical) {
            if ($canonical !== null) {
                $canonicalOwners[$canonical][] = $source;
            }
        }
        foreach ($canonicalOwners as $canonical => $owners) {
            if (count($owners) < 2) {
                continue;
            }
            foreach ($owners as $source) {
                $this->addIssue($issues, $maxIssues, 'duplicate_canonical', 'high', $source, $canonical, null,
                    'More than one public page declares this canonical address.', ['owner_count' => count($owners)]);
            }
        }

        return ['issues' => array_values($issues), 'urls_checked' => count($responses), 'truncated' => $truncated];
    }

    /** @return list<string> */
    private function routeSeeds(): array
    {
        return $this->routes->routes()
            ->map(fn (string $path): ?string => $this->urls->internalPath($path, '/'))
            ->filter()
            ->prepend('/')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Active same-origin redirect destinations are priority crawl seeds. A
     * destination can stop being public after a rule is created (for example,
     * when an editor returns a page to draft), so graph validation at write
     * time cannot replace this operational check.
     *
     * @return array{targets:array<string,list<string>>,truncated:bool}
     */
    private function activeRedirectTargets(int $maxTargets): array
    {
        $targets = [];
        $truncated = false;

        SeoRedirect::query()
            ->where('is_active', true)
            ->lazyById(100, 'id', 'id')
            ->each(function (SeoRedirect $redirect) use (&$targets, &$truncated, $maxTargets): bool {
                $target = $this->urls->internalAuditTarget((string) $redirect->to_url, '/');
                if ($target === null) {
                    return true;
                }
                if (!isset($targets[$target]) && count($targets) >= $maxTargets) {
                    $truncated = true;

                    return false;
                }

                $locale = trim((string) ($redirect->locale ?? ''));
                $source = (string) $redirect->from_path . ($locale !== '' ? ' [' . $locale . ']' : ' [all locales]');
                $targets[$target][] = $source;

                return true;
            });

        foreach ($targets as &$sources) {
            $sources = array_values(array_unique($sources));
        }
        unset($sources);

        return ['targets' => $targets, 'truncated' => $truncated];
    }

    /**
     * Discover URLs through the locale sitemap index without making network
     * requests. Both the sitemap count and every response body are bounded.
     * A legacy single sitemap remains the fail-safe fallback.
     *
     * @return list<string>
     */
    private function sitemapTargets(int $maxBytes): array
    {
        $index = $this->fetcher->fetch('/sitemap-index.xml', $maxBytes);
        if ($index['status'] === 200 && !$index['too_large']) {
            $sitemaps = array_slice($this->xmlLocations($index['body']), 0, 10);
            $groups = [];
            foreach ($sitemaps as $sitemap) {
                if (!str_ends_with(strtolower((string) parse_url($sitemap, PHP_URL_PATH)), '.xml')) {
                    continue;
                }
                $response = $this->fetcher->fetch($sitemap, $maxBytes);
                if ($response['status'] === 200 && !$response['too_large']) {
                    $groups[] = $this->xmlLocations($response['body']);
                }
            }
            $targets = [];
            $largest = $groups === [] ? 0 : max(array_map('count', $groups));
            for ($index = 0; $index < $largest; $index++) {
                foreach ($groups as $group) {
                    if (isset($group[$index])) {
                        $targets[] = $group[$index];
                    }
                }
            }

            return array_values(array_unique($targets));
        }

        $legacy = $this->fetcher->fetch('/sitemap.xml', $maxBytes);

        return $legacy['status'] === 200 && !$legacy['too_large']
            ? $this->xmlLocations($legacy['body'])
            : [];
    }

    /** @return list<string> */
    private function xmlLocations(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $paths = [];
        foreach ($document->getElementsByTagName('loc') as $node) {
            $target = $this->urls->internalAuditTarget(trim($node->textContent), '/');
            if ($target !== null) {
                $paths[] = $target;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param list<array<string,mixed>> $issues
     * @return array{resources:list<array{kind:string,target:string}>,canonical:?string}
     */
    private function analyzeHtml(string $path, string $html, int $maxLinks, array &$issues, int $maxIssues): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            $this->addIssue($issues, $maxIssues, 'head_mismatch', 'high', $path, null, null,
                'The page HTML could not be parsed safely.');
            return ['resources' => [], 'canonical' => null];
        }

        $xpath = new DOMXPath($document);
        $inertiaPage = $this->inertiaPage($xpath);
        $h1Count = $document->getElementsByTagName('h1')->length;
        // Inertia's non-SSR response intentionally contains an empty app
        // mount; the H1 is produced only after Vue hydrates it. Do not report
        // a false missing-heading issue from that unrendered shell. If SSR is
        // enabled (or this is a normal HTML page), real heading counts remain
        // fully audited.
        if ($h1Count === 0 && $inertiaPage === null) {
            $this->addIssue($issues, $maxIssues, 'missing_h1', 'medium', $path, null, null,
                'This page has no H1 heading.');
        } elseif ($h1Count > 1) {
            $this->addIssue($issues, $maxIssues, 'multiple_h1', 'medium', $path, null, null,
                'This page has more than one H1 heading.', ['count' => $h1Count]);
        }

        $titleNodes = $document->getElementsByTagName('title');
        $descriptionNodes = $xpath->query('//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]');
        if ($titleNodes->length !== 1 || trim($titleNodes->item(0)?->textContent ?? '') === '') {
            $this->addIssue($issues, $maxIssues, 'head_mismatch', 'high', $path, null, null,
                'The HTML head must contain one non-empty title.', ['title_count' => $titleNodes->length]);
        }
        if (!$descriptionNodes || $descriptionNodes->length !== 1
            || trim((string) ($descriptionNodes->item(0)?->attributes?->getNamedItem('content')?->nodeValue ?? '')) === '') {
            $this->addIssue($issues, $maxIssues, 'head_mismatch', 'medium', $path, null, null,
                'The HTML head must contain one non-empty meta description.', ['description_count' => $descriptionNodes instanceof \DOMNodeList ? $descriptionNodes->length : 0]);
        }

        $canonicalNodes = $xpath->query('//head/link[contains(concat(" ", normalize-space(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]');
        $canonical = null;
        if (!$canonicalNodes || $canonicalNodes->length === 0) {
            $this->addIssue($issues, $maxIssues, 'head_mismatch', 'medium', $path, null, null,
                'The page is missing a canonical link.');
        } elseif ($canonicalNodes->length > 1) {
            $this->addIssue($issues, $maxIssues, 'canonical_conflict', 'high', $path, null, null,
                'The page declares multiple canonical links.', ['count' => $canonicalNodes->length]);
        } else {
            /** @var DOMElement $node */
            $node = $canonicalNodes->item(0);
            $canonical = $this->urls->internalAuditTarget($node->getAttribute('href'), $path);
            if ($canonical === null || $canonical !== $path) {
                $this->addIssue($issues, $maxIssues, 'canonical_conflict', 'high', $path, $canonical, null,
                    'The canonical link conflicts with this managed page address.');
            }
        }

        $hreflangNodes = $xpath->query('//head/link[@hreflang]');
        $languages = [];
        $alternateTargets = [];
        if ($hreflangNodes) {
            foreach ($hreflangNodes as $node) {
                /** @var DOMElement $node */
                $language = strtolower(trim($node->getAttribute('hreflang')));
                $href = $this->urls->internalAuditTarget($node->getAttribute('href'), $path);
                if ($language === '' || isset($languages[$language]) || $href === null) {
                    $this->addIssue($issues, $maxIssues, 'hreflang_mismatch', 'medium', $path, $href, null,
                        'A language alternate is duplicated, external, or invalid.');
                }
                $languages[$language] = true;
                if ($href !== null && $href !== $path) {
                    $alternateTargets[] = $href;
                }
            }
        }

        $schemaNodes = $xpath->query('//head/script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"] | //body/script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');
        if (!$schemaNodes || $schemaNodes->length === 0) {
            $this->addIssue($issues, $maxIssues, 'schema_mismatch', 'low', $path, null, null,
                'No JSON-LD structured data was found on this page.');
        } else {
            foreach ($schemaNodes as $node) {
                json_decode(trim($node->textContent), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->addIssue($issues, $maxIssues, 'schema_mismatch', 'high', $path, null, null,
                        'The page contains invalid JSON-LD structured data.');
                    break;
                }
            }
        }

        $resources = [];
        $resourceKeys = [];
        foreach ($alternateTargets as $target) {
            $this->appendResource($resources, $resourceKeys, 'link', $target, $maxLinks);
        }
        $linkNodes = $xpath->query('//a[@href] | //img[@src]');
        if ($linkNodes) {
            foreach ($linkNodes as $node) {
                if (count($resources) >= $maxLinks) {
                    break;
                }
                /** @var DOMElement $node */
                $kind = strtolower($node->tagName) === 'img' ? 'image' : 'link';
                $attribute = $kind === 'image' ? 'src' : 'href';
                $target = $this->urls->internalAuditTarget($node->getAttribute($attribute), $path);
                if ($target === null) {
                    continue;
                }
                $this->appendResource($resources, $resourceKeys, $kind, $target, $maxLinks);
            }
        }
        if ($inertiaPage !== null) {
            $this->appendInertiaResources($resources, $resourceKeys, $inertiaPage, $path, $maxLinks);
        }

        return ['resources' => $resources, 'canonical' => $canonical];
    }

    /** @return array<string,mixed>|null */
    private function inertiaPage(DOMXPath $xpath): ?array
    {
        $node = $xpath->query('//*[@id="app" and @data-page]')?->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }
        $decoded = json_decode($node->getAttribute('data-page'), true, 96, JSON_INVALID_UTF8_SUBSTITUTE);

        return is_array($decoded) && is_array($decoded['props'] ?? null) ? $decoded : null;
    }

    /**
     * Recover the links Vue will render from the trusted Inertia payload. This
     * keeps orphan/link diagnostics meaningful without running a browser or
     * treating an unhydrated SPA shell as the final DOM.
     *
     * @param list<array{kind:string,target:string}> $resources
     * @param array<string,bool> $resourceKeys
     * @param array<string,mixed> $page
     */
    private function appendInertiaResources(
        array &$resources,
        array &$resourceKeys,
        array $page,
        string $source,
        int $maxLinks,
    ): void {
        $props = (array) ($page['props'] ?? []);
        foreach (['appMenus', 'appFooterMenus'] as $menuKey) {
            $this->appendMenuResources(
                $resources,
                $resourceKeys,
                (array) ($props[$menuKey] ?? []),
                $source,
                $maxLinks
            );
        }

        // Public controller props contain the final URLs used by buttons,
        // cards, downloads, and images. Traverse only URL-shaped keys and
        // same-origin values; text, tokens, contacts, and arbitrary query data
        // are never retained.
        foreach (['data', 'siteSettings', 'contentSeo', 'seoAlternates'] as $key) {
            if (array_key_exists($key, $props)) {
                $this->appendPropResources($resources, $resourceKeys, $props[$key], $source, $maxLinks, $key);
            }
        }

        $component = (string) ($page['component'] ?? '');
        $componentItems = (array) data_get($props, 'data.items', []);
        if ($component === 'category'
            && count((array) data_get($props, 'data.landing_page.visible_blocks', [])) > 0) {
            // The category component renders the landing-page blocks instead
            // of its archive cards, so those item slugs are not live links.
            $componentItems = [];
        }

        $this->appendComponentResources(
            $resources,
            $resourceKeys,
            $component,
            $componentItems,
            $source,
            $maxLinks,
        );
    }

    /**
     * Some public Vue components receive slugs and build their final hrefs in
     * the browser. Recover only these known route contracts so the crawler sees
     * the same links as a visitor without interpreting arbitrary slug fields as
     * URLs.
     *
     * @param list<array{kind:string,target:string}> $resources
     * @param array<string,bool> $resourceKeys
     * @param array<int|string,mixed> $items
     */
    private function appendComponentResources(
        array &$resources,
        array &$resourceKeys,
        string $component,
        array $items,
        string $source,
        int $maxLinks,
    ): void {
        $prefix = match ($component) {
            'category', 'project' => '/page/',
            'events' => '/event/',
            default => null,
        };

        if ($prefix !== null) {
            foreach ($items as $item) {
                if (!is_array($item) || count($resources) >= $maxLinks) {
                    continue;
                }
                if (filled($item['public_url'] ?? null)) {
                    // appendPropResources already recovered the controller's
                    // canonical destination. Do not also invent its legacy
                    // `/page/{slug}` alias.
                    continue;
                }
                $slug = trim((string) ($item['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $target = $this->urls->internalAuditTarget($prefix . rawurlencode($slug), $source);
                if ($target !== null) {
                    $this->appendResource($resources, $resourceKeys, 'link', $target, $maxLinks);
                }
            }
        }

        if ($component === 'zakat' && count($resources) < $maxLinks) {
            $target = $this->urls->internalAuditTarget('/donate/zakat', $source);
            if ($target !== null) {
                $this->appendResource($resources, $resourceKeys, 'link', $target, $maxLinks);
            }
        }
    }

    /** @param list<array{kind:string,target:string}> $resources @param array<string,bool> $resourceKeys */
    private function appendMenuResources(
        array &$resources,
        array &$resourceKeys,
        array $items,
        string $source,
        int $maxLinks,
    ): void {
        foreach ($items as $item) {
            if (!is_array($item) || count($resources) >= $maxLinks) {
                continue;
            }
            $link = trim((string) ($item['link'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            $candidate = null;
            if ($link === 'custom') {
                $candidate = str_starts_with($slug, '/') ? $slug : null;
            } elseif ($link !== '' && Route::has($link)) {
                try {
                    $candidate = route($link, $slug !== '' ? [$slug] : []);
                } catch (Throwable) {
                    $candidate = null;
                }
            }
            if (is_string($candidate)) {
                $target = $this->urls->internalAuditTarget($candidate, $source);
                if ($target !== null) {
                    $this->appendResource($resources, $resourceKeys, 'link', $target, $maxLinks);
                }
            }
            $this->appendMenuResources(
                $resources,
                $resourceKeys,
                (array) ($item['children'] ?? []),
                $source,
                $maxLinks
            );
        }
    }

    /** @param list<array{kind:string,target:string}> $resources @param array<string,bool> $resourceKeys */
    private function appendPropResources(
        array &$resources,
        array &$resourceKeys,
        mixed $value,
        string $source,
        int $maxLinks,
        string $key,
    ): void {
        if (count($resources) >= $maxLinks) {
            return;
        }
        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->appendPropResources(
                    $resources,
                    $resourceKeys,
                    $child,
                    $source,
                    $maxLinks,
                    is_string($childKey) ? $childKey : $key
                );
            }
            return;
        }
        if (!is_string($value)
            || !preg_match('/(?:^|_)(?:url|href|src|link|image|logo|photo|thumbnail|download)(?:$|_)/i', $key)
            || (!str_starts_with(trim($value), '/') && !preg_match('#^https?://#i', trim($value)))) {
            return;
        }
        $target = $this->urls->internalAuditTarget($value, $source);
        if ($target === null) {
            return;
        }
        $kind = preg_match('/(?:image|logo|photo|thumbnail|src)/i', $key) ? 'image' : 'link';
        $this->appendResource($resources, $resourceKeys, $kind, $target, $maxLinks);
    }

    /** @param list<array{kind:string,target:string}> $resources @param array<string,bool> $resourceKeys */
    private function appendResource(
        array &$resources,
        array &$resourceKeys,
        string $kind,
        string $target,
        int $maxLinks,
    ): void {
        if (count($resources) >= $maxLinks) {
            return;
        }
        $key = $kind . '|' . $target;
        if (!isset($resourceKeys[$key])) {
            $resourceKeys[$key] = true;
            $resources[] = ['kind' => $kind, 'target' => $target];
        }
    }

    /** @param list<array<string,mixed>> $issues */
    private function addIssue(
        array &$issues,
        int $maxIssues,
        string $type,
        string $severity,
        string $source,
        ?string $target,
        ?int $status,
        string $message,
        array $evidence = [],
    ): void {
        if (count($issues) >= $maxIssues) {
            return;
        }
        $fingerprint = hash('sha256', $type . '|' . $source . '|' . ($target ?? ''));
        if (isset($issues[$fingerprint])) {
            return;
        }
        $issues[$fingerprint] = [
            'fingerprint' => $fingerprint,
            'issue_type' => $type,
            'severity' => $severity,
            'source_path' => $source,
            'target_path' => $target,
            'http_status' => $status,
            'message' => $message,
            'evidence' => $evidence === [] ? null : $evidence,
        ];
    }
}
