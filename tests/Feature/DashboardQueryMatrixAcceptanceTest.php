<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobApplicationScore;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\ApplicationListingService;
use App\Services\JobApplicationWorkflowService;
use App\Services\WorkshopRegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DashboardQueryMatrixAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_documented_job_sort_and_common_filter_has_deterministic_semantics(): void
    {
        [$job, $version] = $this->jobContext();
        $assignee = $this->admin('assigned-reviewer');
        $reviewer = $this->admin('score-reviewer');
        $criterion = JobScorecardCriterion::query()->create([
            'job_posting_id' => $job->id,
            'label' => 'Matrix score',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $a = $this->jobApplication($job, $version, [
            'name' => 'Alpha', 'email' => 'alpha-filter@example.test', 'phone' => '+8801700000001',
            'workflow_status' => JobApplication::STATUS_HIRED, 'submission_count' => 1,
            'first_submitted_at' => '2026-09-03 09:00:00', 'last_submitted_at' => '2026-09-01 09:00:00',
        ]);
        $b = $this->jobApplication($job, $version, [
            'name' => 'Bravo', 'email' => 'bravo-filter@example.test', 'phone' => '+8801700000002',
            'workflow_status' => JobApplication::STATUS_NEW, 'submission_count' => 2,
            'assigned_to_admin_id' => $assignee->id,
            'first_submitted_at' => '2026-09-02 09:00:00', 'last_submitted_at' => '2026-09-02 09:00:00',
        ]);
        $c = $this->jobApplication($job, $version, [
            'name' => 'Charlie', 'email' => 'charlie-filter@example.test', 'phone' => '+8801700000003',
            'workflow_status' => JobApplication::STATUS_REJECTED, 'submission_count' => 3,
            'first_submitted_at' => '2026-09-01 09:00:00', 'last_submitted_at' => '2026-09-03 09:00:00',
        ]);
        foreach ([[$a, 1], [$b, 2], [$c, 3]] as [$application, $score]) {
            JobApplicationScore::query()->create([
                'job_application_id' => $application->id,
                'job_scorecard_criterion_id' => $criterion->id,
                'reviewer_admin_id' => $reviewer->id,
                'score' => $score,
                'criterion_label_snapshot' => $criterion->label,
                'maximum_score_snapshot' => 10,
            ]);
        }

        $service = app(ApplicationListingService::class);
        $expectedBySort = [
            'last_submitted_at' => [$a->id, $b->id, $c->id],
            'first_submitted_at' => [$c->id, $b->id, $a->id],
            'name' => [$a->id, $b->id, $c->id],
            'workflow_status' => [$a->id, $b->id, $c->id],
            'submission_count' => [$a->id, $b->id, $c->id],
            'average_score' => [$a->id, $b->id, $c->id],
        ];
        $this->assertSame(array_keys($expectedBySort), ApplicationListingService::JOB_SORTS);
        foreach ($expectedBySort as $sort => $expected) {
            $this->assertSame($expected, $service->jobs($job, ['sort' => $sort, 'direction' => 'asc'])->pluck('id')->all(), $sort);
        }

        $this->assertSame([$a->id], $service->jobs($job, ['status' => JobApplication::STATUS_HIRED])->pluck('id')->all());
        $this->assertSame([$b->id], $service->jobs($job, ['assigned_to' => $assignee->id])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $service->jobs($job, ['assigned_to' => 'unassigned'])->pluck('id')->all());
        $this->assertSame([$b->id], $service->jobs($job, ['from' => '2026-09-02', 'to' => '2026-09-02'])->pluck('id')->all());
        foreach (['Alpha', 'alpha-filter@example.test', '+8801700000001', $a->reference_number] as $search) {
            $this->assertSame([$a->id], $service->jobs($job, [], $search)->pluck('id')->all());
        }
        $this->assertSame([$c->id, $b->id, $a->id], $service->jobs($job, ['sort' => 'tampered'])->pluck('id')->all());
    }

    public function test_every_documented_workshop_sort_and_common_filter_has_deterministic_semantics(): void
    {
        [$workshop, $version] = $this->workshopContext();
        $assignee = $this->admin('workshop-assignee');
        $a = $this->registration($workshop, $version, [
            'name' => 'Alpha', 'email' => 'alpha-workshop@example.test', 'phone' => '+8801800000001',
            'workflow_status' => WorkshopRegistration::STATUS_CANCELLED, 'submission_count' => 1,
            'first_submitted_at' => '2026-09-03 09:00:00', 'last_submitted_at' => '2026-09-01 09:00:00',
            'waitlisted_at' => '2026-09-01 10:00:00', 'confirmed_at' => '2026-09-03 10:00:00',
        ]);
        $b = $this->registration($workshop, $version, [
            'name' => 'Bravo', 'email' => 'bravo-workshop@example.test', 'phone' => '+8801800000002',
            'workflow_status' => WorkshopRegistration::STATUS_CONFIRMED, 'submission_count' => 2,
            'assigned_to_admin_id' => $assignee->id,
            'first_submitted_at' => '2026-09-02 09:00:00', 'last_submitted_at' => '2026-09-02 09:00:00',
            'waitlisted_at' => '2026-09-02 10:00:00', 'confirmed_at' => '2026-09-02 10:00:00',
        ]);
        $c = $this->registration($workshop, $version, [
            'name' => 'Charlie', 'email' => 'charlie-workshop@example.test', 'phone' => '+8801800000003',
            'workflow_status' => WorkshopRegistration::STATUS_WAITLISTED, 'submission_count' => 3,
            'first_submitted_at' => '2026-09-01 09:00:00', 'last_submitted_at' => '2026-09-03 09:00:00',
            'waitlisted_at' => '2026-09-03 10:00:00', 'confirmed_at' => '2026-09-01 10:00:00',
        ]);

        $service = app(ApplicationListingService::class);
        $expectedBySort = [
            'last_submitted_at' => [$a->id, $b->id, $c->id],
            'first_submitted_at' => [$c->id, $b->id, $a->id],
            'name' => [$a->id, $b->id, $c->id],
            'workflow_status' => [$a->id, $b->id, $c->id],
            'submission_count' => [$a->id, $b->id, $c->id],
            'waitlisted_at' => [$a->id, $b->id, $c->id],
            'confirmed_at' => [$c->id, $b->id, $a->id],
        ];
        $this->assertSame(array_keys($expectedBySort), ApplicationListingService::WORKSHOP_SORTS);
        foreach ($expectedBySort as $sort => $expected) {
            $this->assertSame($expected, $service->workshops($workshop, ['sort' => $sort, 'direction' => 'asc'])->pluck('id')->all(), $sort);
        }

        $this->assertSame([$c->id], $service->workshops($workshop, ['status' => WorkshopRegistration::STATUS_WAITLISTED])->pluck('id')->all());
        $this->assertSame([$b->id], $service->workshops($workshop, ['assigned_to' => $assignee->id])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $service->workshops($workshop, ['assigned_to' => 'unassigned'])->pluck('id')->all());
        $this->assertSame([$b->id], $service->workshops($workshop, ['from' => '2026-09-02', 'to' => '2026-09-02'])->pluck('id')->all());
        foreach (['Alpha', 'alpha-workshop@example.test', '+8801800000001', $a->reference_number] as $search) {
            $this->assertSame([$a->id], $service->workshops($workshop, [], $search)->pluck('id')->all());
        }
        $this->assertSame([$c->id, $b->id, $a->id], $service->workshops($workshop, ['sort' => 'tampered'])->pluck('id')->all());
    }

    public function test_mixed_validity_bulk_status_operations_roll_back_every_row_in_both_domains(): void
    {
        [$job, $jobVersion] = $this->jobContext();
        [$workshop, $workshopVersion] = $this->workshopContext();
        $actor = $this->admin('bulk-reviewer');
        $new = $this->jobApplication($job, $jobVersion, ['email' => 'new@bulk.test', 'workflow_status' => JobApplication::STATUS_NEW]);
        $hired = $this->jobApplication($job, $jobVersion, ['email' => 'hired@bulk.test', 'workflow_status' => JobApplication::STATUS_HIRED]);
        try {
            app(JobApplicationWorkflowService::class)->bulkTransition([$new->id, $hired->id], JobApplication::STATUS_UNDER_REVIEW, $actor);
            $this->fail('One invalid job transition must roll back the entire batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(JobApplication::STATUS_NEW, $new->fresh()->workflow_status);
        $this->assertSame(JobApplication::STATUS_HIRED, $hired->fresh()->workflow_status);
        $this->assertDatabaseCount('job_application_status_events', 0);

        $pending = $this->registration($workshop, $workshopVersion, ['email' => 'pending@bulk.test', 'workflow_status' => WorkshopRegistration::STATUS_PENDING]);
        $confirmed = $this->registration($workshop, $workshopVersion, ['email' => 'confirmed@bulk.test', 'workflow_status' => WorkshopRegistration::STATUS_CONFIRMED]);
        try {
            app(WorkshopRegistrationWorkflowService::class)->bulkTransition([$pending->id, $confirmed->id], WorkshopRegistration::STATUS_CONFIRMED, $actor);
            $this->fail('One invalid workshop transition must roll back the entire batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }
        $this->assertSame(WorkshopRegistration::STATUS_PENDING, $pending->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $confirmed->fresh()->workflow_status);
        $this->assertDatabaseCount('workshop_registration_status_events', 0);
    }

    private function jobApplication(JobPosting $job, ApplicationFormVersion $version, array $attributes): JobApplication
    {
        return JobApplication::query()->create(array_merge([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $version->id,
            'name' => 'Applicant',
            'email' => uniqid('app-', true) . '@example.test',
            'workflow_status' => JobApplication::STATUS_NEW,
            'submission_count' => 1,
            'source' => JobApplication::SOURCE_PUBLIC,
        ], $attributes));
    }

    private function registration(Workshop $workshop, ApplicationFormVersion $version, array $attributes): WorkshopRegistration
    {
        return WorkshopRegistration::query()->create(array_merge([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $version->id,
            'name' => 'Registrant',
            'email' => uniqid('reg-', true) . '@example.test',
            'workflow_status' => WorkshopRegistration::STATUS_PENDING,
            'submission_count' => 1,
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ], $attributes));
    }

    /** @return array{JobPosting, ApplicationFormVersion} */
    private function jobContext(): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_JOB);
        return [JobPosting::query()->create([
            'application_form_id' => $form->id, 'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(), 'application_opens_at' => now()->subHour(), 'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME, 'work_arrangement' => JobPosting::WORK_ON_SITE, 'vacancy_count' => 1,
        ]), $version];
    }

    /** @return array{Workshop, ApplicationFormVersion} */
    private function workshopContext(): array
    {
        [$form, $version] = $this->formVersion(ApplicationForm::PURPOSE_WORKSHOP);
        return [Workshop::query()->create([
            'application_form_id' => $form->id, 'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(), 'registration_opens_at' => now()->subHour(), 'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(),
            'attendance_mode' => Workshop::ATTENDANCE_ONLINE, 'registration_mode' => Workshop::REGISTRATION_MANUAL,
        ]), $version];
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function formVersion(string $purpose): array
    {
        $form = ApplicationForm::query()->create(['purpose' => $purpose, 'name' => uniqid($purpose . '-', true)]);
        $version = ApplicationFormVersion::query()->create([
            'application_form_id' => $form->id, 'version' => 1, 'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $form->name), 'published_at' => now(),
        ]);
        return [$form, $version];
    }

    private function admin(string $username): Admin
    {
        return Admin::query()->create(['name' => $username, 'username' => $username, 'email' => $username . '@example.test', 'status' => 1]);
    }
}
