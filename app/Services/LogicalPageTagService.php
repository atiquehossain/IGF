<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageTagModule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LogicalPageTagService
{
    /**
     * Put the same project-group assignments on every translation of a page.
     * Callers that already own a logical-page transaction retain their locks;
     * the deterministic page order also keeps standalone callers predictable.
     *
     * @param array<int, int|string> $tagIds
     */
    public function sync(Page $page, array $tagIds): void
    {
        $wanted = collect($tagIds)
            ->map(fn ($tagId): int => (int) $tagId)
            ->filter(fn (int $tagId): bool => $tagId > 0)
            ->unique()
            ->sort()
            ->values();

        $translations = filled($page->uuid)
            ? Page::query()->where('uuid', $page->uuid)->orderBy('id')->get(['id', 'uuid'])
            : new EloquentCollection([$page]);

        foreach ($translations as $translation) {
            $this->syncRow($translation, $wanted);
        }

        if ($translations->isNotEmpty()) {
            DB::table('pages')
                ->whereIn('id', $translations->pluck('id')->all())
                ->update(['is_relationship' => $wanted->isNotEmpty()]);
        }

        $page->unsetRelation('pageTags');
    }

    /**
     * Replace each supplied page's ordinary relation with the deduplicated
     * logical assignment set shared by every translation carrying its UUID.
     *
     * @param iterable<int, Page> $pages
     */
    public function hydrate(iterable $pages, bool $activeOnly = false): void
    {
        $pages = collect($pages)->filter(fn ($page): bool => $page instanceof Page)->values();
        if ($pages->isEmpty()) {
            return;
        }

        $uuids = $pages->pluck('uuid')->filter()->unique()->values();
        $translationRows = Page::query()
            ->whereIn('uuid', $uuids->all())
            ->get(['id', 'uuid'])
            ->groupBy(fn (Page $page): string => (string) $page->uuid);
        $allIds = $translationRows->flatten(1)->pluck('id')->merge($pages->pluck('id'))->unique()->values();

        $links = PageTagModule::query()
            ->with('tag')
            ->whereIn('page_id', $allIds->all())
            ->orderBy('id')
            ->get()
            ->filter(fn (PageTagModule $link): bool => $link->tag !== null
                && (!$activeOnly || (bool) $link->tag->status))
            ->unique(fn (PageTagModule $link): string => $link->page_id . ':' . $link->tag_id)
            ->values();

        $uuidByPageId = $translationRows
            ->flatten(1)
            ->mapWithKeys(fn (Page $translation): array => [(int) $translation->id => (string) $translation->uuid]);
        $byIdentity = $links->groupBy(function (PageTagModule $link) use ($uuidByPageId): string {
            $uuid = (string) $uuidByPageId->get((int) $link->page_id, '');

            return $uuid !== '' ? 'uuid:' . $uuid : 'id:' . $link->page_id;
        })->map(fn (Collection $identityLinks): EloquentCollection => new EloquentCollection(
            $identityLinks->unique('tag_id')->values()->all()
        ));

        foreach ($pages as $page) {
            $identity = filled($page->uuid) ? 'uuid:' . $page->uuid : 'id:' . $page->id;
            $page->setRelation('pageTags', $byIdentity->get($identity, new EloquentCollection()));
        }
    }

    /** @param Collection<int, int> $wanted */
    private function syncRow(Page $page, Collection $wanted): void
    {
        $links = $page->pageTags()->orderBy('id')->get();
        $kept = collect();

        foreach ($links as $link) {
            $tagId = (int) $link->tag_id;
            if (!$wanted->contains($tagId) || $kept->contains($tagId)) {
                $link->delete();
                continue;
            }

            $kept->push($tagId);
        }

        foreach ($wanted->diff($kept) as $tagId) {
            PageTagModule::create([
                'uuid' => (string) Str::uuid(),
                'page_id' => $page->id,
                'tag_id' => $tagId,
            ]);
        }

        $page->unsetRelation('pageTags');
    }
}
