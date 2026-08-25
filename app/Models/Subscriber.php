<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'email',
        'confirmed_at',
        'confirmation_sent_at',
        'language',
    ];

    protected $casts = [
        'confirmed_at' => 'immutable_datetime',
        'confirmation_sent_at' => 'immutable_datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }
}
