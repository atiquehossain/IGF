<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notice_boards') || !Schema::hasColumn('notice_boards', 'file_path')) {
            return;
        }

        $public = Storage::disk('public');
        $private = Storage::disk('local');
        File::ensureDirectoryExists($private->path('notice-attachments'));

        DB::table('notice_boards')
            ->whereNotNull('file_path')
            ->where('file_path', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($public, $private): void {
                foreach ($records as $record) {
                    $filename = basename((string) $record->file_path);
                    if ($filename === '' || $filename !== (string) $record->file_path) {
                        throw new RuntimeException('Unsafe notice attachment filename encountered during migration.');
                    }

                    $source = $public->path('photos/1/notice_board/' . $filename);
                    $destination = $private->path('notice-attachments/' . $filename);
                    if (!is_file($source)) {
                        continue;
                    }

                    if (is_file($destination)) {
                        if (!$this->sameFile($source, $destination)) {
                            throw new RuntimeException('Conflicting private notice attachment already exists: ' . $filename);
                        }
                        File::delete($source);
                        continue;
                    }

                    $temporary = $destination . '.migrating-' . Str::uuid();
                    try {
                        if (!File::copy($source, $temporary) || !$this->sameFile($source, $temporary)) {
                            throw new RuntimeException('Notice attachment copy verification failed: ' . $filename);
                        }
                        if (!File::move($temporary, $destination) || !$this->sameFile($source, $destination)) {
                            throw new RuntimeException('Notice attachment finalization failed: ' . $filename);
                        }
                        File::delete($source);
                    } finally {
                        File::delete($temporary);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally one-way: moving protected attachments back beneath the
        // public storage symlink would reintroduce unauthenticated disclosure.
    }

    private function sameFile(string $left, string $right): bool
    {
        return is_file($left)
            && is_file($right)
            && filesize($left) === filesize($right)
            && hash_file('sha256', $left) === hash_file('sha256', $right);
    }
};
