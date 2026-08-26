<?php

namespace App\Models;

use App\Models\Concerns\GuardsImmutableFormVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationFormField extends Model
{
    use GuardsImmutableFormVersion, HasFactory;

    public const TYPE_SHORT_TEXT = 'short_text';
    public const TYPE_LONG_TEXT = 'long_text';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOXES = 'checkboxes';
    public const TYPE_YES_NO = 'yes_no';
    public const TYPE_FILE = 'file';
    public const TYPES = [
        self::TYPE_SHORT_TEXT, self::TYPE_LONG_TEXT, self::TYPE_EMAIL,
        self::TYPE_PHONE, self::TYPE_NUMBER, self::TYPE_DATE,
        self::TYPE_DROPDOWN, self::TYPE_RADIO, self::TYPE_CHECKBOXES,
        self::TYPE_YES_NO, self::TYPE_FILE,
    ];

    public const SYSTEM_FULL_NAME = 'full_name';
    public const SYSTEM_EMAIL = 'email';
    public const SYSTEM_PHONE = 'phone';
    public const SYSTEM_CV = 'cv';

    protected $fillable = [
        'application_form_version_id', 'field_key', 'system_key', 'type',
        'position', 'is_required', 'validation',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_required' => 'boolean',
        'validation' => 'array',
    ];

    protected function guardedFormVersionId(): ?int
    {
        return $this->application_form_version_id ? (int) $this->application_form_version_id : null;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'application_form_version_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ApplicationFormFieldTranslation::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ApplicationFormOption::class)->orderBy('position');
    }

    public function visibilityConditions(): HasMany
    {
        return $this->hasMany(ApplicationFormCondition::class, 'target_field_id')->orderBy('position');
    }

    public function dependentConditions(): HasMany
    {
        return $this->hasMany(ApplicationFormCondition::class, 'source_field_id');
    }
}
