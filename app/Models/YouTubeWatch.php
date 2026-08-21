<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class YouTubeWatch extends Model
{
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'video_id',
        'duration_time',
        'user_id',
        'ip',
        'order_by',
        'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'duration_time' => 'float',
        'status' => 'boolean',
    ];
}
