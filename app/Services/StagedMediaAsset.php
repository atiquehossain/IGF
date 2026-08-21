<?php

namespace App\Services;

final readonly class StagedMediaAsset
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public string $disk,
        public string $databaseValue,
        public array $paths,
    ) {
    }
}
