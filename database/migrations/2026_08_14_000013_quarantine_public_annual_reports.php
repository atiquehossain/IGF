<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $publicDirectory = storage_path('app/public/photos/1/annual_report');
        if (!is_dir($publicDirectory)) {
            return;
        }

        $privateDirectory = storage_path('app/annual-reports');
        $quarantineDirectory = storage_path('app/quarantine/annual-reports');
        File::ensureDirectoryExists($privateDirectory, 0700, true);
        File::ensureDirectoryExists($quarantineDirectory, 0700, true);

        foreach (File::files($publicDirectory) as $file) {
            $name = basename($file->getFilename());
            $signature = (string) file_get_contents($file->getPathname(), false, null, 0, 5);
            $mime = File::mimeType($file->getPathname());
            $safePdf = $signature === '%PDF-'
                && $mime === 'application/pdf'
                && $file->getSize() <= 10 * 1024 * 1024;
            $destination = ($safePdf ? $privateDirectory : $quarantineDirectory) . DIRECTORY_SEPARATOR . $name;
            File::move($file->getPathname(), $destination);
        }
    }

    public function down(): void
    {
        // Security migration is intentionally one-way: private/quarantined files
        // must never be moved back under the public web storage link.
    }
};
