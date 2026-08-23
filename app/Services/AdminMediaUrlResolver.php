<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class AdminMediaUrlResolver
{
    private const IMAGE_EXTENSIONS = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'];

    private const LEGACY_COLLECTIONS = [
        'banner',
        'category',
        'notice_board',
        'our_members',
        'page',
        'testimonial',
    ];

    public function image(
        mixed $value,
        ?string $legacyCollection = null,
        ?int $recordId = null,
        ?string $variant = null,
    ): string {
        $value = trim((string) $value);
        if ($value === '') {
            return $this->fallback();
        }

        if ($this->isSafeRemoteUrl($value)) {
            return $value;
        }

        $relative = $this->storageRelativePath($value);
        if ($relative !== null) {
            return $this->existingPublicUrl($relative) ?? $this->fallback();
        }

        $name = $this->safeImageName($value);
        if ($name === null) {
            return $this->fallback();
        }

        foreach ($this->legacyCandidates($name, $legacyCollection, $recordId, $variant) as $candidate) {
            $url = $this->existingPublicUrl($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return $this->fallback();
    }

    public function fallback(): string
    {
        return asset('image/no-image.png');
    }

    private function isSafeRemoteUrl(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function storageRelativePath(string $value): ?string
    {
        if (str_contains($value, "\\") || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
        $normalized = '/' . ltrim($path, '/');

        // Prefer the modern media marker even when a legacy prefix was
        // accidentally prepended (for example /storage/photos/.../storage/media/...).
        $marker = stripos($normalized, '/storage/media/');
        if ($marker !== false) {
            $relative = substr($normalized, $marker + strlen('/storage/'));

            return $this->safeStorageImagePath($relative);
        }

        $marker = stripos($normalized, '/storage/photos/');
        if ($marker === false) {
            return null;
        }

        $relative = substr($normalized, $marker + strlen('/storage/'));

        return $this->safeStorageImagePath($relative);
    }

    private function safeStorageImagePath(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $segments = explode('/', $relative);
        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '' || $decoded === '.' || $decoded === '..') {
                return null;
            }
        }

        return $this->hasImageExtension($relative) ? $relative : null;
    }

    private function safeImageName(string $value): ?string
    {
        if ($value !== basename($value) || str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        return $this->hasImageExtension($value) ? $value : null;
    }

    /** @return list<string> */
    private function legacyCandidates(
        string $name,
        ?string $collection,
        ?int $recordId,
        ?string $variant,
    ): array {
        $candidates = [];

        if ($collection === 'gallery' && $recordId !== null && $recordId > 0) {
            $safeVariant = in_array($variant, ['430X360', 'main'], true) ? $variant : '430X360';
            $candidates[] = "photos/1/gallery/{$recordId}/{$safeVariant}/{$name}";
        } elseif (in_array($collection, self::LEGACY_COLLECTIONS, true)) {
            $candidates[] = "photos/1/{$collection}/{$name}";
        }

        // Imported legacy records retain only their basename while the actual
        // file is stored in the migration's modern media directory.
        $candidates[] = "media/ignite-live/{$name}";
        if (in_array($collection, ['testimonial'], true)) {
            $candidates[] = "media/ignite-live/{$collection}s/{$name}";
        }

        return array_values(array_unique($candidates));
    }

    private function existingPublicUrl(string $relative): ?string
    {
        try {
            $disk = Storage::disk('public');

            return $disk->exists($relative) ? $disk->url($relative) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function hasImageExtension(string $path): bool
    {
        return in_array(strtolower((string) pathinfo(rawurldecode($path), PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }
}
