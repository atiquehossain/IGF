<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;

class District extends Model {

    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'slug',
        'division_id',
        'description',
        'description_bn',
        'hero_image',
        'hero_image_alt',
        'hero_image_alt_bn',
        'status'
    ];

    protected static function booted(): void
    {
        static::creating(function (District $district): void {
            if (blank($district->slug)) {
                $district->slug = Str::slug((string) $district->name);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(LatestNews::class)
            ->where('type', 'our-members');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(NoticeBoard::class);
    }

}
