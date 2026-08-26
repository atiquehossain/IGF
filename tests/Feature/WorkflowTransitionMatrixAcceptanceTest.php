<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\JobApplicationWorkflowService;
use App\Services\WorkshopRegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowTransitionMatrixAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_job_status_pair_matches_the_authoritative_transition_matrix(): void
    {
        [$job, $version] = $this->jobContext();
        $actor = $this->actor();
        $service = app(JobApplicationWorkflowService::class);
        $sequence = 0;

        foreach (JobApplication::STATUSES as $from) {
            foreach (JobApplication::STATUSES as $to) {
                $application = JobApplication::query()->create([
                    'job_posting_id' => $job->id,
                    'application_form_version_id' => $version->id,
                    'name' => 'Matrix Private Candidate',
                    'email' => 'job-matrix-' . (++$sequence) . '@private.example.test',
                    'workflow_status' => $from,
                    'source' => JobApplication::SOURCE_PUBLIC,
                ]);
                $allowed = in_array($to, JobApplicationWorkflowService::TRANSITIONS[$from] ?? [], true);
                $beforeAudits = AdminAuditEvent::query()->count();

                try {
                    $result = $service->transition($application, $to, $actor);
                    $this->assertTrue($allowed, "Unexpectedly allowed job transition {$from} -> {$to}.");
                    $this->assertSame($to, $result->workflow_status);
                    $this->assertSame(1, $application->statusEvents()->count());
                    $this->assertSame($beforeAudits + 1, AdminAuditEvent::query()->count());
                } catch (ValidationException $exception) {
                    $this->assertFalse($allowed, "Unexpectedly rejected job transition {$from} -> {$to}.");
                    $this->assertArrayHasKey('workflow_status', $exception->errors());
                    $this->assertSame($from, $application->fresh()->workflow_status);
                    $this->assertSame(0, $application->statusEvents()->count());
                    $this->assertSame($beforeAudits, AdminAuditEvent::query()->count());
                }
            }
        }

        $auditJson = AdminAuditEvent::query()->get()->toJson();
        $this->assertStringNotContainsString('private.example.test', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('Matrix Private Candidate', $auditJson);
    }

    public function test_every_workshop_status_pair_matches_the_authoritative_transition_matrix(): void
    {
        $actor = $this->actor();
        $service = app(WorkshopRegistrationWorkflowService::class);
        $sequence = 0;

        foreach (WorkshopRegistration::STATUSES as $from) {
            foreach (WorkshopRegistration::STATUSES as $to) {
                [$workshop, $version] = $this->workshopContext();
                $registration = WorkshopRegistration::query()->create([
                    'workshop_id' => $workshop->id,
                    'application_form_version_id' => $version->id,
                    'name' => 'Matrix Private Registrant',
                    'email' => 'workshop-matrix-' . (++$sequence) . '@private.example.test',
                    'workflow_status' => $from,
                    'source' => WorkshopRegistration::SOURCE_PUBLIC,
                ]);
                $allowed = in_array($to, WorkshopRegistrationWorkflowService::TRANSITIONS[$from] ?? [], true);
                $beforeAudits = AdminAuditEvent::query()->count();

                try {
                    $result = $service->transition($registration, $to, $actor);
                    $this->assertTrue($allowed, "Unexpectedly allowed workshop transition {$from} -> {$to}.");
                    $this->assertSame($to, $result->workflow_status);
                    $this->assertSame(1, $registration->statusEvents()->count());
                    $this->assertSame($beforeAudits + 1, AdminAuditEvent::query()->count());
                } catch (ValidationException $exception) {
                    $this->assertFalse($allowed, "Unexpectedly rejected workshop transition {$from} -> {$to}.");
                    $this->assertArrayHasKey('workflow_status', $exception->errors());
                    $this->assertSame($from, $registration->fresh()->workflow_status);
                    $this->assertSame(0, $registration->statusEvents()->count());
                    $this->assertSame($beforeAudits, AdminAuditEvent::query()->count());
                }
            }
        }

        $auditJson = AdminAuditEvent::query()->get()->toJson();
        $this->assertStringNotContainsString('private.example.test', mb_strtolower($auditJson));
        $this->assertStringNotContainsString('Matrix Private Registrant', $auditJson);
    }

    /** @return array{JobPosting, ApplicationFormVersion} */
    private function jobContext(): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_JOB);
        $job = JobPosting::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 1,
        ]);

        return [$job, $version];
    }

    /** @return array{Workshop, ApplicationFormVersion} */
    private function workshopContext(): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = Workshop::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'attendance_mode' => Workshop::ATTENDANCE_ONLINE,
            'registration_mode' => Workshop::REGISTRATION_MANUAL,
            'capacity' => null,
        ]);

        return [$workshop, $version];
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function formVersion(string $purpose): array
    {
        $form = ApplicationForm::query()->create(['purpose' => $purpose, 'name' => $purpose . ' matrix form']);
        $version = ApplicationFormVersion::query()->create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose . '-matrix'),
            'published_at' => now(),
        ]);

        return [$form, $version];
    }

    private function actor(): Admin
    {
        return Admin::query()->create([
            'name' => 'Matrix Reviewer',
            'username' => 'matrix-reviewer',
            'email' => 'reviewer@example.test',
            'status' => 1,
        ]);
    }
}
