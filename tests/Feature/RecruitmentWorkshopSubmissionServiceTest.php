<?php

namespace Tests\Feature;

use App\Contracts\PrivateFileDeletion;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\JobApplicationSubmissionService;
use App\Services\PrivateApplicationDocumentService;
use App\Services\WorkshopRegistrationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\ControllablePrivateFileDeletion;
use Tests\Support\ValidPdfFixture;

class RecruitmentWorkshopSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateApplicationDocumentService::DISK);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_job_resubmission_replaces_latest_answers_and_files_while_preserving_identity_and_workflow(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $posting = $this->jobPosting($form, $version);
        $service = app(JobApplicationSubmissionService::class);

        $first = $service->submit($posting, $this->jobPayload(
            'Candidate@Example.test',
            'First Candidate Name',
            'First answer',
            'first.pdf',
        ));
        $oldReference = $first->reference_number;
        $oldFirstSubmittedAt = $first->first_submitted_at;
        $oldPath = $first->documents->sole()->path;
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($oldPath);

        $reviewer = $this->admin('reviewer');
        $first->update([
            'workflow_status' => JobApplication::STATUS_UNDER_REVIEW,
            'assigned_to_admin_id' => $reviewer->id,
        ]);

        CarbonImmutable::setTestNow('2026-09-10 11:00:00');
        $latest = $service->submit($posting, $this->jobPayload(
            ' candidate@example.TEST ',
            'Updated Candidate Name',
            'Latest answer',
            'latest.pdf',
        ));

        $this->assertSame($first->id, $latest->id);
        $this->assertSame($oldReference, $latest->reference_number);
        $this->assertTrue($oldFirstSubmittedAt->equalTo($latest->first_submitted_at));
        $this->assertSame(2, $latest->submission_count);
        $this->assertSame(JobApplication::STATUS_UNDER_REVIEW, $latest->workflow_status);
        $this->assertSame($reviewer->id, $latest->assigned_to_admin_id);
        $this->assertSame('Updated Candidate Name', $latest->name);
        $this->assertSame('candidate@example.test', $latest->email);
        $this->assertSame('Latest answer', $latest->answers->sole()->value_text);
        $this->assertCount(1, $latest->documents);
        $this->assertNotSame($oldPath, $latest->documents->sole()->path);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($oldPath);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($latest->documents->sole()->path);
        $this->assertDatabaseCount('job_applications', 1);
        $this->assertDatabaseCount('job_application_answers', 1);
        $this->assertDatabaseCount('job_application_documents', 1);
        $this->assertDatabaseCount('job_application_status_events', 1);
        Mail::assertNothingSent();

        $auditJson = AdminAuditEvent::query()
            ->whereIn('action', ['job_application.submitted', 'job_application.resubmitted'])
            ->get()
            ->toJson();
        $this->assertStringNotContainsString('candidate@example.test', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('Updated Candidate Name', $auditJson);
    }

    public function test_submission_rolls_back_database_and_new_files_when_atomic_audit_fails(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $posting = $this->jobPosting($form, $version);
        $service = app(JobApplicationSubmissionService::class);
        $application = $service->submit($posting, $this->jobPayload(
            'rollback@example.test',
            'Rollback Candidate',
            'Original answer',
            'original.pdf',
        ));
        $oldPath = $application->documents->sole()->path;
        $beforeAuditCount = AdminAuditEvent::query()->count();

        Event::listen('eloquent.creating: ' . AdminAuditEvent::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $service->submit($posting, $this->jobPayload(
                'rollback@example.test',
                'Changed Candidate',
                'Must roll back',
                'rolled-back.pdf',
            ));
            $this->fail('The simulated audit failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: ' . AdminAuditEvent::class);
        }

        $application->refresh()->load(['answers', 'documents']);
        $this->assertSame(1, $application->submission_count);
        $this->assertSame('Rollback Candidate', $application->name);
        $this->assertSame('Original answer', $application->answers->sole()->value_text);
        $this->assertSame($oldPath, $application->documents->sole()->path);
        $this->assertSame([$oldPath], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
        $this->assertSame($beforeAuditCount, AdminAuditEvent::query()->count());
    }

    public function test_workshop_modes_enforce_capacity_and_duplicate_updates_never_consume_another_seat(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $service = app(WorkshopRegistrationService::class);

        $manual = $this->workshop($form, $version, Workshop::REGISTRATION_MANUAL, 1);
        $manualRegistration = $service->submit($manual, $this->workshopPayload('manual@example.test'));
        $this->assertSame(WorkshopRegistration::STATUS_PENDING, $manualRegistration->workflow_status);

        $automatic = $this->workshop($form, $version, Workshop::REGISTRATION_AUTOMATIC, 1);
        $automaticFirst = $service->submit($automatic, $this->workshopPayload('auto-one@example.test'));
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $automaticFirst->workflow_status);
        $automaticDuplicate = $service->submit($automatic, $this->workshopPayload('AUTO-ONE@example.test', 'Updated Auto'));
        $this->assertSame($automaticFirst->id, $automaticDuplicate->id);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $automaticDuplicate->workflow_status);
        $this->assertSame(2, $automaticDuplicate->submission_count);
        try {
            $service->submit($automatic, $this->workshopPayload('auto-two@example.test'));
            $this->fail('Automatic mode must reject a new registration when capacity is full.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission', $exception->errors());
        }
        $this->assertSame(1, $automatic->registrations()->count());

        $waitlist = $this->workshop($form, $version, Workshop::REGISTRATION_WAITLIST, 1);
        $confirmed = $service->submit($waitlist, $this->workshopPayload('seat@example.test'));
        $waiting = $service->submit($waitlist, $this->workshopPayload('wait@example.test'));
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $confirmed->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_WAITLISTED, $waiting->workflow_status);
        $waitingAgain = $service->submit($waitlist, $this->workshopPayload('WAIT@example.test', 'Updated Waiting'));
        $this->assertSame($waiting->id, $waitingAgain->id);
        $this->assertSame(WorkshopRegistration::STATUS_WAITLISTED, $waitingAgain->workflow_status);
        $this->assertSame(2, $waitingAgain->submission_count);
        $this->assertSame(1, $waitlist->registrations()->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)->count());

        $unlimited = $this->workshop($form, $version, Workshop::REGISTRATION_AUTOMATIC, null);
        $this->assertSame(
            WorkshopRegistration::STATUS_CONFIRMED,
            $service->submit($unlimited, $this->workshopPayload('unlimited-one@example.test'))->workflow_status,
        );
        $this->assertSame(
            WorkshopRegistration::STATUS_CONFIRMED,
            $service->submit($unlimited, $this->workshopPayload('unlimited-two@example.test'))->workflow_status,
        );
        Mail::assertNothingSent();
    }

    public function test_exact_server_deadline_rejects_without_staging_or_persisting(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $posting = $this->jobPosting($form, $version, ['application_closes_at' => now()]);

        try {
            app(JobApplicationSubmissionService::class)->submit(
                $posting,
                $this->jobPayload('closed@example.test', 'Closed Candidate', 'No save', 'closed.pdf'),
            );
            $this->fail('The exact closing instant must reject the submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission', $exception->errors());
        }

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'job_application.submitted']);
    }

    public function test_job_and_workshop_resubmission_cleanup_failures_are_durable_and_retryable(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        $deleter = new ControllablePrivateFileDeletion();
        $this->app->instance(PrivateFileDeletion::class, $deleter);

        [$jobForm, $jobVersion] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $posting = $this->jobPosting($jobForm, $jobVersion);
        $jobService = app(JobApplicationSubmissionService::class);
        $firstApplication = $jobService->submit(
            $posting,
            $this->jobPayload('durable-job@example.test', 'Durable Job', 'First', 'first-durable.pdf'),
        );
        $oldJobPath = $firstApplication->documents->sole()->path;
        $latestApplication = $jobService->submit(
            $posting,
            $this->jobPayload('durable-job@example.test', 'Durable Job', 'Latest', 'latest-durable.pdf'),
        );

        [$workshopForm, $workshopVersion] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = $this->workshop($workshopForm, $workshopVersion, Workshop::REGISTRATION_AUTOMATIC, null);
        $workshopService = app(WorkshopRegistrationService::class);
        $firstRegistration = $workshopService->submit($workshop, $this->workshopPayload('durable-workshop@example.test'));
        $oldWorkshopPath = 'documents/' . str_repeat('e', 48) . '.pdf';
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($oldWorkshopPath, $this->pdfBytes());
        $firstRegistration->documents()->create([
            'application_form_field_id' => $workshopVersion->fields()->where('field_key', 'motivation')->value('id'),
            'document_kind' => 'attachment',
            'disk' => PrivateApplicationDocumentService::DISK,
            'path' => $oldWorkshopPath,
            'original_name' => 'workshop.pdf',
            'mime_type' => 'application/pdf',
            'bytes' => strlen($this->pdfBytes()),
            'sha256' => hash('sha256', $this->pdfBytes()),
        ]);
        $latestRegistration = $workshopService->submit(
            $workshop,
            $this->workshopPayload('durable-workshop@example.test', 'Latest Workshop'),
        );

        $this->assertSame(2, $latestApplication->submission_count);
        $this->assertSame(1, $latestApplication->documents()->count());
        $this->assertSame(2, $latestRegistration->submission_count);
        $this->assertSame(0, $latestRegistration->documents()->count());
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($oldJobPath);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($oldWorkshopPath);
        $this->assertDatabaseHas('private_file_cleanup_jobs', ['path' => $oldJobPath, 'last_error_code' => 'delete_failed']);
        $this->assertDatabaseHas('private_file_cleanup_jobs', ['path' => $oldWorkshopPath, 'last_error_code' => 'delete_failed']);

        $deleter->fail = false;
        $result = app(\App\Services\PrivateFileCleanupService::class)->processPending();
        $this->assertSame(['claimed' => 2, 'deleted' => 2, 'failed' => 0], $result);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($oldJobPath);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($oldWorkshopPath);
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function publishedForm(string $purpose, bool $withCv = false): array
    {
        $form = ApplicationForm::create(['purpose' => $purpose, 'name' => ucfirst($purpose) . ' form']);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);

        $position = 1;
        $this->field($version, 'full-name', ApplicationFormField::TYPE_SHORT_TEXT, $position++, true, ApplicationFormField::SYSTEM_FULL_NAME);
        $this->field($version, 'email', ApplicationFormField::TYPE_EMAIL, $position++, true, ApplicationFormField::SYSTEM_EMAIL);
        $this->field($version, 'phone', ApplicationFormField::TYPE_PHONE, $position++, false, ApplicationFormField::SYSTEM_PHONE);
        if ($withCv) {
            $this->field($version, 'cv', ApplicationFormField::TYPE_FILE, $position++, true, ApplicationFormField::SYSTEM_CV);
        }
        $this->field($version, 'motivation', ApplicationFormField::TYPE_LONG_TEXT, $position, true);

        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose . '-test-schema'),
            'published_at' => now(),
        ]);

        return [$form, $version->fresh()];
    }

    private function field(
        ApplicationFormVersion $version,
        string $key,
        string $type,
        int $position,
        bool $required,
        ?string $systemKey = null,
    ): ApplicationFormField {
        $field = $version->fields()->create([
            'field_key' => $key,
            'system_key' => $systemKey,
            'type' => $type,
            'position' => $position,
            'is_required' => $required,
            'validation' => $type === ApplicationFormField::TYPE_FILE
                ? ['max_kb' => 5120, 'extensions' => ['pdf']]
                : null,
        ]);
        $field->translations()->create(['locale' => 'en', 'label' => ucfirst(str_replace('-', ' ', $key))]);

        return $field;
    }

    private function jobPosting(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        array $overrides = [],
    ): JobPosting {
        return JobPosting::create(array_merge([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
        ], $overrides));
    }

    private function workshop(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        string $mode,
        ?int $capacity,
    ): Workshop {
        return Workshop::create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => $mode,
            'capacity' => $capacity,
        ]);
    }

    /** @return array<string, mixed> */
    private function jobPayload(string $email, string $name, string $answer, string $filename): array
    {
        return [
            'applicant_name' => $name,
            'email' => $email,
            'phone' => '+880 1712 345678',
            'cv' => UploadedFile::fake()->createWithContent($filename, $this->pdfBytes()),
            'responses' => ['motivation' => $answer],
        ];
    }

    /** @return array<string, mixed> */
    private function workshopPayload(string $email, string $name = 'Workshop Applicant'): array
    {
        return [
            'applicant_name' => $name,
            'email' => $email,
            'phone' => '+880 1712 345678',
            'responses' => ['motivation' => 'I want to learn.'],
        ];
    }

    private function admin(string $suffix): Admin
    {
        return Admin::create([
            'name' => ucfirst($suffix),
            'username' => $suffix,
            'email' => $suffix . '@example.test',
            'status' => 1,
        ]);
    }

    private function pdfBytes(): string
    {
        return ValidPdfFixture::bytes();
    }
}
