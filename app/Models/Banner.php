<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;

class Banner extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'eyebrow',
        'headline',
        'subheadline',
        'type',
        'description',
        'image',
        'path',
        'image_alt',
        'language',
        'url',
        'cta_label',
        'cta_url',
        'uuid',
        'order_by',
        'album_id',
        'grid_column',
        'grid_row',
        'status'
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        $source = trim((string) ($this->attributes['path'] ?? $this->attributes['image'] ?? ''));

        if ($source === '') {
            return null;
        }

        if (Str::startsWith($source, ['https://', 'http://', '//', '/'])) {
            return $source;
        }

        if (Str::startsWith($source, ['storage/', 'image/'])) {
            return '/' . $source;
        }

        return '/storage/photos/1/banner/' . rawurlencode(basename(str_replace('\\', '/', $source)));
    }
}
