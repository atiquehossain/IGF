<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mattiverse\Userstamps\Traits\Userstamps;

class TeamGroup extends Model
{
    use HasFactory, Userstamps;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'slug',
        'order_by',
        'status',
        'language',
    ];

    protected $casts = [
        'order_by' => 'integer',
        'status' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(LatestNews::class, 'team_group_id')
            ->where('type', 'our-members');
    }
}
