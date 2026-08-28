<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;

class AnnualReport extends Model
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
        'language',
        'file_type',
        'file_size',
        'url',
        'location',
        'image_path',
        'cover_image_path',
        'file_path',
        'publisher_name',
        'published_at',
        'ip',
        'order_by',
        'status'
    ];

    protected static function booted(): void
    {
        static::creating(function (AnnualReport $report): void {
            if (blank($report->translation_key)) {
                $report->translation_key = (string) Str::uuid();
            }
        });
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
