<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\Workshop;
use App\Services\OpportunityManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OpportunityManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_complete_job_lifecycle_uses_bilingual_sanitized_content_versions_and_closed_detail_preservation(): void
    {
        $service = app(OpportunityManagementService::class);
        $actor = $this->owner();
        $job = $service->createJob($this->jobData([
            'scorecard_criteria' => [
                ['label' => 'Relevant experience', 'description' => 'Evidence from prior roles', 'maximum_score' => 10, 'is_enabled' => true],
                ['label' => 'Values alignment', 'description' => null, 'maximum_score' => 5, 'is_enabled' => true],
            ],
        ]), $actor);

        $this->assertSame(JobPosting::PUBLICATION_DRAFT, $job->publication_status);
        $this->assertSame(ApplicationForm::PURPOSE_JOB, $job->form->purpose);
        $this->assertSame('published', $job->currentFormVersion->state);
        $this->assertCount(2, $job->translations);
        $this->assertSame(['Relevant experience', 'Values alignment'], $job->scorecardCriteria->pluck('label')->all());
        $this->assertStringNotContainsString('<script', $job->translations->firstWhere('locale', 'en')->description);
        $this->assertStringContainsString('<strong>work</strong>', $job->translations->firstWhere('locale', 'en')->description);

        $published = $service->publishJob($job, 1, $actor);
        $this->assertSame(JobPosting::PUBLICATION_PUBLISHED, $published->publication_status);
        $this->assertTrue(JobPosting::activeList()->whereKey($job->id)->exists());

        try {
            $service->updateJob($job, 1, $this->jobData(['vacancy_count' => 3]), $actor);
            $this->fail('A stale edit must not overwrite a published change.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $closed = $service->closeJob($published, $actor);
        $this->assertFalse(JobPosting::activeList()->whereKey($job->id)->exists());
        $this->assertTrue(JobPosting::publicDetail()->whereKey($job->id)->exists());
        $this->assertLessThanOrEqual(now(), $closed->application_closes_at);

        $copy = $service->duplicateJob($closed, $actor);
        $this->assertSame(JobPosting::PUBLICATION_DRAFT, $copy->publication_status);
        $this->assertNull($copy->visible_from_at);
        $this->assertNotSame($closed->application_form_id, $copy->application_form_id);
        $this->assertSame(['Relevant experience', 'Values alignment'], $copy->scorecardCriteria->pluck('label')->all());
        $this->assertNotSame(
            $closed->translations->firstWhere('locale', 'en')->slug,
            $copy->translations->firstWhere('locale', 'en')->slug,
        );
        $service->deleteJobDraft($copy, $actor);
        $this->assertSoftDeleted('job_postings', ['id' => $copy->id]);

        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.job.created']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.job.published']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.job.closed']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.job.duplicated']);
    }

    public function test_complete_free_workshop_lifecycle_enforces_modes_capacity_and_chronology(): void
    {
        $service = app(OpportunityManagementService::class);
        $actor = $this->owner('workshop-manager-owner');
        $workshop = $service->createWorkshop($this->workshopData(), $actor);
        $this->assertSame(Workshop::PUBLICATION_DRAFT, $workshop->publication_status);
        $this->assertSame(Workshop::REGISTRATION_WAITLIST, $workshop->registration_mode);
        $this->assertSame(30, $workshop->capacity);
        $this->assertArrayNotHasKey('price', $workshop->getAttributes());
        $this->assertArrayNotHasKey('payment', $workshop->getAttributes());

        $published = $service->publishWorkshop($workshop, 1, $actor);
        $this->assertTrue(Workshop::activeList()->whereKey($published->id)->exists());
        $closed = $service->closeWorkshop($published, $actor);
        $this->assertFalse(Workshop::activeList()->whereKey($closed->id)->exists());
        $this->assertTrue(Workshop::publicDetail()->whereKey($closed->id)->exists());

        $copy = $service->duplicateWorkshop($closed, $actor);
        $this->assertNotSame($closed->application_form_id, $copy->application_form_id);
        $this->assertSame(Workshop::PUBLICATION_DRAFT, $copy->publication_status);
        $service->deleteWorkshopDraft($copy, $actor);
        $this->assertSoftDeleted('workshops', ['id' => $copy->id]);

        foreach (['workshop.created', 'workshop.published', 'workshop.closed', 'workshop.duplicated'] as $action) {
            $this->assertDatabaseHas('admin_audit_events', ['action' => $action]);
        }
    }

    public function test_invalid_schedules_waitlists_forms_and_duplicate_slugs_fail_before_partial_writes(): void
    {
        $service = app(OpportunityManagementService::class);
        $actor = $this->owner('validation-owner');
        $before = JobPosting::count();

        try {
            $service->createJob($this->jobData([
                'application_opens_at' => now()->addDays(2),
                'application_closes_at' => now()->addDay(),
            ]), $actor);
            $this->fail('An inverted job schedule must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule', $exception->errors());
        }
        $this->assertSame($before, JobPosting::count());

        try {
            $service->createWorkshop($this->workshopData(['capacity' => null]), $actor);
            $this->fail('Waitlist mode requires an explicit capacity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workshop', $exception->errors());
        }

        $expiredWorkshop = $service->createWorkshop($this->workshopData([
            'visible_from_at' => now()->subDays(5),
            'registration_opens_at' => now()->subDays(4),
            'registration_closes_at' => now()->subDays(3),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(2),
        ]), $actor);
        try {
            $service->publishWorkshop($expiredWorkshop, 1, $actor);
            $this->fail('An expired workshop draft must not be published as an inactive listing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule', $exception->errors());
        }
        $this->assertSame(Workshop::PUBLICATION_DRAFT, $expiredWorkshop->fresh()->publication_status);

        $job = $service->createJob($this->jobData(), $actor);
        try {
            $service->createJob($this->jobData(), $actor);
            $this->fail('Localized public slugs must be unique.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations.en.slug', $exception->errors());
        }
        $this->assertSame(1, JobPosting::whereNull('deleted_at')->count());

        $wrongFormData = $this->jobData(['application_form_id' => 999999]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->updateJob($job, 1, $wrongFormData, $actor);
    }

    public function test_job_scorecard_sync_reorders_updates_and_disables_history_without_accepting_foreign_ids(): void
    {
        $service = app(OpportunityManagementService::class);
        $actor = $this->owner('scorecard-owner');
        $job = $service->createJob($this->jobData([
            'scorecard_criteria' => [
                ['label' => 'Experience', 'maximum_score' => 10, 'is_enabled' => true],
                ['label' => 'Communication', 'maximum_score' => 5, 'is_enabled' => true],
            ],
        ]), $actor);
        $experience = $job->scorecardCriteria->firstWhere('label', 'Experience');
        $communication = $job->scorecardCriteria->firstWhere('label', 'Communication');

        $updated = $service->updateJob($job, 1, $this->jobData([
            'scorecard_criteria' => [
                [
                    'uuid' => $experience->uuid,
                    'label' => 'Relevant experience',
                    'description' => 'Use role-specific evidence.',
                    'maximum_score' => 12,
                    'is_enabled' => true,
                ],
                ['label' => 'Values', 'maximum_score' => 8, 'is_enabled' => true],
            ],
        ]), $actor);
        $this->assertSame(2, $updated->editor_version);
        $this->assertSame('Relevant experience', $experience->fresh()->label);
        $this->assertSame('12.00', $experience->fresh()->maximum_score);
        $this->assertFalse($communication->fresh()->is_enabled);
        $this->assertDatabaseHas('job_scorecard_criteria', [
            'job_posting_id' => $job->id,
            'label' => 'Values',
            'is_enabled' => true,
        ]);

        $otherJob = $service->createJob($this->jobData([
            'translations' => [
                'en' => ['slug' => 'other-scorecard-job'],
                'bn' => ['slug' => 'other-scorecard-job-bn'],
            ],
            'scorecard_criteria' => [
                ['label' => 'Foreign criterion', 'maximum_score' => 10, 'is_enabled' => true],
            ],
        ]), $actor);
        $foreign = $otherJob->scorecardCriteria->sole();

        try {
            $service->updateJob($job->fresh(), 2, $this->jobData([
                'scorecard_criteria' => [[
                    'uuid' => $foreign->uuid,
                    'label' => 'Tampered criterion',
                    'maximum_score' => 10,
                    'is_enabled' => true,
                ]],
            ]), $actor);
            $this->fail('A scorecard criterion from another job must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scorecard_criteria', $exception->errors());
        }
        $this->assertSame(2, $job->fresh()->editor_version);
        $this->assertSame('Foreign criterion', $foreign->fresh()->label);
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, mixed>
     */
    private function jobData(array $overrides = []): array
    {
        return array_replace_recursive([
            'visible_from_at' => now()->subHours(2),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => 2,
            'translations' => [
                'en' => [
                    'slug' => 'programme-officer',
                    'title' => 'Programme Officer',
                    'department' => 'Programmes',
                    'location' => 'Dhaka',
                    'summary' => 'Build good programmes.',
                    'description' => '<script>alert(1)</script><p>Meaningful <strong>work</strong>.</p>',
                    'responsibilities' => '<ul><li>Plan</li></ul>',
                    'requirements' => '<p>Relevant experience.</p>',
                ],
                'bn' => [
                    'slug' => 'programme-officer-bn',
                    'title' => 'প্রোগ্রাম অফিসার',
                    'department' => 'প্রোগ্রাম',
                    'location' => 'ঢাকা',
                    'summary' => 'ভালো প্রোগ্রাম তৈরি করুন।',
                    'description' => '<p>অর্থবহ কাজ।</p>',
                    'responsibilities' => '<ul><li>পরিকল্পনা</li></ul>',
                    'requirements' => '<p>প্রাসঙ্গিক অভিজ্ঞতা।</p>',
                ],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, mixed>
     */
    private function workshopData(array $overrides = []): array
    {
        return array_replace_recursive([
            'visible_from_at' => now()->subHours(2),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_HYBRID,
            'registration_mode' => Workshop::REGISTRATION_WAITLIST,
            'capacity' => 30,
            'private_meeting_url' => 'https://meet.example.test/private-room',
            'translations' => [
                'en' => [
                    'slug' => 'leadership-workshop',
                    'title' => 'Leadership Workshop',
                    'summary' => 'A practical session.',
                    'description' => '<p>Learn together.</p>',
                    'facilitator_name' => 'Facilitator',
                    'venue_name' => 'IGF Centre',
                    'venue_address' => 'Dhaka',
                    'registration_instructions' => '<p>Registration is free.</p>',
                ],
                'bn' => [
                    'slug' => 'leadership-workshop-bn',
                    'title' => 'নেতৃত্ব কর্মশালা',
                    'summary' => 'একটি ব্যবহারিক সেশন।',
                    'description' => '<p>একসাথে শিখুন।</p>',
                    'facilitator_name' => 'প্রশিক্ষক',
                    'venue_name' => 'আইজিএফ কেন্দ্র',
                    'venue_address' => 'ঢাকা',
                    'registration_instructions' => '<p>নিবন্ধন বিনামূল্যে।</p>',
                ],
            ],
        ], $overrides);
    }

    private function owner(string $username = 'opportunity-owner'): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();

        return Admin::query()->create([
            'name' => 'Opportunity Owner',
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
