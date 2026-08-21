<?php

namespace App\Services;

use App\Data\SeoMetadataPayload;
use App\Models\SeoMetadata;

final class SeoEditorialReviewService
{
    public function contentHash(SeoMetadata $metadata, array $fallback, string $url): string
    {
        $values = collect(SeoMetadataPayload::WRITABLE_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $metadata->getAttribute($field)])
            ->all();

        return hash('sha256', json_encode([
            'metadata' => $values,
            'fallback' => [
                'title' => (string) ($fallback['meta_title'] ?? ''),
                'description' => (string) ($fallback['meta_description'] ?? ''),
                'image' => (string) ($fallback['meta_image'] ?? ''),
            ],
            'url' => $url,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{status: string, note: string, stale: bool} */
    public function effectiveState(?SeoMetadata $metadata, array $fallback, string $url): array
    {
        if (!$metadata) {
            return ['status' => 'draft', 'note' => '', 'stale' => false];
        }

        $status = (string) ($metadata->review_status ?: 'draft');
        $note = (string) ($metadata->review_note ?: '');
        if (!in_array($status, ['pending', 'approved', 'changes_requested'], true)) {
            return ['status' => $status, 'note' => $note, 'stale' => false];
        }

        $reviewedHash = (string) $metadata->review_content_hash;
        $currentHash = $this->contentHash($metadata, $fallback, $url);
        if ($reviewedHash !== '' && hash_equals($reviewedHash, $currentHash)) {
            return ['status' => $status, 'note' => $note, 'stale' => false];
        }

        return [
            'status' => 'draft',
            'note' => 'Page content or its public address changed after this SEO review. Submit the current version again.',
            'stale' => true,
        ];
    }
}
