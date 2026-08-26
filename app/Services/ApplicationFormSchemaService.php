<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormCondition;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApplicationFormSchemaService
{
    public const MAX_FIELDS = 100;
    public const MAX_OPTIONS = 50;
    public const OPERATORS = [
        'equals', 'not_equals', 'contains', 'not_contains',
        'is_empty', 'is_not_empty', 'greater_than', 'less_than',
    ];

    public function __construct(private AdminAuditService $audit)
    {
    }

    public function create(string $purpose, string $name, ?Admin $actor, bool $template = false): ApplicationForm
    {
        $purpose = trim($purpose);
        $name = trim($name);
        if (!in_array($purpose, ApplicationForm::PURPOSES, true) || $name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['form' => 'Choose a supported form purpose and a name no longer than 150 characters.']);
        }

        return DB::transaction(function () use ($purpose, $name, $actor, $template): ApplicationForm {
            $form = ApplicationForm::query()->create([
                'purpose' => $purpose,
                'name' => $name,
                'is_template' => $template,
                'editor_version' => 1,
                'created_by_admin_id' => $actor?->id,
                'updated_by_admin_id' => $actor?->id,
            ]);
            $version = $form->versions()->create(['version' => 1, 'state' => ApplicationFormVersion::STATE_DRAFT]);
            $this->persistFields($version, $this->defaultSchema($purpose));
            $this->audit->record($actor, 'application_form.created', $form, context: [
                'purpose' => $purpose,
                'is_template' => $template,
                'editor_version' => 1,
            ]);

            return $form->fresh(['versions.fields.translations', 'versions.fields.options.translations']);
        });
    }

    /** @param list<array<string, mixed>> $fields */
    public function replaceDraft(ApplicationForm $form, int $expectedEditorVersion, array $fields, ?Admin $actor): ApplicationFormVersion
    {
        $normalized = $this->normalizeAndValidate($form->purpose, $fields);

        return DB::transaction(function () use ($form, $expectedEditorVersion, $normalized, $actor): ApplicationFormVersion {
            $locked = ApplicationForm::query()->lockForUpdate()->findOrFail($form->id);
            if ((int) $locked->editor_version !== $expectedEditorVersion) {
                abort(409, 'This form changed after you opened it. Reload before saving.');
            }
            $draft = $locked->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->lockForUpdate()->latest('version')->first();
            if (!$draft) {
                $published = $locked->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->latest('version')->firstOrFail();
                $draft = $this->cloneVersion($published, $published->version + 1, ApplicationFormVersion::STATE_DRAFT);
            }

            $this->clearDraft($draft);
            $this->persistFields($draft, $normalized);
            $draft->update(['schema_hash' => $this->schemaHash($normalized)]);
            $locked->update([
                'editor_version' => $expectedEditorVersion + 1,
                'updated_by_admin_id' => $actor?->id,
            ]);
            $this->audit->record($actor, 'application_form.draft_updated', $locked, changes: [
                'editor_version' => ['from' => $expectedEditorVersion, 'to' => $expectedEditorVersion + 1],
            ], context: ['field_count' => count($normalized)]);

            return $draft->fresh(['fields.translations', 'fields.options.translations', 'fields.visibilityConditions.sourceField']);
        });
    }

    public function publish(ApplicationForm $form, int $expectedEditorVersion, ?Admin $actor): ApplicationFormVersion
    {
        return DB::transaction(function () use ($form, $expectedEditorVersion, $actor): ApplicationFormVersion {
            $locked = ApplicationForm::query()->lockForUpdate()->findOrFail($form->id);
            if ((int) $locked->editor_version !== $expectedEditorVersion) {
                abort(409, 'This form changed after you opened it. Reload before publishing.');
            }
            $draft = $locked->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->lockForUpdate()->latest('version')->firstOrFail();
            $schema = $this->schemaArray($draft);
            $normalized = $this->normalizeAndValidate($locked->purpose, $schema);
            $hash = $this->schemaHash($normalized);

            $locked->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->get()->each(
                fn (ApplicationFormVersion $version) => $version->forceFill(['state' => ApplicationFormVersion::STATE_RETIRED])->save()
            );
            $draft->update([
                'state' => ApplicationFormVersion::STATE_PUBLISHED,
                'schema_hash' => $hash,
                'published_at' => now(),
                'published_by_admin_id' => $actor?->id,
            ]);
            $locked->update([
                'editor_version' => $expectedEditorVersion + 1,
                'updated_by_admin_id' => $actor?->id,
            ]);
            $this->audit->record($actor, 'application_form.published', $locked, context: [
                'form_version' => $draft->version,
                'schema_hash' => $hash,
                'field_count' => count($normalized),
            ]);

            return $draft->fresh(['fields.translations', 'fields.options.translations', 'fields.visibilityConditions.sourceField']);
        });
    }

    public function duplicate(ApplicationForm $source, string $name, ?Admin $actor, ?string $purpose = null, bool $template = false): ApplicationForm
    {
        $version = $source->versions()->whereIn('state', [
            ApplicationFormVersion::STATE_PUBLISHED,
            ApplicationFormVersion::STATE_DRAFT,
        ])->orderByRaw("CASE WHEN state = 'published' THEN 0 ELSE 1 END")->latest('version')->firstOrFail();
        $copy = $this->create($purpose ?: $source->purpose, $name, $actor, $template);

        $this->replaceDraft($copy, (int) $copy->editor_version, $this->schemaArray($version), $actor);

        return $copy->fresh(['versions.fields.translations', 'versions.fields.options.translations']);
    }

    /** @return array{uuid:string,version:int,schema_hash:?string,fields:list<array<string,mixed>>} */
    public function publicSchema(ApplicationFormVersion $version, string $locale): array
    {
        $locale = in_array($locale, ['en', 'bn'], true) ? $locale : 'en';
        $version->loadMissing([
            'fields.translations',
            'fields.options.translations',
            'fields.visibilityConditions.sourceField',
        ]);

        $fields = $version->fields->map(function (ApplicationFormField $field) use ($locale): array {
            $translation = $field->translations->firstWhere('locale', $locale)
                ?: $field->translations->firstWhere('locale', 'en');
            $key = $this->publicKey($field);

            return [
                'uuid' => $field->field_key,
                'key' => $key,
                'system_key' => $field->system_key,
                'type' => $field->type,
                'label' => (string) $translation?->label,
                'help' => (string) ($translation?->help_text ?? ''),
                'placeholder' => (string) ($translation?->placeholder ?? ''),
                'required' => (bool) $field->is_required,
                'validation' => $field->validation ?: [],
                'options' => $field->options->map(function ($option) use ($locale): array {
                    $translation = $option->translations->firstWhere('locale', $locale)
                        ?: $option->translations->firstWhere('locale', 'en');

                    return ['value' => $option->option_key, 'label' => (string) $translation?->label];
                })->values()->all(),
                'conditions' => $field->visibilityConditions->map(fn (ApplicationFormCondition $condition): array => [
                    'source_key' => $this->publicKey($condition->sourceField),
                    'group' => (int) $condition->condition_group,
                    'connector' => $condition->boolean_connector,
                    'operator' => $condition->operator,
                    'value' => data_get($condition->comparison_value, 'value'),
                ])->values()->all(),
                'condition' => $field->visibilityConditions->count() === 1 ? [
                    'field_uuid' => $this->publicKey($field->visibilityConditions->first()->sourceField),
                    'operator' => $field->visibilityConditions->first()->operator,
                    'value' => data_get($field->visibilityConditions->first()->comparison_value, 'value'),
                ] : null,
            ];
        })->values()->all();

        return [
            'uuid' => $version->uuid,
            'version' => (int) $version->version,
            'schema_hash' => $version->schema_hash,
            'fields' => $fields,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function schemaArray(ApplicationFormVersion $version): array
    {
        $version->loadMissing([
            'fields.translations',
            'fields.options.translations',
            'fields.visibilityConditions.sourceField',
        ]);

        return $version->fields->map(fn (ApplicationFormField $field): array => [
            'key' => $field->field_key,
            'system_key' => $field->system_key,
            'type' => $field->type,
            'required' => (bool) $field->is_required,
            'validation' => $field->validation ?: [],
            'translations' => collect(['en', 'bn'])->mapWithKeys(function (string $locale) use ($field): array {
                $translation = $field->translations->firstWhere('locale', $locale);

                return [$locale => [
                    'label' => (string) $translation?->label,
                    'help' => (string) ($translation?->help_text ?? ''),
                    'placeholder' => (string) ($translation?->placeholder ?? ''),
                ]];
            })->all(),
            'options' => $field->options->map(fn ($option): array => [
                'key' => $option->option_key,
                'translations' => collect(['en', 'bn'])->mapWithKeys(function (string $locale) use ($option): array {
                    $translation = $option->translations->firstWhere('locale', $locale);

                    return [$locale => ['label' => (string) $translation?->label]];
                })->all(),
            ])->values()->all(),
            'conditions' => $field->visibilityConditions->map(fn (ApplicationFormCondition $condition): array => [
                'source_key' => $condition->sourceField->field_key,
                'group' => (int) $condition->condition_group,
                'connector' => $condition->boolean_connector,
                'operator' => $condition->operator,
                'value' => data_get($condition->comparison_value, 'value'),
            ])->values()->all(),
        ])->values()->all();
    }

    /** @param list<array<string,mixed>> $fields
     *  @return list<array<string,mixed>>
     */
    private function normalizeAndValidate(string $purpose, array $fields): array
    {
        if (count($fields) < 2 || count($fields) > self::MAX_FIELDS) {
            throw ValidationException::withMessages(['schema' => 'A form must contain between 2 and 100 fields.']);
        }

        $normalized = [];
        $seen = [];
        foreach (array_values($fields) as $index => $input) {
            if (!is_array($input)) {
                throw ValidationException::withMessages(['schema' => 'Every form field must be a structured object.']);
            }
            $key = trim((string) ($input['key'] ?? '')) ?: (string) Str::uuid();
            $type = trim((string) ($input['type'] ?? ''));
            $systemKey = ($input['system_key'] ?? null) ?: null;
            if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $key) || isset($seen[$key])) {
                throw ValidationException::withMessages(['schema' => 'Form field keys must be unique stable identifiers.']);
            }
            if (!in_array($type, ApplicationFormField::TYPES, true)) {
                throw ValidationException::withMessages(['schema' => "Unsupported field type at position " . ($index + 1) . '.']);
            }
            $seen[$key] = $index;

            $translations = [];
            foreach (['en', 'bn'] as $locale) {
                $copy = (array) data_get($input, "translations.{$locale}", []);
                $label = trim((string) ($copy['label'] ?? ''));
                if ($label === '' || mb_strlen($label) > 255) {
                    throw ValidationException::withMessages(['schema' => "Every field requires a bounded {$locale} label."]);
                }
                $translations[$locale] = [
                    'label' => $label,
                    'help' => mb_substr(trim((string) ($copy['help'] ?? '')), 0, 2000),
                    'placeholder' => mb_substr(trim((string) ($copy['placeholder'] ?? '')), 0, 255),
                ];
            }

            $validation = $this->normalizeValidation((array) ($input['validation'] ?? []), $type);
            $options = $this->normalizeOptions((array) ($input['options'] ?? []), $type);
            $conditions = $this->normalizeConditions((array) ($input['conditions'] ?? []), $seen, $key);
            if ($systemKey !== null && $conditions !== []) {
                throw ValidationException::withMessages([
                    'schema' => 'Protected identity and CV fields must always remain visible.',
                ]);
            }
            $normalized[] = [
                'key' => $key,
                'system_key' => $systemKey,
                'type' => $type,
                'required' => (bool) ($input['required'] ?? false),
                'validation' => $validation,
                'translations' => $translations,
                'options' => $options,
                'conditions' => $conditions,
            ];
        }

        $this->assertSystemFields($purpose, $normalized);

        return $normalized;
    }

    /** @return array<string,mixed> */
    private function normalizeValidation(array $rules, string $type): array
    {
        if ($type === ApplicationFormField::TYPE_FILE) {
            if (array_diff(array_keys($rules), ['max_kb', 'extensions']) !== []) {
                throw ValidationException::withMessages(['schema' => 'File fields only support the protected PDF upload policy.']);
            }

            return ['max_kb' => 5120, 'extensions' => ['pdf']];
        }

        $textTypes = [
            ApplicationFormField::TYPE_SHORT_TEXT,
            ApplicationFormField::TYPE_LONG_TEXT,
            ApplicationFormField::TYPE_EMAIL,
            ApplicationFormField::TYPE_PHONE,
        ];
        $allowed = match (true) {
            in_array($type, $textTypes, true) => ['min_length', 'max_length'],
            $type === ApplicationFormField::TYPE_NUMBER,
            $type === ApplicationFormField::TYPE_CHECKBOXES => ['min', 'max'],
            default => [],
        };
        if (array_diff(array_keys($rules), $allowed) !== []) {
            throw ValidationException::withMessages(['schema' => 'A form field contains an unsupported validation setting.']);
        }
        $normalized = [];
        foreach (['min_length', 'max_length'] as $key) {
            if (array_key_exists($key, $rules) && $rules[$key] !== null && $rules[$key] !== '') {
                $value = filter_var($rules[$key], FILTER_VALIDATE_INT);
                if ($value === false || $value < 0 || $value > 20_000) {
                    throw ValidationException::withMessages(['schema' => 'Text length limits must be integers between 0 and 20,000.']);
                }
                $normalized[$key] = $value;
            }
        }
        foreach (['min', 'max'] as $key) {
            if (array_key_exists($key, $rules) && $rules[$key] !== null && $rules[$key] !== '') {
                if (!is_numeric($rules[$key]) || abs((float) $rules[$key]) > 1_000_000_000) {
                    throw ValidationException::withMessages(['schema' => 'Numeric limits must be within the supported range.']);
                }
                $normalized[$key] = (float) $rules[$key];
            }
        }
        if (isset($normalized['min_length'], $normalized['max_length']) && $normalized['min_length'] > $normalized['max_length']) {
            throw ValidationException::withMessages(['schema' => 'Minimum text length cannot exceed maximum text length.']);
        }
        if (isset($normalized['min'], $normalized['max']) && $normalized['min'] > $normalized['max']) {
            throw ValidationException::withMessages(['schema' => 'Minimum number cannot exceed maximum number.']);
        }
        return $normalized;
    }

    /** @return list<array<string,mixed>> */
    private function normalizeOptions(array $options, string $type): array
    {
        $choiceTypes = [ApplicationFormField::TYPE_DROPDOWN, ApplicationFormField::TYPE_RADIO, ApplicationFormField::TYPE_CHECKBOXES];
        if (!in_array($type, $choiceTypes, true)) {
            return [];
        }
        if (count($options) < 2 || count($options) > self::MAX_OPTIONS) {
            throw ValidationException::withMessages(['schema' => 'Choice fields require between 2 and 50 options.']);
        }
        $seen = [];
        return array_map(function (mixed $option) use (&$seen): array {
            $option = (array) $option;
            $key = trim((string) ($option['key'] ?? '')) ?: Str::lower(Str::random(12));
            if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $key) || isset($seen[$key])) {
                throw ValidationException::withMessages(['schema' => 'Choice option keys must be unique stable identifiers.']);
            }
            $seen[$key] = true;
            $translations = [];
            foreach (['en', 'bn'] as $locale) {
                $label = trim((string) data_get($option, "translations.{$locale}.label", ''));
                if ($label === '' || mb_strlen($label) > 255) {
                    throw ValidationException::withMessages(['schema' => "Every option requires a bounded {$locale} label."]);
                }
                $translations[$locale] = ['label' => $label];
            }

            return ['key' => $key, 'translations' => $translations];
        }, array_values($options));
    }

    /** @param array<string,int> $seen
     *  @return list<array<string,mixed>>
     */
    private function normalizeConditions(array $conditions, array $seen, string $targetKey): array
    {
        if (count($conditions) > 20) {
            throw ValidationException::withMessages(['schema' => 'A field cannot contain more than 20 conditions.']);
        }
        $normalized = [];
        foreach (array_values($conditions) as $index => $condition) {
            $condition = (array) $condition;
            $source = trim((string) ($condition['source_key'] ?? ''));
            $operator = trim((string) ($condition['operator'] ?? ''));
            if ($source === $targetKey || !isset($seen[$source]) || !in_array($operator, self::OPERATORS, true)) {
                throw ValidationException::withMessages(['schema' => 'Conditions must reference an earlier field and use a supported operator.']);
            }
            $connector = strtolower((string) ($condition['connector'] ?? 'and'));
            if (!in_array($connector, ['and', 'or'], true)) {
                throw ValidationException::withMessages(['schema' => 'Condition connectors must be and/or.']);
            }
            $group = filter_var($condition['group'] ?? 1, FILTER_VALIDATE_INT);
            if ($group === false || $group < 1 || $group > 20) {
                throw ValidationException::withMessages(['schema' => 'Condition groups must be between 1 and 20.']);
            }
            $normalized[] = [
                'source_key' => $source,
                'operator' => $operator,
                'connector' => $connector,
                'group' => $group,
                'value' => in_array($operator, ['is_empty', 'is_not_empty'], true) ? null : ($condition['value'] ?? null),
                'position' => $index + 1,
            ];
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $fields */
    private function assertSystemFields(string $purpose, array $fields): void
    {
        $expected = [
            ApplicationFormField::SYSTEM_FULL_NAME => [ApplicationFormField::TYPE_SHORT_TEXT, true],
            ApplicationFormField::SYSTEM_EMAIL => [ApplicationFormField::TYPE_EMAIL, true],
            ApplicationFormField::SYSTEM_PHONE => [ApplicationFormField::TYPE_PHONE, false],
        ];
        if ($purpose === ApplicationForm::PURPOSE_JOB) {
            $expected[ApplicationFormField::SYSTEM_CV] = [ApplicationFormField::TYPE_FILE, true];
        }

        $systemFields = collect($fields)->filter(fn (array $field): bool => $field['system_key'] !== null)->keyBy('system_key');
        if ($systemFields->keys()->sort()->values()->all() !== collect(array_keys($expected))->sort()->values()->all()) {
            throw ValidationException::withMessages(['schema' => 'Required identity fields cannot be removed or added.']);
        }
        foreach ($expected as $key => [$type, $required]) {
            $field = $systemFields->get($key);
            if ($field['type'] !== $type || (bool) $field['required'] !== $required) {
                throw ValidationException::withMessages(['schema' => "The {$key} system field cannot change type or requirement."]);
            }
        }
    }

    /** @param list<array<string,mixed>> $fields */
    private function persistFields(ApplicationFormVersion $version, array $fields): void
    {
        $byKey = [];
        foreach ($fields as $position => $definition) {
            $field = $version->fields()->create([
                'field_key' => $definition['key'],
                'system_key' => $definition['system_key'],
                'type' => $definition['type'],
                'position' => $position + 1,
                'is_required' => $definition['required'],
                'validation' => $definition['validation'] ?: null,
            ]);
            $byKey[$definition['key']] = $field;
            foreach ($definition['translations'] as $locale => $copy) {
                $field->translations()->create([
                    'locale' => $locale,
                    'label' => $copy['label'],
                    'help_text' => $copy['help'] ?: null,
                    'placeholder' => $copy['placeholder'] ?: null,
                ]);
            }
            foreach ($definition['options'] as $optionPosition => $optionDefinition) {
                $option = $field->options()->create([
                    'option_key' => $optionDefinition['key'],
                    'position' => $optionPosition + 1,
                ]);
                foreach ($optionDefinition['translations'] as $locale => $copy) {
                    $option->translations()->create(['locale' => $locale, 'label' => $copy['label']]);
                }
            }
        }
        foreach ($fields as $definition) {
            $target = $byKey[$definition['key']];
            foreach ($definition['conditions'] as $condition) {
                $target->visibilityConditions()->create([
                    'source_field_id' => $byKey[$condition['source_key']]->id,
                    'condition_group' => $condition['group'],
                    'boolean_connector' => $condition['connector'],
                    'operator' => $condition['operator'],
                    'comparison_value' => ['value' => $condition['value']],
                    'position' => $condition['position'],
                ]);
            }
        }
    }

    private function clearDraft(ApplicationFormVersion $draft): void
    {
        $fieldIds = $draft->fields()->pluck('id');
        if ($fieldIds->isEmpty()) {
            return;
        }
        DB::table('application_form_conditions')->whereIn('target_field_id', $fieldIds)->orWhereIn('source_field_id', $fieldIds)->delete();
        $optionIds = DB::table('application_form_options')->whereIn('application_form_field_id', $fieldIds)->pluck('id');
        DB::table('application_form_option_translations')->whereIn('application_form_option_id', $optionIds)->delete();
        DB::table('application_form_options')->whereIn('id', $optionIds)->delete();
        DB::table('application_form_field_translations')->whereIn('application_form_field_id', $fieldIds)->delete();
        DB::table('application_form_fields')->whereIn('id', $fieldIds)->delete();
    }

    private function cloneVersion(ApplicationFormVersion $source, int $number, string $state): ApplicationFormVersion
    {
        $target = $source->form->versions()->create(['version' => $number, 'state' => $state]);
        $this->persistFields($target, $this->schemaArray($source));

        return $target;
    }

    /** @param list<array<string,mixed>> $schema */
    private function schemaHash(array $schema): string
    {
        return hash('sha256', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function publicKey(ApplicationFormField $field): string
    {
        return match ($field->system_key) {
            ApplicationFormField::SYSTEM_FULL_NAME => 'applicant_name',
            ApplicationFormField::SYSTEM_EMAIL => 'email',
            ApplicationFormField::SYSTEM_PHONE => 'phone',
            ApplicationFormField::SYSTEM_CV => 'cv',
            default => $field->field_key,
        };
    }

    /** @return list<array<string,mixed>> */
    private function defaultSchema(string $purpose): array
    {
        $definitions = [
            [ApplicationFormField::SYSTEM_FULL_NAME, ApplicationFormField::TYPE_SHORT_TEXT, true, 'Full name', 'পূর্ণ নাম'],
            [ApplicationFormField::SYSTEM_EMAIL, ApplicationFormField::TYPE_EMAIL, true, 'Email address', 'ইমেইল ঠিকানা'],
            [ApplicationFormField::SYSTEM_PHONE, ApplicationFormField::TYPE_PHONE, false, 'Phone number', 'ফোন নম্বর'],
        ];
        if ($purpose === ApplicationForm::PURPOSE_JOB) {
            $definitions[] = [ApplicationFormField::SYSTEM_CV, ApplicationFormField::TYPE_FILE, true, 'CV (PDF, maximum 5 MB)', 'সিভি (পিডিএফ, সর্বোচ্চ ৫ এমবি)'];
        }

        return array_map(fn (array $definition): array => [
            'key' => (string) Str::uuid(),
            'system_key' => $definition[0],
            'type' => $definition[1],
            'required' => $definition[2],
            'validation' => $definition[1] === ApplicationFormField::TYPE_FILE ? ['max_kb' => 5120, 'extensions' => ['pdf']] : [],
            'translations' => [
                'en' => ['label' => $definition[3], 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => $definition[4], 'help' => '', 'placeholder' => ''],
            ],
            'options' => [],
            'conditions' => [],
        ], $definitions);
    }
}
