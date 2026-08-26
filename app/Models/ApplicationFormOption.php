<?php

namespace App\Models;

use App\Models\Concerns\GuardsImmutableFormVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationFormOption extends Model
{
    use GuardsImmutableFormVersion;

    protected $fillable = ['application_form_field_id', 'option_key', 'position'];

    protected $casts = ['position' => 'integer'];

    protected function guardedFormVersionId(): ?int
    {
        return $this->field()->value('application_form_version_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'application_form_field_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ApplicationFormOptionTranslation::class);
    }
}
