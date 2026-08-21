<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;

class NoticeBoard extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'translation_key',
        'title',
        'sub_title',
        'slug',
        'description',
        'inline_css',
        'notice_type',
        'content_kind',
        'language',
        'file_type',
        'file_size',
        'url',
        'location',
        'image_path',
        'file_path',
        'publisher_name',
        'published_at',
        'event_start_at',
        'event_end_at',
        'event_status',
        'event_attendance_mode',
        'ip',
        'order_by',
        'status'
    ];

    protected $casts = [
        'event_start_at' => 'datetime',
        'event_end_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (NoticeBoard $notice): void {
            if (blank($notice->translation_key)) {
                $notice->translation_key = (string) Str::uuid();
            }
        });
    }

    public function translations()
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key');
    }

    public function scopePubliclyReleased(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), 1)
            ->where(function (Builder $release): void {
                $release
                    ->whereNull($release->qualifyColumn('published_at'))
                    ->orWhere($release->qualifyColumn('published_at'), '<=', now());
            });
    }

    public function seo()
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')
            ->where('locale', $this->language ?: app()->getLocale());
    }
}
