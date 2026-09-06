<?php

namespace Tests\Feature;

use App\Mail\VolunteerRegistrationNotification;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\District;
use App\Models\Division;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use App\Models\Upazila;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VolunteerRegistrationProfileIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_public_form_exposes_complete_profile_options_and_bangladesh_location_hierarchy(): void
    {
        VolunteerCause::create(['name' => 'Community service', 'status' => true]);

        $this->assertDatabaseCount('divisions', 8);
        $this->assertDatabaseCount('districts', 64);
        $this->assertDatabaseCount('upazilas', 495);

        $this->get(route('frontend.volunteer_registration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('volunteer-registration')
                ->has('data.locations.divisions', 8)
                ->has('data.locations.districts', 64)
                ->has('data.locations.upazilas', 495)
                ->where('data.locations.divisions', fn ($items): bool => $this->locationItemsHaveShape($items))
                ->where('data.locations.districts', fn ($items): bool => $this->locationItemsHaveShape($items, true)
                    && $this->parentIdsMatch($items, District::class, 'division_id'))
                ->where('data.locations.upazilas', fn ($items): bool => $this->locationItemsHaveShape($items, true)
                    && $this->parentIdsMatch($items, Upazila::class, 'district_id'))
                ->where('data.options.sex', fn ($items): bool => $this->optionLabels($items) === [
                    'Female', 'Male', 'Prefer Not To Say',
                ])
                ->where('data.options.occupations', fn ($items): bool => $this->optionLabels($items) === [
                    'Student', 'Business', 'Service', 'Freelancer', 'Dropout', 'Housewife', 'Unemployed', 'Other',
                ])
                ->where('data.options.education_levels', fn ($items): bool => $this->optionLabels($items) === [
                    'Primary School (Grade 1-5)',
                    'High School (Grade 6-10)',
                    'Secondary School / O Levels /Dakhil Equivalent',
                    'Higher Secondary / A Level /Alim /Equivalent',
                    'Diploma /Equivalent',
                    'Bachelor /Hons /Equivalent',
                    'Masters / Post Graduation /Equivalent',
                ])
                ->where('data.options.blood_groups', fn ($items): bool => $this->optionLabels($items) === [
                    'A Positive (A+)', 'A Negative (A-)', 'B Positive (B+)', 'B Negative (B-)',
                    'AB Positive (AB+)', 'AB Negative (AB-)', 'O Positive (O+)', 'O Negative (O-)',
                ])
                ->where('data.options.emergency_response_training', fn ($items): bool => $this->optionLabels($items) === [
                    'Yes', 'No',
                ])
                ->where('data.options.skills', fn ($items): bool => $this->optionLabels($items) === [
                    'Photography', 'Painting', 'Calligraphy', 'Singing', 'Debate',
                    'Creative Writing', 'Graphic Designing', 'Video Editing', 'Acting', 'Other',
                ])
            );

        $divisionIds = Division::query()->pluck('id')->all();
        $districts = District::query()->get(['id', 'division_id']);
        $districtIds = $districts->pluck('id')->all();

        $this->assertTrue($districts->every(fn (District $district): bool => in_array($district->division_id, $divisionIds, true)));
        $this->assertTrue(Upazila::query()->get(['district_id'])
            ->every(fn (Upazila $upazila): bool => in_array($upazila->district_id, $districtIds, true)));
    }

    public function test_bangla_form_localizes_option_and_location_labels_without_changing_stable_values(): void
    {
        TranslationLocale::query()->whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        VolunteerCause::create(['name' => 'Community service', 'status' => true]);

        $this->get(route('frontend.volunteer_registration.index') . '?lang=bn')
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->component('volunteer-registration')
                ->where('data.options.sex', function ($items): bool {
                    $items = collect($items);

                    return $items->pluck('value')->all() === ['female', 'male', 'prefer_not_to_say']
                        && $items->pluck('label')->all() === ['নারী', 'পুরুষ', 'বলতে অনিচ্ছুক'];
                })
                ->where('data.options.occupations', fn ($items): bool => collect($items)->firstWhere('value', 'student')['label'] === 'শিক্ষার্থী'
                    && collect($items)->firstWhere('value', 'other')['label'] === 'অন্যান্য')
                ->where('data.options.skills', fn ($items): bool => collect($items)->firstWhere('value', 'photography')['label'] === 'ফটোগ্রাফি')
                ->where('data.locations.divisions', fn ($items): bool => $this->localizedLocation($items, 'Dhaka') === 'ঢাকা')
                ->where('data.locations.districts', fn ($items): bool => $this->localizedLocation($items, 'Dhaka') === 'ঢাকা')
                ->where('data.locations.upazilas', fn ($items): bool => $this->localizedLocation($items, 'Savar') === 'সাভার')
            );
    }

    public function test_public_registration_requires_the_new_profile_fields_and_explicit_consent(): void
    {
        $payload = $this->validPayload();

        foreach ([
            'sex', 'date_of_birth', 'division_id', 'district_id', 'upazila_id',
            'occupation', 'education_level', 'blood_group', 'skill', 'consent',
        ] as $field) {
            unset($payload[$field]);
        }

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors([
                'sex', 'date_of_birth', 'division_id', 'district_id', 'upazila_id',
                'occupation', 'education_level', 'blood_group', 'skill', 'consent',
            ]);

        $this->assertDatabaseCount('volunteers', 0);
    }

    public function test_public_registration_rejects_crossed_location_hierarchies(): void
    {
        $payload = $this->validPayload();
        [, $otherDistrict, $otherUpazila] = $this->locationChain('Barishal', 'Barguna', 'Amtali');

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), array_merge($payload, [
                'district_id' => $otherDistrict->id,
                'upazila_id' => $otherUpazila->id,
            ]))
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors('district_id');

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), array_merge($payload, [
                'upazila_id' => $otherUpazila->id,
            ]))
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors('upazila_id');

        $this->assertDatabaseCount('volunteers', 0);
    }

    public function test_public_registration_requires_details_for_other_choices_and_accepted_consent(): void
    {
        $payload = array_merge($this->validPayload(), [
            'occupation' => 'other',
            'occupation_other' => '',
            'skill' => 'other',
            'skill_other' => '',
            'consent' => false,
        ]);

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors(['occupation_other', 'skill_other', 'consent']);

        $this->assertDatabaseCount('volunteers', 0);
    }

    public function test_public_registration_rejects_unknown_profile_options_and_a_nonpast_birth_date(): void
    {
        $payload = array_merge($this->validPayload(), [
            'sex' => 'not-a-listed-option',
            'date_of_birth' => now()->toDateString(),
            'occupation' => 'not-a-listed-option',
            'education_level' => 'not-a-listed-option',
            'blood_group' => 'not-a-listed-option',
            'emergency_response_training' => 'not-a-boolean',
            'skill' => 'not-a-listed-option',
        ]);

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors([
                'sex', 'date_of_birth', 'occupation', 'education_level', 'blood_group',
                'emergency_response_training', 'skill',
            ]);

        $this->assertDatabaseCount('volunteers', 0);
    }

    public function test_valid_public_registration_persists_the_full_profile_and_consent_record(): void
    {
        $payload = array_merge($this->validPayload(), [
            'occupation' => 'other',
            'occupation_other' => 'Community mobilizer',
            'skill' => 'other',
            'skill_other' => 'First aid coordination',
            'emergency_response_training' => true,
        ]);

        $this->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $volunteer = Volunteer::query()->sole();
        $this->assertSame('female', $volunteer->sex);
        $this->assertSame('1998-05-15', $volunteer->date_of_birth?->toDateString());
        $this->assertSame($payload['division_id'], $volunteer->division_id);
        $this->assertSame($payload['district_id'], $volunteer->district_id);
        $this->assertSame($payload['upazila_id'], $volunteer->upazila_id);
        $this->assertSame('other', $volunteer->occupation);
        $this->assertSame('Community mobilizer', $volunteer->occupation_other);
        $this->assertSame('bachelor_equivalent', $volunteer->education_level);
        $this->assertSame('AB+', $volunteer->blood_group);
        $this->assertTrue((bool) $volunteer->emergency_response_training);
        $this->assertSame('other', $volunteer->skill);
        $this->assertSame('First aid coordination', $volunteer->skill_other);
        $this->assertSame('volunteer-registration-v1', $volunteer->consent_version);
        $this->assertSame('en', $volunteer->consent_locale);
        $this->assertNotEmpty($volunteer->consent_text_snapshot);
        $this->assertSame(hash('sha256', $volunteer->consent_text_snapshot), $volunteer->consent_text_hash);
        $this->assertNotNull($volunteer->consented_at);
    }

    public function test_registration_records_the_server_resolved_localized_consent_wording(): void
    {
        TranslationLocale::query()->whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        SiteSetting::create([
            'group' => 'volunteer_page',
            'key' => 'consent_label',
            'locale' => 'bn',
            'value' => 'আমি আমার আবেদন পরিচালনার জন্য তথ্য ব্যবহারে সম্মতি দিচ্ছি।',
            'type' => 'textarea',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'volunteer_page',
            'key' => 'privacy_link_label',
            'locale' => 'bn',
            'value' => 'গোপনীয়তার নিয়ম',
            'type' => 'text',
            'is_public' => true,
        ]);
        $this->get(route('frontend.volunteer_registration.index') . '?lang=bn')->assertOk();
        $payload = array_merge($this->validPayload(), [
            'email' => 'bangla-consent@example.test',
            'consent_text' => 'A visitor must not be able to forge this evidence.',
        ]);

        $this->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $volunteer = Volunteer::query()->where('email', 'bangla-consent@example.test')->sole();
        $expected = 'আমি আমার আবেদন পরিচালনার জন্য তথ্য ব্যবহারে সম্মতি দিচ্ছি। (গোপনীয়তার নিয়ম: /page/privacy-policy)';
        $this->assertSame('bn', $volunteer->consent_locale);
        $this->assertSame($expected, $volunteer->consent_text_snapshot);
        $this->assertSame(hash('sha256', $expected), $volunteer->consent_text_hash);
        $this->assertStringNotContainsString('forge', $volunteer->consent_text_snapshot);
    }

    public function test_registration_email_contains_only_a_reference_and_protected_admin_link(): void
    {
        $payload = array_merge($this->validPayload(), [
            'name' => 'Sensitive Volunteer Name',
            'email' => 'sensitive-volunteer@example.test',
            'phone' => '+8801712345678',
            'address' => 'Sensitive home address',
            'blood_group' => 'O-',
        ]);

        $this->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $volunteer = Volunteer::query()->where('email', $payload['email'])->sole();
        Mail::assertSent(VolunteerRegistrationNotification::class, function (VolunteerRegistrationNotification $mail) use ($payload, $volunteer): bool {
            $rendered = $mail->render();

            return $mail->reference() === sprintf('VOL-%06d', $volunteer->id)
                && $mail->reviewUrl() === route('volunteer.index') . '#volunteer-' . $volunteer->id
                && str_contains($rendered, 'personal and profile details are not included')
                && !str_contains($rendered, $payload['name'])
                && !str_contains($rendered, $payload['email'])
                && !str_contains($rendered, $payload['phone'])
                && !str_contains($rendered, $payload['address'])
                && !str_contains($rendered, $payload['blood_group']);
        });
        Mail::assertSentCount(1);
    }

    public function test_admin_listing_shows_human_readable_profile_and_location_values(): void
    {
        $admin = $this->richVolunteerScenario();

        $this->actingAs($admin, 'admin')
            ->get(route('volunteer.index'))
            ->assertOk()
            ->assertSeeText('Profile Rich Volunteer')
            ->assertSeeText('Prefer Not To Say')
            ->assertSeeText('20 Feb 1995')
            ->assertSeeText('Chattogram')
            ->assertSeeText('Bandarban')
            ->assertSeeText('Thanchi')
            ->assertSeeText('Community advocate')
            ->assertSeeText('Masters / Post Graduation /Equivalent')
            ->assertSeeText('O Negative (O-)')
            ->assertSeeText('Accessibility auditing')
            ->assertSeeText('Wording accepted')
            ->assertSeeText('I consent to this volunteer application review.');
    }

    public function test_volunteer_export_includes_human_readable_profile_location_and_consent_values(): void
    {
        $admin = $this->richVolunteerScenario();

        $response = $this->actingAs($admin, 'admin')->get(route('volunteer.export.excel'));
        $response->assertOk();
        $content = $response->streamedContent();
        $header = str_getcsv(strtok(ltrim($content, "\xEF\xBB\xBF"), "\n"), "\t");

        foreach ([
            'Sex', 'Date of Birth', 'Division', 'District', 'Upazila', 'Occupation',
            'Education Level', 'Blood Group', 'Emergency Response Training', 'Skill',
            'Consent Version', 'Consent Language', 'Consent Text SHA-256', 'Consent Wording', 'Consented At',
        ] as $column) {
            $this->assertContains($column, $header);
        }
        foreach ([
            'Prefer Not To Say', '20-02-1995', 'Chattogram', 'Bandarban', 'Thanchi',
            'Community advocate', 'Masters / Post Graduation /Equivalent', 'O Negative (O-)',
            'No', 'Accessibility auditing', 'volunteer-registration-v1', 'bn',
            'I consent to this volunteer application review.',
        ] as $value) {
            $this->assertStringContainsString($value, $content);
        }
    }

    /** @return array<string,mixed> */
    private function validPayload(): array
    {
        [$division, $district, $upazila] = $this->locationChain('Dhaka', 'Dhaka', 'Savar');
        $cause = VolunteerCause::query()->where('status', true)->first()
            ?? VolunteerCause::create(['name' => 'Education', 'status' => true]);

        return [
            'name' => 'Volunteer Tester',
            'institution' => 'Community College',
            'email' => 'volunteer-profile@example.test',
            'phone' => '+8801700000000',
            'address' => 'Road 1',
            'cause_id' => $cause->id,
            'sex' => 'female',
            'date_of_birth' => '1998-05-15',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'upazila_id' => $upazila->id,
            'occupation' => 'student',
            'occupation_other' => null,
            'education_level' => 'bachelor_equivalent',
            'blood_group' => 'AB+',
            'emergency_response_training' => null,
            'skill' => 'photography',
            'skill_other' => null,
            'consent' => true,
        ];
    }

    /** @return array{Division,District,Upazila} */
    private function locationChain(string $divisionName, string $districtName, string $upazilaName): array
    {
        $division = Division::query()->where('name', $divisionName)->firstOrFail();
        $district = District::query()
            ->where('division_id', $division->id)
            ->where('name', $districtName)
            ->firstOrFail();
        $upazila = Upazila::query()
            ->where('district_id', $district->id)
            ->where('name', $upazilaName)
            ->firstOrFail();

        return [$division, $district, $upazila];
    }

    private function richVolunteerScenario(): Admin
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->ownerAdmin();
        [$division, $district, $upazila] = $this->locationChain('Chattogram', 'Bandarban', 'Thanchi');
        $cause = VolunteerCause::create(['name' => 'Disaster response', 'status' => true]);

        Volunteer::create([
            'name' => 'Profile Rich Volunteer',
            'institution' => 'Community Network',
            'email' => 'profile-rich@example.test',
            'phone' => '+8801700000011',
            'address' => 'Ward 7',
            'cause_id' => $cause->id,
            'sex' => 'prefer_not_to_say',
            'date_of_birth' => '1995-02-20',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'upazila_id' => $upazila->id,
            'occupation' => 'other',
            'occupation_other' => 'Community advocate',
            'education_level' => 'masters_equivalent',
            'blood_group' => 'O-',
            'emergency_response_training' => false,
            'skill' => 'other',
            'skill_other' => 'Accessibility auditing',
            'consent_version' => 'volunteer-registration-v1',
            'consent_locale' => 'bn',
            'consent_text_hash' => hash('sha256', 'I consent to this volunteer application review.'),
            'consent_text_snapshot' => 'I consent to this volunteer application review.',
            'consented_at' => now(),
            'status' => 1,
        ]);

        return $admin;
    }

    private function optionLabels(mixed $items): array
    {
        return collect($items)->pluck('label')->all();
    }

    private function localizedLocation(mixed $items, string $canonicalName): ?string
    {
        $item = collect($items)->firstWhere('name', $canonicalName);

        return $item ? (string) ((array) $item)['label'] : null;
    }

    private function locationItemsHaveShape(mixed $items, bool $hasParent = false): bool
    {
        return collect($items)->every(function ($item) use ($hasParent): bool {
            $item = (array) $item;

            return isset($item['id'], $item['name'])
                && isset($item['label'])
                && (!$hasParent || isset($item['parent_id']));
        });
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function parentIdsMatch(mixed $items, string $model, string $parentColumn): bool
    {
        $expectedParents = $model::query()->pluck($parentColumn, 'id');

        return collect($items)->every(function ($item) use ($expectedParents): bool {
            $item = (array) $item;

            return (int) ($item['parent_id'] ?? 0) === (int) $expectedParents->get($item['id'] ?? null);
        });
    }

    private function ownerAdmin(): Admin
    {
        $role = Role::create([
            'name' => 'Volunteer profile test owner',
            'permission' => AuthMenu::query()->where('status', 1)->pluck('id')->implode(','),
            'actionPermission' => MenuAction::query()->where('status', 1)->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Volunteer profile test owner',
            'username' => 'volunteer-profile-' . Str::lower(Str::random(8)),
            'email' => 'volunteer-profile-' . Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
