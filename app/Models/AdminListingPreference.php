<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminListingPreference extends Model
{
    public const SORT_ASC = 'asc';
    public const SORT_DESC = 'desc';
    public const SORT_DIRECTIONS = [self::SORT_ASC, self::SORT_DESC];

    protected $fillable = [
        'admin_id', 'listing_key', 'visible_columns', 'sort_column', 'sort_direction',
    ];

    protected $casts = ['visible_columns' => 'array'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
