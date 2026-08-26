<?php

namespace Tests\Support;

use App\Contracts\PrivateFileDeletion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ControllablePrivateFileDeletion implements PrivateFileDeletion
{
    public bool $fail = true;

    /** @var list<array{disk:string,path:string}> */
    public array $calls = [];

    public function deleteStored(string $disk, string $path): void
    {
        $this->calls[] = ['disk' => $disk, 'path' => $path];
        if ($this->fail) {
            throw new RuntimeException('Simulated private storage deletion failure.');
        }

        $storage = Storage::disk($disk);
        if ($storage->exists($path) && !$storage->delete($path)) {
            throw new RuntimeException('Private storage deletion failed.');
        }
    }
}
