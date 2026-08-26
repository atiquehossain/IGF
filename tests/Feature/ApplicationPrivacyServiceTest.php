<?php

namespace Tests\Feature;

use App\Contracts\PrivateFileDeletion;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\ApplicationForm;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationDocument;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationPrivacyService;
use App\Services\PrivateApplicationDocumentService;
use App\Services\PrivateFileCleanupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\Support\ControllablePrivateFileDeletion;

class ApplicationPrivacyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(PrivateApplicationDocumentService::DISK);
    }

    public function test_only_owner_can_anonymize_and_the_service_removes_every_applicant_pii_surface(): void
    {
        [$jobApplication, $jobPath] = $this->jobApplication('private@example.test');
        $delegated = $this->admin(Role::query()->where('is_owner', false)->firstOrFail(), 'delegated');

        try {
            app(ApplicationPrivacyService::class)->anonymizeJob($jobApplication, $delegated);
            $this->fail('A delegated administrator cannot anonymize applicants.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('private@example.test', $jobApplication->fresh()->email);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($jobPath);

        $owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'owner');
        $anonymized = app(ApplicationPrivacyService::class)->anonymizeJob($jobApplication, $owner);

        $this->assertSame('Anonymized applicant', $anonymized->name);
        $this->assertStringStartsWith('anonymized-', $anonymized->email);
        $this->assertNull($anonymized->phone);
        $this->assertNotNull($anonymized->anonymized_at);
        $this->assertSame($owner->id, $anonymized->anonymized_by_admin_id);
        $this->assertSame(0, $anonymized->answers()->count());
        $this->assertSame(0, $anonymized->documents()->count());
        $this->assertSame('[removed during applicant anonymization]', $anonymized->notes()->firstOrFail()->body);
        $this->assertNull($anonymized->scores()->firstOrFail()->comment);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($jobPath);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.application.anonymized']);
    }

    public function test_workshop_anonymization_removes_answers_documents_and_note_text_but_preserves_operations(): void
    {
        [$registration, $path] = $this->workshopRegistration('registrant@example.test');
        $owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'workshop-owner');

        $anonymized = app(ApplicationPrivacyService::class)->anonymizeWorkshop($registration, $owner);

        $this->assertSame(0, $anonymized->answers()->count());
        $this->assertSame(0, $anonymized->documents()->count());
        $this->assertSame('[removed during applicant anonymization]', $anonymized->notes()->firstOrFail()->body);
        $this->assertSame(1, $anonymized->statusEvents()->count(), 'Non-identifying workflow history remains intact.');
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($path);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'workshop.registration.anonymized']);
    }

    public function test_explicit_deletion_removes_relationships_files_and_records_but_keeps_a_non_pii_audit_event(): void
    {
        [$jobApplication, $jobPath] = $this->jobApplication('delete-job@example.test');
        [$registration, $workshopPath] = $this->workshopRegistration('delete-workshop@example.test');
        $owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'deletion-owner');
        $jobId = $jobApplication->id;
        $registrationId = $registration->id;

        app(ApplicationPrivacyService::class)->deleteJob($jobApplication, $owner);
        app(ApplicationPrivacyService::class)->deleteWorkshop($registration, $owner);

        $this->assertNull(JobApplication::withTrashed()->find($jobId));
        $this->assertNull(WorkshopRegistration::withTrashed()->find($registrationId));
        $this->assertDatabaseMissing('job_application_answers', ['job_application_id' => $jobId]);
        $this->assertDatabaseMissing('job_application_notes', ['job_application_id' => $jobId]);
        $this->assertDatabaseMissing('workshop_registration_answers', ['workshop_registration_id' => $registrationId]);
        $this->assertDatabaseMissing('workshop_registration_notes', ['workshop_registration_id' => $registrationId]);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($jobPath);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($workshopPath);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.application.deleted']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'workshop.registration.deleted']);
        $this->assertDatabaseMissing('admin_audit_events', ['target_label_snapshot' => 'delete-job@example.test']);
    }

    public function test_audit_failure_rolls_back_privacy_metadata_and_cleanup_intent_without_touching_the_file(): void
    {
        [$application, $path] = $this->jobApplication('privacy-rollback@example.test');
        $owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'privacy-rollback-owner');

        Event::listen('eloquent.creating: ' . AdminAuditEvent::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            app(ApplicationPrivacyService::class)->anonymizeJob($application, $owner);
            $this->fail('The simulated audit failure should escape the privacy transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: ' . AdminAuditEvent::class);
        }

        $application->refresh();
        $this->assertSame('privacy-rollback@example.test', $application->email);
        $this->assertNull($application->anonymized_at);
        $this->assertSame(1, $application->documents()->count());
        $this->assertSame(1, $application->answers()->count());
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($path);
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'recruitment.application.anonymized']);
    }

    public function test_privacy_cleanup_failure_is_durable_and_retry_does_not_restore_removed_pii(): void
    {
        [$registration, $path] = $this->workshopRegistration('privacy-cleanup@example.test');
        $owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'privacy-cleanup-owner');
        $deleter = new ControllablePrivateFileDeletion();
        $this->app->instance(PrivateFileDeletion::class, $deleter);

        $anonymized = app(ApplicationPrivacyService::class)->anonymizeWorkshop($registration, $owner);

        $this->assertNotNull($anonymized->anonymized_at);
        $this->assertSame(0, $anonymized->documents()->count());
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($path);
        $this->assertDatabaseHas('private_file_cleanup_jobs', [
            'disk' => PrivateApplicationDocumentService::DISK,
            'path' => $path,
            'attempts' => 1,
            'last_error_code' => 'delete_failed',
        ]);

        $deleter->fail = false;
        $this->assertSame(
            ['claimed' => 1, 'deleted' => 1, 'failed' => 0],
            app(PrivateFileCleanupService::class)->processPending(),
        );
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($path);
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
        $this->assertSame('Anonymized applicant', $anonymized->fresh()->name);
    }

    /** @return array{JobApplication,string} */
    private function jobApplication(string $email): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, 'Privacy job form ' . $email, null);
        $version = $forms->publish($form, 1, null);
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
        $application = JobApplication::query()->create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $version->id,
            'name' => 'Private Person',
            'email' => $email,
            'phone' => '+8801700000000',
            'workflow_status' => JobApplication::STATUS_UNDER_REVIEW,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
        $field = $version->fields()->firstOrFail();
        $application->answers()->create(['application_form_field_id' => $field->id, 'value_text' => 'Sensitive answer']);
        $path = 'documents/' . str_repeat('a', 48) . '.pdf';
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, '%PDF-1.4 %%EOF');
        $application->documents()->create($this->document($field->id, $path, true));
        $application->notes()->create(['body' => 'Call Private Person', 'author_name_snapshot' => 'Reviewer']);
        $application->statusEvents()->create([
            'from_status' => JobApplication::STATUS_NEW,
            'to_status' => JobApplication::STATUS_UNDER_REVIEW,
            'source' => 'admin',
            'created_at' => now(),
        ]);
        $criterion = JobScorecardCriterion::query()->create([
            'job_posting_id' => $job->id,
            'label' => 'Experience',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $application->scores()->create([
            'job_scorecard_criterion_id' => $criterion->id,
            'score' => 8,
            'criterion_label_snapshot' => 'Experience',
            'maximum_score_snapshot' => 10,
            'comment' => 'Private Person is strong',
        ]);

        return [$application, $path];
    }

    /** @return array{WorkshopRegistration,string} */
    private function workshopRegistration(string $email): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Privacy workshop form ' . $email, null);
        $version = $forms->publish($form, 1, null);
        $workshop = Workshop::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
        ]);
        $registration = WorkshopRegistration::query()->create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $version->id,
            'name' => 'Workshop Person',
            'email' => $email,
            'phone' => '+8801800000000',
            'workflow_status' => WorkshopRegistration::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ]);
        $field = $version->fields()->firstOrFail();
        $registration->answers()->create(['application_form_field_id' => $field->id, 'value_text' => 'Sensitive response']);
        $path = 'documents/' . str_repeat('b', 48) . '.pdf';
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, '%PDF-1.4 %%EOF');
        $registration->documents()->create($this->document($field->id, $path, false));
        $registration->notes()->create(['body' => 'Workshop Person requested support', 'author_name_snapshot' => 'Manager']);
        $registration->statusEvents()->create([
            'from_status' => null,
            'to_status' => WorkshopRegistration::STATUS_CONFIRMED,
            'source' => 'system',
            'created_at' => now(),
        ]);

        return [$registration, $path];
    }

    /** @return array<string, mixed> */
    private function document(int $fieldId, string $path, bool $cv): array
    {
        return [
            'application_form_field_id' => $fieldId,
            'document_kind' => $cv ? JobApplicationDocument::KIND_CV : WorkshopRegistrationDocument::KIND_ATTACHMENT,
            'disk' => PrivateApplicationDocumentService::DISK,
            'path' => $path,
            'original_name' => 'Private Person CV.pdf',
            'mime_type' => 'application/pdf',
            'bytes' => 18,
            'sha256' => hash('sha256', '%PDF-1.4 %%EOF'),
        ];
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => ucfirst($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
