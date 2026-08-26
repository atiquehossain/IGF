<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\WorkshopRegistrationController;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\AdminListingPreference;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\AuthMenu;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\ApplicationFormSchemaService;
use App\Services\PrivateApplicationDocumentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Support\ValidPdfFixture;

class AdminApplicationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerDashboardRoutes();
        $this->seed(DatabaseSeeder::class);
        $this->owner = $this->adminForRole(
            Role::query()->where('is_owner', true)->firstOrFail(),
            'dashboard-owner',
        );
    }

    public function test_job_index_uses_private_session_search_and_persists_safe_answer_columns(): void
    {
        [$job, $field] = $this->job('Programme Officer');
        $application = $this->jobApplication($job, 'Private Candidate', 'private-candidate@example.test');
        $application->answers()->create([
            'application_form_field_id' => $field->id,
            'value_text' => 'Community programme leadership',
        ]);

        $index = $this->actingAs($this->owner, 'admin')->get(route('recruitment.applications.index', [
            'listing' => $job->uuid,
        ]));
        $index->assertOk()
            ->assertSee('Programme Officer')
            ->assertSee('Private Candidate')
            ->assertSee('private-candidate@example.test')
            ->assertSee('Community programme leadership')
            ->assertSee('Private applicant search')
            ->assertSee('data-no-busy', false)
            ->assertSee('application-dashboard/dashboard.css', false);

        $dashboardCss = file_get_contents(public_path('admin-assets/application-dashboard/dashboard.css'));
        $this->assertIsString($dashboardCss);
        $this->assertMatchesRegularExpression('/\.ad-copy-button\s*\{[^}]*min-height:\s*44px;[^}]*min-width:\s*44px;/s', $dashboardCss);

        $search = $this->actingAs($this->owner, 'admin')->post(route('recruitment.applications.search'), [
            'listing' => $job->uuid,
            'search' => 'private-candidate@example.test',
        ]);
        $search->assertRedirect(route('recruitment.applications.index', ['listing' => $job->uuid]));
        $this->assertStringNotContainsString('private-candidate', (string) $search->headers->get('Location'));
        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.applications.index', ['listing' => $job->uuid]))
            ->assertOk()
            ->assertSee('Private search active')
            ->assertSee('Private Candidate');

        $audit = AdminAuditEvent::query()->where('action', 'private_search.started')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('private-candidate', mb_strtolower(json_encode($audit->context)));
        $this->actingAs($this->owner, 'admin')->post(route('recruitment.applications.search.clear'), [
            'listing' => $job->uuid,
        ])->assertRedirect(route('recruitment.applications.index', ['listing' => $job->uuid]));
        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.applications.index', ['listing' => $job->uuid]))
            ->assertDontSee('Private search active');

        $this->actingAs($this->owner, 'admin')->post(route('recruitment.applications.bulk'), [
            'listing' => $job->uuid,
            'operation' => 'preferences',
            'visible_columns' => ['answer:motivation'],
            'sort' => 'name',
            'direction' => 'asc',
        ])->assertRedirect(route('recruitment.applications.index', ['listing' => $job->uuid]));
        $preference = AdminListingPreference::query()->where([
            'admin_id' => $this->owner->id,
            'listing_key' => 'recruitment.applications:' . $job->id,
        ])->firstOrFail();
        $this->assertSame(['answer:motivation'], $preference->visible_columns);
        $this->assertSame('name', $preference->sort_column);

        $this->actingAs($this->owner, 'admin')->post(route('recruitment.applications.bulk'), [
            'listing' => $job->uuid,
            'operation' => 'status',
            'application_ids' => [$application->id],
            'workflow_status' => JobApplication::STATUS_UNDER_REVIEW,
        ])->assertRedirect(route('recruitment.applications.index', ['listing' => $job->uuid]));
    }

    public function test_job_detail_workflow_assignment_append_only_notes_and_scorecard_are_wired(): void
    {
        [$job, $field] = $this->job('Finance Lead');
        $application = $this->jobApplication($job, 'Applicant One', 'one@example.test');
        $application->answers()->create([
            'application_form_field_id' => $field->id,
            'value_text' => 'Seven years of experience',
        ]);
        $criterion = JobScorecardCriterion::query()->create([
            'job_posting_id' => $job->id,
            'label' => 'Relevant experience',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $reviewer = $this->adminForRole($this->owner->roleModel, 'assigned-reviewer');

        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.applications.show', $application))
            ->assertOk()
            ->assertSee('Versioned answers')
            ->assertSee('Seven years of experience')
            ->assertSee('Copy email')
            ->assertSee('Job scorecard')
            ->assertSee('ANONYMIZE ' . $application->reference_number);

        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.applications.assign', $application), [
            'assigned_to_admin_id' => $reviewer->id,
        ])->assertRedirect(route('recruitment.applications.show', $application));
        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.applications.workflow', $application), [
            'workflow_status' => JobApplication::STATUS_UNDER_REVIEW,
        ])->assertRedirect(route('recruitment.applications.show', $application));
        $this->actingAs($this->owner, 'admin')->post(route('recruitment.applications.notes.store', $application), [
            'body' => 'Reviewed qualifications against the role profile.',
        ])->assertRedirect(route('recruitment.applications.show', $application));
        $this->actingAs($this->owner, 'admin')->put(route('recruitment.applications.score', $application), [
            'criterion' => $criterion->uuid,
            'score' => '8.5',
            'comment' => 'Strong evidence provided.',
        ])->assertRedirect(route('recruitment.applications.show', $application));

        $application->refresh();
        $this->assertSame(JobApplication::STATUS_UNDER_REVIEW, $application->workflow_status);
        $this->assertSame($reviewer->id, $application->assigned_to_admin_id);
        $this->assertDatabaseHas('job_application_notes', ['job_application_id' => $application->id]);
        $this->assertDatabaseHas('job_application_scores', [
            'job_application_id' => $application->id,
            'job_scorecard_criterion_id' => $criterion->id,
            'reviewer_admin_id' => $this->owner->id,
            'score' => 8.5,
        ]);
    }

    public function test_job_bulk_actions_reject_ids_from_another_listing_before_service_mutation(): void
    {
        [$firstJob] = $this->job('First job');
        [$secondJob] = $this->job('Second job');
        $first = $this->jobApplication($firstJob, 'First Applicant', 'first@example.test');
        $second = $this->jobApplication($secondJob, 'Second Applicant', 'second@example.test');

        $this->actingAs($this->owner, 'admin')
            ->from(route('recruitment.applications.index', ['listing' => $firstJob->uuid]))
            ->post(route('recruitment.applications.bulk'), [
                'listing' => $firstJob->uuid,
                'operation' => 'status',
                'application_ids' => [$first->id, $second->id],
                'workflow_status' => JobApplication::STATUS_UNDER_REVIEW,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('application_ids');

        $this->assertSame(JobApplication::STATUS_NEW, $first->fresh()->workflow_status);
        $this->assertSame(JobApplication::STATUS_NEW, $second->fresh()->workflow_status);
        $this->assertDatabaseCount('job_application_status_events', 0);
    }

    public function test_export_and_document_download_are_private_streamed_audited_and_parent_scoped(): void
    {
        Storage::fake(PrivateApplicationDocumentService::DISK);
        [$job, $field] = $this->job('Document Review');
        $application = $this->jobApplication($job, 'CSV Candidate', 'csv@example.test');
        $other = $this->jobApplication($job, 'Other Candidate', 'other@example.test');
        $application->answers()->create([
            'application_form_field_id' => $field->id,
            'value_text' => '=DANGEROUS()',
        ]);
        $path = 'documents/' . str_repeat('a', 48) . '.pdf';
        $pdf = ValidPdfFixture::bytes();
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, $pdf);
        $document = JobApplicationDocument::query()->create([
            'job_application_id' => $application->id,
            'application_form_field_id' => null,
            'document_kind' => JobApplicationDocument::KIND_CV,
            'disk' => PrivateApplicationDocumentService::DISK,
            'path' => $path,
            'original_name' => "candidate\r\nunsafe.pdf",
            'mime_type' => 'application/pdf',
            'bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
        ]);

        $export = $this->actingAs($this->owner, 'admin')->get(route('recruitment.applications.export', [
            'listing' => $job->uuid,
            'columns' => ['name', 'email', 'answer:motivation'],
        ]));
        $export->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('CSV Candidate', $csv);
        $this->assertStringContainsString("'=DANGEROUS()", $csv);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'recruitment.applications.exported']);

        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.applications.download', [$other, $document]))
            ->assertNotFound();
        $download = $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.applications.download', [$application, $document]));
        $download->assertOk();
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'recruitment.application.document_downloaded',
            'target_id' => (string) $application->id,
        ]);
    }

    public function test_workshop_dashboard_supports_review_actions_and_cross_domain_roles_fail_closed(): void
    {
        [$workshop, $field] = $this->workshop('Safeguarding Workshop');
        $registration = $this->registration($workshop, 'Workshop Applicant', 'workshop@example.test');
        $registration->answers()->create([
            'application_form_field_id' => $field->id,
            'value_text' => 'Vegetarian',
        ]);
        [$job] = $this->job('Restricted Recruitment');

        $workshopMenu = AuthMenu::query()->where('link', 'workshop.registrations.index')->firstOrFail();
        $workshopRole = Role::query()->create([
            'name' => 'Workshop viewer only',
            'security_rank' => 90,
            'is_owner' => false,
            'permission' => (string) $workshopMenu->id,
            'actionPermission' => '',
            'status' => 1,
        ]);
        $viewer = $this->adminForRole($workshopRole, 'workshop-viewer');
        $this->actingAs($viewer, 'admin')
            ->get(route('workshop.registrations.index', ['listing' => $workshop->uuid]))
            ->assertOk()
            ->assertSee('Workshop Applicant')
            ->assertSee('Vegetarian');
        $this->actingAs($viewer, 'admin')
            ->get(route('recruitment.applications.index', ['listing' => $job->uuid]))
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->post(route('workshop.registrations.anonymize', $registration), [
                'confirmation' => 'ANONYMIZE ' . $registration->reference_number,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner, 'admin')->patch(route('workshop.registrations.workflow', $registration), [
            'workflow_status' => WorkshopRegistration::STATUS_WAITLISTED,
        ])->assertRedirect(route('workshop.registrations.show', $registration));
        $this->actingAs($this->owner, 'admin')->post(route('workshop.registrations.notes.store', $registration), [
            'body' => 'Manual approval review completed.',
        ])->assertRedirect(route('workshop.registrations.show', $registration));
        $this->actingAs($this->owner, 'admin')->post(route('workshop.registrations.bulk'), [
            'listing' => $workshop->uuid,
            'operation' => 'status',
            'registration_ids' => [$registration->id],
            'workflow_status' => WorkshopRegistration::STATUS_CONFIRMED,
        ])->assertRedirect(route('workshop.registrations.index', ['listing' => $workshop->uuid]));
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $registration->fresh()->workflow_status);
        $this->assertDatabaseHas('workshop_registration_notes', ['workshop_registration_id' => $registration->id]);
    }

    public function test_owner_privacy_actions_require_exact_typed_confirmation(): void
    {
        [$workshop] = $this->workshop('Privacy Workshop');
        $registration = $this->registration($workshop, 'Privacy Applicant', 'privacy@example.test');

        $this->actingAs($this->owner, 'admin')
            ->from(route('workshop.registrations.show', $registration))
            ->post(route('workshop.registrations.anonymize', $registration), ['confirmation' => 'ANONYMIZE'])
            ->assertRedirect()
            ->assertSessionHasErrors('confirmation');
        $this->assertNull($registration->fresh()->anonymized_at);

        $this->actingAs($this->owner, 'admin')
            ->post(route('workshop.registrations.anonymize', $registration), [
                'confirmation' => 'ANONYMIZE ' . $registration->reference_number,
            ])
            ->assertRedirect(route('workshop.registrations.show', $registration));
        $this->assertNotNull($registration->fresh()->anonymized_at);
        $this->assertSame('Anonymized applicant', $registration->fresh()->name);

        $deletable = $this->registration($workshop, 'Delete Applicant', 'delete@example.test');
        $this->actingAs($this->owner, 'admin')
            ->delete(route('workshop.registrations.delete', $deletable), [
                'confirmation' => 'DELETE ' . $deletable->reference_number,
            ])
            ->assertRedirect(route('workshop.registrations.index', ['listing' => $workshop->uuid]));
        $this->assertDatabaseMissing('workshop_registrations', ['id' => $deletable->id]);
    }

    /** @return array{JobPosting, ApplicationFormField} */
    private function job(string $title): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_JOB, $title . ' form', $this->owner);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $schema = $forms->schemaArray($draft);
        $schema[] = $this->textField('motivation', 'Motivation');
        $forms->replaceDraft($form, 1, $schema, $this->owner);
        $version = $forms->publish($form, 2, $this->owner);
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
        $job->translations()->create([
            'locale' => 'en',
            'slug' => str($title)->slug() . '-' . $job->id,
            'title' => $title,
        ]);

        return [$job, $version->fields()->where('field_key', 'motivation')->firstOrFail()];
    }

    /** @return array{Workshop, ApplicationFormField} */
    private function workshop(string $title): array
    {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, $title . ' form', $this->owner);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $schema = $forms->schemaArray($draft);
        $schema[] = $this->textField('dietary_requirements', 'Dietary requirements');
        $forms->replaceDraft($form, 1, $schema, $this->owner);
        $version = $forms->publish($form, 2, $this->owner);
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
            'registration_mode' => Workshop::REGISTRATION_MANUAL,
            'capacity' => 20,
        ]);
        $workshop->translations()->create([
            'locale' => 'en',
            'slug' => str($title)->slug() . '-' . $workshop->id,
            'title' => $title,
        ]);

        return [$workshop, $version->fields()->where('field_key', 'dietary_requirements')->firstOrFail()];
    }

    private function jobApplication(JobPosting $job, string $name, string $email): JobApplication
    {
        return JobApplication::query()->create([
            'job_posting_id' => $job->id,
            'application_form_version_id' => $job->current_form_version_id,
            'name' => $name,
            'email' => $email,
            'workflow_status' => JobApplication::STATUS_NEW,
            'source' => JobApplication::SOURCE_PUBLIC,
        ]);
    }

    private function registration(Workshop $workshop, string $name, string $email): WorkshopRegistration
    {
        return WorkshopRegistration::query()->create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $workshop->current_form_version_id,
            'name' => $name,
            'email' => $email,
            'workflow_status' => WorkshopRegistration::STATUS_PENDING,
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ]);
    }

    /** @return array<string, mixed> */
    private function textField(string $key, string $label): array
    {
        return [
            'key' => $key,
            'system_key' => null,
            'type' => ApplicationFormField::TYPE_SHORT_TEXT,
            'required' => false,
            'validation' => ['max_length' => 500],
            'translations' => [
                'en' => ['label' => $label, 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => 'বাংলা ' . $label, 'help' => '', 'placeholder' => ''],
            ],
            'options' => [],
            'conditions' => [],
        ];
    }

    private function adminForRole(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => str($username)->headline(),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    private function registerDashboardRoutes(): void
    {
        if (Route::has('recruitment.applications.index')) {
            return;
        }

        Route::middleware(['web', 'auth:admin', 'permission'])
            ->prefix('__tests/admin')
            ->group(function (): void {
                Route::prefix('recruitment/applications')
                    ->name('recruitment.applications.')
                    ->controller(JobApplicationController::class)
                    ->group(function (): void {
                        Route::get('/', 'index')->name('index');
                        Route::post('/search', 'search')->name('search');
                        Route::post('/search/clear', 'clearSearch')->name('search.clear');
                        Route::post('/bulk', 'bulk')->name('bulk');
                        Route::get('/export', 'export')->name('export');
                        Route::get('/{application}', 'show')->name('show');
                        Route::patch('/{application}/workflow', 'workflow')->name('workflow');
                        Route::patch('/{application}/assignment', 'assign')->name('assign');
                        Route::put('/{application}/score', 'score')->name('score');
                        Route::post('/{application}/notes', 'addNote')->name('notes.store');
                        Route::get('/{application}/documents/{document}', 'download')->name('download');
                        Route::post('/{application}/anonymize', 'anonymize')->name('anonymize');
                        Route::delete('/{application}/delete', 'delete')->name('delete');
                        Route::delete('/{application}', 'destroy')->name('destroy');
                    });

                Route::prefix('workshops/registrations')
                    ->name('workshop.registrations.')
                    ->controller(WorkshopRegistrationController::class)
                    ->group(function (): void {
                        Route::get('/', 'index')->name('index');
                        Route::post('/search', 'search')->name('search');
                        Route::post('/search/clear', 'clearSearch')->name('search.clear');
                        Route::post('/bulk', 'bulk')->name('bulk');
                        Route::get('/export', 'export')->name('export');
                        Route::get('/{registration}', 'show')->name('show');
                        Route::patch('/{registration}/workflow', 'workflow')->name('workflow');
                        Route::patch('/{registration}/assignment', 'assign')->name('assign');
                        Route::post('/{registration}/notes', 'addNote')->name('notes.store');
                        Route::get('/{registration}/documents/{document}', 'download')->name('download');
                        Route::post('/{registration}/anonymize', 'anonymize')->name('anonymize');
                        Route::delete('/{registration}/delete', 'delete')->name('delete');
                        Route::delete('/{registration}', 'destroy')->name('destroy');
                    });
            });
        Route::getRoutes()->refreshNameLookups();
    }
}
