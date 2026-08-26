<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ApplicationImportController;
use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationImportBatch;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\ApplicationFormSchemaService;
use App\Services\ApplicationImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApplicationImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerImportRoutes();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(ApplicationImportService::DISK);
        $this->owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'import-controller-owner');
    }

    public function test_routes_require_authentication_and_the_exact_import_capability_and_pages_are_private(): void
    {
        $job = $this->job('Authorized recruitment import');
        $workshop = $this->workshop('Workshop must stay isolated');

        $this->get(route('recruitment.imports.index'))->assertRedirectContains('login');

        $role = Role::query()->create([
            'name' => 'No import access',
            'permission' => '',
            'actionPermission' => '',
            'status' => 1,
        ]);
        $this->actingAs($this->admin($role, 'no-import-access'), 'admin')
            ->get(route('recruitment.imports.index'))
            ->assertForbidden();

        $response = $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.imports.index', ['listing' => $job->uuid]));
        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Authorized recruitment import')
            ->assertDontSee('Workshop must stay isolated');

        $this->actingAs($this->owner, 'admin')
            ->get(route('workshop.imports.index', ['listing' => $workshop->uuid]))
            ->assertOk()
            ->assertSee('Workshop must stay isolated')
            ->assertDontSee('Authorized recruitment import');
    }

    public function test_upload_and_mapping_screen_never_render_source_rows_or_offer_a_source_download(): void
    {
        $job = $this->job('Safe mapping job');
        $response = $this->actingAs($this->owner, 'admin')->post(
            route('recruitment.imports.store', ['listing' => $job->uuid]),
            [
                'listing' => $job->uuid,
                'file' => UploadedFile::fake()->createWithContent('google-export.csv', implode("\n", [
                    'Name,Email,<img src=x onerror=alert(1)>,CV Link',
                    '"=2+2",private-person@example.test,private answer,https://drive.example.test/private',
                ]) . "\n"),
            ],
        );
        $batch = ApplicationImportBatch::query()->sole();
        $response->assertRedirect(route('recruitment.imports.preview', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]));

        $mapping = $this->actingAs($this->owner, 'admin')->get(route('recruitment.imports.preview', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]));
        $mapping->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('private-person@example.test')
            ->assertDontSee('private answer')
            ->assertDontSee('https://drive.example.test/private')
            ->assertDontSee($batch->source_path)
            ->assertDontSee('Download source')
            ->assertSee('Protected upload fields cannot be mapped');
    }

    public function test_malformed_or_non_csv_uploads_return_reviewable_validation_errors_without_a_batch(): void
    {
        $job = $this->job('Invalid upload job');
        $createRoute = route('recruitment.imports.create', ['listing' => $job->uuid]);

        $this->actingAs($this->owner, 'admin')->from($createRoute)->post(
            route('recruitment.imports.store', ['listing' => $job->uuid]),
            [
                'listing' => $job->uuid,
                'file' => UploadedFile::fake()->createWithContent('broken.csv', "Name,Name\nOne,Two\n"),
            ],
        )->assertRedirect($createRoute)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('application_import_batches', 0);

        $this->actingAs($this->owner, 'admin')->from($createRoute)->post(
            route('recruitment.imports.store', ['listing' => $job->uuid]),
            [
                'listing' => $job->uuid,
                'file' => UploadedFile::fake()->createWithContent('not-a-csv.txt', "Name,Email\nOne,one@example.test\n"),
            ],
        )->assertRedirect($createRoute)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('application_import_batches', 0);
    }

    public function test_review_shows_only_safe_row_states_and_invalid_rows_cannot_be_confirmed(): void
    {
        $job = $this->job('Invalid row review');
        $batch = $this->uploadJob($job, implode("\n", [
            'Name,Email,Phone',
            'Valid Person,valid-private@example.test,+8801700000000',
            '"<svg onload=alert(1)>Bad",not-an-email,+8801800000000',
        ]) . "\n");

        $this->actingAs($this->owner, 'admin')->post(route('recruitment.imports.preview', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]), $this->previewPayload())->assertRedirect();

        $review = $this->actingAs($this->owner, 'admin')->get(route('recruitment.imports.preview', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]));
        $review->assertOk()
            ->assertSee('Review import preview')
            ->assertSee('Invalid rows')
            ->assertSee('This import cannot be confirmed')
            ->assertSee('CSV row')
            ->assertDontSee('valid-private@example.test')
            ->assertDontSee('not-an-email')
            ->assertDontSee('<svg onload=alert(1)>', false)
            ->assertDontSee('Confirm and import');
        $this->assertSame(0, JobApplication::query()->count());

        $this->actingAs($this->owner, 'admin')->from(route('recruitment.imports.preview', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]))->post(route('recruitment.imports.confirm', [
            'batch' => $batch,
            'listing' => $job->uuid,
        ]), [
            'listing' => $job->uuid,
            'confirm_import' => '1',
        ])->assertSessionHasErrors('rows');
        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_confirm_requires_explicit_review_and_completed_workshop_result_is_safe(): void
    {
        $workshop = $this->workshop('Confirmable workshop');
        $batch = $this->uploadWorkshop($workshop, "Name,Email,Phone\nWorkshop Person,workshop-private@example.test,+8801700000000\n");
        $previewRoute = route('workshop.imports.preview', ['batch' => $batch, 'listing' => $workshop->uuid]);
        $confirmRoute = route('workshop.imports.confirm', ['batch' => $batch, 'listing' => $workshop->uuid]);

        $this->actingAs($this->owner, 'admin')->post($previewRoute, $this->previewPayload())->assertRedirect($previewRoute);
        $this->actingAs($this->owner, 'admin')->from($previewRoute)->post($confirmRoute, [
            'listing' => $workshop->uuid,
        ])->assertRedirect($previewRoute)->assertSessionHasErrors('confirm_import');
        $this->assertSame(0, WorkshopRegistration::query()->count());

        $resultRoute = route('workshop.imports.result', ['batch' => $batch, 'listing' => $workshop->uuid]);
        $this->actingAs($this->owner, 'admin')->post($confirmRoute, [
            'listing' => $workshop->uuid,
            'confirm_import' => 'yes',
        ])->assertRedirect($resultRoute);

        $this->assertSame(1, WorkshopRegistration::query()->count());
        $this->actingAs($this->owner, 'admin')->get($resultRoute)
            ->assertOk()
            ->assertSee('CSV import Completed')
            ->assertSee('Imported creates or updates')
            ->assertDontSee('workshop-private@example.test')
            ->assertDontSee($batch->source_path);
    }

    public function test_batch_access_is_scoped_to_route_kind_and_selected_listing_and_mapping_tampering_fails_closed(): void
    {
        $jobA = $this->job('Job A');
        $jobB = $this->job('Job B');
        $workshop = $this->workshop('Workshop A');
        $jobBatch = $this->uploadJob($jobA, "Name,Email\nPerson,person@example.test\n");
        $workshopBatch = $this->uploadWorkshop($workshop, "Name,Email\nRegistrant,registrant@example.test\n");

        $this->actingAs($this->owner, 'admin')->get(route('recruitment.imports.preview', [
            'batch' => $jobBatch,
            'listing' => $jobB->uuid,
        ]))->assertNotFound();
        $this->actingAs($this->owner, 'admin')->get(route('recruitment.imports.preview', [
            'batch' => $workshopBatch,
            'listing' => $jobA->uuid,
        ]))->assertNotFound();
        $this->actingAs($this->owner, 'admin')->get(route('workshop.imports.errors.download', [
            'batch' => $jobBatch,
            'listing' => $workshop->uuid,
        ]))->assertNotFound();

        $this->actingAs($this->owner, 'admin')->from(route('recruitment.imports.preview', [
            'batch' => $jobBatch,
            'listing' => $jobA->uuid,
        ]))->post(route('recruitment.imports.preview', [
            'batch' => $jobBatch,
            'listing' => $jobA->uuid,
        ]), [
            'listing' => $jobA->uuid,
            'columns' => [
                ['header' => 'Name', 'destination' => 'applicant_name'],
                ['header' => 'Email', 'destination' => 'cv'],
            ],
            'duplicate_policy' => 'update',
        ])->assertSessionHasErrors('mapping');
        $this->assertSame(ApplicationImportBatch::STATE_UPLOADED, $jobBatch->fresh()->state);
    }

    private function registerImportRoutes(): void
    {
        if (Route::has('recruitment.imports.index')) {
            return;
        }

        Route::middleware(['web', 'auth:admin', 'permission'])
            ->prefix('__tests/admin')
            ->group(function (): void {
                foreach (['recruitment' => 'recruitment', 'workshops' => 'workshop'] as $uri => $name) {
                    Route::prefix("{$uri}/imports")
                        ->name("{$name}.imports.")
                        ->controller(ApplicationImportController::class)
                        ->group(function (): void {
                            Route::get('/', 'index')->name('index');
                            Route::get('/create', 'create')->name('create');
                            Route::post('/', 'store')->name('store');
                            Route::match(['get', 'post'], '/{batch}/preview', 'preview')->name('preview');
                            Route::post('/{batch}/confirm', 'confirm')->name('confirm');
                            Route::get('/{batch}/result', 'result')->name('result');
                            Route::get('/{batch}/errors', 'downloadErrors')->name('errors.download');
                        });
                }
            });
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function job(string $title): JobPosting
    {
        $form = app(ApplicationFormSchemaService::class)->create(ApplicationForm::PURPOSE_JOB, $title . ' form', $this->owner);
        $version = app(ApplicationFormSchemaService::class)->publish($form, 1, $this->owner);
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
            'slug' => Str::slug($title) . '-' . $job->id,
            'title' => $title,
            'department' => 'Programmes',
            'location' => 'Dhaka',
        ]);

        return $job;
    }

    private function workshop(string $title): Workshop
    {
        $form = app(ApplicationFormSchemaService::class)->create(ApplicationForm::PURPOSE_WORKSHOP, $title . ' form', $this->owner);
        $version = app(ApplicationFormSchemaService::class)->publish($form, 1, $this->owner);
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
            'capacity' => 10,
        ]);
        $workshop->translations()->create([
            'locale' => 'en',
            'slug' => Str::slug($title) . '-' . $workshop->id,
            'title' => $title,
        ]);

        return $workshop;
    }

    private function uploadJob(JobPosting $job, string $csv): ApplicationImportBatch
    {
        return app(ApplicationImportService::class)->upload(
            $job,
            UploadedFile::fake()->createWithContent('import.csv', $csv),
            $this->owner,
        );
    }

    private function uploadWorkshop(Workshop $workshop, string $csv): ApplicationImportBatch
    {
        return app(ApplicationImportService::class)->upload(
            $workshop,
            UploadedFile::fake()->createWithContent('import.csv', $csv),
            $this->owner,
        );
    }

    /** @return array<string,mixed> */
    private function previewPayload(): array
    {
        return [
            'columns' => [
                ['header' => 'Name', 'destination' => 'applicant_name'],
                ['header' => 'Email', 'destination' => 'email'],
                ['header' => 'Phone', 'destination' => 'phone'],
            ],
            'duplicate_policy' => 'update',
        ];
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }
}
