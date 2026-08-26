<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PrivateFileCleanupJob extends Model
{
    public const ERROR_DELETE_FAILED = 'delete_failed';

    protected $fillable = [
        'disk',
        'path',
        'attempts',
        'locked_at',
        'last_failed_at',
        'last_error_code',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'locked_at' => 'immutable_datetime',
        'last_failed_at' => 'immutable_datetime',
    ];
}
