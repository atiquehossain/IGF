<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mattiverse\Userstamps\Traits\Userstamps;

class Division extends Model
{
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'description_bn',
        'status'
    ];

    protected static function booted(): void
    {
        static::creating(function (Division $division): void {
            if (blank($division->slug)) {
                $division->slug = Str::slug((string) $division->name);
            }
        });
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
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
