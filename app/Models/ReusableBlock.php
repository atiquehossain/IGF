<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReusableBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'locale',
        'content',
        'settings',
        'is_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'is_enabled' => 'boolean',
        'editor_version' => 'integer',
    ];

    public function instances()
    {
        return $this->hasMany(PageBlock::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
