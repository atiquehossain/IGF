<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\AuthMenu;
use App\Models\JobPosting;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Workshop;
use App\Services\ApplicationFormSchemaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApplicationFormLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = $this->admin(Role::query()->where('is_owner', true)->firstOrFail(), 'form-lifecycle-owner');
    }

    public function test_manager_can_edit_publish_duplicate_archive_restore_and_safely_purge_unused_drafts(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $form = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Original intake', $this->owner);
        $schema = $schemas->schemaArray($this->draft($form));

        $this->actingAs($this->owner, 'admin')->putJson(route('recruitment.forms.update', $form), [
            'editor_version' => 1,
            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
        ])->assertOk()->assertJsonPath('editor_version', 2);

        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.forms.metadata', $form), [
            'editor_version' => 2,
            'name' => 'Programme application template',
            'is_template' => '1',
        ])->assertRedirect();
        $form->refresh();
        $this->assertSame('Programme application template', $form->name);
        $this->assertTrue($form->is_template);
        $this->assertSame(3, $form->editor_version);

        $this->actingAs($this->owner, 'admin')->postJson(route('recruitment.forms.publish', $form), [
            'editor_version' => 3,
        ])->assertOk()->assertJsonPath('editor_version', 4);
        $form->refresh();
        $published = $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->firstOrFail();
        $publishedIdentity = [$published->id, $published->uuid, $published->schema_hash, $published->updated_at?->toAtomString()];

        $this->actingAs($this->owner, 'admin')->post(route('recruitment.forms.duplicate', $form), [
            'name' => 'Unused draft copy',
            'is_template' => '0',
        ])->assertRedirect();
        $copy = ApplicationForm::query()->where('name', 'Unused draft copy')->firstOrFail();
        $copyVersionId = $this->draft($copy)->id;

        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.forms.destroy', $copy), [
            'editor_version' => $copy->editor_version,
        ])->assertRedirect(route('recruitment.forms.index'));
        $copy = ApplicationForm::onlyTrashed()->whereKey($copy->id)->firstOrFail();
        $this->actingAs($this->owner, 'admin')->get(route('recruitment.forms.trash'))
            ->assertOk()->assertSee('Unused draft copy')->assertSee('Delete forever');
        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.forms.force-destroy', $copy->uuid), [
            'editor_version' => $copy->editor_version,
        ])->assertRedirect(route('recruitment.forms.trash'));
        $this->assertDatabaseMissing('application_forms', ['id' => $copy->id]);
        $this->assertDatabaseMissing('application_form_versions', ['id' => $copyVersionId]);

        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.forms.destroy', $form), [
            'editor_version' => 4,
        ])->assertRedirect(route('recruitment.forms.index'));
        $archived = ApplicationForm::onlyTrashed()->whereKey($form->id)->firstOrFail();
        $this->assertSame(5, $archived->editor_version);
        $this->actingAs($this->owner, 'admin')->get(route('recruitment.forms.trash'))
            ->assertOk()->assertSee('Protected history');
        $this->actingAs($this->owner, 'admin')->patch(route('recruitment.forms.restore', $archived->uuid), [
            'editor_version' => 5,
        ])->assertRedirect();

        $form = ApplicationForm::query()->findOrFail($form->id);
        $this->assertSame(6, $form->editor_version);
        $published->refresh();
        $this->assertSame($publishedIdentity, [$published->id, $published->uuid, $published->schema_hash, $published->updated_at?->toAtomString()]);
        foreach (['application_form.metadata_updated', 'application_form.published', 'application_form.archived', 'application_form.restored', 'application_form.permanently_deleted'] as $action) {
            $this->assertDatabaseHas('admin_audit_events', ['action' => $action]);
        }
    }

    public function test_live_opportunity_references_block_archive_and_historical_references_block_purge(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $jobForm = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Assigned job form', $this->owner);
        $jobVersion = $schemas->publish($jobForm, 1, $this->owner);
        $jobForm->refresh();
        $job = JobPosting::query()->create([
            'application_form_id' => $jobForm->id,
            'current_form_version_id' => $jobVersion->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subHour(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 1,
            'editor_version' => 1,
        ]);

        $this->actingAs($this->owner, 'admin')->from(route('recruitment.forms.index'))
            ->delete(route('recruitment.forms.destroy', $jobForm), ['editor_version' => 2])
            ->assertRedirect(route('recruitment.forms.index'))->assertSessionHasErrors('form');
        $this->assertFalse($jobForm->fresh()->trashed());

        $job->delete();
        $this->actingAs($this->owner, 'admin')->delete(route('recruitment.forms.destroy', $jobForm), [
            'editor_version' => 2,
        ])->assertRedirect(route('recruitment.forms.index'));
        $archived = ApplicationForm::onlyTrashed()->whereKey($jobForm->id)->firstOrFail();
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.forms.trash'))
            ->delete(route('recruitment.forms.force-destroy', $archived->uuid), ['editor_version' => 3])
            ->assertRedirect(route('recruitment.forms.trash'))->assertSessionHasErrors('form');
        $this->assertDatabaseHas('application_forms', ['id' => $jobForm->id]);
        $this->assertDatabaseHas('application_form_versions', ['id' => $jobVersion->id, 'state' => ApplicationFormVersion::STATE_PUBLISHED]);

        $workshopForm = $schemas->create(ApplicationForm::PURPOSE_WORKSHOP, 'Assigned workshop form', $this->owner);
        $workshopVersion = $schemas->publish($workshopForm, 1, $this->owner);
        $workshopForm->refresh();
        Workshop::query()->create([
            'application_form_id' => $workshopForm->id,
            'current_form_version_id' => $workshopVersion->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subHour(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'capacity' => 20,
            'editor_version' => 1,
        ]);
        $this->actingAs($this->owner, 'admin')->from(route('workshop.forms.index'))
            ->delete(route('workshop.forms.destroy', $workshopForm), ['editor_version' => 2])
            ->assertRedirect(route('workshop.forms.index'))->assertSessionHasErrors('form');
        $this->assertFalse($workshopForm->fresh()->trashed());
    }

    public function test_view_only_staff_can_list_and_preview_but_cannot_change_form_lifecycle(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $active = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Visible application form', $this->owner);
        $archived = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Archived application form', $this->owner);
        $schemas->archive($archived, 1, $this->owner);
        $archived = ApplicationForm::onlyTrashed()->whereKey($archived->id)->firstOrFail();
        $viewer = $this->admin($this->role('Form viewer', ['recruitment.jobs.index', 'workshops.index']), 'form-lifecycle-viewer');

        $this->actingAs($viewer, 'admin')->get(route('recruitment.forms.index'))
            ->assertOk()->assertSee('Read-only access.')->assertSee('Preview')
            ->assertDontSee('afb-duplicate', false)
            ->assertDontSee(route('recruitment.forms.edit', $active), false);
        $this->actingAs($viewer, 'admin')->get(route('recruitment.forms.trash'))
            ->assertOk()->assertSee('Archived application form')
            ->assertDontSee(route('recruitment.forms.restore', $archived->uuid), false)
            ->assertDontSee(route('recruitment.forms.force-destroy', $archived->uuid), false);
        $this->actingAs($viewer, 'admin')->get(route('recruitment.forms.preview', $active))->assertOk();

        $this->actingAs($viewer, 'admin')->get(route('recruitment.forms.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get(route('recruitment.forms.edit', $active))->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('recruitment.forms.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->actingAs($viewer, 'admin')->put(route('recruitment.forms.update', $active), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->patch(route('recruitment.forms.metadata', $active), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('recruitment.forms.publish', $active), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('recruitment.forms.duplicate', $active), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('recruitment.forms.destroy', $active), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->patch(route('recruitment.forms.restore', $archived->uuid), [])->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('recruitment.forms.force-destroy', $archived->uuid), [])->assertForbidden();
    }

    private function draft(ApplicationForm $form): ApplicationFormVersion
    {
        return $form->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->firstOrFail();
    }

    /** @param list<string> $capabilities */
    private function role(string $name, array $capabilities): Role
    {
        return Role::query()->create([
            'name' => $name,
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
            'name' => str($username)->headline()->toString(),
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
