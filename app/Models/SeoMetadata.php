<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoMetadata extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'route_name',
        'route_path',
        'locale',
        'title',
        'description',
        'focus_keyword',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'schema_markup',
        'sitemap_priority',
        'sitemap_change_frequency',
        'exclude_from_sitemap',
        'review_status',
        'review_note',
        'review_content_hash',
        'review_requested_by',
        'review_requested_at',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'editor_version' => 'integer',
        'review_request_version' => 'integer',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'exclude_from_sitemap' => 'boolean',
        'sitemap_priority' => 'float',
        'schema_markup' => 'array',
        'review_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function seoable()
    {
        return $this->morphTo();
    }

    public function reviewRequestedBy()
    {
        return $this->belongsTo(Admin::class, 'review_requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function toMetaArray(array $fallback = []): array
    {
        $title = $this->title ?: ($fallback['meta_title'] ?? '');
        $description = $this->description ?: ($fallback['meta_description'] ?? '');
        $image = $this->og_image ?: $this->twitter_image ?: ($fallback['meta_image'] ?? '');

        return [
            'meta_title' => $title,
            'meta_description' => $description,
            'meta_keyword' => $this->focus_keyword ?: ($fallback['meta_keyword'] ?? ''),
            'canonical_url' => $this->canonical_url,
            'robots' => ($this->robots_index ? 'index' : 'noindex') . ',' . ($this->robots_follow ? 'follow' : 'nofollow'),
            'og_title' => $this->og_title ?: $title,
            'og_description' => $this->og_description ?: $description,
            'og_image' => $image,
            'twitter_card' => $this->twitter_card ?: 'summary_large_image',
            'twitter_title' => $this->twitter_title ?: $this->og_title ?: $title,
            'twitter_description' => $this->twitter_description ?: $this->og_description ?: $description,
            'twitter_image' => $this->twitter_image ?: $image,
            'schema_markup' => $this->schema_markup,
        ];
    }
}
