<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoNotFoundHit;
use App\Models\Tag;

final class SeoManagedDestinationService
{
    /** @var array<string,array<string,string>> */
    private array $cached = [];

    public function __construct(
        private SeoRouteRegistry $routes,
        private TechnicalSeoUrlPolicy $urls,
        private LocalizationManager $localization,
    ) {
    }

    /** @return array<string,string> path => label */
    public function all(?string $locale = null): array
    {
        $publicLocales = $this->localization->publicLocales();
        if ($locale !== null && !in_array($locale, $publicLocales, true)) {
            return [];
        }
        $cacheKey = $locale ?? '*';
        if (isset($this->cached[$cacheKey])) {
            return $this->cached[$cacheKey];
        }

        $destinations = [];
        foreach ($this->routes->all() as $definition) {
            if (!$this->routeAvailableInLocale($definition, $locale)) {
                continue;
            }
            $path = $this->urls->internalPath((string) $definition['path'], '/');
            if ($path !== null) {
                $destinations[$path] = (string) ($definition['label'] ?? $path);
            }
        }

        $locales = $locale !== null ? [$locale] : $publicLocales;
        $reservedSlugs = $this->routes->all()->pluck('page_slug')->filter()->all();
        $aliasedPageUuids = Category::query()->where('status', 1)->where('display_mode', 'landing_page')
            ->whereNotNull('landing_page_uuid')->orderBy('id')->limit(300)->pluck('landing_page_uuid')->all();

        Page::query()->publiclyAvailable()
            ->where('visibility', 'public')->whereIn('language', $locales)->whereNotNull('slug')
            ->whereNotIn('slug', $reservedSlugs ?: [''])
            ->where(fn ($query) => $query->whereNull('uuid')->orWhereNotIn('uuid', $aliasedPageUuids ?: ['']))
            ->select(['slug', 'name'])->orderBy('id')->limit(300)->get()
            ->each(function (Page $page) use (&$destinations): void {
                $this->add($destinations, '/page/' . $page->slug, (string) ($page->name ?: $page->slug));
            });

        Category::query()->where('status', 1)->whereIn('language', $locales)->whereNotNull('slug')
            ->select(['slug', 'name'])->orderBy('id')->limit(300)->get()
            ->each(function (Category $category) use (&$destinations): void {
                $this->add($destinations, '/category/' . $category->slug, (string) ($category->name ?: $category->slug));
            });

        NoticeBoard::query()->publiclyReleased()->whereIn('language', $locales)->whereNotNull('slug')
            ->select(['slug', 'title'])->orderBy('id')->limit(300)->get()
            ->each(function (NoticeBoard $event) use (&$destinations): void {
                $this->add($destinations, '/event/' . $event->slug, (string) ($event->title ?: $event->slug));
            });

        Tag::query()->where('status', 1)->whereNotNull('slug')->select(['slug', 'name'])
            ->orderBy('id')->limit(300)->get()
            ->each(function (Tag $project) use (&$destinations): void {
                $this->add($destinations, '/projects/' . $project->slug, (string) ($project->name ?: $project->slug));
            });

        AnnualReport::query()->publiclyReleased()->whereIn('language', $locales)->whereNotNull('slug')
            ->select(['slug', 'title'])->orderBy('id')->limit(300)->get()
            ->each(function (AnnualReport $report) use (&$destinations): void {
                $this->add($destinations, '/annual-report/' . $report->slug, (string) ($report->title ?: $report->slug));
            });

        return $this->cached[$cacheKey] = array_slice($destinations, 0, 1000, true);
    }

    /** @return list<array{path:string,label:string,score:int}> */
    public function suggestions(SeoNotFoundHit|string $hit, int $limit = 3): array
    {
        $source = $hit instanceof SeoNotFoundHit ? $hit->path : $hit;
        $needle = $this->words($source);
        $suggestions = [];
        $locale = $hit instanceof SeoNotFoundHit ? (string) $hit->locale : null;
        foreach ($this->all($locale) as $path => $label) {
            if ($path === $source) {
                continue;
            }
            $candidate = $this->words($path . ' ' . $label);
            $distance = levenshtein(mb_substr($needle, 0, 255), mb_substr($candidate, 0, 255));
            $length = max(1, max(strlen($needle), strlen($candidate)));
            $score = max(0, (int) round(100 * (1 - ($distance / $length))));
            if ($needle !== '' && str_contains($candidate, $needle)) {
                $score = max($score, 85);
            }
            $suggestions[] = ['path' => $path, 'label' => $label, 'score' => $score];
        }

        usort($suggestions, fn (array $left, array $right): int => $right['score'] <=> $left['score'] ?: strcmp($left['path'], $right['path']));

        return array_slice($suggestions, 0, max(1, min(5, $limit)));
    }

    public function isManaged(string $path, ?string $locale = null): bool
    {
        return array_key_exists($path, $this->all($locale));
    }

    /** @param array<string,mixed> $definition */
    private function routeAvailableInLocale(array $definition, ?string $locale): bool
    {
        $pageSlug = trim((string) ($definition['page_slug'] ?? ''));
        if ($locale === null || $pageSlug === '') {
            return true;
        }

        return Page::query()->publiclyAvailable()
            ->where('language', $locale)
            ->where('slug', $pageSlug)
            ->exists();
    }

    private function words(string $value): string
    {
        $value = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /** @param array<string,string> $destinations */
    private function add(array &$destinations, string $candidate, string $label): void
    {
        $path = $this->urls->internalPath($candidate, '/');
        if ($path !== null) {
            $label = mb_substr(trim($label), 0, 120);
            $destinations[$path] = $label !== '' ? $label : $path;
        }
    }
}
