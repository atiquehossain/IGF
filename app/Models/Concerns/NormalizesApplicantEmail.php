<?php

namespace App\Models\Concerns;

use App\Support\ApplicationIdentity;
use Illuminate\Database\Eloquent\Model;

trait NormalizesApplicantEmail
{
    public static function bootNormalizesApplicantEmail(): void
    {
        static::saving(function (Model $model): void {
            if (!$model->isDirty('email')) {
                return;
            }

            $email = ApplicationIdentity::normalizeEmail((string) $model->getAttribute('email'));
            $model->setAttribute('email', $email);
            $model->setAttribute('email_hash', ApplicationIdentity::emailHash($email));
        });
    }
}
