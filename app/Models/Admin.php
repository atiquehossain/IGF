<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const SESSION_AUTH_VERSION = 'admin_auth_version';

    protected $guard = 'admins';

    protected $attributes = [
        'auth_version' => 0,
    ];

    protected $fillable = [
        'name',
        'username',
        'address',
        'mobile',
        'role',
        'email',
        'image',
        'status',
        'password',
        'must_change_password',
        'password_changed_at',
    ];

    protected $casts = [
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'auth_version' => 'integer',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role');
    }

    public function isOwner(): bool
    {
        return (bool) ($this->roleModel?->is_owner
            ?? Role::query()->whereKey($this->role)->value('is_owner'));
    }
}
