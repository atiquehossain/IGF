<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class PublicFormTokenService
{
    private const MAX_AGE_SECONDS = 14_400;
    private const MIN_AGE_SECONDS = 1;

    public function issue(string $kind, string $listingUuid): string
    {
        $payload = $this->encode(json_encode([
            'kind' => $kind,
            'listing' => $listingUuid,
            'issued_at' => now()->timestamp,
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->signature($payload);
    }

    public function assertValid(?string $token, string $kind, string $listingUuid, ?string $honeypot): void
    {
        if (trim((string) $honeypot) !== '') {
            $this->reject();
        }

        $parts = explode('.', (string) $token, 2);
        if (count($parts) !== 2 || !hash_equals($this->signature($parts[0]), $parts[1])) {
            $this->reject();
        }

        $decoded = $this->decode($parts[0]);
        $payload = $decoded === null ? null : json_decode($decoded, true);
        $age = now()->timestamp - (int) ($payload['issued_at'] ?? 0);
        if (!is_array($payload)
            || !hash_equals($kind, (string) ($payload['kind'] ?? ''))
            || !hash_equals($listingUuid, (string) ($payload['listing'] ?? ''))
            || $age < self::MIN_AGE_SECONDS
            || $age > self::MAX_AGE_SECONDS) {
            $this->reject();
        }
    }

    private function signature(string $payload): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \RuntimeException('APP_KEY is required to protect public form tokens.');
        }

        return hash_hmac('sha256', $payload, $key);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) {
            return null;
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'submission' => 'This form session is no longer valid. Refresh the page and try again.',
        ]);
    }
}
