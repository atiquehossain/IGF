<?php

namespace App\Models\Concerns;

use LogicException;

trait AppendOnlyRecord
{
    public static function bootAppendOnlyRecord(): void
    {
        static::updating(function (): never {
            throw new LogicException('This history record is append-only.');
        });
        static::deleting(function (): never {
            throw new LogicException('This history record is append-only.');
        });
    }
}
