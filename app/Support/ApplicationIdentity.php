<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class ApplicationIdentity
{
    public static function normalizeEmail(string $email): string
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '' || strlen($normalized) > 254 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid applicant email address is required.');
        }

        return $normalized;
    }

    public static function emailHash(string $email): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \RuntimeException('APP_KEY is required to protect applicant lookup hashes.');
        }

        return hash_hmac('sha256', self::normalizeEmail($email), $key);
    }

    public static function reference(string $kind, ?\DateTimeInterface $date = null): string
    {
        $prefix = match ($kind) {
            'job' => 'IGF-JOB',
            'workshop' => 'IGF-WS',
            default => throw new InvalidArgumentException('Unsupported application reference kind.'),
        };

        return sprintf(
            '%s-%s-%s',
            $prefix,
            ($date ?: now())->format('Ymd'),
            Str::upper(Str::random(10))
        );
    }
}
