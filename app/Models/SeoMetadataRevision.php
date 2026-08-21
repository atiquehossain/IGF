<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMetadataRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'seo_metadata_id',
        'seoable_type',
        'seoable_id',
        'route_name',
        'locale',
        'snapshot',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function metadata()
    {
        return $this->belongsTo(SeoMetadata::class, 'seo_metadata_id')->withTrashed();
    }

    public function changedBy()
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }
}
