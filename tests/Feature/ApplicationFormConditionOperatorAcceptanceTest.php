<?php

namespace Tests\Feature;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationFormSubmissionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationFormConditionOperatorAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_condition_operator_and_group_connector_is_enforced_by_the_server(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Condition operator matrix', null);
        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('text_source', ApplicationFormField::TYPE_SHORT_TEXT);
        $schema[] = $this->field('empty_source', ApplicationFormField::TYPE_SHORT_TEXT);
        $schema[] = $this->field('number_source', ApplicationFormField::TYPE_NUMBER);
        $schema[] = $this->field('choice_source', ApplicationFormField::TYPE_CHECKBOXES, options: ['research', 'writing']);
        $conditions = [
            'equals_target' => ['text_source', 'equals', 'Alpha programme'],
            'not_equals_target' => ['text_source', 'not_equals', 'Beta'],
            'contains_target' => ['text_source', 'contains', 'programme'],
            'not_contains_target' => ['text_source', 'not_contains', 'finance'],
            'empty_target' => ['empty_source', 'is_empty', null],
            'not_empty_target' => ['text_source', 'is_not_empty', null],
            'greater_target' => ['number_source', 'greater_than', 5],
            'less_target' => ['number_source', 'less_than', 20],
        ];
        foreach ($conditions as $key => [$source, $operator, $value]) {
            $schema[] = $this->field($key, ApplicationFormField::TYPE_SHORT_TEXT, true, conditions: [[
                'source_key' => $source, 'operator' => $operator, 'value' => $value,
                'group' => 1, 'connector' => 'and',
            ]]);
        }
        $schema[] = $this->field('or_connector_target', ApplicationFormField::TYPE_SHORT_TEXT, true, conditions: [
            ['source_key' => 'text_source', 'operator' => 'equals', 'value' => 'never', 'group' => 1, 'connector' => 'and'],
            ['source_key' => 'number_source', 'operator' => 'greater_than', 'value' => 5, 'group' => 1, 'connector' => 'or'],
        ]);
        $schema[] = $this->field('alternate_group_target', ApplicationFormField::TYPE_SHORT_TEXT, true, conditions: [
            ['source_key' => 'text_source', 'operator' => 'equals', 'value' => 'never', 'group' => 1, 'connector' => 'and'],
            ['source_key' => 'choice_source', 'operator' => 'contains', 'value' => 'research', 'group' => 2, 'connector' => 'and'],
        ]);
        $schema[] = $this->field('hidden_target', ApplicationFormField::TYPE_SHORT_TEXT, true, conditions: [[
            'source_key' => 'number_source', 'operator' => 'less_than', 'value' => 0,
            'group' => 1, 'connector' => 'and',
        ]]);
        $forms->replaceDraft($form, 1, $schema, null);
        $published = $forms->publish($form, 2, null);

        $responses = [
            'text_source' => 'Alpha programme',
            'number_source' => '10',
            'choice_source' => ['research'],
            'hidden_target' => 'must be discarded',
            'or_connector_target' => 'OR passed',
            'alternate_group_target' => 'group passed',
        ];
        foreach (array_keys($conditions) as $key) {
            $responses[$key] = $key . ' passed';
        }
        $submission = app(ApplicationFormSubmissionValidator::class)->validate($published, [
            'applicant_name' => 'Condition Applicant',
            'email' => 'conditions@example.test',
            'responses' => $responses,
        ], requireCv: false);

        foreach (array_keys($conditions) as $key) {
            $this->assertSame($key . ' passed', $submission->values[$key]);
        }
        $this->assertSame('OR passed', $submission->values['or_connector_target']);
        $this->assertSame('group passed', $submission->values['alternate_group_target']);
        $this->assertArrayNotHasKey('hidden_target', $submission->values);
        $this->assertFalse(collect($submission->answers)->contains(
            fn (array $answer): bool => $answer['application_form_field_id'] === $published->fields()->where('field_key', 'hidden_target')->value('id')
        ));
    }

    public function test_schema_field_option_and_condition_limits_fail_before_any_draft_is_replaced(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Bounded form', null);
        $original = $forms->schemaArray($this->draft($form));

        $tooManyFields = $original;
        for ($index = count($original); $index <= ApplicationFormSchemaService::MAX_FIELDS; $index++) {
            $tooManyFields[] = $this->field('field_' . $index, ApplicationFormField::TYPE_SHORT_TEXT);
        }
        $this->assertSchemaRejected($forms, $form, $tooManyFields);

        $tooManyOptions = $original;
        $tooManyOptions[] = $this->field(
            'oversized_options',
            ApplicationFormField::TYPE_DROPDOWN,
            options: array_map(fn (int $index): string => 'option_' . $index, range(1, ApplicationFormSchemaService::MAX_OPTIONS + 1)),
        );
        $this->assertSchemaRejected($forms, $form, $tooManyOptions);

        $tooManyConditions = $original;
        $tooManyConditions[] = $this->field('condition_source', ApplicationFormField::TYPE_SHORT_TEXT);
        $tooManyConditions[] = $this->field(
            'condition_target',
            ApplicationFormField::TYPE_SHORT_TEXT,
            conditions: array_map(fn (int $index): array => [
                'source_key' => 'condition_source', 'operator' => 'equals', 'value' => (string) $index,
                'group' => 1, 'connector' => 'or',
            ], range(1, 21)),
        );
        $this->assertSchemaRejected($forms, $form, $tooManyConditions);

        $this->assertSame(1, $form->fresh()->editor_version);
        $this->assertSame($original, $forms->schemaArray($this->draft($form)));
    }

    public function test_each_non_file_field_type_rejects_its_invalid_boundary_value(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Typed validation boundaries', null);
        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('short_value', ApplicationFormField::TYPE_SHORT_TEXT, true, ['min_length' => 2, 'max_length' => 4]);
        $schema[] = $this->field('long_value', ApplicationFormField::TYPE_LONG_TEXT, true, ['min_length' => 2, 'max_length' => 5]);
        $schema[] = $this->field('email_value', ApplicationFormField::TYPE_EMAIL, true);
        $schema[] = $this->field('phone_value', ApplicationFormField::TYPE_PHONE, true);
        $schema[] = $this->field('number_value', ApplicationFormField::TYPE_NUMBER, true, ['min' => 1, 'max' => 2]);
        $schema[] = $this->field('date_value', ApplicationFormField::TYPE_DATE, true);
        $schema[] = $this->field('dropdown_value', ApplicationFormField::TYPE_DROPDOWN, true, options: ['one', 'two']);
        $schema[] = $this->field('radio_value', ApplicationFormField::TYPE_RADIO, true, options: ['one', 'two']);
        $schema[] = $this->field('checkbox_value', ApplicationFormField::TYPE_CHECKBOXES, true, ['min' => 1, 'max' => 1], ['one', 'two']);
        $schema[] = $this->field('yes_no_value', ApplicationFormField::TYPE_YES_NO, true);
        $forms->replaceDraft($form, 1, $schema, null);
        $published = $forms->publish($form, 2, null);
        $valid = [
            'short_value' => 'good', 'long_value' => 'valid', 'email_value' => 'valid@example.test',
            'phone_value' => '+88017', 'number_value' => 2, 'date_value' => '2026-09-30',
            'dropdown_value' => 'one', 'radio_value' => 'two', 'checkbox_value' => ['one'], 'yes_no_value' => 'yes',
        ];
        $invalid = [
            'short_value' => 'x',
            'long_value' => '123456',
            'email_value' => 'not-an-email',
            'phone_value' => 'abcde',
            'number_value' => 3,
            'date_value' => '2026-02-30',
            'dropdown_value' => 'three',
            'radio_value' => ['one'],
            'checkbox_value' => ['one', 'two'],
            'yes_no_value' => 'maybe',
        ];

        foreach ($invalid as $key => $value) {
            try {
                app(ApplicationFormSubmissionValidator::class)->validate($published, [
                    'applicant_name' => 'Boundary Applicant',
                    'email' => 'boundary@example.test',
                    'responses' => array_replace($valid, [$key => $value]),
                ], requireCv: false);
                $this->fail("Invalid {$key} boundary was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('responses.' . $key, $exception->errors(), $key);
            }
        }
    }

    private function assertSchemaRejected(
        ApplicationFormSchemaService $forms,
        ApplicationForm $form,
        array $schema,
    ): void {
        try {
            $forms->replaceDraft($form, 1, $schema, null);
            $this->fail('An out-of-bounds form schema was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schema', $exception->errors());
        }
    }

    private function draft(ApplicationForm $form): ApplicationFormVersion
    {
        return $form->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->firstOrFail();
    }

    /** @param list<string> $options
     *  @param list<array<string,mixed>> $conditions
     *  @return array<string,mixed>
     */
    private function field(
        string $key,
        string $type,
        bool $required = false,
        array $validation = [],
        array $options = [],
        array $conditions = [],
    ): array {
        return [
            'key' => $key, 'system_key' => null, 'type' => $type, 'required' => $required,
            'validation' => $validation,
            'translations' => [
                'en' => ['label' => $key, 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => 'বাংলা ' . $key, 'help' => '', 'placeholder' => ''],
            ],
            'options' => array_map(fn (string $option): array => [
                'key' => $option,
                'translations' => ['en' => ['label' => $option], 'bn' => ['label' => 'বাংলা ' . $option]],
            ], $options),
            'conditions' => $conditions,
        ];
    }
}
