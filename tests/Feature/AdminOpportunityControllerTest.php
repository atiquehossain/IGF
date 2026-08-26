<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\AuthMenu;
use App\Models\JobPosting;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Workshop;
use App\Services\ApplicationFormSchemaService;
use App\Services\OpportunityManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOpportunityControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'opportunity-controller-owner');
    }

    public function test_guests_are_redirected_and_job_and_workshop_roles_are_strictly_isolated(): void
    {
        $this->get(route('recruitment.jobs.index'))->assertRedirect(route('admin.login'));
        $this->get(route('workshops.index'))->assertRedirect(route('admin.login'));

        $recruiter = $this->admin($this->role('Recruitment manager', [
            'recruitment.jobs.index',
            'recruitment.jobs.create',
            'recruitment.jobs.edit',
            'recruitment.jobs.status',
            'recruitment.jobs.destroy',
        ]), 'recruitment-manager');
        $workshopManager = $this->admin($this->role('Workshop manager', [
            'workshops.index',
            'workshops.create',
            'workshops.edit',
            'workshops.status',
            'workshops.destroy',
        ]), 'workshop-manager');

        $this->actingAs($recruiter, 'admin')->get(route('recruitment.jobs.index'))->assertOk();
        $this->actingAs($recruiter, 'admin')->get(route('workshops.index'))->assertForbidden();
        $this->actingAs($workshopManager, 'admin')->get(route('workshops.index'))->assertOk();
        $this->actingAs($workshopManager, 'admin')->get(route('recruitment.jobs.index'))->assertForbidden();
    }

    public function test_job_index_only_renders_controls_granted_to_the_current_role(): void
    {
        $job = app(OpportunityManagementService::class)->createJob($this->jobData(), $this->owner);
        $viewer = $this->admin($this->role('Recruitment viewer', ['recruitment.jobs.index']), 'recruitment-viewer');

        $response = $this->actingAs($viewer, 'admin')->get(route('recruitment.jobs.index'));

        $response->assertOk()
            ->assertSee('Programme Officer')
            ->assertDontSee(route('recruitment.jobs.create'), false)
            ->assertDontSee(route('recruitment.jobs.edit', $job), false)
            ->assertDontSee(route('recruitment.jobs.status', $job), false)
            ->assertDontSee(route('recruitment.jobs.destroy', $job), false)
            ->assertDontSee(route('recruitment.applications.index', ['listing' => $job->uuid]), false);
        $this->actingAs($viewer, 'admin')->get(route('recruitment.jobs.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->patch(route('recruitment.jobs.status', $job), [
            'action' => 'publish',
            'editor_version' => 1,
        ])->assertForbidden();
    }

    public function test_workshop_index_only_renders_controls_granted_to_the_current_role(): void
    {
        $workshop = app(OpportunityManagementService::class)->createWorkshop($this->workshopData(), $this->owner);
        $viewer = $this->admin($this->role('Workshop viewer', ['workshops.index']), 'workshop-viewer');

        $response = $this->actingAs($viewer, 'admin')->get(route('workshops.index'));

        $response->assertOk()
            ->assertSee('Leadership Workshop')
            ->assertSee('Always free')
            ->assertDontSee(route('workshops.create'), false)
            ->assertDontSee(route('workshops.edit', $workshop), false)
            ->assertDontSee(route('workshops.status', $workshop), false)
            ->assertDontSee(route('workshops.destroy', $workshop), false)
            ->assertDontSee(route('workshop.registrations.index', ['listing' => $workshop->uuid]), false);
        $this->actingAs($viewer, 'admin')->get(route('workshops.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->patch(route('workshops.status', $workshop), [
            'action' => 'publish',
            'editor_version' => 1,
        ])->assertForbidden();
    }

    public function test_job_create_requires_complete_bilingual_content_and_rejects_tampered_schedules(): void
    {
        $create = route('recruitment.jobs.create');
        $missingBangla = $this->jobData();
        unset($missingBangla['translations']['bn']);

        $this->actingAs($this->owner, 'admin')->from($create)
            ->post(route('recruitment.jobs.store'), $missingBangla)
            ->assertRedirect($create)
            ->assertSessionHasErrors('translations.bn');

        $badSchedule = $this->jobData([
            'visible_from_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'application_opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'application_closes_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $this->actingAs($this->owner, 'admin')->from($create)
            ->post(route('recruitment.jobs.store'), $badSchedule)
            ->assertRedirect($create)
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('job_postings', 0);
    }

    public function test_job_and_workshop_edits_cannot_drop_either_public_language(): void
    {
        $job = app(OpportunityManagementService::class)->createJob($this->jobData(), $this->owner);
        $workshop = app(OpportunityManagementService::class)->createWorkshop($this->workshopData(), $this->owner);

        $jobUpdate = $this->jobData(['editor_version' => 1]);
        unset($jobUpdate['translations']['bn']);
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.jobs.edit', $job))
            ->put(route('recruitment.jobs.update', $job), $jobUpdate)
            ->assertRedirect(route('recruitment.jobs.edit', $job))
            ->assertSessionHasErrors('translations.bn');

        $workshopUpdate = $this->workshopData(['editor_version' => 1]);
        unset($workshopUpdate['translations']['en']);
        $this->actingAs($this->owner, 'admin')->from(route('workshops.edit', $workshop))
            ->put(route('workshops.update', $workshop), $workshopUpdate)
            ->assertRedirect(route('workshops.edit', $workshop))
            ->assertSessionHasErrors('translations.en');

        $this->assertSame(1, $job->fresh()->editor_version);
        $this->assertSame(1, $workshop->fresh()->editor_version);
        $this->assertSame(['bn', 'en'], $job->translations()->pluck('locale')->sort()->values()->all());
        $this->assertSame(['bn', 'en'], $workshop->translations()->pluck('locale')->sort()->values()->all());
    }

    public function test_form_picker_only_shows_published_forms_of_the_correct_purpose_and_tampering_is_rejected(): void
    {
        [$publishedJobForm, $publishedJobVersion] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, 'Published job form');
        [, $otherJobVersion] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, 'Other published job form');
        [$publishedWorkshopForm] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP, 'Published workshop form');
        $draftJobForm = app(ApplicationFormSchemaService::class)->create(ApplicationForm::PURPOSE_JOB, 'Draft job form', $this->owner);

        $this->actingAs($this->owner, 'admin')->get(route('recruitment.jobs.create'))
            ->assertOk()
            ->assertSee('Published job form')
            ->assertDontSee('Published workshop form')
            ->assertDontSee('Draft job form');
        $this->actingAs($this->owner, 'admin')->get(route('workshops.create'))
            ->assertOk()
            ->assertSee('Published workshop form')
            ->assertDontSee('Published job form')
            ->assertDontSee('Draft job form');

        $valid = $this->jobData([
            'application_form_id' => $publishedJobForm->id,
            'form_version_id' => $publishedJobVersion->id,
        ]);
        $this->actingAs($this->owner, 'admin')->post(route('recruitment.jobs.store'), $valid)->assertRedirect();
        $job = JobPosting::query()->sole();
        $this->assertSame($publishedJobForm->id, $job->application_form_id);
        $this->assertSame($publishedJobVersion->id, $job->current_form_version_id);

        $wrongPurpose = $this->jobData([
            'translations' => $this->jobTranslations('Wrong purpose', 'wrong-purpose-job'),
            'application_form_id' => $publishedWorkshopForm->id,
        ]);
        $this->actingAs($this->owner, 'admin')->post(route('recruitment.jobs.store'), $wrongPurpose)->assertNotFound();

        $wrongWorkshopPurpose = $this->workshopData([
            'application_form_id' => $publishedJobForm->id,
        ]);
        $this->actingAs($this->owner, 'admin')->post(route('workshops.store'), $wrongWorkshopPurpose)->assertNotFound();

        $mismatchedVersion = $this->jobData([
            'translations' => $this->jobTranslations('Mismatched version', 'mismatched-version-job'),
            'application_form_id' => $publishedJobForm->id,
            'form_version_id' => $otherJobVersion->id,
        ]);
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.jobs.create'))
            ->post(route('recruitment.jobs.store'), $mismatchedVersion)
            ->assertRedirect(route('recruitment.jobs.create'))
            ->assertSessionHasErrors('application_form_id');

        $draftOnly = $this->jobData([
            'translations' => $this->jobTranslations('Draft form', 'draft-form-job'),
            'application_form_id' => $draftJobForm->id,
        ]);
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.jobs.create'))
            ->post(route('recruitment.jobs.store'), $draftOnly)
            ->assertRedirect(route('recruitment.jobs.create'))
            ->assertSessionHasErrors('application_form_id');
        $this->assertDatabaseCount('job_postings', 1);
        $this->assertDatabaseCount('workshops', 0);
    }

    public function test_job_controller_executes_full_publication_duplicate_delete_lifecycle_and_audits_each_step(): void
    {
        $this->actingAs($this->owner, 'admin')->post(route('recruitment.jobs.store'), $this->jobData([
            'scorecard_criteria' => [[
                'label' => 'Relevant experience',
                'description' => 'Assess evidence.',
                'maximum_score' => 10,
                'is_enabled' => true,
            ]],
        ]))->assertRedirect();
        $job = JobPosting::query()->with(['translations', 'scorecardCriteria'])->sole();
        $this->assertSame(['bn', 'en'], $job->translations->pluck('locale')->sort()->values()->all());
        $this->assertSame('Relevant experience', $job->scorecardCriteria->sole()->label);

        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.jobs.status', $job), [
            'action' => 'publish',
            'editor_version' => 1,
        ])->assertRedirect(route('recruitment.jobs.index'));
        $job->refresh();
        $this->assertSame(JobPosting::PUBLICATION_PUBLISHED, $job->publication_status);
        $this->assertSame(2, $job->editor_version);

        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.jobs.status', $job), ['action' => 'close'])
            ->assertRedirect(route('recruitment.jobs.index'));
        $job->refresh();
        $this->assertTrue($job->application_closes_at->lessThanOrEqualTo(now()));

        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.jobs.status', $job), ['action' => 'withdraw'])
            ->assertRedirect(route('recruitment.jobs.index'));
        $job->refresh();
        $this->assertSame(JobPosting::PUBLICATION_WITHDRAWN, $job->publication_status);

        $this->actingAs($this->owner, 'admin')->post(route('recruitment.jobs.duplicate', $job))->assertRedirect();
        $copy = JobPosting::query()->whereKeyNot($job->id)->sole();
        $this->assertSame(JobPosting::PUBLICATION_DRAFT, $copy->publication_status);
        $this->assertNotSame($job->application_form_id, $copy->application_form_id);

        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.jobs.destroy', $copy))->assertRedirect(route('recruitment.jobs.index'));
        $this->assertSoftDeleted('job_postings', ['id' => $copy->id]);
        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.jobs.destroy', $job))->assertStatus(409);

        foreach ([
            'recruitment.job.created', 'recruitment.job.published', 'recruitment.job.closed',
            'recruitment.job.withdrawn', 'recruitment.job.duplicated', 'recruitment.job.deleted',
        ] as $action) {
            $event = AdminAuditEvent::query()->where('action', $action)->latest('id')->firstOrFail();
            $this->assertSame($this->owner->id, $event->actor_admin_id);
            $this->assertNotEmpty($event->context['route'] ?? null);
        }
    }

    public function test_job_update_enforces_optimistic_versioning_and_scorecard_ownership(): void
    {
        $job = app(OpportunityManagementService::class)->createJob($this->jobData([
            'scorecard_criteria' => [
                ['label' => 'Experience', 'maximum_score' => 10, 'is_enabled' => true],
                ['label' => 'Communication', 'maximum_score' => 5, 'is_enabled' => true],
            ],
        ]), $this->owner);
        $experience = $job->scorecardCriteria->firstWhere('label', 'Experience');
        $communication = $job->scorecardCriteria->firstWhere('label', 'Communication');

        $stale = $this->jobData(['editor_version' => 99]);
        $this->actingAs($this->owner, 'admin')->put(route('recruitment.jobs.update', $job), $stale)->assertStatus(409);
        $this->assertSame(1, $job->fresh()->editor_version);

        $update = $this->jobData([
            'editor_version' => 1,
            'scorecard_criteria' => [[
                'uuid' => $experience->uuid,
                'label' => 'Relevant experience',
                'description' => 'Updated guidance',
                'maximum_score' => 20,
                'is_enabled' => true,
            ]],
        ]);
        $this->actingAs($this->owner, 'admin')->put(route('recruitment.jobs.update', $job), $update)->assertRedirect();
        $this->assertDatabaseHas('job_scorecard_criteria', [
            'id' => $experience->id,
            'label' => 'Relevant experience',
            'maximum_score' => 20,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('job_scorecard_criteria', ['id' => $communication->id, 'is_enabled' => 0]);

        $otherJob = app(OpportunityManagementService::class)->createJob($this->jobData([
            'translations' => $this->jobTranslations('Other Job', 'other-job'),
            'scorecard_criteria' => [['label' => 'Foreign criterion', 'maximum_score' => 5, 'is_enabled' => true]],
        ]), $this->owner);
        $foreign = $otherJob->scorecardCriteria->sole();
        $tampered = $this->jobData([
            'editor_version' => 2,
            'scorecard_criteria' => [[
                'uuid' => $foreign->uuid,
                'label' => 'Stolen criterion',
                'maximum_score' => 5,
                'is_enabled' => true,
            ]],
        ]);
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.jobs.edit', $job))
            ->put(route('recruitment.jobs.update', $job), $tampered)
            ->assertRedirect(route('recruitment.jobs.edit', $job))
            ->assertSessionHasErrors('scorecard_criteria');
        $this->assertSame(2, $job->fresh()->editor_version);
    }

    public function test_workshop_create_enforces_bilingual_chronology_https_and_always_free_contract(): void
    {
        $create = route('workshops.create');
        $page = $this->actingAs($this->owner, 'admin')->get($create);
        $page->assertOk()
            ->assertSee('Always free')
            ->assertSee('Four guided steps')
            ->assertSee('Bangladesh time (Asia/Dhaka, UTC+6)')
            ->assertSee('Choose attendance type')
            ->assertSee('Choose a registration decision')
            ->assertSee('Add poster or image')
            ->assertSee('Nothing becomes public until an authorized user publishes it.')
            ->assertSee('name="capacity_choice"', false)
            ->assertDontSee('name="price"', false)
            ->assertDontSee('name="fee"', false)
            ->assertDontSee('name="payment_url"', false);

        $missingCapacityChoice = $this->workshopData();
        unset($missingCapacityChoice['capacity_choice']);
        $this->actingAs($this->owner, 'admin')->from($create)->post(route('workshops.store'), $missingCapacityChoice)
            ->assertRedirect($create)->assertSessionHasErrors('capacity_choice');

        $missingBangla = $this->workshopData();
        unset($missingBangla['translations']['bn']);
        $this->actingAs($this->owner, 'admin')->from($create)->post(route('workshops.store'), $missingBangla)
            ->assertRedirect($create)->assertSessionHasErrors('translations.bn');

        $badSchedule = $this->workshopData([
            'registration_closes_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'starts_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);
        $this->actingAs($this->owner, 'admin')->from($create)->post(route('workshops.store'), $badSchedule)
            ->assertRedirect($create)->assertSessionHasErrors('schedule');

        $insecureUrl = $this->workshopData(['private_meeting_url' => 'http://meet.example.test/private']);
        $this->actingAs($this->owner, 'admin')->from($create)->post(route('workshops.store'), $insecureUrl)
            ->assertRedirect($create)->assertSessionHasErrors('private_meeting_url');

        $payload = $this->workshopData() + [
            'price' => '9999.00',
            'fee' => '9999.00',
            'payment_url' => 'https://pay.example.test/checkout',
        ];
        $this->actingAs($this->owner, 'admin')->post(route('workshops.store'), $payload)->assertRedirect();
        $workshop = Workshop::query()->sole();
        $this->assertSame(['bn', 'en'], $workshop->translations()->pluck('locale')->sort()->values()->all());
        $this->assertFalse(Schema::hasColumn('workshops', 'price'));
        $this->assertFalse(Schema::hasColumn('workshops', 'fee'));
        $this->assertFalse(Schema::hasColumn('workshops', 'payment_url'));
        $event = AdminAuditEvent::query()->where('action', 'workshop.created')->latest('id')->firstOrFail();
        $this->assertTrue($event->context['always_free']);
    }

    public function test_workshop_unlimited_choice_is_explicit_and_clears_an_accidental_number(): void
    {
        $payload = $this->workshopData([
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'capacity_choice' => 'unlimited',
            'capacity' => 75,
        ]);

        $this->actingAs($this->owner, 'admin')->post(route('workshops.store'), $payload)->assertRedirect();

        $workshop = Workshop::query()->sole();
        $this->assertSame(Workshop::REGISTRATION_AUTOMATIC, $workshop->registration_mode);
        $this->assertNull($workshop->capacity);

        $this->actingAs($this->owner, 'admin')->get(route('workshops.edit', $workshop))
            ->assertOk()
            ->assertSee('Current registration questions:')
            ->assertSee('Publish saved draft')
            ->assertSee('Private draft.')
            ->assertSee('id="capacity-unlimited"', false)
            ->assertSee('checked', false);
    }

    public function test_workshop_controller_executes_lifecycle_duplicate_delete_and_optimistic_conflict(): void
    {
        $this->actingAs($this->owner, 'admin')->post(route('workshops.store'), $this->workshopData())->assertRedirect();
        $workshop = Workshop::query()->sole();

        $stale = $this->workshopData(['editor_version' => 42]);
        $this->actingAs($this->owner, 'admin')->put(route('workshops.update', $workshop), $stale)->assertStatus(409);
        $this->assertSame(1, $workshop->fresh()->editor_version);

        $this->actingAs($this->owner, 'admin')->patch(route('workshops.status', $workshop), [
            'action' => 'publish',
            'editor_version' => 1,
        ])->assertRedirect(route('workshops.index'));
        $workshop->refresh();
        $this->assertSame(Workshop::PUBLICATION_PUBLISHED, $workshop->publication_status);
        $this->assertSame(2, $workshop->editor_version);

        $this->actingAs($this->owner, 'admin')->patch(route('workshops.status', $workshop), ['action' => 'close'])
            ->assertRedirect(route('workshops.index'));
        $this->assertTrue($workshop->fresh()->registration_closes_at->lessThanOrEqualTo(now()));
        $this->actingAs($this->owner, 'admin')->patch(route('workshops.status', $workshop), ['action' => 'withdraw'])
            ->assertRedirect(route('workshops.index'));
        $this->assertSame(Workshop::PUBLICATION_WITHDRAWN, $workshop->fresh()->publication_status);

        $this->actingAs($this->owner, 'admin')->post(route('workshops.duplicate', $workshop))->assertRedirect();
        $copy = Workshop::query()->whereKeyNot($workshop->id)->sole();
        $this->assertSame(Workshop::PUBLICATION_DRAFT, $copy->publication_status);
        $this->assertNotSame($workshop->application_form_id, $copy->application_form_id);
        $this->actingAs($this->owner, 'admin')->delete(route('workshops.destroy', $copy))->assertRedirect(route('workshops.index'));
        $this->assertSoftDeleted('workshops', ['id' => $copy->id]);
        $this->actingAs($this->owner, 'admin')->delete(route('workshops.destroy', $workshop))->assertStatus(409);

        foreach (['workshop.created', 'workshop.published', 'workshop.closed', 'workshop.withdrawn', 'workshop.duplicated', 'workshop.deleted'] as $action) {
            $event = AdminAuditEvent::query()->where('action', $action)->latest('id')->firstOrFail();
            $this->assertSame($this->owner->id, $event->actor_admin_id);
            $this->assertNotEmpty($event->context['route'] ?? null);
        }
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function publishedForm(string $purpose, string $name): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create($purpose, $name, $this->owner);
        $version = $forms->publish($form, (int) $form->editor_version, $this->owner);

        return [$form->fresh(), $version];
    }

    /** @param list<string> $capabilities */
    private function role(string $name, array $capabilities): Role
    {
        return Role::query()->create([
            'name' => $name . ' ' . Str::lower(Str::random(6)),
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => AuthMenu::query()->whereIn('link', $capabilities)->pluck('id')->implode(','),
            'actionPermission' => MenuAction::query()->whereIn('link', $capabilities)->pluck('id')->implode(','),
            'serial' => '[]',
            'order_by' => 200,
            'status' => 1,
        ]);
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Admin-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function jobData(array $overrides = []): array
    {
        return array_replace_recursive([
            'visible_from_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
            'application_opens_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'application_closes_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => 2,
            'translations' => $this->jobTranslations('Programme Officer', 'programme-officer'),
        ], $overrides);
    }

    /** @return array<string, array<string, string>> */
    private function jobTranslations(string $englishTitle, string $slug): array
    {
        return [
            'en' => [
                'slug' => $slug,
                'title' => $englishTitle,
                'department' => 'Programmes',
                'location' => 'Dhaka',
                'summary' => 'Build good programmes.',
                'description' => '<script>alert(1)</script><p>Meaningful work.</p>',
                'responsibilities' => '<ul><li>Plan</li></ul>',
                'requirements' => '<p>Relevant experience.</p>',
            ],
            'bn' => [
                'slug' => $slug . '-bn',
                'title' => 'প্রোগ্রাম অফিসার',
                'department' => 'প্রোগ্রাম',
                'location' => 'ঢাকা',
                'summary' => 'ভালো প্রোগ্রাম তৈরি করুন।',
                'description' => '<p>অর্থবহ কাজ।</p>',
                'responsibilities' => '<ul><li>পরিকল্পনা</li></ul>',
                'requirements' => '<p>প্রাসঙ্গিক অভিজ্ঞতা।</p>',
            ],
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function workshopData(array $overrides = []): array
    {
        return array_replace_recursive([
            'visible_from_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
            'registration_opens_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'registration_closes_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
            'attendance_mode' => Workshop::ATTENDANCE_HYBRID,
            'registration_mode' => Workshop::REGISTRATION_WAITLIST,
            'capacity_choice' => 'limited',
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
}
