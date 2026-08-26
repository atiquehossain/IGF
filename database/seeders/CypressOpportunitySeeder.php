<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormCondition;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\TranslationLocale;
use App\Models\Workshop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CypressOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('testing')) {
            throw new RuntimeException('Cypress opportunity fixtures may only be seeded in the testing environment.');
        }

        DB::transaction(function (): void {
            TranslationLocale::query()->whereKey('bn')->update([
                'is_enabled' => true,
                'enabled_at' => now(),
            ]);

            [$jobForm, $jobVersion] = $this->jobForm();
            [$workshopForm, $workshopVersion] = $this->workshopForm();

            $this->job($jobForm, $jobVersion, false);
            $this->job($jobForm, $jobVersion, true);
            $this->workshop($workshopForm, $workshopVersion, false);
            $this->workshop($workshopForm, $workshopVersion, true);
        });
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function jobForm(): array
    {
        $form = ApplicationForm::query()->firstOrCreate(
            ['name' => 'Cypress public job form'],
            ['purpose' => ApplicationForm::PURPOSE_JOB, 'editor_version' => 1],
        );
        $version = $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->first();
        if ($version) {
            return [$form, $version];
        }

        $version = $form->versions()->create([
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);
        $this->field($version, 'cypress-full-name', ApplicationFormField::TYPE_SHORT_TEXT, 1, true, ApplicationFormField::SYSTEM_FULL_NAME, [
            'en' => ['Full name', '', 'Enter your full name'],
            'bn' => ['পূর্ণ নাম', '', 'আপনার পূর্ণ নাম লিখুন'],
        ]);
        $this->field($version, 'cypress-email', ApplicationFormField::TYPE_EMAIL, 2, true, ApplicationFormField::SYSTEM_EMAIL, [
            'en' => ['Email address', '', 'name@example.com'],
            'bn' => ['ইমেইল ঠিকানা', '', 'name@example.com'],
        ]);
        $this->field($version, 'cypress-phone', ApplicationFormField::TYPE_PHONE, 3, false, ApplicationFormField::SYSTEM_PHONE, [
            'en' => ['Phone number', '', 'Enter your phone number'],
            'bn' => ['ফোন নম্বর', '', 'আপনার ফোন নম্বর লিখুন'],
        ]);
        $this->field($version, 'cypress-cv', ApplicationFormField::TYPE_FILE, 4, true, ApplicationFormField::SYSTEM_CV, [
            'en' => ['CV', 'Upload one PDF file. Maximum size: 5 MB.', ''],
            'bn' => ['সিভি', 'একটি PDF ফাইল আপলোড করুন। সর্বোচ্চ আকার: ৫ এমবি।', ''],
        ], ['max_kb' => 5120, 'extensions' => ['pdf']]);
        $experience = $this->field($version, 'experience-level', ApplicationFormField::TYPE_DROPDOWN, 5, true, null, [
            'en' => ['Experience level', 'Choose the option that best describes you.', 'Select an option'],
            'bn' => ['অভিজ্ঞতার স্তর', 'আপনার জন্য সবচেয়ে উপযুক্ত বিকল্পটি বেছে নিন।', 'একটি বিকল্প বেছে নিন'],
        ]);
        $this->option($experience, 'entry', 1, 'Entry level', 'প্রাথমিক স্তর');
        $this->option($experience, 'experienced', 2, 'Experienced', 'অভিজ্ঞ');
        $leadership = $this->field($version, 'leadership-example', ApplicationFormField::TYPE_LONG_TEXT, 6, true, null, [
            'en' => ['Leadership example', 'Tell us about a relevant leadership experience.', 'Describe your example'],
            'bn' => ['নেতৃত্বের উদাহরণ', 'প্রাসঙ্গিক নেতৃত্বের অভিজ্ঞতা সম্পর্কে বলুন।', 'আপনার উদাহরণ লিখুন'],
        ], ['min_length' => 10, 'max_length' => 2000]);
        $leadership->visibilityConditions()->create([
            'source_field_id' => $experience->id,
            'condition_group' => 1,
            'boolean_connector' => ApplicationFormCondition::CONNECTOR_AND,
            'operator' => ApplicationFormCondition::OPERATOR_EQUALS,
            'comparison_value' => ['value' => 'experienced'],
            'position' => 1,
        ]);

        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', 'cypress-public-job-form-v1'),
            'published_at' => now(),
        ]);

        return [$form, $version->fresh()];
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function workshopForm(): array
    {
        $form = ApplicationForm::query()->firstOrCreate(
            ['name' => 'Cypress public workshop form'],
            ['purpose' => ApplicationForm::PURPOSE_WORKSHOP, 'editor_version' => 1],
        );
        $version = $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->first();
        if ($version) {
            return [$form, $version];
        }

        $version = $form->versions()->create([
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);
        $this->field($version, 'cypress-workshop-full-name', ApplicationFormField::TYPE_SHORT_TEXT, 1, true, ApplicationFormField::SYSTEM_FULL_NAME, [
            'en' => ['Full name', '', 'Enter your full name'],
            'bn' => ['পূর্ণ নাম', '', 'আপনার পূর্ণ নাম লিখুন'],
        ]);
        $this->field($version, 'cypress-workshop-email', ApplicationFormField::TYPE_EMAIL, 2, true, ApplicationFormField::SYSTEM_EMAIL, [
            'en' => ['Email address', '', 'name@example.com'],
            'bn' => ['ইমেইল ঠিকানা', '', 'name@example.com'],
        ]);
        $this->field($version, 'cypress-workshop-phone', ApplicationFormField::TYPE_PHONE, 3, false, ApplicationFormField::SYSTEM_PHONE, [
            'en' => ['Phone number', '', 'Enter your phone number'],
            'bn' => ['ফোন নম্বর', '', 'আপনার ফোন নম্বর লিখুন'],
        ]);
        $this->field($version, 'workshop-interest', ApplicationFormField::TYPE_LONG_TEXT, 4, true, null, [
            'en' => ['Why do you want to join?', '', 'Tell us what you hope to learn'],
            'bn' => ['আপনি কেন যোগ দিতে চান?', '', 'আপনি কী শিখতে চান তা লিখুন'],
        ], ['min_length' => 10, 'max_length' => 2000]);

        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', 'cypress-public-workshop-form-v1'),
            'published_at' => now(),
        ]);

        return [$form, $version->fresh()];
    }

    /**
     * @param array{en: array{0: string, 1: string, 2: string}, bn: array{0: string, 1: string, 2: string}} $translations
     * @param array<string, mixed>|null $validation
     */
    private function field(
        ApplicationFormVersion $version,
        string $key,
        string $type,
        int $position,
        bool $required,
        ?string $systemKey,
        array $translations,
        ?array $validation = null,
    ): ApplicationFormField {
        $field = $version->fields()->create([
            'field_key' => $key,
            'system_key' => $systemKey,
            'type' => $type,
            'position' => $position,
            'is_required' => $required,
            'validation' => $validation,
        ]);

        foreach ($translations as $locale => [$label, $help, $placeholder]) {
            $field->translations()->create([
                'locale' => $locale,
                'label' => $label,
                'help_text' => $help ?: null,
                'placeholder' => $placeholder ?: null,
            ]);
        }

        return $field;
    }

    private function option(ApplicationFormField $field, string $key, int $position, string $english, string $bangla): void
    {
        $option = $field->options()->create([
            'option_key' => $key,
            'position' => $position,
        ]);
        $option->translations()->create(['locale' => 'en', 'label' => $english]);
        $option->translations()->create(['locale' => 'bn', 'label' => $bangla]);
    }

    private function job(ApplicationForm $form, ApplicationFormVersion $version, bool $closed): void
    {
        $englishSlug = $closed ? 'closed-role' : 'program-officer';
        $posting = JobPosting::query()
            ->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $englishSlug))
            ->first();
        $attributes = [
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDays(2),
            'application_opens_at' => now()->subDay(),
            'application_closes_at' => $closed ? now()->subHour() : now()->addDays(30),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => $closed ? 1 : 2,
        ];
        if ($posting) {
            $posting->update($attributes);
        } else {
            $posting = JobPosting::query()->create($attributes);
        }

        $posting->translations()->updateOrCreate(['locale' => 'en'], [
            'slug' => $englishSlug,
            'title' => $closed ? 'Closed Role' : 'Program Officer',
            'department' => 'Programs',
            'location' => 'Dhaka',
            'summary' => $closed ? 'A preserved historical career opportunity.' : 'Support community programs across Bangladesh.',
            'description' => '<p>Build practical programs with local communities.</p>',
            'responsibilities' => '<ul><li>Coordinate partners and program delivery.</li></ul>',
            'requirements' => '<ul><li>Strong communication and organization skills.</li></ul>',
        ]);
        if (!$closed) {
            $posting->translations()->updateOrCreate(['locale' => 'bn'], [
                'slug' => 'program-officer-bn',
                'title' => 'কর্মসূচি কর্মকর্তা',
                'department' => 'কর্মসূচি',
                'location' => 'ঢাকা',
                'summary' => 'বাংলাদেশজুড়ে কমিউনিটি কর্মসূচিতে সহায়তা করুন।',
                'description' => '<p>স্থানীয় কমিউনিটির সঙ্গে বাস্তবমুখী কর্মসূচি তৈরি করুন।</p>',
                'responsibilities' => '<ul><li>অংশীদার ও কর্মসূচি বাস্তবায়ন সমন্বয় করুন।</li></ul>',
                'requirements' => '<ul><li>ভালো যোগাযোগ ও সাংগঠনিক দক্ষতা।</li></ul>',
            ]);
        }
    }

    private function workshop(ApplicationForm $form, ApplicationFormVersion $version, bool $closed): void
    {
        $englishSlug = $closed ? 'closed-workshop' : 'free-leadership';
        $workshop = Workshop::query()
            ->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $englishSlug))
            ->first();
        $attributes = [
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDays(2),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => $closed ? now()->subHour() : now()->addDays(30),
            'starts_at' => $closed ? now()->subDays(3) : now()->addDays(10),
            'ends_at' => $closed ? now()->subDays(3)->addHours(2) : now()->addDays(10)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'capacity' => null,
            'private_meeting_url' => 'https://private.example.test/cypress-secret-meeting',
        ];
        if ($workshop) {
            $workshop->update($attributes);
        } else {
            $workshop = Workshop::query()->create($attributes);
        }

        $workshop->translations()->updateOrCreate(['locale' => 'en'], [
            'slug' => $englishSlug,
            'title' => $closed ? 'Closed Workshop' : 'Community Leadership Workshop',
            'summary' => $closed ? 'A preserved historical workshop.' : 'A free, practical leadership workshop.',
            'description' => ($closed ? '' : '<figure><img src="/storage/media/cypress/workshop-poster.jpg" alt="Community Leadership Workshop poster"></figure>') .
                '<p>Learn practical tools for leading community initiatives.</p>',
            'facilitator_name' => 'IGF Learning Team',
            'venue_name' => 'Community Hall',
            'venue_address' => 'Dhanmondi, Dhaka',
            'registration_instructions' => '<p>Registration is completely free. Bring a notebook.</p>',
        ]);
        if (!$closed) {
            $workshop->translations()->updateOrCreate(['locale' => 'bn'], [
                'slug' => 'free-leadership-bn',
                'title' => 'কমিউনিটি নেতৃত্ব কর্মশালা',
                'summary' => 'একটি বিনামূল্যের ব্যবহারিক নেতৃত্ব কর্মশালা।',
                'description' => '<figure><img src="/storage/media/cypress/workshop-poster.jpg" alt="কমিউনিটি নেতৃত্ব কর্মশালার পোস্টার"></figure>' .
                    '<p>কমিউনিটি উদ্যোগ পরিচালনার ব্যবহারিক কৌশল শিখুন।</p>',
                'facilitator_name' => 'আইজিএফ লার্নিং টিম',
                'venue_name' => 'কমিউনিটি হল',
                'venue_address' => 'ধানমন্ডি, ঢাকা',
                'registration_instructions' => '<p>নিবন্ধন সম্পূর্ণ বিনামূল্যে। একটি নোটবুক আনুন।</p>',
            ]);
        }
    }
}
