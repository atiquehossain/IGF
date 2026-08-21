<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ContentFileQuarantine
{
    private const ROOT = 'app/content-purge-quarantine';

    public function __construct(private LegacyMediaReferenceService $references)
    {
    }

    public function stage(Model $item): ?string
    {
        $entries = $this->entries($item);
        if ($entries === []) {
            return null;
        }

        $batch = (string) Str::uuid();
        $manifest = [
            'version' => 1,
            'status' => 'staging',
            'model' => $item::class,
            'key' => (string) $item->getKey(),
            'created_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];
        $this->writeManifest($batch, $manifest);

        try {
            foreach ($entries as $index => $entry) {
                $source = Storage::disk($entry['disk'])->path($entry['path']);
                if (!File::exists($source)) {
                    continue;
                }
                $destination = $this->payloadPath($batch, $index);
                File::ensureDirectoryExists(dirname($destination));
                $moved = $entry['directory']
                    ? File::moveDirectory($source, $destination, true)
                    : File::move($source, $destination);
                if (!$moved) {
                    throw new RuntimeException('A media item could not be moved into quarantine.');
                }
            }
            $manifest['status'] = 'staged';
            $this->writeManifest($batch, $manifest);

            return $batch;
        } catch (Throwable $exception) {
            $this->restore($batch, $manifest);
            throw new RuntimeException('The content media could not be moved into recoverable quarantine.', 0, $exception);
        }
    }

    public function commit(?string $batch): void
    {
        if ($batch === null) {
            return;
        }

        $manifest = $this->manifest($batch);
        if ($manifest !== null) {
            $manifest['status'] = 'committed';
            $this->writeManifest($batch, $manifest);
        }
        File::deleteDirectory($this->batchPath($batch));
    }

    public function rollback(?string $batch): void
    {
        if ($batch === null) {
            return;
        }

        if ($manifest = $this->manifest($batch)) {
            $this->restore($batch, $manifest);
        }
    }

    /** @return array{restored:int,discarded:int} */
    public function recoverStale(int $minimumAgeMinutes = 15): array
    {
        $result = ['restored' => 0, 'discarded' => 0];
        $root = storage_path(self::ROOT);
        if (!File::isDirectory($root)) {
            return $result;
        }

        foreach (File::directories($root) as $directory) {
            if (File::lastModified($directory) > now()->subMinutes($minimumAgeMinutes)->timestamp) {
                continue;
            }
            $batch = basename($directory);
            if (!Str::isUuid($batch)) {
                continue;
            }
            $manifest = $this->manifest($batch);
            if ($manifest === null) {
                // Unknown files are deliberately retained for manual review.
                continue;
            }

            $model = $manifest['model'] ?? null;
            $exists = is_string($model) && is_subclass_of($model, Model::class)
                && $model::withTrashed()->whereKey($manifest['key'] ?? null)->exists();
            if ($exists && ($manifest['status'] ?? '') !== 'committed') {
                $this->restore($batch, $manifest);
                $result['restored']++;
            } else {
                File::deleteDirectory($directory);
                $result['discarded']++;
            }
        }

        return $result;
    }

    /** @return list<array{disk:string,path:string,directory:bool}> */
    private function entries(Model $item): array
    {
        $entries = match (true) {
            $item instanceof AnnualReport => [
                ['disk' => 'local', 'path' => 'annual-reports/' . basename((string) $item->image_path), 'directory' => false],
                ['disk' => 'local', 'path' => 'annual-reports/' . basename((string) $item->file_path), 'directory' => false],
            ],
            $item instanceof Banner => [
                ['disk' => 'public', 'path' => 'photos/1/banner/' . basename((string) $item->image), 'directory' => false],
                ['disk' => 'public', 'path' => 'photos/1/banner/' . basename((string) $item->path), 'directory' => false],
            ],
            $item instanceof Category => [
                ['disk' => 'public', 'path' => 'photos/1/category/' . basename((string) $item->image), 'directory' => false],
                ['disk' => 'public', 'path' => 'photos/1/category/' . basename((string) $item->path), 'directory' => false],
            ],
            $item instanceof Gallery => [['disk' => 'public', 'path' => 'photos/1/gallery/' . (int) $item->id, 'directory' => true]],
            $item instanceof LatestNews => [
                ['disk' => 'public', 'path' => 'photos/1/our_members/' . basename((string) $item->image), 'directory' => false],
                ['disk' => 'public', 'path' => 'photos/1/our_members/' . basename((string) $item->path), 'directory' => false],
            ],
            $item instanceof NoticeBoard => [
                ['disk' => 'public', 'path' => 'photos/1/notice_board/' . basename((string) $item->image_path), 'directory' => false],
                ['disk' => 'local', 'path' => 'notice-attachments/' . basename((string) $item->file_path), 'directory' => false],
            ],
            $item instanceof Testimonial => [['disk' => 'public', 'path' => 'photos/1/testimonial/' . basename((string) $item->photo), 'directory' => false]],
            default => [],
        };

        $unique = [];
        foreach ($entries as $entry) {
            if (str_ends_with($entry['path'], '/')) {
                continue;
            }
            if ($this->references->physicalPathInUse($entry['disk'], $entry['path'], $item)) {
                continue;
            }
            $unique[$entry['disk'] . '|' . $entry['path']] = $entry;
        }

        return array_values($unique);
    }

    private function restore(string $batch, array $manifest): void
    {
        foreach (array_reverse($manifest['entries'] ?? [], true) as $index => $entry) {
            $quarantined = $this->payloadPath($batch, $index);
            if (!File::exists($quarantined)) {
                continue;
            }
            $original = Storage::disk($entry['disk'])->path($entry['path']);
            File::ensureDirectoryExists(dirname($original));
            $moved = $entry['directory']
                ? File::moveDirectory($quarantined, $original, true)
                : File::move($quarantined, $original);
            if (!$moved) {
                throw new RuntimeException('A quarantined media item could not be restored.');
            }
        }
        File::deleteDirectory($this->batchPath($batch));
    }

    private function manifest(string $batch): ?array
    {
        $path = $this->batchPath($batch) . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!File::isFile($path)) {
            return null;
        }
        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeManifest(string $batch, array $manifest): void
    {
        $directory = $this->batchPath($batch);
        File::ensureDirectoryExists($directory);
        File::put(
            $directory . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    private function batchPath(string $batch): string
    {
        abort_unless(Str::isUuid($batch), 500);

        return storage_path(self::ROOT . '/' . $batch);
    }

    private function payloadPath(string $batch, int|string $index): string
    {
        return $this->batchPath($batch) . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . (int) $index;
    }
}
