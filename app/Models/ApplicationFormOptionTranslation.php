<?php

namespace App\Models;

use App\Models\Concerns\GuardsImmutableFormVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFormOptionTranslation extends Model
{
    use GuardsImmutableFormVersion;

    protected $fillable = ['application_form_option_id', 'locale', 'label'];

    protected function guardedFormVersionId(): ?int
    {
        return $this->option()->with('field:id,application_form_version_id')->first()?->field?->application_form_version_id;
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormOption::class, 'application_form_option_id');
    }

    public function translationLocale(): BelongsTo
    {
        return $this->belongsTo(TranslationLocale::class, 'locale', 'locale');
    }
}
