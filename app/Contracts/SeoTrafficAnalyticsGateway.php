<?php

namespace App\Contracts;

interface SeoTrafficAnalyticsGateway
{
    public function configured(): bool;

    /** @return array<string, mixed> */
    public function fetch(int $days): array;
}
