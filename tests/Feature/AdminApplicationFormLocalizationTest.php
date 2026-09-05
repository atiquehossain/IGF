<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\Role;
use App\Services\ApplicationFormSchemaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApplicationFormLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $this->owner = Admin::query()->create([
            'name' => 'Localized Form Owner',
            'username' => 'localized-form-owner',
            'email' => 'localized-form-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    public function test_bangla_admin_routes_render_localized_form_builder_operating_copy(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $jobForm = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Programme officer intake', $this->owner);
        $workshopForm = $schemas->create(ApplicationForm::PURPOSE_WORKSHOP, 'Workshop registration', $this->owner);

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->get(route('recruitment.forms.index'))
            ->assertOk()
            ->assertSee('ফর্ম ও টেমপ্লেট')
            ->assertSee('ফর্ম ফিল্টার করুন')
            ->assertSee('নিয়োগ');

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->get(route('recruitment.forms.create'))
            ->assertOk()
            ->assertSee('ফর্ম তৈরি')
            ->assertSee('খালি নিয়োগ ফর্ম')
            ->assertSee('তৈরি করে নির্মাতা খুলুন');

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->get(route('recruitment.forms.edit', $jobForm))
            ->assertOk()
            ->assertSee('নিয়োগ ফর্ম নির্মাতা')
            ->assertSee('প্রশ্ন যোগ করুন')
            ->assertSee('সংক্ষিপ্ত লেখা')
            ->assertSee('সংরক্ষণ হচ্ছে…');

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->get(route('workshop.forms.preview', [$workshopForm, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('কর্মশালা ফর্ম প্রিভিউ')
            ->assertSee('শুধু প্রিভিউ।')
            ->assertSee('নির্মাতায় ফিরুন')
            ->assertSee('Full name');
    }

    public function test_bangla_save_conflict_and_schema_validation_messages_reach_json_clients(): void
    {
        $schemas = app(ApplicationFormSchemaService::class);
        $form = $schemas->create(ApplicationForm::PURPOSE_JOB, 'Localized validation', $this->owner);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $fields = $schemas->schemaArray($draft);

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->putJson(route('recruitment.forms.update', $form), [
                'editor_version' => 1,
                'schema' => json_encode($fields, JSON_THROW_ON_ERROR),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'খসড়া সংরক্ষিত হয়েছে।');

        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->putJson(route('recruitment.forms.update', $form), [
                'editor_version' => 1,
                'schema' => json_encode($fields, JSON_THROW_ON_ERROR),
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'আপনি খোলার পর ফর্মটি বদলেছে। সংরক্ষণের আগে আবার লোড করুন।');

        $tampered = array_values(array_filter(
            $fields,
            static fn (array $field): bool => $field['system_key'] !== 'cv'
        ));
        $this->actingAs($this->owner, 'admin')->withSession(['locale' => 'bn'])
            ->putJson(route('recruitment.forms.update', $form), [
                'editor_version' => 2,
                'schema' => json_encode($tampered, JSON_THROW_ON_ERROR),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.schema.0', 'আবশ্যক পরিচয় ঘর সরানো বা নতুন করে যোগ করা যাবে না।');
    }

    public function test_application_form_catalogues_and_builder_dictionary_plumbing_stay_in_sync(): void
    {
        $english = require resource_path('lang/en/admin_ui.php');
        $bangla = require resource_path('lang/bn/admin_ui.php');

        $this->assertSame(
            $this->leafPaths($english['application_forms']),
            $this->leafPaths($bangla['application_forms'])
        );

        $controller = file_get_contents(app_path('Http/Controllers/Admin/ApplicationFormController.php'));
        $script = file_get_contents(public_path('admin-assets/application-form-builder/form-builder.js'));
        $this->assertIsString($controller);
        $this->assertIsString($script);
        $this->assertStringContainsString("AdminUi::section('application_forms.builder_ui')", $controller);
        $this->assertStringContainsString('config.ui', $script);
        $this->assertStringContainsString('translatedBuilderText', $script);
        $this->assertStringContainsString('BUILDER_UI_FALLBACKS', $script);

        foreach (glob(resource_path('views/admin/shared/form-builder/*.blade.php')) ?: [] as $view) {
            $source = file_get_contents($view);
            $this->assertIsString($source);
            $this->assertStringContainsString('application_forms.', $source, basename($view));
        }
    }

    /** @return list<string> */
    private function leafPaths(array $values, string $prefix = ''): array
    {
        $paths = [];
        foreach ($values as $key => $value) {
            $path = ltrim($prefix . '.' . $key, '.');
            if (is_array($value)) {
                array_push($paths, ...$this->leafPaths($value, $path));
            } else {
                $paths[] = $path;
            }
        }
        sort($paths);

        return $paths;
    }
}
