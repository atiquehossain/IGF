<?php

namespace App\Contracts;

interface PrivateFileDeletion
{
    public function deleteStored(string $disk, string $path): void;
}
