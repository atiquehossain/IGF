<?php

namespace App\Services;

final class SeoIndexingPolicy
{
    public const BLOCKED_DIRECTIVE = 'noindex,nofollow,noarchive';

    public function indexingAllowed(): bool
    {
        return config('app.env') === 'production'
            && (bool) config('seo.robots.indexing_enabled', false);
    }

    /** @return array{robots:string}|array{} */
    public function metadataOverride(): array
    {
        return $this->indexingAllowed()
            ? []
            : ['robots' => self::BLOCKED_DIRECTIVE];
    }
}
