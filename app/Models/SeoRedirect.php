<?php

namespace App\Models;

use App\Services\SeoRedirectService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoRedirect extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'from_path',
        'to_url',
        'status_code',
        'is_active',
        'locale',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hits' => 'integer',
        'status_code' => 'integer',
        'last_hit_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SeoRedirect $redirect): void {
            // Deactivation must remain possible even for unsafe historical
            // rows. Every active write and every create receives full policy
            // enforcement.
            if ($redirect->exists && $redirect->isDirty('is_active') && !$redirect->is_active
                && !$redirect->isDirty(['from_path', 'to_url', 'status_code', 'locale'])) {
                return;
            }

            app(SeoRedirectService::class)->prepareForPersistence($redirect);
        });

        static::deleting(function (SeoRedirect $redirect): void {
            if ($redirect->isForceDeleting()) {
                return;
            }

            $actor = auth('admin')->id() ?? $redirect->deleted_by;
            $redirect->forceFill([
                'is_active' => false,
                'deleted_by' => $actor,
                'updated_by' => $actor ?? $redirect->updated_by,
            ])->saveQuietly();
        });

        static::restoring(function (SeoRedirect $redirect): void {
            // A restored rule never begins redirecting traffic until it passes
            // a separate activation check.
            $redirect->forceFill([
                'is_active' => false,
                'restored_by' => auth('admin')->id(),
                'restored_at' => now(),
            ]);
        });
    }
}
