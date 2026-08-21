<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AdminAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'context' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Administrator audit events are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Administrator audit events are append-only.');
        });
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Administrator audit events are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Administrator audit events are append-only.');
    }
}
