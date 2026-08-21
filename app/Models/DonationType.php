<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;
use Illuminate\Support\Str;
use LogicException;

class DonationType extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    public const PURPOSE_OPTIONS = [
        '' => 'Regular donation cause',
        'zakat' => 'Use for the Zakat donation page',
    ];

    public const DESTINATION_OPTIONS = [
        'unrestricted' => 'Where it is needed most (unrestricted)',
        'restricted_fund' => 'A named restricted fund',
        'category' => 'A program or category',
        'page' => 'One specific project or page',
    ];

    protected $fillable = [
        'uuid',
        'slug',
        'purpose_key',
        'destination_type',
        'destination_name',
        'destination_category_uuid',
        'destination_page_uuid',
        'name',
        'description',
        'image',
        'image_media_uuid',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = static::availableSlug((string) $model->name);
            }
            if (empty($model->destination_type)) {
                $generalNames = ['where it is needed most', 'general', 'general donation', 'general fund', 'unrestricted'];
                $model->destination_type = in_array(mb_strtolower(trim((string) $model->name)), $generalNames, true)
                    && $model->purpose_key !== 'zakat'
                    ? 'unrestricted'
                    : 'restricted_fund';
            }
            if ($model->destination_type === 'restricted_fund' && empty($model->destination_name)) {
                $model->destination_name = $model->purpose_key === 'zakat'
                    ? 'Zakat Fund'
                    : (string) $model->name;
            }
        });

        static::updating(function (DonationType $model): void {
            if ($model->isDirty('slug')) {
                throw new LogicException('A donation cause slug is a stable public identifier and cannot be changed.');
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function imageAsset()
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_uuid', 'uuid')->withTrashed();
    }

    private static function availableSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'donation-cause';
        $candidate = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
        }

        return $candidate;
    }
}
