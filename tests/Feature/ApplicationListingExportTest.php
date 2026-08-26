<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\ApplicationExportService;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationListingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationListingExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_job_filters_sorts_dynamic_columns_and_csv_are_safe_and_audited(): void
    {
        [$job, $field] = $this->job();
        $alice = $this->jobApplicant($job, 'Alice', 'alice@example.test', JobApplication::STATUS_NEW, 2);
        $alice->answers()->create([
            'application_form_field_id' => $field->id,
            'value_text' => '  =HYPERLINK("https://attacker.test")',
        ]);
        $this->jobApplicant($job, 'Bob', 'bob@example.test', JobApplication::STATUS_REJECTED, 1);
        $actor = $this->owner();
        $filters = ['status' => JobApplication::STATUS_NEW, 'sort' => 'name', 'direction' => 'asc'];
        $query = app(ApplicationListingService::class)->jobs($job, $filters, 'Alice');

        $this->assertSame([$alice->id], $query->pluck('id')->all());
        $response = app(ApplicationExportService::class)->jobs(
            $job,
            app(ApplicationListingService::class)->jobs($job, $filters, 'Alice'),
            ['name', 'email', 'answer:motivation', 'not-a-real-column'],
            $actor,
            $filters,
        );
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Name,Email,Motivation', $csv);
        $this->assertStringContainsString("'  =HYPERLINK", $csv);
        $this->assertStringNotContainsString('Bob', $csv);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.applications.exported']);
        $audit = \App\Models\AdminAuditEvent::query()->where('action', 'recruitment.applications.exported')->firstOrFail();
        $this->assertSame(1, $audit->context['row_count']);
        $this->assertStringNotContainsString('Alice', json_encode($audit->context));
    }

    public function test_job_export_includes_selected_answers_from_every_form_version_in_the_filtered_results(): void
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Versioned export form', null);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $schema = $forms->schemaArray($draft);
        $schema[] = $this->textField('legacy_context', 'Legacy context');
        $forms->replaceDraft($form, (int) $form->editor_version, $schema, null);
        $form = $form->fresh();
        $versionOne = $forms->publish($form, (int) $form->editor_version, null);
        $job = JobPosting::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $versionOne->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subDay(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 1,
        ]);
        $legacy = $this->jobApplicant($job, 'Legacy Applicant', 'legacy@example.test', JobApplication::STATUS_NEW, 1);
        $legacy->answers()->create([
            'application_form_field_id' => $versionOne->fields()->where('field_key', 'legacy_context')->valueOrFail('id'),
            'value_text' => '=LEGACY(1,1)',
        ]);

        $schema = collect($forms->schemaArray($versionOne))
            ->reject(fn (array $field): bool => $field['key'] === 'legacy_context')
            ->values()
            ->all();
        $schema[] = $this->textField('current_focus', 'Current focus');
        $form = $form->fresh();
        $forms->replaceDraft($form, (int) $form->editor_version, $schema, null);
        $form = $form->fresh();
        $versionTwo = $forms->publish($form, (int) $form->editor_version, null);
        $job->update(['current_form_version_id' => $versionTwo->id]);
        $current = $this->jobApplicant($job, 'Modern Applicant', 'modern@example.test', JobApplication::STATUS_NEW, 1);
        $current->answers()->create([
            'application_form_field_id' => $versionTwo->fields()->where('field_key', 'current_focus')->valueOrFail('id'),
            'value_text' => 'Ready',
        ]);

        $filters = ['sort' => 'name', 'direction' => 'asc'];
        $response = app(ApplicationExportService::class)->jobs(
            $job,
            app(ApplicationListingService::class)->jobs($job, $filters),
            ['name', 'answer:legacy_context', 'answer:current_focus', 'answer:not_in_results'],
            $this->owner('versioned-export-owner'),
            $filters,
        );
        ob_start();
        $response->sendContent();
        $rows = $this->csvRows((string) ob_get_clean());

        $this->assertSame(['Name', 'Legacy context', 'Current focus'], $rows[0]);
        $this->assertSame(['Legacy Applicant', "'=LEGACY(1,1)", ''], $rows[1]);
        $this->assertSame(['Modern Applicant', '', 'Ready'], $rows[2]);
        $this->assertCount(3, $rows);
    }

    public function test_workshop_filters_handle_unassigned_and_empty_exports_without_leaking_search_state(): void
    {
        $workshop = $this->workshop();
        $registration = WorkshopRegistration::query()->create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshop->current_form_version_id,
            'name' => '@SUM(1,1)',
            'email' => 'person@example.test',
            'workflow_status' => WorkshopRegistration::STATUS_WAITLISTED,
            'waitlisted_at' => now(),
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ]);
        $filters = [
            'status' => WorkshopRegistration::STATUS_WAITLISTED,
            'assigned_to' => 'unassigned',
            'sort' => 'waitlisted_at',
            'direction' => 'asc',
        ];
        $query = app(ApplicationListingService::class)->workshops($workshop, $filters);
        $this->assertSame([$registration->id], $query->pluck('id')->all());

        $response = app(ApplicationExportService::class)->workshops(
            $workshop,
            app(ApplicationListingService::class)->workshops($workshop, $filters),
            ['reference', 'name', 'status'],
            $this->owner('workshop-export-owner'),
            $filters,
        );
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();
        $this->assertStringContainsString("'@SUM(1,1)", $csv);
        $this->assertStringContainsString('waitlisted', $csv);

        $empty = app(ApplicationExportService::class)->workshops(
            $workshop,
            app(ApplicationListingService::class)->workshops($workshop, ['status' => WorkshopRegistration::STATUS_REJECTED]),
            [],
            $this->owner('empty-export-owner'),
        );
        ob_start();
        $empty->sendContent();
        $emptyCsv = (string) ob_get_clean();
        $this->assertSame(1, substr_count(trim(substr($emptyCsv, 3)), "\n") + 1, 'An empty export contains a header row only.');
    }

    /** @return array{JobPosting,ApplicationFormField} */
    private function job(): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Export form', null);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $schema = $forms->schemaArray($draft);
        $schema[] = $this->textField('motivation');
        $forms->replaceDraft($form, 1, $schema, null);
        $version = $forms->publish($form, 2, null);
        $job = JobPosting::query()->create([
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

        return [$job, $version->fields()->where('field_key', 'motivation')->firstOrFail()];
    }

    private function workshop(): Workshop
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Export workshop', null);
        $version = $forms->publish($form, 1, null);

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
            'capacity' => 1,
        ]);
    }

    private function jobApplicant(JobPosting $job, string $name, string $email, string $status, int $count): JobApplication
    {
        return JobApplication::query()->create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $job->current_form_version_id,
            'name' => $name,
            'email' => $email,
            'workflow_status' => $status,
            'submission_count' => $count,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
    }

    /** @return array<string, mixed> */
    private function textField(string $key, string $label = 'Motivation'): array
    {
        return [
            'key' => $key,
            'system_key' => null,
            'type' => ApplicationFormField::TYPE_SHORT_TEXT,
            'required' => false,
            'validation' => ['max_length' => 500],
            'translations' => [
                'en' => ['label' => $label, 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => 'প্রেরণা', 'help' => '', 'placeholder' => ''],
            ],
            'options' => [],
            'conditions' => [],
        ];
    }

    /** @return list<list<string|null>> */
    private function csvRows(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', rtrim(substr($csv, 3)));

        return array_map(
            fn (string $line): array => str_getcsv($line, ',', '"', ''),
            is_array($lines) ? $lines : [],
        );
    }

    private function owner(string $username = 'export-owner'): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();

        return Admin::query()->create([
            'name' => 'Export Owner',
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
