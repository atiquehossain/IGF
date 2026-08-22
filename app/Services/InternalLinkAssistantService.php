<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SeoMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class InternalLinkAssistantService
{
    // Pairwise relevance scoring is intentionally bounded. At this limit the
    // worst case is 62,250 source/target comparisons, while larger libraries
    // get an explicit partial-review notice in the UI.
    private const MAX_PAGES = 250;

    private const ENGLISH_STOP_WORDS = [
        'about', 'after', 'again', 'also', 'among', 'and', 'are', 'because', 'been', 'before', 'being',
        'between', 'both', 'but', 'can', 'could', 'does', 'each', 'for', 'from', 'had', 'has', 'have',
        'how', 'into', 'its', 'more', 'most', 'not', 'our', 'over', 'page', 'that', 'the', 'their',
        'there', 'these', 'they', 'this', 'through', 'under', 'very', 'was', 'were', 'what', 'when',
        'where', 'which', 'while', 'who', 'will', 'with', 'would', 'you', 'your',
    ];

    private const BANGLA_STOP_WORDS = [
        'এই', 'ওই', 'এবং', 'অথবা', 'একটি', 'একজন', 'এর', 'এটি', 'এতে', 'এদের', 'এখানে', 'করা',
        'করে', 'করতে', 'কেন', 'কী', 'কি', 'জন্য', 'থেকে', 'দিকে', 'দিয়ে', 'দিয়ে', 'না', 'নিয়ে',
        'নিয়ে', 'পর', 'প্রতি', 'বলে', 'মধ্যে', 'যে', 'যা', 'যার', 'যদি', 'সঙ্গে', 'হয়', 'হয়',
        'হতে', 'হবে', 'আমাদের', 'আপনার', 'তাদের',
    ];

    private ?Collection $routeDefinitions = null;

    public function __construct(
        private SeoMetadataService $metadata,
        private SeoRouteRegistry $routes
    ) {
    }

    /**
     * Build read-only, language-matched internal-link recommendations.
     *
     * @return array{
     *   locale: string,
     *   public_page_count: int,
     *   weak_target_count: int,
     *   orphan_target_count: int,
     *   suggestion_count: int,
     *   is_limited: bool,
     *   targets: array<int, array<string, mixed>>
     * }
     */
    public function recommendations(string $locale): array
    {
        $locale = trim($locale);
        $query = Page::query()
            ->publiclyAvailable()
            ->where('language', $locale)
            ->whereNotNull('slug')
            ->with(['category', 'pageTags.tag', 'visibleBlocks.reusableBlock'])
            ->orderBy('name')
            ->orderBy('id');

        $pageCount = (clone $query)->count();
        $pages = $query->limit(self::MAX_PAGES)->get();
        $seo = SeoMetadata::query()
            ->where('seoable_type', Page::class)
            ->where('locale', $locale)
            ->whereIn('seoable_id', $pages->pluck('id'))
            ->get()
            ->keyBy(fn (SeoMetadata $metadata): int => (int) $metadata->seoable_id);

        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $defaultSlugsByUuid = $locale === $defaultLocale
            ? collect()
            : Page::query()
                ->where('language', $defaultLocale)
                ->whereIn('uuid', $pages->pluck('uuid')->filter())
                ->pluck('slug', 'uuid');

        $documents = $pages
            ->reject(function (Page $page) use ($seo): bool {
                $metadata = $seo->get((int) $page->getKey());

                return $metadata instanceof SeoMetadata
                    && (!$metadata->robots_index || $metadata->exclude_from_sitemap);
            })
            ->map(fn (Page $page): array => $this->document(
                $page,
                $seo->get((int) $page->getKey()),
                (string) $defaultSlugsByUuid->get((string) $page->uuid, '')
            ))
            ->values();

        $linkedTargetsBySource = $this->linkedTargetsBySource($documents);
        $inboundSources = [];
        foreach ($documents as $target) {
            $inboundSources[$target['id']] = [];
        }
        foreach ($linkedTargetsBySource as $sourceId => $targetIds) {
            foreach ($targetIds as $targetId) {
                if ((int) $sourceId !== (int) $targetId) {
                    $inboundSources[$targetId][(int) $sourceId] = true;
                }
            }
        }

        $targets = $documents
            ->map(function (array $target) use ($documents, $linkedTargetsBySource, $inboundSources): ?array {
                $inboundCount = count($inboundSources[$target['id']] ?? []);
                if ($inboundCount > 1) {
                    return null;
                }

                $suggestions = $documents
                    ->reject(fn (array $source): bool => $source['id'] === $target['id'])
                    ->reject(fn (array $source): bool => in_array(
                        $target['id'],
                        $linkedTargetsBySource[$source['id']] ?? [],
                        true
                    ))
                    ->map(fn (array $source): ?array => $this->suggestion($source, $target))
                    ->filter()
                    ->sortByDesc(fn (array $suggestion): string => sprintf(
                        '%06d:%s',
                        $suggestion['score'],
                        Str::lower($suggestion['source_title'])
                    ))
                    ->take(5)
                    ->values()
                    ->all();

                return [
                    'id' => $target['id'],
                    'title' => $target['title'],
                    'locale' => $target['locale'],
                    'public_url' => $target['public_url'],
                    'editor_url' => route('seo.content.edit', [
                        'type' => 'page',
                        'id' => $target['id'],
                        'locale' => $target['locale'],
                    ]),
                    'content_editor_url' => route('page.builder.edit', [
                        'uuid' => $target['uuid'],
                        'locale' => $target['locale'],
                    ]),
                    'inbound_count' => $inboundCount,
                    'status' => $inboundCount === 0 ? 'orphan' : 'weak',
                    'status_label' => $inboundCount === 0 ? 'No contextual links found in managed page content' : 'Only one contextual link found in managed page content',
                    'focus_phrase' => $target['focus_phrase'],
                    'suggestions' => $suggestions,
                ];
            })
            ->filter()
            ->sortBy([
                ['inbound_count', 'asc'],
                ['title', 'asc'],
            ])
            ->values();

        return [
            'locale' => $locale,
            'public_page_count' => $documents->count(),
            'weak_target_count' => $targets->count(),
            'orphan_target_count' => $targets->where('status', 'orphan')->count(),
            'suggestion_count' => $targets->sum(fn (array $target): int => count($target['suggestions'])),
            'is_limited' => $pageCount > self::MAX_PAGES,
            'targets' => $targets->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function document(Page $page, ?SeoMetadata $seo, string $defaultTranslationSlug): array
    {
        // The editorial page name is the natural label people should link
        // with. SEO titles commonly append the site brand, and using those
        // repeated words would inflate relevance across otherwise unrelated
        // pages and suggest awkward branded anchor text.
        $title = trim((string) ($page->name ?: $seo?->title ?: $page->meta_title ?: $page->slug));
        $focusPhrase = trim((string) ($seo?->focus_keyword ?: $page->meta_keyword));
        $category = trim((string) ($page->category?->name ?: $page->category?->slug));
        $tags = $page->pageTags
            ->pluck('tag')
            ->filter()
            ->map(fn ($tag): array => [
                'name' => trim((string) ($tag->name ?: $tag->slug)),
                'slug' => trim((string) $tag->slug),
            ])
            ->filter(fn (array $tag): bool => $tag['name'] !== '')
            ->unique('slug')
            ->values();

        $contentValues = [
            $page->name,
            $page->sub_title,
            $page->description,
            $page->meta_title,
            $page->meta_keyword,
            $page->meta_description,
            $seo?->title,
            $seo?->description,
            $seo?->focus_keyword,
        ];
        $linkValues = [$page->description, $page->sub_title];
        foreach ($page->visibleBlocks as $block) {
            $contentValues[] = $block->resolvedLabel();
            $contentValues[] = $block->resolvedContent();
            $contentValues[] = $block->resolvedSettings();
            $linkValues[] = $block->resolvedContent();
            $linkValues[] = $block->resolvedSettings();
        }

        $plainContent = $this->plainText($contentValues);
        $taxonomyText = trim($category . ' ' . $tags->pluck('name')->implode(' '));

        $titleTokens = $this->tokens($title);
        $focusTokens = $this->tokens($focusPhrase);
        $taxonomyTokens = $this->tokens($taxonomyText);
        $contentTokens = $this->tokens($plainContent);

        return [
            'id' => (int) $page->getKey(),
            'uuid' => (string) ($page->uuid ?: $page->getKey()),
            'locale' => (string) $page->language,
            'title' => $title,
            'focus_phrase' => $focusPhrase,
            'category' => Str::lower($category),
            'category_label' => $category,
            'tags' => $tags->pluck('slug')->filter()->map(fn (string $tag): string => Str::lower($tag))->all(),
            'tag_labels' => $tags->pluck('name', 'slug')->all(),
            'title_tokens' => $titleTokens,
            'focus_tokens' => $focusTokens,
            'taxonomy_tokens' => $taxonomyTokens,
            'content_tokens' => $contentTokens,
            'all_tokens' => array_values(array_unique(array_merge(
                $titleTokens,
                $focusTokens,
                $taxonomyTokens,
                $contentTokens
            ))),
            'search_text' => Str::lower($plainContent . ' ' . $taxonomyText),
            'links' => $this->extractLinks($linkValues),
            'slug' => trim((string) $page->slug),
            'public_url' => $this->publicUrl($page, $defaultTranslationSlug),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $documents
     *  @return array<int, array<int, int>>
     */
    private function linkedTargetsBySource(Collection $documents): array
    {
        $targetsByPath = [];
        foreach ($documents as $target) {
            foreach ($this->targetPaths($target) as $path) {
                $targetsByPath[$path][] = $target['id'];
            }
        }

        $linked = [];
        foreach ($documents as $source) {
            $targetIds = [];
            foreach ($source['links'] as $path) {
                foreach ($targetsByPath[$path] ?? [] as $targetId) {
                    $targetIds[(int) $targetId] = (int) $targetId;
                }
            }
            $linked[$source['id']] = array_values($targetIds);
        }

        return $linked;
    }

    /** @param array<string, mixed> $document
     *  @return array<int, string>
     */
    private function targetPaths(array $document): array
    {
        return collect([
            parse_url((string) $document['public_url'], PHP_URL_PATH),
            '/page/' . rawurlencode((string) $document['slug']),
            '/page/' . (string) $document['slug'],
        ])->map(fn ($path): string => $this->normalizePath((string) $path))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function suggestion(array $source, array $target): ?array
    {
        $score = 0;
        $reasons = [];

        if ($target['category'] !== '' && hash_equals($target['category'], $source['category'])) {
            $score += 35;
            $reasons[] = 'Same category: ' . $target['category_label'];
        }

        $sharedTags = array_values(array_intersect($target['tags'], $source['tags']));
        if ($sharedTags !== []) {
            $score += min(50, count($sharedTags) * 25);
            $tagLabels = collect($sharedTags)
                ->map(fn (string $slug): string => (string) ($target['tag_labels'][$slug] ?? $slug))
                ->implode(', ');
            $reasons[] = 'Shared project topic: ' . $tagLabels;
        }

        $normalizedFocus = Str::lower(trim((string) $target['focus_phrase']));
        if ($normalizedFocus !== '' && str_contains($source['search_text'], $normalizedFocus)) {
            $score += 40;
            $reasons[] = 'Target focus phrase appears in the source content';
        }

        $titleOverlap = count(array_intersect($target['title_tokens'], $source['all_tokens']));
        if ($titleOverlap > 0) {
            $score += min(32, $titleOverlap * 8);
            $reasons[] = $titleOverlap . ' target title ' . Str::plural('term', $titleOverlap) . ' in common';
        }

        $focusOverlap = count(array_intersect($target['focus_tokens'], $source['all_tokens']));
        if ($focusOverlap > 0 && !in_array('Target focus phrase appears in the source content', $reasons, true)) {
            $score += min(27, $focusOverlap * 9);
            $reasons[] = 'Related focus-phrase terms';
        }

        $taxonomyOverlap = count(array_intersect($target['taxonomy_tokens'], $source['all_tokens']));
        if ($taxonomyOverlap > 0 && $sharedTags === [] && $target['category'] !== $source['category']) {
            $score += min(21, $taxonomyOverlap * 7);
            $reasons[] = 'Related category or project vocabulary';
        }

        $contentOverlap = count(array_intersect($target['content_tokens'], $source['content_tokens']));
        if ($contentOverlap >= 2) {
            $score += min(20, $contentOverlap * 2);
            $reasons[] = 'Related page content';
        }

        if ($score < 8 || $reasons === []) {
            return null;
        }

        $anchors = collect([
            $target['focus_phrase'],
            $target['title'],
            collect($sharedTags)->map(fn (string $slug): string => (string) ($target['tag_labels'][$slug] ?? ''))->first(),
        ])->map(fn ($anchor): string => trim(strip_tags((string) $anchor)))
            ->filter(fn (string $anchor): bool => $anchor !== '' && mb_strlen($anchor) <= 100)
            ->unique(fn (string $anchor): string => Str::lower($anchor))
            ->take(3)
            ->values()
            ->all();

        return [
            'source_id' => $source['id'],
            'source_title' => $source['title'],
            'source_public_url' => $source['public_url'],
            'source_editor_url' => route('page.builder.edit', [
                'uuid' => $source['uuid'],
                'locale' => $source['locale'],
            ]),
            'source_locale' => $source['locale'],
            'score' => min(100, $score),
            'reasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
            'anchor_phrases' => $anchors,
        ];
    }

    /** @return array<int, string> */
    private function tokens(string $value): array
    {
        $normalized = Str::lower(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = array_flip(array_merge(self::ENGLISH_STOP_WORDS, self::BANGLA_STOP_WORDS));

        return collect($parts)
            ->map(fn (string $token): string => trim($token))
            ->filter(function (string $token) use ($stopWords): bool {
                if (isset($stopWords[$token])) {
                    return false;
                }

                return preg_match('/[\x{0980}-\x{09FF}]/u', $token) === 1
                    ? mb_strlen($token) >= 2
                    : mb_strlen($token) >= 3;
            })
            ->unique()
            ->take(250)
            ->values()
            ->all();
    }

    private function publicUrl(Page $page, string $defaultTranslationSlug): string
    {
        $routes = $this->routeDefinitions ??= $this->routes->all();
        $definition = $routes->first(
            fn (array $candidate): bool => ($candidate['page_slug'] ?? null) === (string) $page->slug
        );
        if (!$definition && $defaultTranslationSlug !== '') {
            $definition = $routes->first(
                fn (array $candidate): bool => ($candidate['page_slug'] ?? null) === $defaultTranslationSlug
            );
        }

        $url = $definition
            ? url((string) $definition['path'])
            : route('frontend.page', ['slug' => (string) $page->slug]);

        return (string) $this->metadata->localizedUrl(
            $url,
            (string) $page->language,
            (string) config('app.fallback_locale', 'en')
        );
    }

    private function plainText(mixed $value): string
    {
        $strings = [];
        $walk = function (mixed $item) use (&$walk, &$strings): void {
            if (is_array($item)) {
                foreach ($item as $nested) {
                    $walk($nested);
                }
                return;
            }
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        };
        $walk($value);

        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags(implode(' ', $strings)), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
    }

    /** @return array<int, string> */
    private function extractLinks(mixed $value): array
    {
        $links = [];
        $walk = function (mixed $item, string $key = '') use (&$walk, &$links): void {
            if (is_array($item)) {
                foreach ($item as $nestedKey => $nested) {
                    $walk($nested, (string) $nestedKey);
                }
                return;
            }
            if (!is_scalar($item)) {
                return;
            }

            $string = html_entity_decode((string) $item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match_all('/\bhref\s*=\s*(["\'])(.*?)\1/iu', $string, $matches)) {
                foreach ($matches[2] as $match) {
                    $links[] = (string) $match;
                }
            }
            if (preg_match('/(?:url|href|link|path|target)/i', $key) === 1) {
                $links[] = trim($string);
            }
        };
        $walk($value);

        return collect($links)
            ->map(fn (string $link): string => $this->normalizeLink($link))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeLink(string $link): string
    {
        $link = trim($link);
        if ($link === '' || str_starts_with($link, '#') || preg_match('/^(?:mailto|tel|javascript|data):/i', $link)) {
            return '';
        }

        $host = (string) parse_url($link, PHP_URL_HOST);
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($host !== '' && $appHost !== '' && !hash_equals(Str::lower($appHost), Str::lower($host))) {
            return '';
        }

        $path = (string) parse_url($link, PHP_URL_PATH);
        if ($path === '' && !str_contains($link, '://')) {
            $path = explode('?', explode('#', $link, 2)[0], 2)[0];
        }

        return $this->normalizePath($path);
    }

    private function normalizePath(string $path): string
    {
        $path = rawurldecode(trim($path));
        if ($path === '') {
            return '';
        }
        $path = '/' . ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
