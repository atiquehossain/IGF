<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ApplicationFormController;
use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\Role;
use App\Services\ApplicationFormSchemaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminApplicationFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFormRoutes();
        $this->seed(DatabaseSeeder::class);
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $this->owner = Admin::query()->create([
            'name' => 'Form Builder Owner',
            'username' => 'form-builder-owner',
            'email' => 'form-builder-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    public function test_index_and_create_views_are_purpose_isolated_and_template_aware(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $job = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Recruitment intake', $this->owner);
        $template = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Recruitment starter', $this->owner, true);
        $schemas->create(ApplicationForm::PURPOSE_WORKSHOP, 'Private workshop intake', $this->owner, true);

        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.forms.index'))
            ->assertOk()
            ->assertSee('Recruitment intake')
            ->assertSee('Recruitment starter')
            ->assertDontSee('Private workshop intake')
            ->assertSee('Forms and templates')
            ->assertSee('application-form-builder/form-builder.css', false);

        $this->actingAs($this->owner, 'admin')
            ->get(route('recruitment.forms.create'))
            ->assertOk()
            ->assertSee('Recruitment starter')
            ->assertDontSee('Private workshop intake')
            ->assertSee('Save as a reusable template');

        $this->actingAs($this->owner, 'admin')
            ->get(route('workshop.forms.edit', $job))
            ->assertNotFound();

        $this->assertTrue($template->is_template);
    }

    public function test_staff_can_create_from_a_same_purpose_template_without_linking_the_copy(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $template = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Bilingual job template', $this->owner, true);
        $templateFields = $this->draft($template)->fields()->count();

        $response = $this->actingAs($this->owner, 'admin')->post(route('recruitment.forms.store'), [
            'name' => 'Programme Officer application',
            'is_template' => '0',
            'template_uuid' => $template->uuid,
        ]);

        $copy = ApplicationForm::query()->where('name', 'Programme Officer application')->firstOrFail();
        $response->assertRedirect(route('recruitment.forms.edit', $copy));
        $this->assertSame(ApplicationForm::PURPOSE_JOB, $copy->purpose);
        $this->assertFalse($copy->is_template);
        $this->assertNotSame($template->uuid, $copy->uuid);
        $this->assertSame($templateFields, $this->draft($copy)->fields()->count());

        $workshopTemplate = $schemas->create(ApplicationForm::PURPOSE_WORKSHOP, 'Workshop only', $this->owner, true);
        $this->actingAs($this->owner, 'admin')->from(route('recruitment.forms.create'))->post(route('recruitment.forms.store'), [
            'name' => 'Cross-purpose copy',
            'template_uuid' => $workshopTemplate->uuid,
        ])->assertRedirect(route('recruitment.forms.create'))
            ->assertSessionHasErrors('template_uuid');
        $this->assertDatabaseMissing('application_forms', ['name' => 'Cross-purpose copy']);
    }

    public function test_editor_renders_every_server_supported_control_and_escapes_schema_copy(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $form = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Hostile copy form', $this->owner);
        $fields = $schemas->schemaArray($this->draft($form));
        $fields[0]['translations']['en']['label'] = '</textarea><script>window.pwned=true</script>';
        $fields[] = $this->field('choice', ApplicationFormField::TYPE_DROPDOWN, [
            ['key' => 'one', 'translations' => ['en' => ['label' => 'One'], 'bn' => ['label' => 'এক']]],
            ['key' => 'two', 'translations' => ['en' => ['label' => 'Two'], 'bn' => ['label' => 'দুই']]],
        ]);
        $schemas->replaceDraft($form, 1, $fields, $this->owner);

        $response = $this->actingAs($this->owner, 'admin')->get(route('recruitment.forms.edit', $form));
        $response->assertOk()
            ->assertSee('English')
            ->assertSee('Write English and Bangla copy')
            ->assertSee('Protected fields')
            ->assertSee('application-form-builder/form-builder.js', false)
            ->assertSee('checkboxes')
            ->assertSee('yes_no')
            ->assertSee('Protected PDF upload');
        $this->assertStringNotContainsString('</textarea><script>window.pwned=true</script>', $response->getContent());
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;', $response->getContent());
        $this->assertStringNotContainsString('applicant@example.test', $response->getContent());
    }

    public function test_schema_update_is_authoritative_and_stale_editor_versions_return_a_conflict(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $form = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Typed recruitment form', $this->owner);
        $fields = $schemas->schemaArray($this->draft($form));
        $fields[] = $this->field('experience', ApplicationFormField::TYPE_NUMBER, validation: ['min' => 0, 'max' => 50]);
        $fields[] = $this->field('department', ApplicationFormField::TYPE_RADIO, [
            ['key' => 'programmes', 'translations' => ['en' => ['label' => 'Programmes'], 'bn' => ['label' => 'প্রোগ্রাম']]],
            ['key' => 'finance', 'translations' => ['en' => ['label' => 'Finance'], 'bn' => ['label' => 'অর্থ']]],
        ]);
        $fields[] = $this->field('motivation', ApplicationFormField::TYPE_LONG_TEXT, conditions: [[
            'source_key' => 'department', 'group' => 1, 'connector' => 'and', 'operator' => 'equals', 'value' => 'programmes',
        ]]);

        $this->actingAs($this->owner, 'admin')->putJson(route('recruitment.forms.update', $form), [
            'editor_version' => 1,
            'schema' => json_encode($fields, JSON_THROW_ON_ERROR),
        ])->assertOk()
            ->assertJsonPath('editor_version', 2)
            ->assertJsonPath('message', 'Draft saved.');

        $this->assertSame(['full_name', 'email', 'phone', 'cv'], $this->draft($form->fresh())
            ->fields()->whereNotNull('system_key')->orderBy('position')->pluck('system_key')->all());
        $this->assertDatabaseHas('application_form_conditions', ['operator' => 'equals']);

        $this->actingAs($this->owner, 'admin')->putJson(route('recruitment.forms.update', $form), [
            'editor_version' => 1,
            'schema' => json_encode($fields, JSON_THROW_ON_ERROR),
        ])->assertConflict()
            ->assertJsonPath('conflict', true);

        $this->actingAs($this->owner, 'admin')
            ->from(url('/css/app.css'))
            ->put(route('recruitment.forms.update', $form), [
                'editor_version' => 1,
                'schema' => json_encode($fields, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('recruitment.forms.edit', $form))
            ->assertSessionHasErrors('editor_version');

        $tampered = array_values(array_filter($fields, fn (array $field): bool => $field['system_key'] !== 'cv'));
        $this->actingAs($this->owner, 'admin')->putJson(route('recruitment.forms.update', $form), [
            'editor_version' => 2,
            'schema' => json_encode($tampered, JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('schema');
    }

    public function test_publish_preview_and_duplicate_keep_versions_and_purposes_separate(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $form = $schemas->create(ApplicationForm::PURPOSE_WORKSHOP, 'Free workshop registration', $this->owner);

        $this->actingAs($this->owner, 'admin')->postJson(route('workshop.forms.publish', $form), [
            'editor_version' => 1,
        ])->assertOk()
            ->assertJsonPath('editor_version', 2)
            ->assertJsonPath('form_version', 1);

        $preview = $this->actingAs($this->owner, 'admin')->get(route('workshop.forms.preview', [$form, 'locale' => 'bn']));
        $preview->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Preview only.')
            ->assertSee('পূর্ণ নাম')
            ->assertSee('Submit (disabled in preview)')
            ->assertDontSee('CV (PDF');

        $response = $this->actingAs($this->owner, 'admin')->post(route('workshop.forms.duplicate', $form), [
            'name' => 'Reusable free workshop form',
            'is_template' => '1',
        ]);
        $copy = ApplicationForm::query()->where('name', 'Reusable free workshop form')->firstOrFail();
        $response->assertRedirect(route('workshop.forms.edit', $copy));
        $this->assertTrue($copy->is_template);
        $this->assertSame(ApplicationForm::PURPOSE_WORKSHOP, $copy->purpose);
        $this->assertSame(ApplicationFormVersion::STATE_DRAFT, $this->draft($copy)->state);
        $this->assertDatabaseHas('application_form_versions', [
            'application_form_id' => $form->id,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
        ]);
    }

    private function registerFormRoutes(): void
    {
        if (Route::has('recruitment.forms.index')) {
            return;
        }

        Route::middleware(['web', 'auth:admin', 'permission'])
            ->prefix('__tests/admin')
            ->group(function (): void {
                foreach (['recruitment' => 'recruitment', 'workshops' => 'workshop'] as $uri => $name) {
                    Route::prefix("{$uri}/forms")
                        ->name("{$name}.forms.")
                        ->controller(ApplicationFormController::class)
                        ->group(function (): void {
                            Route::get('/', 'index')->name('index');
                            Route::get('/create', 'create')->name('create');
                            Route::post('/', 'store')->name('store');
                            Route::get('/{form}/edit', 'edit')->name('edit');
                            Route::put('/{form}', 'update')->name('update');
                            Route::post('/{form}/publish', 'publish')->name('publish');
                            Route::post('/{form}/duplicate', 'duplicate')->name('duplicate');
                            Route::get('/{form}/preview', 'preview')->name('preview');
                        });
                }
            });
        Route::getRoutes()->refreshNameLookups();
    }

    private function draft(ApplicationForm $form): ApplicationFormVersion
    {
        return $form->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->firstOrFail();
    }

    /** @param list<array<string,mixed>> $options
     *  @param array<string,mixed> $validation
     *  @param list<array<string,mixed>> $conditions
     *  @return array<string,mixed>
     */
    private function field(
        string $key,
        string $type,
        array $options = [],
        array $validation = [],
        array $conditions = []
    ): array {
        return [
            'key' => $key,
            'system_key' => null,
            'type' => $type,
            'required' => false,
            'validation' => $validation,
            'translations' => [
                'en' => ['label' => ucfirst(str_replace('_', ' ', $key)), 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => 'বাংলা ' . $key, 'help' => '', 'placeholder' => ''],
            ],
            'options' => $options,
            'conditions' => $conditions,
        ];
    }
}
