<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class TwoFactorChallengeService
{
    private const TTL_SECONDS = 300;

    /**
     * Create an opaque, short-lived challenge. Passwords and other login
     * credentials must never be included in the cached payload.
     */
    public function create(User $user, ?string $pendingSecret = null): string
    {
        $token = Str::random(64);

        Cache::put($this->key($token), [
            'user_id' => $user->getKey(),
            'pending_secret' => $pendingSecret === null
                ? null
                : Crypt::encryptString($pendingSecret),
        ], now()->addSeconds(self::TTL_SECONDS));

        return $token;
    }

    /**
     * Consume a challenge exactly once, regardless of whether verification
     * succeeds. A failed code therefore requires a fresh password challenge.
     *
     * @return array{user_id: int, pending_secret: string|null}|null
     */
    public function consume(string $token): ?array
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $payload = Cache::lock($this->lockKey($token), 10)->get(
            fn () => Cache::pull($this->key($token))
        );

        if (!is_array($payload) || !isset($payload['user_id'])) {
            return null;
        }

        $pendingSecret = null;
        if (isset($payload['pending_secret'])) {
            try {
                $pendingSecret = Crypt::decryptString((string) $payload['pending_secret']);
            } catch (DecryptException) {
                // A malformed or pre-hardening challenge is consumed and rejected.
                return null;
            }
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'pending_secret' => $pendingSecret,
        ];
    }

    private function key(string $token): string
    {
        return 'auth:two-factor-challenge:' . hash('sha256', $token);
    }

    private function lockKey(string $token): string
    {
        return 'auth:two-factor-challenge-lock:' . hash('sha256', $token);
    }
}
