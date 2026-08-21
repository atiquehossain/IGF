<?php

namespace App\Services;

use Illuminate\Http\Request;

final class TechnicalSeoPathNormalizer
{
    public const REDACTED_SEGMENT = '[redacted]';

    public function normalize(string $path): string
    {
        $parts = parse_url($path);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '/';
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/[\x00-\x1F\x7F]/u', '', $path) ?? '';
        $path = preg_replace('#/+#', '/', '/' . ltrim($path, '/')) ?? '/';

        $segments = array_slice(explode('/', trim($path, '/')), 0, 24);
        $safe = [];
        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            $decoded = preg_replace('/[\x00-\x1F\x7F]/u', '', $decoded) ?? '';
            if ($decoded === '' || $decoded === '.') {
                continue;
            }
            if ($decoded === '..') {
                array_pop($safe);
                continue;
            }
            $safe[] = $this->looksSensitive($decoded)
                ? self::REDACTED_SEGMENT
                : mb_substr($decoded, 0, 120);
        }

        $normalized = '/' . implode('/', $safe);
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return mb_substr($normalized ?: '/', 0, 1024);
    }

    public function sameOriginReferrer(Request $request): ?string
    {
        $raw = trim((string) $request->headers->get('referer', ''));
        if ($raw === '' || strlen($raw) > 4096) {
            return null;
        }

        $parts = parse_url($raw);
        if (!is_array($parts) || isset($parts['user'], $parts['pass'])) {
            return null;
        }

        if (isset($parts['host'])) {
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower(rtrim((string) $parts['host'], '.'));
            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            $requestPort = (int) $request->getPort();
            if (!in_array($scheme, ['http', 'https'], true)
                || !hash_equals(strtolower(rtrim($request->getHost(), '.')), $host)
                || $requestPort !== $port) {
                return null;
            }
        } elseif (!str_starts_with($raw, '/')) {
            return null;
        }

        return $this->normalize((string) ($parts['path'] ?? '/'));
    }

    public function containsRedaction(string $path): bool
    {
        return str_contains($path, self::REDACTED_SEGMENT);
    }

    private function looksSensitive(string $segment): bool
    {
        if (filter_var($segment, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        if (preg_match('/^[+() .-]*\d(?:[+() .-]*\d){7,}[+() .-]*$/', $segment)) {
            return true;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $segment)) {
            return true;
        }
        if (substr_count($segment, '.') === 2 && strlen($segment) >= 32) {
            return true;
        }

        return strlen($segment) >= 32
            && preg_match('/[A-Za-z]/', $segment)
            && preg_match('/\d/', $segment)
            && !preg_match('/^[\pL\pN]+(?:-[\pL\pN]+){2,}$/u', $segment);
    }
}
