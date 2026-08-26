<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminListingPreference;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormCondition;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormFieldTranslation;
use App\Models\ApplicationFormOption;
use App\Models\ApplicationFormOptionTranslation;
use App\Models\ApplicationFormVersion;
use App\Models\ApplicationImportBatch;
use App\Models\ApplicationImportRow;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationNote;
use App\Models\WorkshopRegistrationStatusEvent;
use App\Support\ApplicationIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class RecruitmentWorkshopPersistenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_schema_is_complete_and_excludes_out_of_scope_tracking(): void
    {
        $tables = [
            'application_forms',
            'application_form_versions',
            'application_form_fields',
            'application_form_field_translations',
            'application_form_options',
            'application_form_option_translations',
            'application_form_conditions',
            'job_postings',
            'job_posting_translations',
            'job_applications',
            'job_application_answers',
            'job_application_documents',
            'job_application_notes',
            'job_application_status_events',
            'job_scorecard_criteria',
            'job_application_scores',
            'workshops',
            'workshop_translations',
            'workshop_registrations',
            'workshop_registration_answers',
            'workshop_registration_documents',
            'workshop_registration_notes',
            'workshop_registration_status_events',
            'application_import_batches',
            'application_import_rows',
            'admin_listing_preferences',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }

        $this->assertTrue(Schema::hasColumns('workshop_registrations', [
            'assigned_to_admin_id', 'email_hash', 'workflow_status', 'submission_count',
        ]));
        $this->assertTrue(Schema::hasColumns('job_application_answers', [
            'value_text', 'value_number', 'value_date', 'value_boolean', 'value_json',
        ]));
        $this->assertTrue(Schema::hasColumns('workshop_registration_answers', [
            'value_text', 'value_number', 'value_date', 'value_boolean', 'value_json',
        ]));

        foreach (['job_applications', 'workshop_registrations'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'user_id'));
            $this->assertFalse(Schema::hasColumn($table, 'payment_status'));
            $this->assertFalse(Schema::hasColumn($table, 'attended_at'));
            $this->assertFalse(Schema::hasColumn($table, 'qr_code'));
            $this->assertFalse(Schema::hasColumn($table, 'certificate_path'));
            $this->assertFalse(Schema::hasColumn($table, 'feedback'));
        }

        foreach ($this->foundationMigrationPaths() as $path) {
            (require $path)->up();
        }

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Rerun removed table {$table}");
        }
    }

    public function test_form_versions_and_their_normalized_children_become_immutable_after_publish(): void
    {
        [$form, $version] = $this->createFormVersion(ApplicationForm::PURPOSE_JOB);

        $choice = ApplicationFormField::create([
            'application_form_version_id' => $version->id,
            'field_key' => 'experience_level',
            'type' => ApplicationFormField::TYPE_DROPDOWN,
            'position' => 1,
            'is_required' => true,
            'validation' => ['max' => 1],
        ]);
        $details = ApplicationFormField::create([
            'application_form_version_id' => $version->id,
            'field_key' => 'experience_details',
            'type' => ApplicationFormField::TYPE_LONG_TEXT,
            'position' => 2,
            'is_required' => false,
        ]);
        ApplicationFormFieldTranslation::create([
            'application_form_field_id' => $choice->id,
            'locale' => 'en',
            'label' => 'Experience level',
        ]);
        $option = ApplicationFormOption::create([
            'application_form_field_id' => $choice->id,
            'option_key' => 'senior',
            'position' => 1,
        ]);
        ApplicationFormOptionTranslation::create([
            'application_form_option_id' => $option->id,
            'locale' => 'en',
            'label' => 'Senior',
        ]);
        ApplicationFormCondition::create([
            'target_field_id' => $details->id,
            'source_field_id' => $choice->id,
            'condition_group' => 1,
            'boolean_connector' => ApplicationFormCondition::CONNECTOR_AND,
            'operator' => ApplicationFormCondition::OPERATOR_EQUALS,
            'comparison_value' => ['senior'],
            'position' => 1,
        ]);

        $this->assertSame(2, $version->fields()->count());
        $this->assertSame('Senior', $option->translations()->firstOrFail()->label);
        $this->assertNotEmpty($form->uuid);

        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => str_repeat('a', 64),
            'published_at' => now(),
        ]);

        $this->assertThrowsLogicException(fn () => $choice->update(['is_required' => false]));
        $this->assertThrowsLogicException(fn () => $version->update(['schema_hash' => str_repeat('b', 64)]));
    }

    public function test_public_schedule_scopes_preserve_closed_details_and_use_strict_close_boundaries(): void
    {
        $at = CarbonImmutable::parse('2026-09-01 10:00:00');
        [$jobForm, $jobVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_JOB);
        [$workshopForm, $workshopVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_WORKSHOP);

        $job = $this->createJobPosting($jobForm, $jobVersion, [
            'visible_from_at' => $at,
            'application_opens_at' => $at->addHour(),
            'application_closes_at' => $at->addHours(2),
        ]);
        $workshop = $this->createWorkshop($workshopForm, $workshopVersion, [
            'visible_from_at' => $at,
            'registration_opens_at' => $at->addHour(),
            'registration_closes_at' => $at->addHours(2),
        ]);

        $this->assertFalse(JobPosting::publicDetail($at->subSecond())->whereKey($job->getKey())->exists());
        $this->assertTrue(JobPosting::publicDetail($at)->whereKey($job->getKey())->exists());
        $this->assertTrue(JobPosting::activeList($at)->whereKey($job->getKey())->exists());
        $this->assertFalse(JobPosting::openForSubmission($at)->whereKey($job->getKey())->exists());
        $this->assertTrue(JobPosting::openForSubmission($at->addHour())->whereKey($job->getKey())->exists());
        $this->assertFalse(JobPosting::activeList($at->addHours(2))->whereKey($job->getKey())->exists());
        $this->assertFalse(JobPosting::openForSubmission($at->addHours(2))->whereKey($job->getKey())->exists());
        $this->assertTrue(JobPosting::publicDetail($at->addYear())->whereKey($job->getKey())->exists());

        $this->assertFalse(Workshop::publicDetail($at->subSecond())->whereKey($workshop->getKey())->exists());
        $this->assertTrue(Workshop::publicDetail($at)->whereKey($workshop->getKey())->exists());
        $this->assertTrue(Workshop::activeList($at)->whereKey($workshop->getKey())->exists());
        $this->assertFalse(Workshop::openForSubmission($at)->whereKey($workshop->getKey())->exists());
        $this->assertTrue(Workshop::openForSubmission($at->addHour())->whereKey($workshop->getKey())->exists());
        $this->assertFalse(Workshop::activeList($at->addHours(2))->whereKey($workshop->getKey())->exists());
        $this->assertFalse(Workshop::openForSubmission($at->addHours(2))->whereKey($workshop->getKey())->exists());
        $this->assertTrue(Workshop::publicDetail($at->addYear())->whereKey($workshop->getKey())->exists());
    }

    public function test_email_identity_is_private_and_unique_per_listing_but_reusable_across_listings(): void
    {
        [$jobForm, $jobVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_JOB);
        [$workshopForm, $workshopVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_WORKSHOP);
        $firstJob = $this->createJobPosting($jobForm, $jobVersion);
        $secondJob = $this->createJobPosting($jobForm, $jobVersion);
        $workshop = $this->createWorkshop($workshopForm, $workshopVersion);

        $first = JobApplication::create([
            'job_posting_id' => $firstJob->id,
            'application_form_version_id' => $jobVersion->id,
            'name' => 'Applicant',
            'email' => ' Applicant@Example.COM ',
        ]);

        $this->assertSame('applicant@example.com', $first->email);
        $this->assertSame(ApplicationIdentity::emailHash('applicant@example.com'), $first->getRawOriginal('email_hash'));
        $this->assertMatchesRegularExpression('/^IGF-JOB-\d{8}-[A-Z0-9]{10}$/', $first->reference_number);

        JobApplication::create([
            'job_posting_id' => $secondJob->id,
            'application_form_version_id' => $jobVersion->id,
            'name' => 'Applicant',
            'email' => 'applicant@example.com',
        ]);
        $registration = WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshopVersion->id,
            'name' => 'Applicant',
            'email' => 'applicant@example.com',
        ]);
        $this->assertMatchesRegularExpression('/^IGF-WS-\d{8}-[A-Z0-9]{10}$/', $registration->reference_number);

        $this->assertThrowsQueryException(fn () => JobApplication::create([
            'job_posting_id' => $firstJob->id,
            'application_form_version_id' => $jobVersion->id,
            'name' => 'Duplicate',
            'email' => 'APPLICANT@example.com',
        ]));
        $this->assertThrowsQueryException(fn () => WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshopVersion->id,
            'name' => 'Duplicate',
            'email' => 'APPLICANT@example.com',
        ]));
    }

    public function test_assignment_import_preferences_and_append_only_history_relationships_are_persisted(): void
    {
        $admin = Admin::create(['name' => 'HR reviewer', 'email' => 'hr@example.com']);
        [$jobForm, $jobVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_JOB);
        [$workshopForm, $workshopVersion] = $this->createFormVersion(ApplicationForm::PURPOSE_WORKSHOP);
        $job = $this->createJobPosting($jobForm, $jobVersion);
        $workshop = $this->createWorkshop($workshopForm, $workshopVersion);

        $application = JobApplication::create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $jobVersion->id,
            'assigned_to_admin_id' => $admin->id,
            'name' => 'Job candidate',
            'email' => 'job@example.com',
        ]);
        $registration = WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshopVersion->id,
            'assigned_to_admin_id' => $admin->id,
            'name' => 'Workshop registrant',
            'email' => 'workshop@example.com',
        ]);

        $batch = ApplicationImportBatch::create([
            'target_kind' => ApplicationImportBatch::TARGET_JOB,
            'job_posting_id' => $job->id,
            'application_form_version_id' => $jobVersion->id,
            'form_schema_hash' => str_repeat('c', 64),
            'source_disk' => 'private',
            'source_path' => 'imports/job.csv',
            'source_name' => 'job.csv',
            'source_sha256' => str_repeat('d', 64),
            'column_mapping' => ['Email' => 'email'],
            'options' => ['duplicate_policy' => 'last_wins'],
            'uploaded_by_admin_id' => $admin->id,
        ]);
        $row = ApplicationImportRow::create([
            'application_import_batch_id' => $batch->id,
            'row_number' => 2,
            'state' => ApplicationImportRow::STATE_VALID,
            'action' => ApplicationImportRow::ACTION_UPDATE,
            'raw_data' => ['Email' => 'job@example.com'],
            'normalized_data' => ['email' => 'job@example.com'],
        ]);
        $preference = AdminListingPreference::create([
            'admin_id' => $admin->id,
            'listing_key' => 'job-applications',
            'visible_columns' => ['name', 'email', 'workflow_status'],
            'sort_column' => 'last_submitted_at',
            'sort_direction' => AdminListingPreference::SORT_DESC,
        ]);

        $jobNote = JobApplicationNote::create([
            'job_application_id' => $application->id,
            'author_admin_id' => $admin->id,
            'author_name_snapshot' => $admin->name,
            'body' => 'Reviewed CV.',
        ]);
        $jobEvent = JobApplicationStatusEvent::create([
            'job_application_id' => $application->id,
            'from_status' => JobApplication::STATUS_NEW,
            'to_status' => JobApplication::STATUS_UNDER_REVIEW,
            'actor_admin_id' => $admin->id,
            'actor_name_snapshot' => $admin->name,
        ]);
        $workshopNote = WorkshopRegistrationNote::create([
            'workshop_registration_id' => $registration->id,
            'author_admin_id' => $admin->id,
            'author_name_snapshot' => $admin->name,
            'body' => 'Manual approval checked.',
        ]);
        $workshopEvent = WorkshopRegistrationStatusEvent::create([
            'workshop_registration_id' => $registration->id,
            'from_status' => WorkshopRegistration::STATUS_PENDING,
            'to_status' => WorkshopRegistration::STATUS_CONFIRMED,
            'actor_admin_id' => $admin->id,
            'actor_name_snapshot' => $admin->name,
        ]);

        $this->assertTrue($application->assignedAdmin->is($admin));
        $this->assertTrue($registration->assignedAdmin->is($admin));
        $this->assertIsInt($registration->assigned_to_admin_id);
        $this->assertTrue($batch->jobPosting->is($job));
        $this->assertSame(['email' => 'job@example.com'], $row->normalized_data);
        $this->assertSame(['name', 'email', 'workflow_status'], $preference->visible_columns);
        $this->assertThrowsLogicException(fn () => $jobNote->update(['body' => 'Changed']));
        $this->assertThrowsLogicException(fn () => $jobEvent->update(['to_status' => JobApplication::STATUS_REJECTED]));
        $this->assertThrowsLogicException(fn () => $workshopNote->update(['body' => 'Changed']));
        $this->assertThrowsLogicException(fn () => $workshopEvent->delete());
    }

    public function test_data_bearing_foundation_migration_refuses_destructive_rollback(): void
    {
        $this->createFormVersion(ApplicationForm::PURPOSE_JOB);
        $migration = require database_path('migrations/2026_08_26_090000_create_application_form_foundation.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production data exists');

        $migration->down();
    }

    private function createFormVersion(string $purpose): array
    {
        $form = ApplicationForm::create([
            'purpose' => $purpose,
            'name' => ucfirst($purpose) . ' form',
        ]);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);

        return [$form, $version];
    }

    private function createJobPosting(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        array $overrides = []
    ): JobPosting {
        $at = CarbonImmutable::parse('2026-09-01 10:00:00');

        return JobPosting::create(array_merge([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => $at->subDay(),
            'application_opens_at' => $at->subHour(),
            'application_closes_at' => $at->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
        ], $overrides));
    }

    private function createWorkshop(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        array $overrides = []
    ): Workshop {
        $at = CarbonImmutable::parse('2026-09-01 10:00:00');

        return Workshop::create(array_merge([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => $at->subDay(),
            'registration_opens_at' => $at->subHour(),
            'registration_closes_at' => $at->addDay(),
            'starts_at' => $at->addDays(2),
            'ends_at' => $at->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
        ], $overrides));
    }

    private function foundationMigrationPaths(): array
    {
        return [
            database_path('migrations/2026_08_26_090000_create_application_form_foundation.php'),
            database_path('migrations/2026_08_26_090100_create_job_posting_foundation.php'),
            database_path('migrations/2026_08_26_090200_create_job_application_foundation.php'),
            database_path('migrations/2026_08_26_090300_create_workshop_foundation.php'),
            database_path('migrations/2026_08_26_090400_create_workshop_registration_foundation.php'),
            database_path('migrations/2026_08_26_090500_create_application_import_foundation.php'),
        ];
    }

    private function assertThrowsLogicException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a LogicException.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertThrowsQueryException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a unique-key QueryException.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
