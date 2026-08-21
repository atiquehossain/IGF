<?php

namespace App\Models;

use App\Casts\EncryptedLegacyCompatible;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Passport;

class User extends Authenticatable {

    use HasApiTokens,
        Notifiable; //HasFactory

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'phone_no',
        'email',
        'org',
        'designation',
        'is_approved',
        'verify_code',
        'gender',
        'dob',
        'address',
        'nationalid',
        'study_type',
        'institute_name',
        'division_id',
        'district_id',
        'upazila_id',
        'post_code',
        'avatar',
        'device_id',
        'status',
        'rating',
        'points',
        'status',
        'password',
        'google2fa_secret',
        'provider_type',
        'social_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'google2fa_secret' => EncryptedLegacyCompatible::class,
    ];

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            $becameInactive = $user->wasChanged('status') && !(bool) $user->status;
            $lostApproval = $user->wasChanged('is_approved') && (int) $user->is_approved !== 1;

            if ($becameInactive || $lostApproval) {
                $user->revokeAuthenticationArtifacts();
            }
        });
    }

    public function revokeAuthenticationArtifacts(): void
    {
        $tokenIds = $this->tokens()->pluck('oauth_access_tokens.id');

        if ($tokenIds->isNotEmpty()) {
            Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', $tokenIds)
                ->update(['revoked' => true]);
            Passport::token()->newQuery()
                ->whereIn('id', $tokenIds)
                ->update(['revoked' => true]);
        }

        $this->setRememberToken(Str::random(60));
        $this->saveQuietly();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->google2fa_secret);
    }

    public function encryptLegacyTwoFactorSecretIfNeeded(): void
    {
        $rawSecret = $this->getRawOriginal('google2fa_secret');

        if ($rawSecret === null
            || $rawSecret === ''
            || EncryptedLegacyCompatible::isEncryptedValue($rawSecret)) {
            return;
        }

        $this->google2fa_secret = $this->google2fa_secret;
        $this->saveQuietly();
    }

    public function isAuthenticationEligible(): bool
    {
        return (bool) $this->status && (int) $this->is_approved === 1;
    }

    public function avatarUrl(): ?string
    {
        $avatar = (string) $this->avatar;
        if ($avatar === '') {
            return null;
        }
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        if (!preg_match('#\A(\d+)/(350X350)/([a-f0-9]{48}\.(?:jpg|png|webp))\z#i', $avatar, $parts)
            || (int) $parts[1] !== (int) $this->getKey()) {
            return null;
        }

        return route('api.avatar', [
            'id' => $parts[1],
            'size' => $parts[2],
            'img' => $parts[3],
        ]);
    }

}
