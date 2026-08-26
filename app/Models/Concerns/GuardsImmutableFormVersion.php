<?php

namespace App\Models\Concerns;

use App\Models\ApplicationFormVersion;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait GuardsImmutableFormVersion
{
    abstract protected function guardedFormVersionId(): ?int;

    public static function bootGuardsImmutableFormVersion(): void
    {
        $assertMutable = function (Model $model): void {
            $versionId = $model->guardedFormVersionId();
            if ($versionId !== null && !ApplicationFormVersion::query()->whereKey($versionId)->where('state', ApplicationFormVersion::STATE_DRAFT)->exists()) {
                throw new LogicException('Published and retired application form versions are immutable.');
            }
        };

        static::creating($assertMutable);
        static::updating($assertMutable);
        static::deleting($assertMutable);
    }
}
