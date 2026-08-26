<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\AuthMenu;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationStatusEvent;
use App\Services\ApplicationFormSchemaService;
use App\Services\PrivateApplicationDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CypressAdminOpportunitySeeder extends Seeder
{
    public const JOB_UUID = '90000000-0000-4000-8000-000000000001';
    public const WORKSHOP_UUID = '90000000-0000-4000-8000-000000000002';

    /** @var list<string> */
    private const RECRUITMENT_CAPABILITIES = [
        'recruitment.jobs.index',
        'recruitment.applications.index',
        'recruitment.jobs.create',
        'recruitment.jobs.edit',
        'recruitment.jobs.status',
        'recruitment.jobs.destroy',
        'recruitment.jobs.templates.manage',
        'recruitment.applications.edit',
        'recruitment.applications.status',
        'recruitment.applications.export',
        'recruitment.applications.download',
        'recruitment.applications.import',
    ];

    /** @var list<string> */
    private const WORKSHOP_CAPABILITIES = [
        'workshops.index',
        'workshop.registrations.index',
        'workshops.create',
        'workshops.edit',
        'workshops.status',
        'workshops.destroy',
        'workshops.templates.manage',
        'workshop.registrations.edit',
        'workshop.registrations.status',
        'workshop.registrations.export',
        'workshop.registrations.download',
        'workshop.registrations.import',
    ];

    public function run(): void
    {
        $this->assertIsolatedCypressEnvironment();
        Cache::flush();

        $ownerUsername = (string) env('LOCAL_ADMIN_USERNAME');
        if (! Admin::query()->where('username', $ownerUsername)->exists()) {
            $this->call(LocalDevelopmentSeeder::class);
        }

        $password = (string) env('LOCAL_ADMIN_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('The isolated Cypress administrator password is missing.');
        }

        $owner = Admin::query()
            ->where('username', $ownerUsername)
            ->firstOrFail();
        $hrRole = $this->role('Cypress HR only', self::RECRUITMENT_CAPABILITIES, 200);
        $workshopRole = $this->role('Cypress Workshop only', self::WORKSHOP_CAPABILITIES, 210);
        $combinedRole = $this->role(
            'Cypress HR and Workshop',
            array_values(array_unique(array_merge(self::RECRUITMENT_CAPABILITIES, self::WORKSHOP_CAPABILITIES))),
            220,
        );

        $hr = $this->admin($hrRole, 'cypress-hr', 'Cypress HR Manager', $password, true);
        $this->admin($workshopRole, 'cypress-workshop', 'Cypress Workshop Manager', $password);
        $combined = $this->admin($combinedRole, 'cypress-combined', 'Cypress Combined Manager', $password);

        [$jobForm, $jobVersion, $motivation] = $this->publishedForm(
            ApplicationForm::PURPOSE_JOB,
            'Cypress recruitment form',
            'motivation',
            'Motivation',
            $owner,
        );
        [$workshopForm, $workshopVersion, $dietaryNeeds] = $this->publishedForm(
            ApplicationForm::PURPOSE_WORKSHOP,
            'Cypress workshop form',
            'dietary_needs',
            'Dietary needs',
            $owner,
        );
        app(ApplicationFormSchemaService::class)->create(
            ApplicationForm::PURPOSE_JOB,
            'Cypress Browser Builder',
            $owner,
        );

        $job = $this->job($jobForm, $jobVersion, $owner);
        $criterion = $job->scorecardCriteria()->create([
            'label' => 'Relevant experience',
            'description' => 'Assess evidence from the application.',
            'maximum_score' => 10,
            'position' => 1,
            'is_enabled' => true,
        ]);
        $this->jobApplication(
            $job,
            $jobVersion->id,
            $motivation->id,
            '91000000-0000-4000-8000-000000000001',
            'IGF-JOB-CYP-001',
            'Alice Candidate',
            'alice.candidate@example.test',
            'Community programme leadership',
            JobApplication::STATUS_NEW,
            null,
            1,
            true,
        );
        $this->jobApplication(
            $job,
            $jobVersion->id,
            $motivation->id,
            '91000000-0000-4000-8000-000000000002',
            'IGF-JOB-CYP-002',
            'Bob Candidate',
            'bob.candidate@example.test',
            'Field operations and logistics',
            JobApplication::STATUS_NEW,
            $hr->id,
            2,
        );
        $charlie = $this->jobApplication(
            $job,
            $jobVersion->id,
            $motivation->id,
            '91000000-0000-4000-8000-000000000003',
            'IGF-JOB-CYP-003',
            'Charlie Candidate',
            'charlie.candidate@example.test',
            'Monitoring and learning',
            JobApplication::STATUS_REJECTED,
            $combined->id,
            3,
        );
        $charlie->scores()->create([
            'job_scorecard_criterion_id' => $criterion->id,
            'reviewer_admin_id' => $combined->id,
            'score' => 6,
            'criterion_label_snapshot' => $criterion->label,
            'maximum_score_snapshot' => $criterion->maximum_score,
            'comment' => 'Seeded comparison score.',
        ]);

        $workshop = $this->workshop($workshopForm, $workshopVersion, $owner);
        $this->registration(
            $workshop,
            $workshopVersion->id,
            $dietaryNeeds->id,
            '92000000-0000-4000-8000-000000000001',
            'IGF-WS-CYP-001',
            'Pending Participant',
            'pending.participant@example.test',
            'Vegetarian',
            WorkshopRegistration::STATUS_PENDING,
            null,
            1,
        );
        $this->registration(
            $workshop,
            $workshopVersion->id,
            $dietaryNeeds->id,
            '92000000-0000-4000-8000-000000000002',
            'IGF-WS-CYP-002',
            'Waitlisted Participant',
            'waitlisted.participant@example.test',
            'No restrictions',
            WorkshopRegistration::STATUS_WAITLISTED,
            $combined->id,
            2,
        );
    }

    /** @param list<string> $capabilities */
    private function role(string $name, array $capabilities, int $rank): Role
    {
        $menuQuery = AuthMenu::query()->whereIn('link', $capabilities)->where('status', 1);
        $actionQuery = MenuAction::query()->whereIn('link', $capabilities)->where('status', 1);
        $registered = $menuQuery->clone()->pluck('link')
            ->merge($actionQuery->clone()->pluck('link'))
            ->unique();
        $missing = collect($capabilities)->diff($registered);
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing Cypress role capabilities: ' . $missing->implode(', '));
        }

        return Role::query()->create([
            'name' => $name,
            'security_rank' => $rank,
            'is_owner' => false,
            'permission' => $menuQuery->pluck('id')->implode(','),
            'actionPermission' => $actionQuery->pluck('id')->implode(','),
            'serial' => '[]',
            'order_by' => $rank,
            'status' => true,
        ]);
    }

    private function admin(
        Role $role,
        string $username,
        string $name,
        string $password,
        bool $mustChangePassword = false,
    ): Admin {
        return Admin::query()->create([
            'name' => $name,
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => true,
            'password' => Hash::make($password),
            'must_change_password' => $mustChangePassword,
            'password_changed_at' => $mustChangePassword ? null : now(),
        ]);
    }

    /** @return array{ApplicationForm,\App\Models\ApplicationFormVersion,ApplicationFormField} */
    private function publishedForm(
        string $purpose,
        string $name,
        string $fieldKey,
        string $fieldLabel,
        Admin $owner,
    ): array {
        $forms = app(ApplicationFormSchemaService::class);
        $form = $forms->create($purpose, $name, $owner);
        $draft = $form->versions()->where('state', 'draft')->firstOrFail();
        $schema = $forms->schemaArray($draft);
        $schema[] = [
            'key' => $fieldKey,
            'system_key' => null,
            'type' => ApplicationFormField::TYPE_LONG_TEXT,
            'required' => false,
            'validation' => ['max_length' => 2000],
            'translations' => [
                'en' => ['label' => $fieldLabel, 'help' => '', 'placeholder' => ''],
                'bn' => ['label' => $fieldLabel . ' (BN)', 'help' => '', 'placeholder' => ''],
            ],
            'options' => [],
            'conditions' => [],
        ];
        $forms->replaceDraft($form, (int) $form->editor_version, $schema, $owner);
        $form = $form->fresh();
        $version = $forms->publish($form, (int) $form->editor_version, $owner);

        return [$form->fresh(), $version, $version->fields()->where('field_key', $fieldKey)->firstOrFail()];
    }

    private function job(ApplicationForm $form, $version, Admin $owner): JobPosting
    {
        $job = new JobPosting();
        $job->forceFill([
            'uuid' => self::JOB_UUID,
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDays(2),
            'application_opens_at' => now()->subDay(),
            'application_closes_at' => now()->addDays(30),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => 2,
            'editor_version' => 2,
            'created_by_admin_id' => $owner->id,
            'updated_by_admin_id' => $owner->id,
            'published_by_admin_id' => $owner->id,
        ])->save();
        $job->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => 'cypress-recruitment-pipeline',
                'title' => 'Cypress Recruitment Pipeline',
                'department' => 'Programmes',
                'location' => 'Dhaka',
                'summary' => 'Deterministic recruitment review fixture.',
                'description' => '<p>Coordinate community programmes.</p>',
                'responsibilities' => '<ul><li>Lead delivery.</li></ul>',
                'requirements' => '<ul><li>Strong communication.</li></ul>',
            ],
            [
                'locale' => 'bn',
                'slug' => 'cypress-recruitment-pipeline-bn',
                'title' => 'সাইপ্রেস নিয়োগ পাইপলাইন',
                'department' => 'কর্মসূচি',
                'location' => 'ঢাকা',
                'summary' => 'নির্ধারিত নিয়োগ পরীক্ষার তথ্য।',
                'description' => '<p>কমিউনিটি কর্মসূচি সমন্বয় করুন।</p>',
                'responsibilities' => '<ul><li>বাস্তবায়নে নেতৃত্ব দিন।</li></ul>',
                'requirements' => '<ul><li>ভালো যোগাযোগ দক্ষতা।</li></ul>',
            ],
        ]);

        return $job;
    }

    private function jobApplication(
        JobPosting $job,
        int $versionId,
        int $fieldId,
        string $uuid,
        string $reference,
        string $name,
        string $email,
        string $motivation,
        string $status,
        ?int $assigneeId,
        int $daysAgo,
        bool $withDocument = false,
    ): JobApplication {
        $submittedAt = now()->subDays($daysAgo);
        $application = new JobApplication();
        $application->forceFill([
            'uuid' => $uuid,
            'reference_number' => $reference,
            'job_posting_id' => $job->id,
            'application_form_version_id' => $versionId,
            'name' => $name,
            'email' => $email,
            'phone' => '+88017000000' . $daysAgo,
            'workflow_status' => $status,
            'assigned_to_admin_id' => $assigneeId,
            'submission_count' => $daysAgo === 2 ? 2 : 1,
            'first_submitted_at' => $submittedAt->copy()->subHour(),
            'last_submitted_at' => $submittedAt,
            'source' => JobApplication::SOURCE_PUBLIC,
        ])->save();
        $application->answers()->create([
            'application_form_field_id' => $fieldId,
            'value_text' => $motivation,
        ]);
        $application->statusEvents()->create([
            'from_status' => null,
            'to_status' => $status,
            'source' => JobApplicationStatusEvent::SOURCE_SYSTEM,
            'created_at' => $submittedAt,
        ]);

        if ($withDocument) {
            $pdf = $this->pdf();
            $path = 'documents/' . str_repeat('a', 48) . '.pdf';
            Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, $pdf);
            $application->documents()->create([
                'application_form_field_id' => null,
                'document_kind' => JobApplicationDocument::KIND_CV,
                'disk' => PrivateApplicationDocumentService::DISK,
                'path' => $path,
                'original_name' => 'cypress-alice-cv.pdf',
                'mime_type' => 'application/pdf',
                'bytes' => strlen($pdf),
                'sha256' => hash('sha256', $pdf),
            ]);
        }

        return $application;
    }

    private function workshop(ApplicationForm $form, $version, Admin $owner): Workshop
    {
        $workshop = new Workshop();
        $workshop->forceFill([
            'uuid' => self::WORKSHOP_UUID,
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDays(2),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(20),
            'starts_at' => now()->addDays(25),
            'ends_at' => now()->addDays(25)->addHours(3),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_MANUAL,
            'capacity' => 25,
            'private_meeting_url' => null,
            'editor_version' => 2,
            'created_by_admin_id' => $owner->id,
            'updated_by_admin_id' => $owner->id,
            'published_by_admin_id' => $owner->id,
        ])->save();
        $workshop->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => 'cypress-free-workshop',
                'title' => 'Cypress Free Workshop',
                'summary' => 'Deterministic workshop review fixture.',
                'description' => '<p>Learn practical community leadership.</p>',
                'facilitator_name' => 'IGF Learning Team',
                'venue_name' => 'Community Hall',
                'venue_address' => 'Dhanmondi, Dhaka',
                'registration_instructions' => '<p>Registration is free.</p>',
            ],
            [
                'locale' => 'bn',
                'slug' => 'cypress-free-workshop-bn',
                'title' => 'সাইপ্রেস বিনামূল্যের কর্মশালা',
                'summary' => 'নির্ধারিত কর্মশালা পরীক্ষার তথ্য।',
                'description' => '<p>কমিউনিটি নেতৃত্ব শিখুন।</p>',
                'facilitator_name' => 'আইজিএফ লার্নিং টিম',
                'venue_name' => 'কমিউনিটি হল',
                'venue_address' => 'ধানমন্ডি, ঢাকা',
                'registration_instructions' => '<p>নিবন্ধন বিনামূল্যে।</p>',
            ],
        ]);

        return $workshop;
    }

    private function registration(
        Workshop $workshop,
        int $versionId,
        int $fieldId,
        string $uuid,
        string $reference,
        string $name,
        string $email,
        string $dietaryNeeds,
        string $status,
        ?int $assigneeId,
        int $daysAgo,
    ): WorkshopRegistration {
        $submittedAt = now()->subDays($daysAgo);
        $registration = new WorkshopRegistration();
        $registration->forceFill([
            'uuid' => $uuid,
            'reference_number' => $reference,
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $versionId,
            'name' => $name,
            'email' => $email,
            'phone' => '+88018000000' . $daysAgo,
            'workflow_status' => $status,
            'assigned_to_admin_id' => $assigneeId,
            'submission_count' => 1,
            'first_submitted_at' => $submittedAt,
            'last_submitted_at' => $submittedAt,
            'waitlisted_at' => $status === WorkshopRegistration::STATUS_WAITLISTED ? $submittedAt : null,
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ])->save();
        $registration->answers()->create([
            'application_form_field_id' => $fieldId,
            'value_text' => $dietaryNeeds,
        ]);
        $registration->statusEvents()->create([
            'from_status' => null,
            'to_status' => $status,
            'source' => WorkshopRegistrationStatusEvent::SOURCE_SYSTEM,
            'created_at' => $submittedAt,
        ]);

        return $registration;
    }

    private function pdf(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
            4 => "<< /Length 0 >>\nstream\n\nendstream",
        ];
        $pdf = "%PDF-1.7\n%IGF\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function assertIsolatedCypressEnvironment(): void
    {
        $database = realpath((string) config('database.connections.sqlite.database'));
        $expected = realpath(database_path('cypress.sqlite'));
        if (!app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || $database === false
            || $expected === false
            || strcasecmp($database, $expected) !== 0) {
            throw new RuntimeException('CypressAdminOpportunitySeeder may run only against database/cypress.sqlite in testing.');
        }
    }
}
