<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationStatusEvent;
use App\Services\JobApplicationWorkflowService;
use App\Services\WorkshopRegistrationWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class RecruitmentWorkshopWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_job_workflow_enforces_transitions_and_audits_assignment_notes_and_scores_without_pii(): void
    {
        [$posting, $version] = $this->jobContext();
        $application = $this->jobApplication($posting, $version, 'private-candidate@example.test', 'Private Candidate');
        $actor = $this->admin('actor');
        $assignee = $this->admin('assignee');
        $service = app(JobApplicationWorkflowService::class);

        $application = $service->transition($application, JobApplication::STATUS_UNDER_REVIEW, $actor);
        $this->assertSame(JobApplication::STATUS_UNDER_REVIEW, $application->workflow_status);
        $this->assertSame(JobApplicationStatusEvent::SOURCE_ADMIN, $application->statusEvents->sole()->source);
        $this->assertSame($actor->id, $application->statusEvents->sole()->actor_admin_id);

        $eventCount = $application->statusEvents()->count();
        try {
            $service->transition($application, JobApplication::STATUS_HIRED, $actor);
            $this->fail('An invalid workflow transition should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(JobApplication::STATUS_UNDER_REVIEW, $application->fresh()->workflow_status);
        $this->assertSame($eventCount, $application->statusEvents()->count());

        $application = $service->assign($application, $assignee, $actor);
        $this->assertTrue($application->assignedAdmin->is($assignee));
        $note = $service->addNote($application, 'Applicant disclosed confidential household details.', $actor);
        $this->assertSame($actor->id, $note->author_admin_id);

        $criterion = JobScorecardCriterion::create([
            'job_posting_id' => $posting->id,
            'label' => 'Relevant experience',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $score = $service->score($application, $criterion, 7.5, $actor, 'Private scoring rationale.');
        $updated = $service->score($application, $criterion, 9, $actor, 'Updated private rationale.');
        $this->assertSame($score->id, $updated->id);
        $this->assertSame('9.00', $updated->score);
        $this->assertSame('Relevant experience', $updated->criterion_label_snapshot);
        $this->assertDatabaseCount('job_application_scores', 1);
        Mail::assertNothingSent();

        $auditJson = AdminAuditEvent::query()
            ->where('action', 'like', 'job_application.%')
            ->get()
            ->toJson();
        $this->assertStringNotContainsString('private-candidate@example.test', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('Private Candidate', $auditJson);
        $this->assertStringNotContainsString('confidential household', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('private rationale', mb_strtolower($auditJson));
    }

    public function test_job_bulk_operations_are_bounded_atomic_and_audited(): void
    {
        [$posting, $version] = $this->jobContext();
        $actor = $this->admin('bulk-actor');
        $assignee = $this->admin('bulk-assignee');
        $first = $this->jobApplication($posting, $version, 'first@example.test', 'First');
        $second = $this->jobApplication($posting, $version, 'second@example.test', 'Second');
        $service = app(JobApplicationWorkflowService::class);

        $changed = $service->bulkTransition([$second->id, $first->id], JobApplication::STATUS_UNDER_REVIEW, $actor);
        $this->assertSame(
            [JobApplication::STATUS_UNDER_REVIEW, JobApplication::STATUS_UNDER_REVIEW],
            $changed->pluck('workflow_status')->all(),
        );
        $assigned = $service->bulkAssign([$first->id, $second->id], $assignee, $actor);
        $this->assertSame([$assignee->id, $assignee->id], $assigned->pluck('assigned_to_admin_id')->all());
        $this->assertDatabaseCount('job_application_status_events', 2);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'job_application.bulk_status_changed']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'job_application.bulk_assigned']);

        $second->update(['workflow_status' => JobApplication::STATUS_HIRED]);
        $beforeEvents = JobApplicationStatusEvent::query()->count();
        try {
            $service->bulkTransition([$first->id, $second->id], JobApplication::STATUS_SHORTLISTED, $actor);
            $this->fail('One invalid member must roll back the whole bulk transition.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(JobApplication::STATUS_UNDER_REVIEW, $first->fresh()->workflow_status);
        $this->assertSame(JobApplication::STATUS_HIRED, $second->fresh()->workflow_status);
        $this->assertSame($beforeEvents, JobApplicationStatusEvent::query()->count());

        try {
            $service->bulkAssign(range(1, JobApplicationWorkflowService::MAX_BULK_RECORDS + 1), $assignee, $actor);
            $this->fail('Oversized bulk operations should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('applications', $exception->errors());
        }
    }

    public function test_failed_job_audit_rolls_back_status_and_append_only_event(): void
    {
        [$posting, $version] = $this->jobContext();
        $application = $this->jobApplication($posting, $version, 'audit-rollback@example.test', 'Audit Rollback');
        $actor = $this->admin('audit-rollback-actor');

        Event::listen('eloquent.creating: ' . AdminAuditEvent::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            app(JobApplicationWorkflowService::class)->transition(
                $application,
                JobApplication::STATUS_UNDER_REVIEW,
                $actor,
            );
            $this->fail('The simulated audit failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: ' . AdminAuditEvent::class);
        }

        $this->assertSame(JobApplication::STATUS_NEW, $application->fresh()->workflow_status);
        $this->assertDatabaseCount('job_application_status_events', 0);
    }

    public function test_confirmed_cancellation_promotes_oldest_waitlist_without_overbooking(): void
    {
        [$workshop, $version] = $this->workshopContext(Workshop::REGISTRATION_WAITLIST, 1);
        $actor = $this->admin('workshop-actor');
        $confirmed = $this->registration($workshop, $version, 'confirmed@example.test', WorkshopRegistration::STATUS_CONFIRMED, now()->subHours(3));
        $oldest = $this->registration($workshop, $version, 'oldest@example.test', WorkshopRegistration::STATUS_WAITLISTED, now()->subHours(2));
        $newer = $this->registration($workshop, $version, 'newer@example.test', WorkshopRegistration::STATUS_WAITLISTED, now()->subHour());
        $service = app(WorkshopRegistrationWorkflowService::class);

        $cancelled = $service->transition($confirmed, WorkshopRegistration::STATUS_CANCELLED, $actor);
        $this->assertSame(WorkshopRegistration::STATUS_CANCELLED, $cancelled->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $oldest->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_WAITLISTED, $newer->fresh()->workflow_status);
        $this->assertSame(1, $workshop->registrations()->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)->count());
        $promotion = $oldest->statusEvents()->latest('id')->firstOrFail();
        $this->assertSame(WorkshopRegistrationStatusEvent::SOURCE_SYSTEM, $promotion->source);
        $this->assertNull($promotion->actor_admin_id);

        $beforeEvents = $newer->statusEvents()->count();
        try {
            $service->transition($newer, WorkshopRegistration::STATUS_CONFIRMED, $actor);
            $this->fail('A full workshop must not overbook through manual confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(WorkshopRegistration::STATUS_WAITLISTED, $newer->fresh()->workflow_status);
        $this->assertSame($beforeEvents, $newer->statusEvents()->count());

        $service->transition($oldest->fresh(), WorkshopRegistration::STATUS_CANCELLED, $actor);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $newer->fresh()->workflow_status);
        $this->assertSame(1, $workshop->registrations()->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)->count());
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'workshop_registration.waitlist_promoted']);
        Mail::assertNothingSent();
    }

    public function test_cancellation_never_auto_promotes_outside_an_active_published_waitlist_window(): void
    {
        $this->travelTo(now()->startOfSecond());
        $actor = $this->admin('promotion-eligibility-actor');
        $service = app(WorkshopRegistrationWorkflowService::class);
        $cases = [
            'manual mode' => static function (Workshop $workshop): void {
                $workshop->forceFill(['registration_mode' => Workshop::REGISTRATION_MANUAL])->save();
            },
            'closed registration' => static function (Workshop $workshop): void {
                $workshop->forceFill(['registration_closes_at' => now()])->save();
            },
            'started workshop' => static function (Workshop $workshop): void {
                $workshop->forceFill([
                    'starts_at' => now(),
                    'ends_at' => now()->addHours(2),
                ])->save();
            },
            'withdrawn workshop' => static function (Workshop $workshop): void {
                $workshop->forceFill(['publication_status' => Workshop::PUBLICATION_WITHDRAWN])->save();
            },
            'draft workshop' => static function (Workshop $workshop): void {
                $workshop->forceFill(['publication_status' => Workshop::PUBLICATION_DRAFT])->save();
            },
            'missing visibility' => static function (Workshop $workshop): void {
                $workshop->forceFill(['visible_from_at' => null])->save();
            },
            'not yet visible' => static function (Workshop $workshop): void {
                $workshop->forceFill(['visible_from_at' => now()->addMinute()])->save();
            },
            'registration not open' => static function (Workshop $workshop): void {
                $workshop->forceFill(['registration_opens_at' => now()->addMinute()])->save();
            },
        ];

        foreach ($cases as $label => $mutateWorkshop) {
            [$workshop, $version] = $this->workshopContext(Workshop::REGISTRATION_WAITLIST, 1);
            $mutateWorkshop($workshop);
            $confirmed = $this->registration(
                $workshop,
                $version,
                Str::slug($label) . '-confirmed@example.test',
                WorkshopRegistration::STATUS_CONFIRMED,
                now()->subHour(),
            );
            $waiting = $this->registration(
                $workshop,
                $version,
                Str::slug($label) . '-waiting@example.test',
                WorkshopRegistration::STATUS_WAITLISTED,
                now()->subMinutes(30),
            );

            $service->transition($confirmed, WorkshopRegistration::STATUS_CANCELLED, $actor);

            $this->assertSame(
                WorkshopRegistration::STATUS_WAITLISTED,
                $waiting->fresh()->workflow_status,
                "Waitlisted registration was incorrectly promoted for {$label}.",
            );
            $this->assertDatabaseMissing('workshop_registration_status_events', [
                'workshop_registration_id' => $waiting->id,
                'to_status' => WorkshopRegistration::STATUS_CONFIRMED,
                'source' => WorkshopRegistrationStatusEvent::SOURCE_SYSTEM,
            ]);
        }

        $this->assertDatabaseMissing('admin_audit_events', [
            'action' => 'workshop_registration.waitlist_promoted',
        ]);
        Mail::assertNothingSent();
    }

    public function test_workshop_manual_bulk_confirmation_is_atomic_and_supports_assignment_and_notes(): void
    {
        [$workshop, $version] = $this->workshopContext(Workshop::REGISTRATION_MANUAL, 1);
        $actor = $this->admin('manual-actor');
        $assignee = $this->admin('manual-assignee');
        $first = $this->registration($workshop, $version, 'manual-first@example.test', WorkshopRegistration::STATUS_PENDING);
        $second = $this->registration($workshop, $version, 'manual-second@example.test', WorkshopRegistration::STATUS_PENDING);
        $service = app(WorkshopRegistrationWorkflowService::class);

        try {
            $service->bulkTransition([$first->id, $second->id], WorkshopRegistration::STATUS_CONFIRMED, $actor);
            $this->fail('Bulk confirmation beyond capacity should roll back every registration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(WorkshopRegistration::STATUS_PENDING, $first->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_PENDING, $second->fresh()->workflow_status);
        $this->assertDatabaseCount('workshop_registration_status_events', 0);

        $confirmed = $service->transition($first, WorkshopRegistration::STATUS_CONFIRMED, $actor);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $confirmed->workflow_status);
        $assigned = $service->bulkAssign([$first->id, $second->id], $assignee, $actor);
        $this->assertSame([$assignee->id, $assignee->id], $assigned->pluck('assigned_to_admin_id')->all());
        $note = $service->addNote($second, 'Private registration review note.', $actor);
        $this->assertSame($actor->id, $note->author_admin_id);

        $auditJson = AdminAuditEvent::query()
            ->where('action', 'like', 'workshop_registration.%')
            ->get()
            ->toJson();
        $this->assertStringNotContainsString('manual-first@example.test', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('Private registration review note.', $auditJson);
    }

    public function test_failed_promotion_audit_rolls_back_cancellation_promotion_and_events(): void
    {
        [$workshop, $version] = $this->workshopContext(Workshop::REGISTRATION_WAITLIST, 1);
        $actor = $this->admin('promotion-rollback-actor');
        $confirmed = $this->registration(
            $workshop,
            $version,
            'promotion-trigger@example.test',
            WorkshopRegistration::STATUS_CONFIRMED,
            now()->subHour(),
        );
        $waiting = $this->registration(
            $workshop,
            $version,
            'promotion-waiting@example.test',
            WorkshopRegistration::STATUS_WAITLISTED,
            now(),
        );

        Event::listen('eloquent.creating: ' . AdminAuditEvent::class, fn () => throw new RuntimeException('Audit unavailable'));
        try {
            app(WorkshopRegistrationWorkflowService::class)->transition(
                $confirmed,
                WorkshopRegistration::STATUS_CANCELLED,
                $actor,
            );
            $this->fail('The simulated promotion audit failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: ' . AdminAuditEvent::class);
        }

        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $confirmed->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_WAITLISTED, $waiting->fresh()->workflow_status);
        $this->assertDatabaseCount('workshop_registration_status_events', 0);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    /** @return array{0: JobPosting, 1: ApplicationFormVersion} */
    private function jobContext(): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_JOB);
        $posting = JobPosting::create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
        ]);

        return [$posting, $version];
    }

    /** @return array{0: Workshop, 1: ApplicationFormVersion} */
    private function workshopContext(string $mode, ?int $capacity): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = Workshop::create([
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

        return [$workshop, $version];
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function formVersion(string $purpose): array
    {
        $form = ApplicationForm::create(['purpose' => $purpose, 'name' => ucfirst($purpose) . ' workflow form']);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose . '-workflow'),
            'published_at' => now(),
        ]);

        return [$form, $version];
    }

    private function jobApplication(
        JobPosting $posting,
        ApplicationFormVersion $version,
        string $email,
        string $name,
        string $status = JobApplication::STATUS_NEW,
    ): JobApplication {
        return JobApplication::create([
            'job_posting_id' => $posting->id,
            'application_form_version_id' => $version->id,
            'name' => $name,
            'email' => $email,
            'workflow_status' => $status,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
    }

    private function registration(
        Workshop $workshop,
        ApplicationFormVersion $version,
        string $email,
        string $status,
        mixed $statusAt = null,
    ): WorkshopRegistration {
        return WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $version->id,
            'name' => 'Private Registrant',
            'email' => $email,
            'workflow_status' => $status,
            'waitlisted_at' => $status === WorkshopRegistration::STATUS_WAITLISTED ? ($statusAt ?: now()) : null,
            'confirmed_at' => $status === WorkshopRegistration::STATUS_CONFIRMED ? ($statusAt ?: now()) : null,
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ]);
    }

    private function admin(string $suffix): Admin
    {
        return Admin::create([
            'name' => ucfirst(str_replace('-', ' ', $suffix)),
            'username' => $suffix,
            'email' => $suffix . '@example.test',
            'status' => 1,
        ]);
    }
}
