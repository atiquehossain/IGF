<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Throwable;

final class MemberCredentialVerifier
{
    public const FAILURE_MESSAGE = 'The supplied credentials are invalid.';

    /**
     * A fixed bcrypt hash used only to equalize password work when no local
     * identity exists. It is not associated with any account or credential.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$c.VWNqeDCKHLvmJ34Ht9juRtP2ZWB7WTjCXPgXcNBWdEFaGgHVSEG';

    public function passes(?User $user, string $password): bool
    {
        $hash = is_string($user?->password) && $user->password !== ''
            ? $user->password
            : self::DUMMY_PASSWORD_HASH;

        try {
            $passwordMatches = Hash::check($password, $hash);
        } catch (Throwable) {
            Hash::check($password, self::DUMMY_PASSWORD_HASH);

            return false;
        }

        return $user !== null
            && $passwordMatches
            && $user->isAuthenticationEligible();
    }
}
