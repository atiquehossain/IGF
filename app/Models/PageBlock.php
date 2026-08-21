<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'page_id',
        'reusable_block_id',
        'uuid',
        'translation_key',
        'type',
        'label',
        'content',
        'settings',
        'sort_order',
        'is_enabled',
        'show_on_desktop',
        'show_on_mobile',
        'available_from',
        'available_until',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'is_enabled' => 'boolean',
        'show_on_desktop' => 'boolean',
        'show_on_mobile' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function reusableBlock()
    {
        return $this->belongsTo(ReusableBlock::class);
    }

    public function resolvedContent(): array
    {
        return $this->reusableBlock?->is_enabled
            ? ($this->reusableBlock->content ?? [])
            : ($this->content ?? []);
    }

    public function resolvedSettings(): array
    {
        $settings = $this->reusableBlock?->is_enabled
            ? ($this->reusableBlock->settings ?? [])
            : ($this->settings ?? []);

        return array_filter(
            $settings,
            static fn (mixed $key): bool => ! str_starts_with((string) $key, '_migration_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function resolvedLabel(): string
    {
        return (string) ($this->reusableBlock?->name ?: $this->label);
    }

    public function scopeVisible($query)
    {
        return $query
            ->where('is_enabled', true)
            ->where(function ($builder) {
                $builder->whereNull('available_from')->orWhere('available_from', '<=', now());
            })
            ->where(function ($builder) {
                $builder->whereNull('available_until')->orWhere('available_until', '>=', now());
            });
    }
}
