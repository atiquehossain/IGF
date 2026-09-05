<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\AuthMenu;
use App\Models\JobPosting;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\TranslationLocale;
use App\Models\Workshop;
use App\Services\ApplicationFormSchemaService;
use App\Services\SiteSettingVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpportunityValidationCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.localization', true);
    }

    public function test_editor_managed_bangla_validation_copy_reaches_open_job_and_workshop_forms(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $admin = $this->websiteEditor();
        $settings = $this->defaultSettings('bn');

        $settings['career_page']['required_label'] = 'আবশ্যক তথ্য';
        $settings['career_page']['required_message'] = 'অনুগ্রহ করে {field} লিখুন।';
        $settings['career_page']['minimum_length_message'] = '{field} ঘরে অন্তত {min} অক্ষর লিখুন।';
        $settings['career_page']['error_summary_title'] = 'আবেদনের তথ্য দেখুন';
        $settings['career_page']['error_summary_introduction'] = 'চিহ্নিত ঘরগুলো ঠিক করুন।';
        $settings['career_page']['error_summary_submission_label'] = 'চাকরির আবেদন';
        $settings['career_page']['error_summary_general_label'] = 'আবেদন ফর্ম';

        $settings['workshop_page']['required_label'] = 'নিবন্ধনের জন্য আবশ্যক';
        $settings['workshop_page']['required_message'] = '{field} ছাড়া নিবন্ধন হবে না।';
        $settings['workshop_page']['maximum_selections_message'] = '{field} থেকে সর্বোচ্চ {max}টি বেছে নিন।';
        $settings['workshop_page']['error_summary_title'] = 'নিবন্ধনের তথ্য দেখুন';
        $settings['workshop_page']['error_summary_introduction'] = 'নিবন্ধনের ভুলগুলো ঠিক করুন।';
        $settings['workshop_page']['error_summary_submission_label'] = 'কর্মশালা নিবন্ধন';
        $settings['workshop_page']['error_summary_general_label'] = 'নিবন্ধন ফর্ম';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), [
                'locale' => 'bn',
                'global_settings_version' => app(SiteSettingVersionService::class)->currentForLocale('bn'),
                'settings' => $settings,
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'bn']))
            ->assertSessionHasNoErrors();

        [$jobSlug, $workshopSlug] = $this->openOpportunities($admin);

        $this->get(route('frontend.jobs.show', ['job' => $jobSlug, 'lang' => 'bn']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job')
                ->has('data.form.token')
                ->has('data.form.fields', 4)
                ->where('data.copy.required_label', 'আবশ্যক তথ্য')
                ->where('data.copy.required_message', 'অনুগ্রহ করে {field} লিখুন।')
                ->where('data.copy.minimum_length_message', '{field} ঘরে অন্তত {min} অক্ষর লিখুন।')
                ->where('data.copy.error_summary.title', 'আবেদনের তথ্য দেখুন')
                ->where('data.copy.error_summary.introduction', 'চিহ্নিত ঘরগুলো ঠিক করুন।')
                ->where('data.copy.error_summary.submission_label', 'চাকরির আবেদন')
                ->where('data.copy.error_summary.general_label', 'আবেদন ফর্ম')
            );

        $this->get(route('frontend.workshops.show', ['workshop' => $workshopSlug, 'lang' => 'bn']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workshop')
                ->has('data.form.token')
                ->has('data.form.fields', 3)
                ->where('data.copy.required_label', 'নিবন্ধনের জন্য আবশ্যক')
                ->where('data.copy.required_message', '{field} ছাড়া নিবন্ধন হবে না।')
                ->where('data.copy.maximum_selections_message', '{field} থেকে সর্বোচ্চ {max}টি বেছে নিন।')
                ->where('data.copy.error_summary.title', 'নিবন্ধনের তথ্য দেখুন')
                ->where('data.copy.error_summary.introduction', 'নিবন্ধনের ভুলগুলো ঠিক করুন।')
                ->where('data.copy.error_summary.submission_label', 'কর্মশালা নিবন্ধন')
                ->where('data.copy.error_summary.general_label', 'নিবন্ধন ফর্ম')
            );
    }

    public function test_opportunity_validation_templates_cannot_drop_required_interpolation_tokens(): void
    {
        $admin = $this->websiteEditor();
        $settings = $this->defaultSettings('bn');
        $settings['career_page']['required_message'] = 'এই ঘরটি পূরণ করুন।';
        $settings['career_page']['minimum_length_message'] = '{field} আরও বড় করুন।';
        $settings['workshop_page']['maximum_selections_message'] = 'সর্বোচ্চ {max}টি বেছে নিন।';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'bn']))
            ->put(route('site.settings.update'), [
                'locale' => 'bn',
                'global_settings_version' => app(SiteSettingVersionService::class)->currentForLocale('bn'),
                'settings' => $settings,
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'bn']))
            ->assertSessionHasErrors([
                'settings.career_page.required_message',
                'settings.career_page.minimum_length_message',
                'settings.workshop_page.maximum_selections_message',
            ]);

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'career_page',
            'key' => 'required_message',
            'locale' => 'bn',
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'group' => 'career_page',
            'key' => 'minimum_length_message',
            'locale' => 'bn',
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'group' => 'workshop_page',
            'key' => 'maximum_selections_message',
            'locale' => 'bn',
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function defaultSettings(string $locale): array
    {
        $settings = [];
        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $settings[$groupKey][$key] = ($field['localized'] ?? false)
                    ? data_get($field, "localized_defaults.{$locale}", $field['default'] ?? null)
                    : ($field['default'] ?? null);
            }
        }

        return $settings;
    }

    /** @return array{0: string, 1: string} */
    private function openOpportunities(Admin $admin): array
    {
        $forms = app(ApplicationFormSchemaService::class);

        $jobForm = $forms->create(ApplicationForm::PURPOSE_JOB, 'Public job form', $admin);
        $jobVersion = $forms->publish($jobForm, (int) $jobForm->editor_version, $admin);
        $job = JobPosting::create([
            'application_form_id' => $jobForm->id,
            'current_form_version_id' => $jobVersion->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 1,
        ]);
        $jobSlug = 'localized-validation-role';
        $job->translations()->create([
            'locale' => 'en',
            'slug' => $jobSlug,
            'title' => 'Localized validation role',
            'department' => 'Programs',
            'location' => 'Dhaka',
            'summary' => 'Test role.',
            'description' => '<p>Role description.</p>',
            'responsibilities' => '<p>Responsibilities.</p>',
            'requirements' => '<p>Requirements.</p>',
        ]);

        $workshopForm = $forms->create(ApplicationForm::PURPOSE_WORKSHOP, 'Public workshop form', $admin);
        $workshopVersion = $forms->publish($workshopForm, (int) $workshopForm->editor_version, $admin);
        $workshop = Workshop::create([
            'application_form_id' => $workshopForm->id,
            'current_form_version_id' => $workshopVersion->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
        ]);
        $workshopSlug = 'localized-validation-workshop';
        $workshop->translations()->create([
            'locale' => 'en',
            'slug' => $workshopSlug,
            'title' => 'Localized validation workshop',
            'summary' => 'Test workshop.',
            'description' => '<p>Workshop description.</p>',
            'facilitator_name' => 'IGF Facilitator',
            'venue_name' => 'Community Hall',
            'venue_address' => 'Dhaka',
            'registration_instructions' => '<p>Bring a notebook.</p>',
        ]);

        return [$jobSlug, $workshopSlug];
    }

    private function websiteEditor(): Admin
    {
        $menu = AuthMenu::where('link', 'site.settings.index')->firstOrFail();
        $action = MenuAction::where('link', 'site.settings.edit')->firstOrFail();
        $role = Role::create([
            'name' => 'Opportunity copy editor',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Opportunity Copy Editor',
            'username' => 'opportunity-copy-' . Str::lower(Str::random(8)),
            'email' => 'opportunity-copy-' . Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
