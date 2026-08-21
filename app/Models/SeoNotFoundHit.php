<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoNotFoundHit extends Model
{
    protected $fillable = [
        'scope_hash',
        'path_hash',
        'path',
        'locale',
        'referrer_path',
        'hits',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'redirect_id',
    ];

    protected $casts = [
        'hits' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
