<?php

namespace App\Services;

use App\Data\SeoMetadataPayload;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeoMetadataRevisionService
{
    public function recentFor(?SeoMetadata $metadata, int $limit = 10): Collection
    {
        if (!$metadata || !$metadata->exists) {
            return collect();
        }

        return SeoMetadataRevision::query()
            ->when($metadata->seoable_type, fn ($query) => $query
                ->where('seoable_type', $metadata->seoable_type)
                ->where('seoable_id', $metadata->seoable_id))
            ->when(!$metadata->seoable_type, fn ($query) => $query
                ->where('route_name', $metadata->route_name))
            ->where('locale', $metadata->locale)
            ->latest('id')
            ->limit(max(1, min($limit, 50)))
            ->get();
    }

    public function capture(SeoMetadata $metadata, string $reason = 'Before SEO update'): SeoMetadataRevision
    {
        // Reload database defaults and the latest committed values. Newly
        // created Eloquent instances do not automatically contain column
        // defaults such as robots_index, and a partial in-memory instance must
        // never produce a corrupt restore point.
        if ($metadata->exists) {
            $metadata = SeoMetadata::withTrashed()->findOrFail($metadata->getKey());
        }

        $revision = SeoMetadataRevision::create([
            'uuid' => (string) Str::uuid(),
            'seo_metadata_id' => $metadata->getKey(),
            'seoable_type' => $metadata->seoable_type,
            'seoable_id' => $metadata->seoable_id,
            'route_name' => $metadata->route_name,
            'locale' => $metadata->locale,
            'snapshot' => $metadata->only($this->snapshotFields()),
            'reason' => mb_substr(trim($reason) ?: 'Before SEO update', 0, 255),
            'changed_by' => auth('admin')->id(),
            'created_at' => now(),
        ]);

        $history = SeoMetadataRevision::query()
            ->when($metadata->seoable_type, fn ($query) => $query
                ->where('seoable_type', $metadata->seoable_type)
                ->where('seoable_id', $metadata->seoable_id))
            ->when(!$metadata->seoable_type, fn ($query) => $query
                ->where('route_name', $metadata->route_name))
            ->where('locale', $metadata->locale)
            ->latest('id')
            ->pluck('id');
        if ($history->count() > 50) {
            SeoMetadataRevision::query()->whereIn('id', $history->slice(50))->delete();
        }

        return $revision;
    }

    /** @param null|callable(SeoMetadataRevision, SeoMetadata): void $beforeRestore */
    public function restore(SeoMetadataRevision $revision, ?callable $beforeRestore = null): SeoMetadata
    {
        return DB::transaction(function () use ($revision, $beforeRestore): SeoMetadata {
            $lockedRevision = SeoMetadataRevision::query()
                ->whereKey($revision->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $metadata = SeoMetadata::withTrashed()
                ->whereKey($lockedRevision->seo_metadata_id)
                ->lockForUpdate()
                ->first();

            abort_if(!$metadata, 409, 'This restore point no longer has an SEO record to restore.');
            abort_unless($this->sameIdentity($metadata, $lockedRevision), 409, 'This restore point no longer belongs to this SEO record.');
            if ($beforeRestore) {
                $beforeRestore($lockedRevision, $metadata);
            }

            // Capture the exact locked state first so every restore is undoable.
            $this->capture($metadata, 'Before restoring an earlier SEO version');
            if ($metadata->trashed()) {
                $metadata->restore();
            }

            // Old snapshots may contain legacy identity keys. The payload DTO
            // deliberately accepts only editor-owned values, so a restore can
            // never move a record to another model, route, or language.
            $snapshot = SeoMetadataPayload::from($lockedRevision->snapshot ?: [])->attributes();
            $metadata->fill($snapshot);
            $contentChanged = $metadata->isDirty(SeoMetadataPayload::WRITABLE_FIELDS);
            // Every completed restore is a new editor generation, even when
            // the selected snapshot happens to match the current payload.
            $audit = [
                'updated_by' => auth('admin')->id(),
                'editor_version' => (int) $metadata->editor_version + 1,
            ];

            if ($contentChanged) {
                // A prior approval or pending request only applies to the
                // exact SEO payload that was reviewed. Restoring different
                // writable content must return the current record to draft;
                // the immutable revisions still retain the audit trail.
                $audit += [
                    'review_status' => 'draft',
                    'review_note' => null,
                    'review_content_hash' => null,
                    'review_requested_by' => null,
                    'review_requested_at' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            }

            $metadata->forceFill($audit)->save();

            return $metadata->fresh();
        });
    }

    /** @return array<int, string> */
    public function snapshotFields(): array
    {
        return SeoMetadataPayload::WRITABLE_FIELDS;
    }

    private function sameIdentity(SeoMetadata $metadata, SeoMetadataRevision $revision): bool
    {
        return (string) ($metadata->seoable_type ?? '') === (string) ($revision->seoable_type ?? '')
            && (string) ($metadata->seoable_id ?? '') === (string) ($revision->seoable_id ?? '')
            && (string) ($metadata->route_name ?? '') === (string) ($revision->route_name ?? '')
            && (string) $metadata->locale === (string) $revision->locale;
    }
}
