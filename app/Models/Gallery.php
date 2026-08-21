<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class Gallery extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'type',
        'description',
        'image',
        'path',
        'language',
        'url',
        'uuid',
        'order_by',
        'album_id',
        'grid_column',
        'grid_row',
        'status'
    ];
}
