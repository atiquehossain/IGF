<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;

class AdminAvatarService
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_DIMENSION = 4096;
    private const MAX_PIXELS = 12_000_000;
    private const OUTPUT_DIMENSION = 512;

    public function store(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['image' => 'The profile image must be a valid file no larger than 2 MB.']);
        }

        $bytes = file_get_contents($file->getRealPath());
        $mime = $bytes === false ? false : (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        $allowedTypes = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        ];

        if ($bytes === false
            || !$size
            || !isset($allowedTypes[$mime])
            || (int) $size[2] !== $allowedTypes[$mime]
            || (int) $size[0] > self::MAX_DIMENSION
            || (int) $size[1] > self::MAX_DIMENSION
            || ((int) $size[0] * (int) $size[1]) > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                'image' => 'Upload a decoded JPEG, PNG, or WebP image within the permitted dimensions.',
            ]);
        }

        $outputMime = $mime;
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        if (extension_loaded('gd') || extension_loaded('imagick')) {
            try {
                $image = Image::make($bytes);
                $image->resize(self::OUTPUT_DIMENSION, self::OUTPUT_DIMENSION, function ($constraint): void {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $normalized = (string) $image->encode('jpg', 85);
                $outputMime = 'image/jpeg';
                $extension = 'jpg';
            } catch (\Throwable) {
                throw ValidationException::withMessages(['image' => 'The profile image could not be safely decoded.']);
            }
        } else {
            // Some supported deployments do not expose GD/Imagick to PHP. In
            // that case retain the verified raster container but remove every
            // byte after its canonical end marker, including polyglot payloads.
            $normalized = $this->stripTrailingData($bytes, $mime);
        }

        $normalizedSize = @getimagesizefromstring($normalized);
        if (!$normalizedSize || (new \finfo(FILEINFO_MIME_TYPE))->buffer($normalized) !== $outputMime) {
            throw ValidationException::withMessages(['image' => 'The profile image could not be safely normalized.']);
        }

        $name = bin2hex(random_bytes(24)) . '.' . $extension;
        if (!Storage::disk('local')->put('uploads/admin/' . $name, $normalized)) {
            throw ValidationException::withMessages(['image' => 'The profile image could not be stored.']);
        }

        return $name;
    }

    public function delete(?string $name): void
    {
        if ($this->isSupportedStoredName($name)) {
            Storage::disk('local')->delete('uploads/admin/' . $name);
        }
    }

    public function isCanonicalName(?string $name): bool
    {
        return is_string($name) && preg_match('/\A[a-f0-9]{48}\.(?:jpg|png|webp)\z/', $name) === 1;
    }

    public function isSupportedStoredName(?string $name): bool
    {
        return $this->isCanonicalName($name)
            || (is_string($name) && preg_match('/\A[a-f0-9]{40}\.(?:jpe?g|png|webp)\z/i', $name) === 1);
    }

    /** @return array{bytes: string, mime: string} */
    public function read(string $name): array
    {
        if (!$this->isSupportedStoredName($name)) {
            abort(404);
        }

        $path = $this->path($name);
        if (!Storage::disk('local')->exists($path) || Storage::disk('local')->size($path) > self::MAX_BYTES) {
            abort(404);
        }

        $bytes = Storage::disk('local')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size = @getimagesizefromstring($bytes);
        $allowedTypes = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        ];

        if (!$size
            || !isset($allowedTypes[$mime])
            || (int) $size[2] !== $allowedTypes[$mime]
            || (int) $size[0] > self::MAX_DIMENSION
            || (int) $size[1] > self::MAX_DIMENSION
            || ((int) $size[0] * (int) $size[1]) > self::MAX_PIXELS) {
            abort(404);
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }

    public function path(string $name): string
    {
        return 'uploads/admin/' . $name;
    }

    private function stripTrailingData(string $bytes, string $mime): string
    {
        $end = match ($mime) {
            'image/jpeg' => (($position = strrpos($bytes, "\xFF\xD9")) === false ? null : $position + 2),
            'image/png' => (($position = strrpos($bytes, "\x00\x00\x00\x00IEND")) === false ? null : $position + 12),
            'image/webp' => strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP'
                ? 8 + unpack('Vlength', substr($bytes, 4, 4))['length']
                : null,
            default => null,
        };

        if ($end === null || $end > strlen($bytes)) {
            throw ValidationException::withMessages(['image' => 'The profile image container is malformed.']);
        }

        return substr($bytes, 0, $end);
    }
}
