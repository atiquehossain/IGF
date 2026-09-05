<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageBlock extends Model
{
    use HasFactory, SoftDeletes;

    public const UPDATE_ITEM_KINDS = ['event', 'news'];

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
        $content = $this->reusableBlock?->is_enabled
            ? ($this->reusableBlock->content ?? [])
            : ($this->content ?? []);

        return $this->type === 'cards'
            ? self::normalizeUpdatesContent($content)
            : $content;
    }

    /**
     * Give the legacy two-column updates block a language-neutral discriminator.
     * Old records predate `kind`; their original column order is retained until
     * an editor saves an explicit event/news choice.
     */
    public static function normalizeUpdatesContent(array $content): array
    {
        if (($content['variant'] ?? null) !== 'updates' || !is_array($content['items'] ?? null)) {
            return $content;
        }

        $items = array_values($content['items']);
        $total = count($items);

        $content['items'] = array_map(
            static function (mixed $item, int $index) use ($total): mixed {
                if (!is_array($item)) {
                    return $item;
                }

                $item['kind'] = self::normalizeUpdateItemKind($item, $index, $total);

                return $item;
            },
            $items,
            array_keys($items),
        );

        return $content;
    }

    public static function normalizeUpdateItemKind(array $item, int $index, int $total): string
    {
        $kind = strtolower(trim((string) ($item['kind'] ?? '')));
        if (in_array($kind, self::UPDATE_ITEM_KINDS, true)) {
            return $kind;
        }

        $contentKind = strtolower(trim((string) ($item['content_kind'] ?? '')));
        if ($contentKind === 'event' || filled($item['event_start_at'] ?? null)) {
            return 'event';
        }
        if (in_array($contentKind, ['article', 'news'], true)) {
            return 'news';
        }

        return $index < (int) ceil(max(1, $total) / 2) ? 'event' : 'news';
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
