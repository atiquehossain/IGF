<?php

namespace App\Services;

use App\Data\SeoMetadataPayload;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageRevision;
use App\Models\DonationType;
use App\Models\ReusableBlock;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class PageRevisionService
{
    public const SHARED_CONFLICT_MESSAGE = 'A reusable section changed in another editor. Your unsaved work is still here; reload the page, review the latest shared version, and apply your changes again.';

    public function capture(
        Page $page,
        ?string $note = null,
        ?Collection $lockedReusableBlocks = null
    ): PageRevision
    {
        return DB::transaction(function () use ($page, $note, $lockedReusableBlocks): PageRevision {
            $logicalPages = $this->lockLogicalPageRows($page);
            $lockedPage = $logicalPages->first(fn (Page $candidate): bool =>
                (int) $candidate->getKey() === (int) $page->getKey()
            );
            abort_unless($lockedPage instanceof Page, 404);
            $lockedReusableBlocks ??= $this->lockReusableBlocksForPageIds(
                $logicalPages->pluck('id')
            );

            // Reusable rows must be the complete, globally ordered union before
            // any PageBlock is locked. Lock every block belonging to the
            // logical Page second, then fail closed if a caller supplied an
            // incomplete reusable set. Never acquire an extra reusable lock
            // from inside capture: doing so could invert R1/R2 across pages.
            $lockedPageBlocks = PageBlock::withTrashed()
                ->whereIn('page_id', $logicalPages->pluck('id'))
                ->orderBy('page_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $missingReusableIds = $lockedPageBlocks
                ->pluck('reusable_block_id')
                ->filter(fn ($id): bool => $id !== null && $id !== '')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->diff($lockedReusableBlocks->pluck('id')->map(fn ($id): int => (int) $id));
            if ($missingReusableIds->isNotEmpty()) {
                throw new LogicException(
                    'The locked reusable-section set is incomplete for this logical Page.'
                );
            }

            $lockedPage->load(['pageTags', 'seo']);
            $lockedPage->setRelation(
                'blocks',
                $lockedPageBlocks
                    ->where('page_id', $lockedPage->id)
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values()
            );
            $lockedPage->blocks->each(function (PageBlock $block) use ($lockedReusableBlocks): void {
                $block->setRelation(
                    'reusableBlock',
                    $block->reusable_block_id
                        ? $lockedReusableBlocks->get((int) $block->reusable_block_id)
                        : null
                );
            });

            // The canonical page-row lock serializes numbering and snapshots.
            // Every page mutation uses the same lock before changing blocks.
            $nextRevision = ((int) PageRevision::where('page_id', $lockedPage->id)->max('revision')) + 1;

            return PageRevision::create([
                'page_id' => $lockedPage->id,
                'uuid' => (string) Str::uuid(),
                'revision' => $nextRevision,
                'snapshot' => [
                    'page' => $lockedPage->attributesToArray(),
                    'blocks' => $lockedPage->blocks->map->attributesToArray()->values()->all(),
                    'reusable_blocks' => $lockedPage->blocks
                        ->pluck('reusableBlock')
                        ->filter()
                        ->unique('id')
                        ->map->attributesToArray()
                        ->values()
                        ->all(),
                    'tags' => $lockedPage->pageTags->map->attributesToArray()->values()->all(),
                    // Page revisions carry only values owned by the SEO
                    // editor. Identity, workflow and audit columns must stay
                    // anchored to the live metadata record.
                    'seo' => $lockedPage->seo
                        ? $lockedPage->seo->only(SeoMetadataPayload::WRITABLE_FIELDS)
                        : null,
                ],
                'note' => $note,
                'created_by' => auth('admin')->id(),
            ]);
        });
    }

    /**
     * @param array<string, int|null> $expectedReusableVersions
     */
    public function restore(Page $page, PageRevision $revision, array $expectedReusableVersions = []): Page
    {
        abort_unless($revision->page_id === $page->id, 404);

        return DB::transaction(function () use ($page, $revision, $expectedReusableVersions) {
            Page::withTrashed()
                ->where('uuid', $page->uuid)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $page = Page::withTrashed()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $revision = PageRevision::query()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($revision->page_id === $page->id, 404);

            $snapshot = $revision->snapshot;
            $snapshotReusableUuids = collect($snapshot['reusable_blocks'] ?? [])
                ->pluck('uuid')
                ->filter();
            $snapshotReusableIds = ReusableBlock::withTrashed()
                ->whereIn('uuid', $snapshotReusableUuids)
                ->pluck('id');
            $lockedReusableBlocksById = $this->lockReusableBlocksForPage($page, $snapshotReusableIds);
            $lockedReusableBlocks = $lockedReusableBlocksById->keyBy('uuid');

            $this->assertFundingDestinationRemainsPublic($page, $revision);
            $this->assertExpectedReusableVersions(
                $lockedReusableBlocks,
                collect($snapshot['reusable_blocks'] ?? []),
                $expectedReusableVersions
            );
            $this->capture(
                $page,
                'Automatic backup before restoring revision ' . $revision->revision,
                $lockedReusableBlocksById
            );

            // Funding eligibility is a global financial control shared by all
            // language rows. Logical identity and locale are immutable too;
            // a revision may restore copy/state, never move a row to another
            // project or language.
            $pageData = Arr::except(
                Arr::only($snapshot['page'] ?? [], $page->getFillable()),
                ['uuid', 'language', 'is_funding_project', 'is_zakat_eligible']
            );
            $page->fill($pageData)->save();

            $restoredReusableIds = [];
            foreach ($snapshot['reusable_blocks'] ?? [] as $reusableData) {
                $reusable = $lockedReusableBlocks->get((string) $reusableData['uuid']) ?? new ReusableBlock();
                $nextEditorVersion = ((int) ($reusable->editor_version ?? 0)) + 1;
                $originalId = $reusableData['id'] ?? null;
                $reusable->fill(Arr::only($reusableData, $reusable->getFillable()));
                // A revision may contain the historical token, but restoring
                // content is itself a new shared write. Never roll the token
                // backwards or let a pre-restore editor remain current.
                $reusable->editor_version = $nextEditorVersion;
                $reusable->save();

                if (!empty($reusableData['deleted_at'])) {
                    if (!$reusable->trashed()) {
                        $reusable->delete();
                    }
                } elseif ($reusable->trashed()) {
                    $reusable->restore();
                }

                if ($originalId) {
                    $restoredReusableIds[(int) $originalId] = $reusable->id;
                }
            }

            PageBlock::withTrashed()->where('page_id', $page->id)->forceDelete();

            foreach ($snapshot['blocks'] ?? [] as $blockData) {
                $block = new PageBlock();
                $block->fill(Arr::only($blockData, $block->getFillable()));
                $block->page_id = $page->id;
                if (!empty($blockData['reusable_block_id'])) {
                    $block->reusable_block_id = $restoredReusableIds[(int) $blockData['reusable_block_id']]
                        ?? $blockData['reusable_block_id'];
                }
                $block->save();

                if (!empty($blockData['deleted_at'])) {
                    $block->delete();
                }
            }

            $page->pageTags()->delete();
            foreach ($snapshot['tags'] ?? [] as $tagData) {
                $page->pageTags()->create(Arr::except($tagData, [
                    'id', 'page_id', 'created_at', 'updated_at', 'created_by', 'updated_by',
                ]));
            }

            if (!empty($snapshot['seo'])) {
                $locale = $page->language ?: app()->getLocale();
                $seo = $page->seo()->withTrashed()->firstOrNew(['locale' => $locale]);
                $isNew = !$seo->exists;
                if ($seo->trashed()) {
                    $seo->restore();
                }
                $seo->fill(SeoMetadataPayload::from((array) $snapshot['seo'])->attributes())
                    ->forceFill([
                        'review_status' => 'draft',
                        'review_note' => null,
                        'review_content_hash' => null,
                        'review_requested_by' => null,
                        'review_requested_at' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'updated_by' => auth('admin')->id(),
                    ]);
                if ($isNew) {
                    $seo->created_by = auth('admin')->id();
                }
                $seo->save();
            } elseif (array_key_exists('seo', $snapshot)) {
                $currentSeo = $page->seo()->withTrashed()->first();
                if ($currentSeo && !$currentSeo->trashed()) {
                    $currentSeo->delete();
                }
            }

            return $page->fresh(['blocks', 'seo']);
        });
    }

    /**
     * Return the reusable-section tokens an editor must present to restore a
     * revision. A null token deliberately represents a UUID that no longer
     * exists and prevents an unnoticed replacement from winning the race.
     *
     * @return array<string, int|null>
     */
    public function currentReusableVersions(PageRevision $revision): array
    {
        $uuids = collect(data_get($revision->snapshot, 'reusable_blocks', []))
            ->pluck('uuid')
            ->filter(fn ($uuid): bool => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();
        $current = ReusableBlock::withTrashed()
            ->whereIn('uuid', $uuids)
            ->get(['uuid', 'editor_version'])
            ->keyBy('uuid');

        return $uuids
            ->mapWithKeys(fn (string $uuid): array => [
                $uuid => $current->has($uuid)
                    ? (int) $current->get($uuid)->editor_version
                    : null,
            ])
            ->all();
    }

    /**
     * Pre-discover the complete reusable set for every locale row of a logical
     * Page, add any requested target or revision rows, and lock the union once
     * in primary-key order. Call this immediately after the Page lock and
     * before locking or mutating any PageBlock.
     *
     * @param iterable<int, int|string|null> $additionalIds
     * @return Collection<int, ReusableBlock> keyed by primary key
     */
    public function lockReusableBlocksForPage(Page $page, iterable $additionalIds = []): Collection
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Reusable section locks require an active database transaction.');
        }

        $logicalPages = $this->lockLogicalPageRows($page);

        return $this->lockReusableBlocksForPageIds(
            $logicalPages->pluck('id'),
            $additionalIds
        );
    }

    /**
     * Shared reusable sections can be changed from several pages. Always lock
     * their rows in one stable order after the owning page row is locked so a
     * revision never snapshots or restores half of a concurrent shared edit.
     *
     * @param Collection<int, int|string|null> $ids
     * @return Collection<int, ReusableBlock>
     */
    private function lockReusableBlocks(Collection $ids): Collection
    {
        $ids = $ids
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ReusableBlock::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param Collection<int, int|string> $pageIds
     * @param iterable<int, int|string|null> $additionalIds
     * @return Collection<int, ReusableBlock>
     */
    private function lockReusableBlocksForPageIds(Collection $pageIds, iterable $additionalIds = []): Collection
    {
        $referencedIds = $pageIds->isEmpty()
            ? collect()
            : PageBlock::withTrashed()
                ->whereIn('page_id', $pageIds)
                ->whereNotNull('reusable_block_id')
                ->orderBy('page_id')
                ->orderBy('id')
                ->pluck('reusable_block_id');

        return $this->lockReusableBlocks(
            $referencedIds->merge(collect($additionalIds))
        );
    }

    /** @return Collection<int, Page> */
    private function lockLogicalPageRows(Page $page): Collection
    {
        $uuid = trim((string) $page->uuid);

        return Page::withTrashed()
            ->when(
                $uuid !== '',
                fn ($query) => $query->where('uuid', $uuid),
                fn ($query) => $query->whereKey($page->getKey())
            )
            ->orderBy('uuid')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param Collection<int, ReusableBlock> $lockedReusableBlocks keyed by UUID
     * @param Collection<int, array<string, mixed>> $snapshotReusableBlocks
     * @param array<string, int|null> $expectedReusableVersions
     */
    private function assertExpectedReusableVersions(
        Collection $lockedReusableBlocks,
        Collection $snapshotReusableBlocks,
        array $expectedReusableVersions
    ): void {
        foreach ($snapshotReusableBlocks->unique('uuid') as $snapshotReusableBlock) {
            $uuid = (string) ($snapshotReusableBlock['uuid'] ?? '');
            abort_if($uuid === '' || !array_key_exists($uuid, $expectedReusableVersions), 409, self::SHARED_CONFLICT_MESSAGE);

            $current = $lockedReusableBlocks->get($uuid);
            $expected = $expectedReusableVersions[$uuid];
            $matches = $current
                ? is_numeric($expected) && (int) $expected === (int) $current->editor_version
                : $expected === null;

            abort_unless($matches, 409, self::SHARED_CONFLICT_MESSAGE);
        }
    }

    private function assertFundingDestinationRemainsPublic(Page $page, PageRevision $revision): void
    {
        // The logical Page rows are already locked by restore(). Lock matching
        // causes second, following the system-wide target -> cause order.
        $isFixedDestination = DonationType::query()
            ->where('status', 1)
            ->where('destination_type', 'page')
            ->where('destination_page_uuid', $page->uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->isNotEmpty();
        if (!$isFixedDestination) {
            return;
        }

        $snapshotPage = (array) data_get($revision->snapshot, 'page', []);
        $status = array_key_exists('status', $snapshotPage)
            ? (bool) $snapshotPage['status']
            : (bool) $page->status;
        $publication = (string) ($snapshotPage['publication_status'] ?? $page->publication_status);
        $visibility = (string) ($snapshotPage['visibility'] ?? $page->visibility);

        abort_if(
            !$status || $publication !== 'published' || $visibility !== 'public',
            422,
            'This revision would make an active donation destination unavailable. Reassign or unpublish the donation cause before restoring a draft, scheduled, unlisted, or private version.'
        );
    }
}
