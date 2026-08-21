<?php

namespace App\Helper;

use Illuminate\Support\Facades\File;

final class IgfFile
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    private const MAX_IMAGE_DIMENSION = 8192;
    private const MAX_IMAGE_PIXELS = 30_000_000;

    public static function image($storagePath = null)
    {
        $relative = self::normalizedRelativePath($storagePath);
        abort_if($relative === null, 404);

        $path = self::containedExistingPath($relative);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return self::fallbackImage();
        }

        $size = filesize($path);
        if (!is_int($size) || $size < 1 || $size > self::MAX_IMAGE_BYTES) {
            return self::fallbackImage();
        }

        $bytes = File::get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $dimensions = @getimagesizefromstring($bytes);
        $allowed = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
            'image/gif' => IMAGETYPE_GIF,
        ];

        if (!$dimensions
            || !isset($allowed[$mime])
            || (int) $dimensions[2] !== $allowed[$mime]
            || (int) $dimensions[0] > self::MAX_IMAGE_DIMENSION
            || (int) $dimensions[1] > self::MAX_IMAGE_DIMENSION
            || ((int) $dimensions[0] * (int) $dimensions[1]) > self::MAX_IMAGE_PIXELS) {
            return self::fallbackImage();
        }

        return self::imageResponse($bytes, $mime);
    }

    public static function remove($storagePath = null, $isFullPath = false): void
    {
        $path = self::safeMutationPath($storagePath, (bool) $isFullPath);
        if ($path !== null && is_file($path)) {
            File::delete($path);
        }
    }

    public static function removeFolder($storagePath = null, $isFullPath = false): void
    {
        $path = self::safeMutationPath($storagePath, (bool) $isFullPath);
        $root = self::storageRoot();
        if ($path !== null && $root !== null && is_dir($path) && !self::samePath($path, $root)) {
            File::deleteDirectory($path);
        }
    }

    private static function fallbackImage()
    {
        $path = public_path('image/no-image.png');
        abort_unless(is_file($path) && is_readable($path), 404);
        $bytes = File::get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true), 404);

        return self::imageResponse($bytes, $mime);
    }

    private static function imageResponse(string $bytes, string $mime)
    {
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="image"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function safeMutationPath(mixed $storagePath, bool $isFullPath): ?string
    {
        if ($isFullPath) {
            $candidate = realpath((string) $storagePath);
            $root = self::storageRoot();

            return is_string($candidate) && $root !== null && self::isWithinRoot($candidate, $root)
                ? $candidate
                : null;
        }

        $relative = self::normalizedRelativePath($storagePath);

        return $relative === null ? null : self::containedExistingPath($relative);
    }

    private static function normalizedRelativePath(mixed $storagePath): ?string
    {
        $path = trim((string) $storagePath, " \t\n\r\0\x0B/");
        if ($path === '' || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/u', $path)) {
            return null;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, ':')) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    private static function containedExistingPath(string $relative): ?string
    {
        $root = self::storageRoot();
        if ($root === null) {
            return null;
        }

        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);

        return is_string($resolved) && self::isWithinRoot($resolved, $root) ? $resolved : null;
    }

    private static function storageRoot(): ?string
    {
        $root = realpath(storage_path('app/public/photos/1'));

        return is_string($root) ? $root : null;
    }

    private static function isWithinRoot(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', $candidate);
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }

        return str_starts_with($candidate, $root);
    }

    private static function samePath(string $first, string $second): bool
    {
        $first = rtrim(str_replace('\\', '/', $first), '/');
        $second = rtrim(str_replace('\\', '/', $second), '/');

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($first, $second) === 0
            : $first === $second;
    }
}
