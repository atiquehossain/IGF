<?php

namespace App\Services;

final class StagedPrivateDocument
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $bytes,
        public readonly string $sha256,
    ) {
    }
}
