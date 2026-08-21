<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

class PageCategoryTranslationMapper
{
    public function __construct(private PageEditorVersionService $editorVersions)
    {
    }

    /**
     * Resolve the category reference a translated page should store.
     *
     * Numeric category IDs are locale-specific. Category UUIDs are the stable
     * identity shared by translations, so they are the safe temporary value
     * when the target category has not been created yet.
     */
    public function targetCategoryId(Page $source, string $targetLocale): int|string|null
    {
        if (blank($source->category_id)) {
            return null;
        }

        $sourceCategory = $this->sourceCategory($source);
        if (!$sourceCategory || blank($sourceCategory->uuid)) {
            // Preserve legacy or orphaned references rather than guessing by
            // a translated name or slug, neither of which is stable identity.
            return $source->category_id;
        }

        $targetCategory = Category::query()
            ->where('uuid', $sourceCategory->uuid)
            ->where('language', $targetLocale)
            ->first();

        return $targetCategory
            ? (string) $targetCategory->id
            : (string) $sourceCategory->uuid;
    }

    /**
     * Repair a translated page only while it still carries a source-derived
     * reference. A category deliberately selected by an editor is preserved.
     */
    public function remap(Page $source, Page $target, string $targetLocale): void
    {
        $sourceCategory = $this->sourceCategory($source);
        if (!$sourceCategory) {
            return;
        }

        $current = (string) ($target->category_id ?? '');
        $replaceable = collect([
            '',
            (string) ($source->category_id ?? ''),
            (string) $sourceCategory->id,
            (string) ($sourceCategory->uuid ?? ''),
        ])->filter(fn (string $value) => $value !== '')->push('')->unique();

        if (!$replaceable->contains($current)) {
            return;
        }

        $mapped = $this->targetCategoryId($source, $targetLocale);
        if ((string) ($mapped ?? '') === $current) {
            return;
        }

        $target->category_id = $mapped;
        $target->save();
    }

    /**
     * Reconcile pages created before their target-locale category. This keeps
     * page-first and category-first translation workflows equivalent.
     */
    public function remapPagesForCategory(Category $sourceCategory, Category $targetCategory): int
    {
        if (blank($sourceCategory->uuid)
            || (string) $sourceCategory->uuid !== (string) $targetCategory->uuid
            || blank($sourceCategory->language)
            || blank($targetCategory->language)) {
            return 0;
        }

        $candidateUuids = Page::query()
            ->where('language', $sourceCategory->language)
            ->whereNotNull('uuid')
            ->whereIn('category_id', array_values(array_filter([
                (string) $sourceCategory->id,
                (string) $sourceCategory->uuid,
            ])))
            ->pluck('uuid')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($candidateUuids->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($candidateUuids, $sourceCategory, $targetCategory): int {
            $pageLocks = $this->editorVersions->lockForMutation($candidateUuids);
            $changed = [];

            foreach ($candidateUuids as $uuid) {
                $logicalPages = $pageLocks->get($uuid, collect());
                $source = $logicalPages->first(fn (Page $page): bool =>
                    !$page->trashed() && $page->language === $sourceCategory->language
                );
                $target = $logicalPages->first(fn (Page $page): bool =>
                    !$page->trashed() && $page->language === $targetCategory->language
                );
                if (!$source instanceof Page || !$target instanceof Page) {
                    continue;
                }

                $before = (string) ($target->category_id ?? '');
                $this->remap($source, $target, (string) $targetCategory->language);
                if ((string) ($target->category_id ?? '') !== $before) {
                    $changed[] = (string) $uuid;
                }
            }

            $this->editorVersions->advanceLocked($pageLocks, $changed);

            return count($changed);
        });
    }

    private function sourceCategory(Page $source): ?Category
    {
        $reference = trim((string) $source->category_id);
        if ($reference === '') {
            return null;
        }

        $query = Category::query()->where('language', $source->language);
        if (ctype_digit($reference)) {
            $byId = (clone $query)->whereKey((int) $reference)->first();
            if ($byId) {
                return $byId;
            }
        }

        return $query->where('uuid', $reference)->first();
    }
}
