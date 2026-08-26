<?php

namespace App\Models;

use App\Models\Concerns\GuardsImmutableFormVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFormFieldTranslation extends Model
{
    use GuardsImmutableFormVersion;

    protected $fillable = ['application_form_field_id', 'locale', 'label', 'help_text', 'placeholder'];

    protected function guardedFormVersionId(): ?int
    {
        return $this->field()->value('application_form_version_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'application_form_field_id');
    }

    public function translationLocale(): BelongsTo
    {
        return $this->belongsTo(TranslationLocale::class, 'locale', 'locale');
    }
}
