<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'visitor_token_hash',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'locale',
        'status',
        'page_url',
        'assigned_admin_id',
        'last_message_at',
        'admin_read_at',
        'closed_at',
    ];

    protected $hidden = [
        'visitor_token_hash',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'admin_read_at' => 'datetime',
        'closed_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            $conversation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeRestorable(Builder $query): Builder
    {
        return $query->where('status', '<>', 'closed');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }
}
