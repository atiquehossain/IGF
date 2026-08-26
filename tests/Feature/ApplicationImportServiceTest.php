<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationImportBatch;
use App\Models\ApplicationImportRow;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApplicationImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(ApplicationImportService::DISK);
    }

    public function test_reviewed_preview_reports_invalid_rows_and_never_trusts_formulas_html_or_external_file_links(): void
    {
        $job = $this->job();
        $actor = $this->owner();
        $service = app(ApplicationImportService::class);
        $file = UploadedFile::fake()->createWithContent('google.csv', implode("\n", [
            'Name,Email,Phone,CV Link',
            '"=2+2",first@example.test,+8801700000000,https://drive.example.test/private',
            '"<img src=x onerror=alert(1)>Bad",not-an-email,+8801800000000,/etc/passwd',
        ]) . "\n");
        $batch = $service->upload($job, $file, $actor);
        $this->assertSame(ApplicationImportBatch::STATE_UPLOADED, $batch->state);
        $this->assertStringNotContainsString('google.csv', $batch->source_path);
        Storage::disk(ApplicationImportService::DISK)->assertExists($batch->source_path);

        $preview = $service->preview($batch, [
            'Name' => 'applicant_name',
            'Email' => 'email',
            'Phone' => 'phone',
            'CV Link' => 'ignore',
        ], 'update', $actor);
        $this->assertSame(2, $preview->total_rows);
        $this->assertSame(1, $preview->valid_rows);
        $this->assertSame(1, $preview->invalid_rows);
        $this->assertSame('=2+2', $preview->rows->first()->normalized_data['name'], 'Formula-looking cells remain inert text.');
        $this->assertStringContainsString('<img', json_encode($preview->rows->last()->raw_data), 'The review record preserves source text but never renders or evaluates it.');
        $this->assertArrayNotHasKey('CV Link', $preview->rows->first()->normalized_data);

        $report = $service->errorReport($preview, $actor);
        ob_start();
        $report->sendContent();
        $csv = (string) ob_get_clean();
        $header = str_getcsv(strtok(substr($csv, 3), "\n"), ',', '"', '');
        $this->assertSame(['CSV row', 'State', 'Action', 'Validation errors'], $header);
        $this->assertStringContainsString('3,invalid', $csv);
        $this->assertStringNotContainsString('not-an-email', $csv, 'The error report identifies the row without echoing private source values.');

        try {
            $service->confirm($preview, $actor);
            $this->fail('Invalid previews cannot be partially committed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows', $exception->errors());
        }
        $this->assertSame(0, $job->applications()->count());
    }

    public function test_update_policy_imports_the_last_duplicate_atomically_and_preserves_existing_workflow(): void
    {
        $job = $this->job();
        $existing = JobApplication::query()->create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $job->current_form_version_id,
            'name' => 'Existing Name',
            'email' => 'existing@example.test',
            'workflow_status' => JobApplication::STATUS_SHORTLISTED,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
        $actor = $this->owner('import-update-owner');
        $service = app(ApplicationImportService::class);
        $batch = $service->upload($job, UploadedFile::fake()->createWithContent('history.csv', implode("\n", [
            'Name,Email,Phone',
            'Earlier duplicate,existing@example.test,+8801700000000',
            'Latest duplicate,EXISTING@example.test,+8801800000000',
            'New person,new@example.test,+8801900000000',
        ]) . "\n"), $actor);
        $preview = $service->preview($batch, $this->mapping(), 'update', $actor);

        $this->assertSame(3, $preview->valid_rows);
        $this->assertSame(2, $preview->duplicate_rows);
        $this->assertSame(ApplicationImportRow::ACTION_SKIP, $preview->rows->first()->action);
        $this->assertSame(ApplicationImportRow::ACTION_UPDATE, $preview->rows->get(1)->action);

        $completed = $service->confirm($preview, $actor);
        $this->assertSame(ApplicationImportBatch::STATE_COMPLETED, $completed->state);
        $this->assertSame(2, $completed->imported_rows);
        $this->assertSame(2, $job->applications()->count());
        $updated = $existing->fresh();
        $this->assertSame('Latest duplicate', $updated->name);
        $this->assertSame('existing@example.test', $updated->email);
        $this->assertSame(2, $updated->submission_count);
        $this->assertSame(JobApplication::STATUS_SHORTLISTED, $updated->workflow_status);
        $this->assertSame(JobApplication::SOURCE_IMPORT, $updated->source);
        $new = $job->applications()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(JobApplication::STATUS_NEW, $new->workflow_status);
        $this->assertSame(1, $new->statusEvents()->where('source', 'import')->count());
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'application_import.completed']);

        $this->expectException(HttpException::class);
        $service->confirm($completed, $actor);
    }

    public function test_preview_digest_detects_source_or_duplicate_state_changes_before_any_write(): void
    {
        $job = $this->job();
        $actor = $this->owner('import-tamper-owner');
        $service = app(ApplicationImportService::class);
        $batch = $service->upload($job, UploadedFile::fake()->createWithContent(
            'one.csv',
            "Name,Email,Phone\nOne Person,one@example.test,+8801700000000\n",
        ), $actor);
        $preview = $service->preview($batch, $this->mapping(), 'update', $actor);

        JobApplication::query()->create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $job->current_form_version_id,
            'name' => 'Concurrent public applicant',
            'email' => 'one@example.test',
            'workflow_status' => JobApplication::STATUS_NEW,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
        try {
            $service->confirm($preview, $actor);
            $this->fail('A changed duplicate decision must force a new reviewed preview.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(ApplicationImportBatch::STATE_PREVIEWED, $preview->fresh()->state);
        $this->assertSame(1, $job->applications()->count());

        Storage::disk(ApplicationImportService::DISK)->put($preview->source_path, "Name,Email\nTampered,tampered@example.test\n");
        $this->expectException(ValidationException::class);
        $service->preview($preview, $this->mapping(), 'update', $actor);
    }

    public function test_form_change_after_preview_rejects_confirmation_and_repreview_without_any_import_writes(): void
    {
        $job = $this->job();
        $actor = $this->owner('stale-form-import-owner');
        $service = app(ApplicationImportService::class);
        $batch = $service->upload($job, UploadedFile::fake()->createWithContent(
            'stale-form.csv',
            "Name,Email,Phone\nStale Person,stale@example.test,+8801700000000\n",
        ), $actor);
        $preview = $service->preview($batch, $this->mapping(), 'update', $actor);
        $pinnedVersionId = $preview->application_form_version_id;
        $pinnedSchemaHash = $preview->form_schema_hash;

        $forms = app(ApplicationFormSchemaService::class);
        $form = $job->form()->firstOrFail();
        $schema = $forms->schemaArray($job->currentFormVersion()->firstOrFail());
        $schema[] = $this->textField('fresh_mapping_field', 'Fresh mapping field');
        $forms->replaceDraft($form, (int) $form->editor_version, $schema, $actor);
        $form = $form->fresh();
        $replacement = $forms->publish($form, (int) $form->editor_version, $actor);
        $job->update(['current_form_version_id' => $replacement->id]);

        $this->assertNotSame($pinnedVersionId, $replacement->id);
        $this->assertNotSame($pinnedSchemaHash, $replacement->schema_hash);
        $batchBefore = $preview->fresh()->only([
            'state', 'column_mapping', 'options', 'valid_rows', 'invalid_rows',
            'duplicate_rows', 'imported_rows', 'previewed_at', 'confirmed_at',
            'confirmed_by_admin_id',
        ]);
        $rowsBefore = ApplicationImportRow::query()
            ->where('application_import_batch_id', $preview->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ApplicationImportRow $row): array => $row->only([
                'id', 'row_number', 'state', 'action', 'raw_data', 'normalized_data',
                'validation_errors', 'imported_target_uuid', 'created_at', 'updated_at',
            ]))
            ->all();
        $auditCountBefore = \App\Models\AdminAuditEvent::query()->count();

        try {
            $service->confirm($preview, $actor);
            $this->fail('A preview pinned to an old form version cannot be confirmed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('mapping', $exception->errors());
            $this->assertStringContainsString('fresh column mapping', $exception->errors()['mapping'][0]);
        }

        try {
            $service->preview($preview, $this->mapping(), 'update', $actor);
            $this->fail('A stale batch cannot replace its reviewed rows using an obsolete mapping.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('mapping', $exception->errors());
        }

        $this->assertEquals($batchBefore, $preview->fresh()->only(array_keys($batchBefore)));
        $this->assertEquals($rowsBefore, ApplicationImportRow::query()
            ->where('application_import_batch_id', $preview->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ApplicationImportRow $row): array => $row->only(array_keys($rowsBefore[0])))
            ->all());
        $this->assertSame($auditCountBefore, \App\Models\AdminAuditEvent::query()->count());
        $this->assertDatabaseCount('job_applications', 0);
        $this->assertDatabaseCount('job_application_answers', 0);
        $this->assertDatabaseCount('job_application_status_events', 0);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'application_import.completed']);
    }

    public function test_workshop_imports_are_pending_and_mapping_cannot_target_protected_file_fields(): void
    {
        $workshop = $this->workshop();
        $actor = $this->owner('workshop-import-owner');
        $service = app(ApplicationImportService::class);
        $batch = $service->upload($workshop, UploadedFile::fake()->createWithContent(
            'workshop.csv',
            "Name,Email,Phone\nWorkshop Person,workshop@example.test,+8801700000000\n",
        ), $actor);
        try {
            $service->preview($batch, ['Name' => 'applicant_name', 'Email' => 'email', 'Phone' => 'cv'], 'update', $actor);
            $this->fail('CSV paths or links cannot be mapped into protected file fields.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('mapping', $exception->errors());
        }

        $preview = $service->preview($batch, $this->mapping(), 'skip', $actor);
        $service->confirm($preview, $actor);
        $registration = WorkshopRegistration::query()->firstOrFail();
        $this->assertSame(WorkshopRegistration::STATUS_PENDING, $registration->workflow_status);
        $this->assertSame(WorkshopRegistration::SOURCE_IMPORT, $registration->source);
        $this->assertNull($registration->confirmed_at);
    }

    private function job(): JobPosting
    {
        $form = app(ApplicationFormSchemaService::class)->create(ApplicationForm::PURPOSE_JOB, 'Import job form', null);
        $version = app(ApplicationFormSchemaService::class)->publish($form, 1, null);

        return JobPosting::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subDay(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 1,
        ]);
    }

    private function workshop(): Workshop
    {
        $form = app(ApplicationFormSchemaService::class)->create(ApplicationForm::PURPOSE_WORKSHOP, 'Import workshop form', null);
        $version = app(ApplicationFormSchemaService::class)->publish($form, 1, null);

        return Workshop::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_WAITLIST,
            'capacity' => 10,
        ]);
    }

    /** @return array<string, string> */
    private function mapping(): array
    {
        return ['Name' => 'applicant_name', 'Email' => 'email', 'Phone' => 'phone'];
    }

    /** @return array<string, mixed> */
    private function textField(string $key, string $label): array
    {
        return [
            'key' => $key,
            'system_key' => null,
            'type' => ApplicationFormField::TYPE_SHORT_TEXT,
            'required' => false,
            'validation' => ['max_length' => 500],
            'translations' => [
                'en' => ['label' => $label, 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => $label, 'help' => '', 'placeholder' => ''],
            ],
            'options' => [],
            'conditions' => [],
        ];
    }

    private function owner(string $username = 'import-owner'): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();

        return Admin::query()->create([
            'name' => 'Import Owner',
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
