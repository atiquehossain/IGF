<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class LatestNews extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'category_id',
        'type',
        'description',
        'biography',
        'qualification',
        'image',
        'path',
        'url',
        'social_links',
        'email',
        'language',
        'order_by',
        'status'
    ];

    protected $casts = [
        'social_links' => 'array',
        'order_by' => 'integer',
        'status' => 'integer',
    ];

    protected $hidden = [
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

}
