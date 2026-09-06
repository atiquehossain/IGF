<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\AuthMenu;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\OpportunityManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpportunityContentTrashIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_job_and_workshop_drafts_have_localized_searchable_recovery_ui(): void
    {
        $admin = $this->admin(
            ['recruitment.jobs.index', 'workshops.index', 'content.trash.index'],
            ['recruitment.jobs.destroy', 'workshops.destroy', 'content.trash.edit', 'content.trash.destroy'],
            'recovery-editor'
        );
        $job = $this->job('programme-officer');
        $workshop = $this->workshop('leadership-workshop');

        $this->asAdmin($admin)->get(route('recruitment.jobs.index'))
            ->assertOk()
            ->assertSee('Move to trash')
            ->assertSee(route('content.trash.index', ['type' => 'job']), false)
            ->assertSee('Deleted job drafts');
        $this->asAdmin($admin)->delete(route('recruitment.jobs.destroy', $job))
            ->assertRedirect(route('recruitment.jobs.index'))
            ->assertSessionHas('message', 'Unused job draft moved to Content Trash.');

        $this->asAdmin($admin)->get(route('workshops.index'))
            ->assertOk()
            ->assertSee('Move to trash')
            ->assertSee(route('content.trash.index', ['type' => 'workshop']), false)
            ->assertSee('Deleted workshop drafts');
        $this->asAdmin($admin)->delete(route('workshops.destroy', $workshop))
            ->assertRedirect(route('workshops.index'))
            ->assertSessionHas('message', 'Unused workshop draft moved to Content Trash.');

        $this->asAdmin($admin)->get(route('content.trash.index', [
            'type' => 'job',
            'search' => 'programme-officer-bn',
        ]))->assertOk()
            ->assertSee('Job draft')
            ->assertSee('Programme Officer')
            ->assertSee('EN: /careers/programme-officer')
            ->assertSee('BN: /careers/programme-officer-bn')
            ->assertSee(route('content.trash.restore', ['job', $job->id]), false)
            ->assertDontSee('Leadership Workshop');

        $this->asAdmin($admin)->get(route('content.trash.index', [
            'type' => 'workshop',
            'search' => 'Leadership Workshop',
        ]))->assertOk()
            ->assertSee('Workshop draft')
            ->assertSee('Leadership Workshop')
            ->assertSee('EN: /workshops/leadership-workshop')
            ->assertSee('BN: /workshops/leadership-workshop-bn')
            ->assertSee(route('content.trash.force-destroy', ['workshop', $workshop->id]), false)
            ->assertDontSee('Programme Officer');
    }

    public function test_restore_recovers_opportunity_roots_and_any_soft_deleted_seo_without_replacing_shared_forms(): void
    {
        $admin = $this->admin(['content.trash.index'], ['content.trash.edit'], 'restore-editor');
        $job = $this->job('restorable-job');
        $workshop = $this->workshop('restorable-workshop');
        $jobSeo = $this->seo($job, 'Restorable job SEO');
        $workshopSeo = $this->seo($workshop, 'Restorable workshop SEO');
        $jobFormId = $job->application_form_id;
        $workshopFormId = $workshop->application_form_id;

        $job->delete();
        $workshop->delete();
        $workshopSeo->delete();

        $this->assertDatabaseHas('seo_metadata', ['id' => $jobSeo->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('seo_metadata', ['id' => $workshopSeo->id]);

        $this->asAdmin($admin)->postJson(route('content.trash.restore', ['job', $job->id]))
            ->assertOk()
            ->assertJson(['message' => 'Content restored successfully.']);
        $this->asAdmin($admin)->postJson(route('content.trash.restore', ['workshop', $workshop->id]))
            ->assertOk()
            ->assertJson(['message' => 'Content restored successfully.']);

        $this->assertDatabaseHas('job_postings', ['id' => $job->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('workshops', ['id' => $workshop->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('seo_metadata', ['id' => $jobSeo->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('seo_metadata', ['id' => $workshopSeo->id, 'deleted_at' => null]);
        $this->assertSame(2, $job->translations()->count());
        $this->assertSame(2, $workshop->translations()->count());
        $this->assertDatabaseHas('application_forms', ['id' => $jobFormId]);
        $this->assertDatabaseHas('application_forms', ['id' => $workshopFormId]);
    }

    public function test_permanent_delete_removes_owned_translations_scorecards_and_seo_but_keeps_reusable_forms(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        }
        $admin = $this->admin(['content.trash.index'], ['content.trash.destroy'], 'purge-editor');
        $job = $this->job('purge-job');
        $workshop = $this->workshop('purge-workshop');
        $criterion = JobScorecardCriterion::create([
            'job_posting_id' => $job->id,
            'label' => 'Experience',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $jobSeo = $this->seo($job, 'Purge job SEO');
        $workshopSeo = $this->seo($workshop, 'Purge workshop SEO');
        $jobRevision = $this->seoRevision($jobSeo);
        $workshopRevision = $this->seoRevision($workshopSeo);
        $jobFormId = $job->application_form_id;
        $workshopFormId = $workshop->application_form_id;
        $jobVersionId = $job->current_form_version_id;
        $workshopVersionId = $workshop->current_form_version_id;

        $job->delete();
        $workshop->delete();

        $this->asAdmin($admin)->deleteJson(route('content.trash.force-destroy', ['job', $job->id]))
            ->assertOk()
            ->assertJson(['message' => 'Content permanently deleted.']);
        $this->asAdmin($admin)->deleteJson(route('content.trash.force-destroy', ['workshop', $workshop->id]))
            ->assertOk()
            ->assertJson(['message' => 'Content permanently deleted.']);

        $this->assertDatabaseMissing('job_postings', ['id' => $job->id]);
        $this->assertDatabaseMissing('workshops', ['id' => $workshop->id]);
        $this->assertDatabaseMissing('job_posting_translations', ['job_posting_id' => $job->id]);
        $this->assertDatabaseMissing('workshop_translations', ['workshop_id' => $workshop->id]);
        $this->assertDatabaseMissing('job_scorecard_criteria', ['id' => $criterion->id]);
        $this->assertDatabaseMissing('seo_metadata', ['id' => $jobSeo->id]);
        $this->assertDatabaseMissing('seo_metadata', ['id' => $workshopSeo->id]);
        $this->assertDatabaseMissing('seo_metadata_revisions', ['id' => $jobRevision->id]);
        $this->assertDatabaseMissing('seo_metadata_revisions', ['id' => $workshopRevision->id]);
        $this->assertDatabaseHas('application_forms', ['id' => $jobFormId]);
        $this->assertDatabaseHas('application_forms', ['id' => $workshopFormId]);
        $this->assertDatabaseHas('application_form_versions', ['id' => $jobVersionId]);
        $this->assertDatabaseHas('application_form_versions', ['id' => $workshopVersionId]);
    }

    public function test_historical_submissions_block_purge_and_recovery_actions_require_independent_permissions(): void
    {
        $manager = $this->admin(
            ['content.trash.index'],
            ['content.trash.edit', 'content.trash.destroy'],
            'retention-manager'
        );
        $job = $this->job('retained-job');
        $workshop = $this->workshop('retained-workshop');
        $jobSeo = $this->seo($job, 'Retained job SEO');
        $workshopSeo = $this->seo($workshop, 'Retained workshop SEO');
        $application = JobApplication::create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $job->current_form_version_id,
            'name' => 'Historical applicant',
            'email' => 'historical-job@example.test',
        ]);
        $registration = WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshop->current_form_version_id,
            'name' => 'Historical registrant',
            'email' => 'historical-workshop@example.test',
        ]);
        $application->delete();
        $registration->delete();
        app(OpportunityManagementService::class)->deleteJobDraft($job, $manager);
        app(OpportunityManagementService::class)->deleteWorkshopDraft($workshop, $manager);

        $this->asAdmin($manager)->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('This job is retained because applicant records reference it.')
            ->assertSee('This workshop is retained because registration records reference it.')
            ->assertSee('Retained for records');

        $this->asAdmin($manager)->deleteJson(route('content.trash.force-destroy', ['job', $job->id]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'This job is retained because applicant records reference it. Restore the job to manage it; applicant history cannot be removed from Content Trash.']);
        $this->asAdmin($manager)->deleteJson(route('content.trash.force-destroy', ['workshop', $workshop->id]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'This workshop is retained because registration records reference it. Restore the workshop to manage it; registration history cannot be removed from Content Trash.']);
        $this->assertSoftDeleted('job_postings', ['id' => $job->id]);
        $this->assertSoftDeleted('workshops', ['id' => $workshop->id]);
        $this->assertSoftDeleted('job_applications', ['id' => $application->id]);
        $this->assertSoftDeleted('workshop_registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('seo_metadata', ['id' => $jobSeo->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('seo_metadata', ['id' => $workshopSeo->id, 'deleted_at' => null]);

        $viewer = $this->admin(['content.trash.index'], [], 'trash-viewer');
        $this->asAdmin($viewer)->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee('View only')
            ->assertDontSee('class="btn btn-sm btn-success trash-action"', false);
        $this->asAdmin($viewer)->postJson(route('content.trash.restore', ['job', $job->id]))
            ->assertForbidden();
        $this->asAdmin($viewer)->deleteJson(route('content.trash.force-destroy', ['workshop', $workshop->id]))
            ->assertForbidden();
    }

    private function job(string $slug): JobPosting
    {
        [$form, $version] = $this->form(ApplicationForm::PURPOSE_JOB, 'Application for ' . $slug);
        $job = JobPosting::create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_DRAFT,
            'application_opens_at' => now()->addDay(),
            'application_closes_at' => now()->addDays(10),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => 1,
            'editor_version' => 1,
        ]);
        $job->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => $slug,
                'title' => str_contains($slug, 'programme-officer') ? 'Programme Officer' : Str::headline($slug),
                'department' => 'Programmes',
                'location' => 'Dhaka',
            ],
            [
                'locale' => 'bn',
                'slug' => $slug . '-bn',
                'title' => 'বাংলা ' . Str::headline($slug),
                'department' => 'প্রোগ্রাম',
                'location' => 'ঢাকা',
            ],
        ]);

        return $job;
    }

    private function workshop(string $slug): Workshop
    {
        [$form, $version] = $this->form(ApplicationForm::PURPOSE_WORKSHOP, 'Registration for ' . $slug);
        $workshop = Workshop::create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_DRAFT,
            'registration_opens_at' => now()->addDay(),
            'registration_closes_at' => now()->addDays(5),
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_HYBRID,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'editor_version' => 1,
        ]);
        $workshop->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => $slug,
                'title' => str_contains($slug, 'leadership-workshop') ? 'Leadership Workshop' : Str::headline($slug),
            ],
            [
                'locale' => 'bn',
                'slug' => $slug . '-bn',
                'title' => 'বাংলা ' . Str::headline($slug),
            ],
        ]);

        return $workshop;
    }

    /** @return array{0:ApplicationForm,1:ApplicationFormVersion} */
    private function form(string $purpose, string $name): array
    {
        $form = ApplicationForm::create([
            'purpose' => $purpose,
            'name' => $name,
            'editor_version' => 1,
        ]);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);

        return [$form, $version];
    }

    private function seo(JobPosting|Workshop $owner, string $title): SeoMetadata
    {
        return SeoMetadata::create([
            'seoable_type' => $owner::class,
            'seoable_id' => $owner->id,
            'locale' => 'en',
            'title' => $title,
        ]);
    }

    private function seoRevision(SeoMetadata $metadata): SeoMetadataRevision
    {
        return SeoMetadataRevision::create([
            'uuid' => (string) Str::uuid(),
            'seo_metadata_id' => $metadata->id,
            'seoable_type' => $metadata->seoable_type,
            'seoable_id' => $metadata->seoable_id,
            'locale' => $metadata->locale,
            'snapshot' => ['title' => $metadata->title],
            'reason' => 'Before test update',
            'created_at' => now(),
        ]);
    }

    /** @param list<string> $menuLinks
     *  @param list<string> $actionLinks
     */
    private function admin(array $menuLinks, array $actionLinks, string $username): Admin
    {
        $menus = AuthMenu::query()->whereIn('link', $menuLinks)->get();
        $actions = MenuAction::query()->whereIn('link', $actionLinks)->get();
        $this->assertCount(count($menuLinks), $menus);
        $this->assertCount(count($actionLinks), $actions);
        $role = Role::create([
            'name' => Str::headline($username),
            'permission' => $menus->pluck('id')->implode(','),
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }
}
