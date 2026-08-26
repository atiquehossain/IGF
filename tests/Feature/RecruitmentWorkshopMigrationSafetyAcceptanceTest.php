<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminListingPreference;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\ApplicationImportBatch;
use App\Models\AuthMenu;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RecruitmentWorkshopMigrationSafetyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_migrations_are_rerunnable_and_every_data_bearing_rollback_fails_closed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ownerRole = Role::query()->where('is_owner', true)->firstOrFail();
        $admin = Admin::query()->create([
            'name' => 'Migration owner', 'username' => 'migration-owner',
            'email' => 'migration-owner@example.test', 'role' => (string) $ownerRole->id, 'status' => 1,
        ]);
        [$jobForm, $jobVersion] = $this->formVersion(ApplicationForm::PURPOSE_JOB);
        [$workshopForm, $workshopVersion] = $this->formVersion(ApplicationForm::PURPOSE_WORKSHOP);
        $job = JobPosting::query()->create([
            'application_form_id' => $jobForm->id, 'current_form_version_id' => $jobVersion->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(), 'application_opens_at' => now()->subHour(), 'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME, 'work_arrangement' => JobPosting::WORK_ON_SITE, 'vacancy_count' => 1,
        ]);
        $workshop = Workshop::query()->create([
            'application_form_id' => $workshopForm->id, 'current_form_version_id' => $workshopVersion->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(), 'registration_opens_at' => now()->subHour(), 'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(),
            'attendance_mode' => Workshop::ATTENDANCE_ONLINE, 'registration_mode' => Workshop::REGISTRATION_MANUAL,
        ]);
        JobApplication::query()->create([
            'job_posting_id' => $job->id, 'application_form_version_id' => $jobVersion->id,
            'name' => 'Preserved applicant', 'email' => 'preserved-job@example.test',
        ]);
        WorkshopRegistration::query()->create([
            'workshop_id' => $workshop->id, 'application_form_version_id' => $workshopVersion->id,
            'name' => 'Preserved registrant', 'email' => 'preserved-workshop@example.test',
        ]);
        ApplicationImportBatch::query()->create([
            'target_kind' => ApplicationImportBatch::TARGET_JOB,
            'job_posting_id' => $job->id,
            'application_form_version_id' => $jobVersion->id,
            'form_schema_hash' => str_repeat('a', 64),
            'source_disk' => 'applicant_imports', 'source_path' => 'imports/preserved.csv',
            'source_name' => 'preserved.csv', 'source_sha256' => str_repeat('b', 64),
            'uploaded_by_admin_id' => $admin->id,
        ]);
        AdminListingPreference::query()->create([
            'admin_id' => $admin->id, 'listing_key' => 'migration-safety',
            'visible_columns' => ['name'], 'sort_column' => 'name', 'sort_direction' => 'asc',
        ]);

        $migrationFiles = [
            '2026_08_26_090000_create_application_form_foundation.php',
            '2026_08_26_090100_create_job_posting_foundation.php',
            '2026_08_26_090200_create_job_application_foundation.php',
            '2026_08_26_090300_create_workshop_foundation.php',
            '2026_08_26_090400_create_workshop_registration_foundation.php',
            '2026_08_26_090500_create_application_import_foundation.php',
        ];
        $protectedTables = [
            'application_forms', 'application_form_versions', 'job_postings', 'job_applications',
            'workshops', 'workshop_registrations', 'application_import_batches', 'admin_listing_preferences',
        ];
        $before = collect($protectedTables)->mapWithKeys(fn (string $table): array => [$table => \DB::table($table)->count()])->all();

        foreach ($migrationFiles as $file) {
            $migration = require database_path('migrations/' . $file);
            $migration->up();
        }
        foreach ($before as $table => $count) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame($count, \DB::table($table)->count(), "Rerunning migrations changed {$table}.");
        }

        foreach ($migrationFiles as $file) {
            $migration = require database_path('migrations/' . $file);
            try {
                $migration->down();
                $this->fail("{$file} dropped data-bearing tables.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Refusing to drop', $exception->getMessage(), $file);
            }
        }
        foreach ($before as $table => $count) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame($count, \DB::table($table)->count(), "Rollback guard changed {$table}.");
        }
    }

    public function test_permission_migration_rollback_is_a_no_op_that_preserves_stable_ids_and_grants(): void
    {
        $this->seed(DatabaseSeeder::class);
        $menuBefore = AuthMenu::query()->whereIn('link', ['recruitment.jobs.index', 'workshops.index'])
            ->pluck('id', 'link')->all();
        $actionsBefore = MenuAction::query()->whereIn('link', [
            'recruitment.jobs.create', 'workshops.create',
            'recruitment.applications.anonymize', 'workshop.registrations.anonymize',
        ])->pluck('id', 'link')->all();
        $owner = Role::query()->where('is_owner', true)->firstOrFail();
        $grantBefore = [$owner->permission, $owner->actionPermission];

        $migration = require database_path('migrations/2026_08_26_090000_register_recruitment_workshop_permissions.php');
        $migration->up();
        $migration->down();

        $this->assertSame($menuBefore, AuthMenu::query()->whereIn('link', array_keys($menuBefore))->pluck('id', 'link')->all());
        $this->assertSame($actionsBefore, MenuAction::query()->whereIn('link', array_keys($actionsBefore))->pluck('id', 'link')->all());
        $owner->refresh();
        $this->assertSame($grantBefore, [$owner->permission, $owner->actionPermission]);
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function formVersion(string $purpose): array
    {
        $form = ApplicationForm::query()->create(['purpose' => $purpose, 'name' => $purpose . ' migration form']);
        $version = ApplicationFormVersion::query()->create([
            'application_form_id' => $form->id, 'version' => 1,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose . '-migration'), 'published_at' => now(),
        ]);
        return [$form, $version];
    }
}
