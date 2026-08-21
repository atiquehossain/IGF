<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatFaq extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'locale',
        'question',
        'answer',
        'sort_order',
        'is_active',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'click_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $faq): void {
            $faq->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
