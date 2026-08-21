<?php

namespace App\Services;

use Illuminate\Http\Request;

final class AdminPrivateSearch
{
    private const SESSION_KEY = 'admin_private_listing_searches';
    private const TTL_MINUTES = 10;
    private const MAX_LENGTH = 100;

    public function current(Request $request, string $scope): string
    {
        $all = $request->session()->get(self::SESSION_KEY, []);
        $state = is_array($all) ? ($all[$scope] ?? null) : null;

        if (!is_array($state)
            || (int) ($state['admin_id'] ?? 0) !== (int) auth('admin')->id()
            || (int) ($state['expires_at'] ?? 0) <= now()->timestamp) {
            $this->forget($request, $scope);

            return '';
        }

        return $this->normalize((string) ($state['value'] ?? ''));
    }

    public function store(Request $request, string $scope, string $value): string
    {
        $normalized = $this->normalize($value);
        $all = $request->session()->get(self::SESSION_KEY, []);
        $all = is_array($all) ? $all : [];
        $all[$scope] = [
            'admin_id' => (int) auth('admin')->id(),
            'value' => $normalized,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ];
        $request->session()->put(self::SESSION_KEY, $all);

        return $normalized;
    }

    public function forget(Request $request, string $scope): void
    {
        $all = $request->session()->get(self::SESSION_KEY, []);
        if (!is_array($all) || !array_key_exists($scope, $all)) {
            return;
        }

        unset($all[$scope]);
        if ($all === []) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $all);
        }
    }

    public function normalize(string $value): string
    {
        $value = mb_substr(trim($value), 0, self::MAX_LENGTH);
        // SQL wildcard characters are not useful to staff and make an
        // accidentally broad PII search too easy. Control bytes are removed
        // before this value ever reaches a query or a server-side session.
        $value = preg_replace('/[%_\\\\\x00-\x1F\x7F]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
