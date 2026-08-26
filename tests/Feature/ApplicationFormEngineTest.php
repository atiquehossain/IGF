<?php

namespace Tests\Feature;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationFormSubmissionValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApplicationFormEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_drafts_publish_as_immutable_versions_and_optimistic_edits_do_not_overwrite_each_other(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'General job application', null);
        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('department', ApplicationFormField::TYPE_DROPDOWN, options: ['programmes', 'finance']);
        $schema[] = $this->field('motivation', ApplicationFormField::TYPE_LONG_TEXT, conditions: [[
            'source_key' => 'department',
            'operator' => 'equals',
            'value' => 'programmes',
            'group' => 1,
            'connector' => 'and',
        ]]);

        $draft = $forms->replaceDraft($form, 1, $schema, null);
        $this->assertSame(2, (int) $form->fresh()->editor_version);
        $this->assertCount(6, $draft->fields);

        try {
            $forms->replaceDraft($form, 1, $schema, null);
            $this->fail('A stale editor version must not overwrite a newer draft.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $publishedOne = $forms->publish($form, 2, null);
        $this->assertSame(ApplicationFormVersion::STATE_PUBLISHED, $publishedOne->state);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) $publishedOne->schema_hash);

        $publishedField = $publishedOne->fields->firstWhere('field_key', 'department');
        $this->expectException(LogicException::class);
        $publishedField->update(['position' => 99]);
    }

    public function test_republishing_retires_but_does_not_mutate_the_previous_schema_and_templates_duplicate_independently(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Workshop registration', null);
        $first = $forms->publish($form, 1, null);
        $firstHash = $first->schema_hash;

        $schema = $forms->schemaArray($first);
        $schema[] = $this->field('experience', ApplicationFormField::TYPE_RADIO, options: ['beginner', 'experienced']);
        $forms->replaceDraft($form, 2, $schema, null);
        $second = $forms->publish($form, 3, null);

        $this->assertSame(ApplicationFormVersion::STATE_RETIRED, $first->fresh()->state);
        $this->assertSame($firstHash, $first->fresh()->schema_hash);
        $this->assertSame(ApplicationFormVersion::STATE_PUBLISHED, $second->state);
        $this->assertNotSame($firstHash, $second->schema_hash);

        $copy = $forms->duplicate($form, 'Reusable workshop template', null, template: true);
        $this->assertTrue($copy->is_template);
        $this->assertSame(ApplicationForm::PURPOSE_WORKSHOP, $copy->purpose);
        $this->assertCount(count($schema), $this->draft($copy)->fields);
        $this->assertNotSame($form->uuid, $copy->uuid);
    }

    public function test_every_supported_field_type_is_normalized_and_conditions_are_enforced_server_side(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $validator = app(ApplicationFormSubmissionValidator::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Typed job form', null);
        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('bio', ApplicationFormField::TYPE_LONG_TEXT, validation: ['min_length' => 5, 'max_length' => 100]);
        $schema[] = $this->field('backup_email', ApplicationFormField::TYPE_EMAIL);
        $schema[] = $this->field('backup_phone', ApplicationFormField::TYPE_PHONE);
        $schema[] = $this->field('years', ApplicationFormField::TYPE_NUMBER, validation: ['min' => 0, 'max' => 50]);
        $schema[] = $this->field('available_on', ApplicationFormField::TYPE_DATE);
        $schema[] = $this->field('department', ApplicationFormField::TYPE_DROPDOWN, options: ['programmes', 'finance']);
        $schema[] = $this->field('level', ApplicationFormField::TYPE_RADIO, options: ['junior', 'senior']);
        $schema[] = $this->field('skills', ApplicationFormField::TYPE_CHECKBOXES, validation: ['min' => 1, 'max' => 2], options: ['writing', 'research', 'excel']);
        $schema[] = $this->field('relocate', ApplicationFormField::TYPE_YES_NO);
        $schema[] = $this->field('portfolio', ApplicationFormField::TYPE_FILE);
        $schema[] = $this->field('relocation_note', ApplicationFormField::TYPE_SHORT_TEXT, required: true, conditions: [[
            'source_key' => 'relocate',
            'operator' => 'equals',
            'value' => 'yes',
            'group' => 1,
            'connector' => 'and',
        ]]);
        $forms->replaceDraft($form, 1, $schema, null);
        $published = $forms->publish($form, 2, null);

        $submission = $validator->validate($published, [
            'applicant_name' => '  Jane Applicant  ',
            'email' => ' JANE@Example.Test ',
            'phone' => '+880 1700-000000',
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', '%PDF-1.4 body %%EOF'),
            'responses' => [
                'bio' => 'Experienced programme professional',
                'backup_email' => 'backup@example.test',
                'backup_phone' => '+880 1800-000000',
                'years' => '7.5',
                'available_on' => '2026-09-01',
                'department' => 'programmes',
                'level' => 'senior',
                'skills' => ['research', 'writing'],
                'relocate' => 'yes',
                'portfolio' => UploadedFile::fake()->createWithContent('work.pdf', '%PDF-1.4 sample %%EOF'),
                'relocation_note' => 'Dhaka preferred',
            ],
        ]);

        $this->assertSame('Jane Applicant', $submission->name);
        $this->assertSame('jane@example.test', $submission->email);
        $this->assertSame('+880 1700-000000', $submission->phone);
        $this->assertCount(10, $submission->answers);
        $this->assertCount(2, $submission->files);
        $this->assertSame(['research', 'writing'], $submission->values['skills']);
        $this->assertTrue($submission->values['relocate']);
        $this->assertSame('Dhaka preferred', $submission->values['relocation_note']);
    }

    public function test_hidden_answers_are_discarded_and_unknown_or_invalid_values_fail_closed_in_bangla(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $validator = app(ApplicationFormSubmissionValidator::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Conditional workshop form', null);
        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('attending_online', ApplicationFormField::TYPE_YES_NO);
        $schema[] = $this->field('platform', ApplicationFormField::TYPE_DROPDOWN, required: true, options: ['zoom', 'meet'], conditions: [[
            'source_key' => 'attending_online',
            'operator' => 'equals',
            'value' => 'yes',
            'group' => 1,
            'connector' => 'and',
        ]]);
        $forms->replaceDraft($form, 1, $schema, null);
        $published = $forms->publish($form, 2, null);

        $submission = $validator->validate($published, [
            'applicant_name' => 'রহিম',
            'email' => 'rahim@example.test',
            'responses' => ['attending_online' => 'no', 'platform' => 'not-a-real-option'],
        ], 'bn', requireCv: false);
        $this->assertFalse($submission->values['attending_online']);
        $this->assertArrayNotHasKey('platform', $submission->values);

        try {
            $validator->validate($published, [
                'applicant_name' => 'রহিম',
                'email' => 'bad-email',
                'responses' => ['attending_online' => 'yes', 'platform' => 'not-a-real-option'],
            ], 'bn', requireCv: false);
            $this->fail('Invalid visible answers must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('responses.platform', $exception->errors());
            $this->assertStringContainsString('বৈধ', $exception->errors()['email'][0]);
        }

        $this->expectException(ValidationException::class);
        $validator->validate($published, [
            'applicant_name' => 'Rahim',
            'email' => 'rahim@example.test',
            'responses' => ['attending_online' => 'no', 'expired-field' => 'smuggled'],
        ], requireCv: false);
    }

    public function test_schema_rejects_missing_locked_fields_cycles_unsupported_rules_and_oversized_files(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $validator = app(ApplicationFormSubmissionValidator::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Protected form', null);
        $schema = $forms->schemaArray($this->draft($form));
        array_pop($schema);

        try {
            $forms->replaceDraft($form, 1, $schema, null);
            $this->fail('The mandatory CV field cannot be removed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schema', $exception->errors());
        }

        $schema = $forms->schemaArray($this->draft($form));
        $schema[] = $this->field('bad', ApplicationFormField::TYPE_SHORT_TEXT, validation: ['pattern' => '.*']);
        try {
            $forms->replaceDraft($form, 1, $schema, null);
            $this->fail('Unsupported regular expressions cannot enter a published schema.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schema', $exception->errors());
        }

        $published = $forms->publish($form, 1, null);
        $this->expectException(ValidationException::class);
        $validator->validate($published, [
            'applicant_name' => 'Applicant',
            'email' => 'applicant@example.test',
            'cv' => UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
            'responses' => [],
        ]);
    }

    public function test_protected_fields_reject_conditions_and_a_legacy_hidden_cv_still_remains_mandatory(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $validator = app(ApplicationFormSubmissionValidator::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Unconditional identity form', null);
        $schema = $forms->schemaArray($this->draft($form));
        $cvIndex = collect($schema)->search(fn (array $field): bool => $field['system_key'] === ApplicationFormField::SYSTEM_CV);
        $email = collect($schema)->firstWhere('system_key', ApplicationFormField::SYSTEM_EMAIL);
        $schema[$cvIndex]['conditions'] = [[
            'source_key' => $email['key'],
            'operator' => 'equals',
            'value' => 'never-matches@example.test',
            'group' => 1,
            'connector' => 'and',
        ]];

        try {
            $forms->replaceDraft($form, 1, $schema, null);
            $this->fail('Protected identity and CV fields must not accept conditions.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schema', $exception->errors());
        }

        $published = $forms->publish($form, 1, null);
        $published->load('fields');
        $emailField = $published->fields->firstWhere('system_key', ApplicationFormField::SYSTEM_EMAIL);
        $cvField = $published->fields->firstWhere('system_key', ApplicationFormField::SYSTEM_CV);
        \Illuminate\Support\Facades\DB::table('application_form_conditions')->insert([
            'target_field_id' => $cvField->id,
            'source_field_id' => $emailField->id,
            'condition_group' => 1,
            'boolean_connector' => 'and',
            'operator' => 'equals',
            'comparison_value' => json_encode(['value' => 'never-matches@example.test'], JSON_THROW_ON_ERROR),
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $validator->validate($published->fresh(), [
                'applicant_name' => 'Applicant',
                'email' => 'applicant@example.test',
                'responses' => [],
            ]);
            $this->fail('A hidden CV in legacy or tampered schema data must still be mandatory.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cv', $exception->errors());
        }
    }

    private function draft(ApplicationForm $form): ApplicationFormVersion
    {
        return $form->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validation
     * @param list<string> $options
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
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
            'key' => $key,
            'system_key' => null,
            'type' => $type,
            'required' => $required,
            'validation' => $validation,
            'translations' => [
                'en' => ['label' => ucfirst(str_replace('_', ' ', $key)), 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => 'বাংলা ' . $key, 'help' => '', 'placeholder' => ''],
            ],
            'options' => array_map(fn (string $option): array => [
                'key' => $option,
                'translations' => [
                    'en' => ['label' => ucfirst($option)],
                    'bn' => ['label' => 'বাংলা ' . $option],
                ],
            ], $options),
            'conditions' => $conditions,
        ];
    }
}
