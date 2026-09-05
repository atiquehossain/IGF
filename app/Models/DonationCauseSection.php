<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DonationCauseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'donation_type_id',
        'layout',
        'title',
        'body',
        'image_media_uuid',
        'image_alt',
        'video_media_uuid',
        'video_url',
        'video_title',
        'video_transcript',
        'cta_label',
        'cta_url',
        'display_order',
        'enabled',
    ];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'image_alt' => 'array',
        'video_title' => 'array',
        'video_transcript' => 'array',
        'cta_label' => 'array',
        'display_order' => 'integer',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (DonationCauseSection $section): void {
            $section->uuid ??= (string) Str::uuid();
        });
    }

    public function donationType(): BelongsTo
    {
        return $this->belongsTo(DonationType::class);
    }

    public function imageAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_uuid', 'uuid');
    }

    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'video_media_uuid', 'uuid');
    }
}
