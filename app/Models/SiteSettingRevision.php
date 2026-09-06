<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SiteSettingRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'locale',
        'snapshot',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function changedBy()
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Website-setting revisions are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Website-setting revisions are append-only.');
        });
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Website-setting revisions are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Website-setting revisions are append-only.');
    }
}
