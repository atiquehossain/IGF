<?php

namespace App\Contracts;

interface SeoSearchPerformanceGateway
{
    public function configured(): bool;

    /** @return array<string, mixed> */
    public function fetch(int $days): array;
}
