<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use RuntimeException;
use Throwable;

class SafeMediaReplacementService
{
    private ?LegacyMediaReferenceService $references = null;

    private const MAX_DIMENSION = 8192;

    private const MAX_PIXELS = 30_000_000;

    private const FLAT_COLLECTIONS = [
        'banner',
        'category',
        'notice_board',
        'our_members',
        'page',
        'testimonial',
    ];

    public function stageResizedPublicImage(
        UploadedFile $file,
        string $collection,
        int $width,
        ?int $height = null,
    ): StagedMediaAsset {
        $this->assertFlatCollection($collection);
        [$source, $mime, $sourceWidth, $sourceHeight] = $this->readImage($file);

        if ($height === null) {
            $height = max(1, (int) round($sourceHeight / ($sourceWidth / $width)));
        }
        $this->assertDimensions($width, $height);

        $extension = $this->extensionFor($mime);
        $name = bin2hex(random_bytes(24)) . '.' . $extension;
        $path = "photos/1/{$collection}/{$name}";
        $bytes = $this->resizeAndEncode($source, $extension, $width, $height);

        return $this->writeVerifiedImages('public', $name, [$path => $bytes]);
    }

    public function stageGalleryImage(UploadedFile $file, int $recordId): StagedMediaAsset
    {
        if ($recordId < 1) {
            throw new RuntimeException('A persisted gallery record is required before staging its image.');
        }

        [$source, $mime, $sourceWidth, $sourceHeight] = $this->readImage($file);
        $extension = $this->extensionFor($mime);
        $name = bin2hex(random_bytes(24)) . '.' . $extension;
        $base = "photos/1/gallery/{$recordId}";

        return $this->writeVerifiedImages('public', $name, [
            "{$base}/430X360/{$name}" => $this->resizeAndEncode($source, $extension, 430, 360),
            "{$base}/main/{$name}" => $this->resizeAndEncode($source, $extension, $sourceWidth, $sourceHeight),
        ]);
    }

    public function stageUserAvatar(int $userId, string $bytes, string $mime): StagedMediaAsset
    {
        if ($userId < 1 || !isset($this->allowedImageTypes()[$mime])) {
            throw new RuntimeException('The profile image destination is invalid.');
        }

        $this->verifyImageBytes($bytes);
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
            throw new RuntimeException('The profile image format changed during validation.');
        }

        $name = bin2hex(random_bytes(24)) . '.' . $this->extensionFor($mime);
        $databaseValue = "{$userId}/350X350/{$name}";

        return $this->writeVerifiedImages('local', $databaseValue, [
            'uploads/users/' . $databaseValue => $bytes,
        ]);
    }

    /** @param iterable<StagedMediaAsset> $assets */
    public function discardMany(iterable $assets): void
    {
        foreach ($assets as $asset) {
            if ($asset instanceof StagedMediaAsset) {
                $this->deletePaths($asset->disk, $asset->paths);
            }
        }
    }

    /** @param array<int, string|null>|string|null $names */
    public function deleteLegacyFlatImages(string $collection, array|string|null $names): void
    {
        $this->assertFlatCollection($collection);
        $paths = [];
        foreach ((array) $names as $name) {
            if ($this->isSafeStoredName($name)
                && !$this->references()->flatImageInUse($collection, $name)) {
                $paths[] = "photos/1/{$collection}/{$name}";
            }
        }

        $this->deletePaths('public', array_values(array_unique($paths)));
    }

    /** @param array<int, string|null>|string|null $names */
    public function deleteLegacyGalleryImages(int $recordId, array|string|null $names): void
    {
        if ($recordId < 1) {
            return;
        }

        $paths = [];
        foreach ((array) $names as $name) {
            if (!$this->isSafeStoredName($name)) {
                continue;
            }
            if ($this->references()->galleryImageInUse($recordId, $name)) {
                continue;
            }
            $paths[] = "photos/1/gallery/{$recordId}/430X360/{$name}";
            $paths[] = "photos/1/gallery/{$recordId}/main/{$name}";
        }

        $this->deletePaths('public', array_values(array_unique($paths)));
    }

    public function deleteLegacyUserAvatar(?string $relativePath): void
    {
        if (is_string($relativePath)
            && preg_match('#\A\d+/350X350/[a-f0-9]{40,48}\.(?:jpe?g|png|webp)\z#i', $relativePath)
            && !$this->references()->physicalPathInUse('local', 'uploads/users/' . $relativePath)) {
            $this->deletePaths('local', ['uploads/users/' . $relativePath]);
        }
    }

    private function references(): LegacyMediaReferenceService
    {
        return $this->references ??= app(LegacyMediaReferenceService::class);
    }

    /**
     * @param  array<string, string>  $imagesByPath
     */
    private function writeVerifiedImages(string $diskName, string $databaseValue, array $imagesByPath): StagedMediaAsset
    {
        $disk = Storage::disk($diskName);
        $attempted = [];

        try {
            foreach ($imagesByPath as $path => $bytes) {
                $attempted[] = $path;
                if (!$disk->put($path, $bytes)) {
                    throw new RuntimeException("The staged media file could not be written: {$path}");
                }

                $stored = $disk->get($path);
                if (!hash_equals(hash('sha256', $bytes), hash('sha256', $stored))) {
                    throw new RuntimeException("The staged media file failed integrity verification: {$path}");
                }
                $this->verifyImageBytes($stored);
            }
        } catch (Throwable $exception) {
            $this->deletePaths($diskName, $attempted);
            throw $exception;
        }

        return new StagedMediaAsset($diskName, $databaseValue, array_keys($imagesByPath));
    }

    /** @return array{string, string, int, int} */
    private function readImage(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new RuntimeException('The uploaded image is not valid.');
        }

        $path = $file->getRealPath();
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('The uploaded image does not have a readable temporary path.');
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        [$mime, $width, $height] = $this->verifyImageBytes($bytes);

        return [$bytes, $mime, $width, $height];
    }

    /** @return array{string, int, int} */
    private function verifyImageBytes(string $bytes): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $dimensions = @getimagesizefromstring($bytes);
        $allowed = $this->allowedImageTypes();

        if (!$dimensions
            || !isset($allowed[$mime])
            || (int) $dimensions[2] !== $allowed[$mime]) {
            throw new RuntimeException('The staged media is not a supported raster image.');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        $this->assertDimensions($width, $height);

        return [$mime, $width, $height];
    }

    private function resizeAndEncode(string $source, string $extension, int $width, int $height): string
    {
        $this->assertDimensions($width, $height);
        $image = Image::make($source)->resize($width, $height);
        $encoded = (string) $image->encode($extension, 75);
        [$mime] = $this->verifyImageBytes($encoded);
        if ($this->extensionFor($mime) !== $extension) {
            throw new RuntimeException('The resized image format does not match its generated filename.');
        }

        return $encoded;
    }

    /** @return array<string, int> */
    private function allowedImageTypes(): array
    {
        return [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        ];
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('The image format is not supported.'),
        };
    }

    private function assertDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1
            || $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
            || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('The image dimensions exceed the safe processing limit.');
        }
    }

    private function assertFlatCollection(string $collection): void
    {
        if (!in_array($collection, self::FLAT_COLLECTIONS, true)) {
            throw new RuntimeException('The media collection is not supported.');
        }
    }

    private function isSafeStoredName(mixed $name): bool
    {
        return is_string($name)
            && $name !== ''
            && $name === basename($name)
            && !str_contains($name, '\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $name);
    }

    /** @param list<string> $paths */
    private function deletePaths(string $diskName, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            Storage::disk($diskName)->delete(array_values(array_unique($paths)));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
