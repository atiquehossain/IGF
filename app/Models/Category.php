<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class Category extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_id',
        'type',
        'display_mode',
        'landing_page_uuid',
        'image',
        'path',
        'inline_css',
        'name_enabled',
        'status',
        'uuid',
        'language',
        'meta_title',
        'meta_keyword',
        'meta_description'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

    public function page()
    {
        return $this->hasMany(Page::class)->publiclyAvailable()->orderBy('order_by', 'ASC');
    }

    public function banner() {
        return $this->belongsTo(Banner::class)->where('status', 1);
    }

    public function seo()
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')
            ->where('locale', $this->language ?: app()->getLocale());
    }

    public function ourTeams()
    {
        return $this->hasMany(LatestNews::class)
            ->where('status', 1)->orderBy('order_by', 'ASC');
    }
}
