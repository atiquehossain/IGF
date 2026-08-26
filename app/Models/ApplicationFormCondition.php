<?php

namespace App\Models;

use App\Models\Concerns\GuardsImmutableFormVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApplicationFormCondition extends Model
{
    use GuardsImmutableFormVersion;

    public const CONNECTOR_AND = 'and';
    public const CONNECTOR_OR = 'or';
    public const CONNECTORS = [self::CONNECTOR_AND, self::CONNECTOR_OR];

    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_NOT_EQUALS = 'not_equals';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_GREATER_THAN = 'greater_than';
    public const OPERATOR_GREATER_OR_EQUAL = 'greater_or_equal';
    public const OPERATOR_LESS_THAN = 'less_than';
    public const OPERATOR_LESS_OR_EQUAL = 'less_or_equal';
    public const OPERATOR_IS_EMPTY = 'is_empty';
    public const OPERATOR_IS_NOT_EMPTY = 'is_not_empty';
    public const OPERATORS = [
        self::OPERATOR_EQUALS,
        self::OPERATOR_NOT_EQUALS,
        self::OPERATOR_CONTAINS,
        self::OPERATOR_NOT_CONTAINS,
        self::OPERATOR_GREATER_THAN,
        self::OPERATOR_GREATER_OR_EQUAL,
        self::OPERATOR_LESS_THAN,
        self::OPERATOR_LESS_OR_EQUAL,
        self::OPERATOR_IS_EMPTY,
        self::OPERATOR_IS_NOT_EMPTY,
    ];

    protected $fillable = [
        'target_field_id', 'source_field_id', 'condition_group',
        'boolean_connector', 'operator', 'comparison_value', 'position',
    ];

    protected $casts = [
        'condition_group' => 'integer',
        'comparison_value' => 'array',
        'position' => 'integer',
    ];

    protected function guardedFormVersionId(): ?int
    {
        return $this->targetField()->value('application_form_version_id');
    }

    protected static function booted(): void
    {
        static::saving(function (ApplicationFormCondition $condition): void {
            $fieldVersions = ApplicationFormField::query()
                ->whereKey([$condition->target_field_id, $condition->source_field_id])
                ->pluck('application_form_version_id')
                ->unique();

            if ($fieldVersions->count() !== 1) {
                throw new LogicException('Conditional fields must belong to the same application form version.');
            }
        });
    }

    public function targetField(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'target_field_id');
    }

    public function sourceField(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'source_field_id');
    }
}
