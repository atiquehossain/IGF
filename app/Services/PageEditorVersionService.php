<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PageEditorVersionService
{
    public const CONFLICT_MESSAGE = 'This page changed in another editor. Your unsaved work is still here; reload the page, review the latest version, and apply your changes again.';

    public function current(string $uuid): int
    {
        return (int) Page::withTrashed()->where('uuid', $uuid)->max('editor_version');
    }

    /**
     * The caller must already be inside a database transaction.
     */
    public function lockAndAssert(string $uuid, string $locale, int $expectedVersion): Page
    {
        $locked = $this->lockForMutation([$uuid]);

        return $this->assertExpected($locked, $uuid, $locale, $expectedVersion);
    }

    /**
     * Validate an editor token against rows already locked for mutation.
     *
     * @param \Illuminate\Support\Collection<string, Collection<int, Page>> $locked
     */
    public function assertExpected($locked, string $uuid, string $locale, int $expectedVersion): Page
    {
        $pages = $locked->get($uuid, new Collection());
        $page = $pages->first(fn (Page $candidate): bool =>
            $candidate->language === $locale && $candidate->deleted_at === null
        );

        abort_unless($page instanceof Page, 404);
        abort_if(
            (int) $pages->max('editor_version') !== $expectedVersion,
            409,
            self::CONFLICT_MESSAGE
        );

        return $page;
    }

    /**
     * Invalidate every open editor for one logical Page identity and return
     * the new generation. This is safe both inside and outside an existing
     * transaction; nested Laravel transactions retain the caller's locks.
     */
    public function advance(string $uuid): int
    {
        return $this->advanceMany([$uuid])[$uuid] ?? 0;
    }

    /**
     * Lock logical identities in deterministic order and advance every locale
     * row to the same generation without changing editorial timestamps.
     *
     * @param iterable<string> $uuids
     * @return array<string, int>
     */
    public function advanceMany(iterable $uuids): array
    {
        $normalized = $this->normalizeUuids($uuids);

        if ($normalized === []) {
            return [];
        }

        return DB::transaction(function () use ($normalized): array {
            $locked = $this->lockForMutation($normalized);

            return $this->advanceLocked($locked, $normalized);
        });
    }

    /**
     * Acquire every logical Page row before a caller locks or mutates child
     * records. Callers must be inside a transaction and must retain the
     * returned lock set until advanceLocked() completes.
     *
     * @param iterable<string> $uuids
     * @return \Illuminate\Support\Collection<string, Collection<int, Page>>
     */
    public function lockForMutation(iterable $uuids)
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Page editor-version locks require an active database transaction.');
        }

        return $this->lockLogicalRows($this->normalizeUuids($uuids));
    }

    /**
     * Advance rows already locked by lockForMutation(). A subset may be used
     * when a bulk operation discovers that only some locked identities changed.
     *
     * @param \Illuminate\Support\Collection<string, Collection<int, Page>> $locked
     * @param iterable<string>|null $uuids
     * @return array<string, int>
     */
    public function advanceLocked($locked, ?iterable $uuids = null): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Page editor-version advancement requires an active database transaction.');
        }

        $normalized = $uuids === null
            ? $this->normalizeUuids($locked->keys())
            : $this->normalizeUuids($uuids);
        $versions = [];

        foreach ($normalized as $uuid) {
            /** @var Collection<int, Page> $pages */
            $pages = $locked->get($uuid, new Collection());
            if ($pages->isEmpty()) {
                continue;
            }

            $nextVersion = (int) $pages->max('editor_version') + 1;
            DB::table('pages')
                ->where('uuid', $uuid)
                ->update(['editor_version' => $nextVersion]);
            $versions[$uuid] = $nextVersion;
        }

        return $versions;
    }

    /**
     * @param iterable<string> $uuids
     * @return \Illuminate\Support\Collection<string, Collection<int, Page>>
     */
    private function lockLogicalRows(iterable $uuids)
    {
        $ordered = $this->normalizeUuids($uuids);

        return Page::withTrashed()
            ->whereIn('uuid', $ordered)
            ->orderBy('uuid')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('uuid');
    }

    /** @param iterable<string> $uuids */
    private function normalizeUuids(iterable $uuids): array
    {
        return collect($uuids)
            ->map(fn ($uuid): string => trim((string) $uuid))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
