<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Collection;

class CategoryLandingPageAliasService
{
    /**
     * Find the active category that owns this page as its localized landing
     * page. The page's category_id is intentionally not part of this lookup:
     * landing_page_uuid is the explicit SEO ownership relationship.
     */
    public function categoryForPage(Page $page, ?string $locale = null): ?Category
    {
        if (blank($page->uuid)) {
            return null;
        }

        $locale ??= (string) ($page->language ?: app()->getLocale());

        $categories = Category::query()
            ->where('status', 1)
            ->where('display_mode', 'landing_page')
            ->where('landing_page_uuid', $page->uuid)
            ->orderBy('id')
            ->get();

        // Prefer the requested locale's real category slug. If the content is
        // misconfigured and that translation is missing, keep the alias
        // fail-closed by redirecting to an existing owner instead of allowing
        // the generic page URL to become indexable.
        return $categories->firstWhere('language', $locale)
            ?? $categories->firstWhere('language', (string) config('app.fallback_locale', 'en'))
            ?? $categories->first();
    }

    /** @return Collection<int, string> */
    public function pageUuids(): Collection
    {
        return Category::query()
            ->where('status', 1)
            ->where('display_mode', 'landing_page')
            ->whereNotNull('landing_page_uuid')
            ->pluck('landing_page_uuid')
            ->filter(fn ($uuid) => filled($uuid))
            ->map(fn ($uuid) => (string) $uuid)
            ->unique()
            ->values();
    }
}
