<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class SplashScreen extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'title',
        'details',
        'language',
        'status',
        'published_at',
        'uuid'
    ];

    protected $hidden = [
        'created_by', 'updated_by',
    ];

    protected $appends = [
        'last_updated_date'
    ];

    protected $casts = [
        'created_at' => 'date:Y/m/d',
        'updated_at' => 'date:Y/m/d',
        'published_at' => 'date:Y-m-d',
        'status' => 'boolean',
    ];

    public function getLastUpdatedDateAttribute(): ?string
    {
        return $this->published_at?->format('F d Y');
    }

    /**
     * Keep the cross-locale UUID stable for translation pairing while making
     * every public content edit a distinct dismissible announcement version.
     */
    public function publicVersion(): string
    {
        return hash('sha256', implode("\0", [
            (string) $this->getRawOriginal('uuid'),
            (string) $this->getRawOriginal('title'),
            (string) $this->getRawOriginal('details'),
            (string) $this->getRawOriginal('published_at'),
            (string) $this->getRawOriginal('updated_at'),
        ]));
    }
}
