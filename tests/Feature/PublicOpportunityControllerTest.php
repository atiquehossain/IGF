<?php

namespace Tests\Feature;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\TranslationLocale;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\PrivateApplicationDocumentService;
use App\Services\PublicFormTokenService;
use App\Services\WorkshopRegistrationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Support\ValidPdfFixture;

class PublicOpportunityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        Storage::fake(PrivateApplicationDocumentService::DISK);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_route_contract_is_anonymous_and_only_submission_routes_are_opportunity_throttled(): void
    {
        $contracts = [
            'frontend.jobs.index' => [['GET', 'HEAD'], 'careers', false],
            'frontend.jobs.show' => [['GET', 'HEAD'], 'careers/{job}', false],
            'frontend.jobs.apply' => [['POST'], 'careers/{job}/apply', true],
            'frontend.workshops.index' => [['GET', 'HEAD'], 'workshops', false],
            'frontend.workshops.show' => [['GET', 'HEAD'], 'workshops/{workshop}', false],
            'frontend.workshops.register' => [['POST'], 'workshops/{workshop}/register', true],
        ];

        foreach ($contracts as $name => [$methods, $uri, $throttled]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "{$name} must be registered.");
            $this->assertSame($methods, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertNotContains('auth', $route->gatherMiddleware());
            $this->assertSame(
                $throttled,
                in_array('throttle:public-opportunity-submission', $route->gatherMiddleware(), true),
            );
        }

        $this->assertNull(Route::getRoutes()->getByName('frontend.applicant.dashboard'));
        $this->assertNull(Route::getRoutes()->getByName('frontend.jobs.dashboard'));
        $this->assertNull(Route::getRoutes()->getByName('frontend.workshops.dashboard'));
    }

    public function test_job_list_uses_strict_server_time_and_closed_detail_preserves_sanitized_content(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $active = $this->job($form, $version, 'program-officer', [
            'description' => '<p>Safe description.</p><script>window.steal()</script>',
            'responsibilities' => '<ul><li>Coordinate partners.</li></ul>',
            'requirements' => '<p>Three years of experience.</p>',
        ]);
        $closed = $this->job($form, $version, 'closed-role', [], [
            'application_closes_at' => now(),
        ]);
        $upcoming = $this->job($form, $version, 'upcoming-role', [], [
            'application_opens_at' => now()->addSecond(),
            'application_closes_at' => now()->addDays(2),
        ]);
        $draft = $this->job($form, $version, 'draft-role', [], [
            'publication_status' => JobPosting::PUBLICATION_DRAFT,
        ]);
        $future = $this->job($form, $version, 'future-role', [], [
            'visible_from_at' => now()->addSecond(),
        ]);

        $this->get(route('frontend.jobs.index'))
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertInertia(fn (Assert $page) => $page
                ->component('careers')
                ->has('data.items', 2)
                ->where('data.items.0.uuid', $active->uuid)
                ->where('data.items.0.slug', 'program-officer')
                ->where('data.items.0.application_state', 'open')
                ->where('data.items.1.uuid', $upcoming->uuid)
                ->where('data.items.1.application_state', 'upcoming')
                ->where('data.items.1.is_open', false)
                ->where('properties.total_count', 2)
            );

        $detail = $this->get(route('frontend.jobs.show', 'program-officer'));
        $detail->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('job')
            ->where('data.listing.description', '<p>Safe description.</p>')
            ->where('data.listing.responsibilities', '<ul><li>Coordinate partners.</li></ul>')
            ->where('data.listing.requirements', '<p>Three years of experience.</p>')
            ->where('data.listing.work_arrangement_label', 'On site')
            ->has('data.listing.application_opens_label')
            ->where('data.listing.is_open', true)
            ->has('data.form.token')
            ->where('data.form.honeypot_name', 'company_website')
            ->where('data.form.fields.3.key', 'cv')
            ->where('data.form.fields.3.required', true)
            ->where('data.form.fields.3.validation.max_kb', 5120)
            ->where('data.form.fields.3.validation.extensions.0', 'pdf')
        );
        $this->assertStringNotContainsString('window.steal', $detail->getContent());

        $this->get(route('frontend.jobs.show', 'closed-role'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job')
                ->where('data.listing.uuid', $closed->uuid)
                ->where('data.listing.application_state', 'closed')
                ->where('data.listing.is_open', false)
                ->where('data.form', null)
            );

        $this->get(route('frontend.jobs.show', 'upcoming-role'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.listing.application_state', 'upcoming')
                ->where('data.listing.is_open', false)
                ->where('data.form', null)
            );

        $this->get(route('frontend.jobs.show', 'draft-role'))->assertNotFound();
        $this->get(route('frontend.jobs.show', 'future-role'))->assertNotFound();
        $this->assertNotSame($draft->id, $future->id);
    }

    public function test_enabled_bangla_content_uses_bangla_then_falls_back_to_english(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        [$jobForm, $jobVersion] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($jobForm, $jobVersion, 'english-role');
        $job->translations()->create([
            'locale' => 'bn',
            'slug' => 'bangla-role',
            'title' => 'বাংলা কর্মসূচি কর্মকর্তা',
            'summary' => 'বাংলা সারসংক্ষেপ',
            'description' => '<p>বাংলা বিবরণ</p>',
            'responsibilities' => '<p>বাংলা দায়িত্ব</p>',
            'requirements' => '<p>বাংলা যোগ্যতা</p>',
        ]);

        $this->get(route('frontend.jobs.show', ['job' => 'bangla-role', 'lang' => 'bn']))
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->component('job')
                ->where('title', 'বাংলা কর্মসূচি কর্মকর্তা')
                ->where('data.listing.title', 'বাংলা কর্মসূচি কর্মকর্তা')
                ->where('data.copy.responsibilities_title', 'দায়িত্বসমূহ')
                ->where('data.copy.required_message', '{field} আবশ্যক।')
                ->where('data.copy.invalid_email_message', 'একটি বৈধ ইমেইল ঠিকানা লিখুন।')
                ->where('data.form.fields.0.label', 'Full name')
            );

        [$workshopForm, $workshopVersion] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $this->workshop($workshopForm, $workshopVersion, 'english-workshop');

        $this->get(route('frontend.workshops.show', ['workshop' => 'english-workshop', 'lang' => 'bn']))
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->component('workshop')
                ->where('data.listing.title', 'English Workshop')
                ->where('data.copy.title', 'বিনামূল্যের কর্মশালা')
            );
    }

    public function test_workshop_detail_exposes_free_public_content_but_never_private_meeting_or_applicant_data(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = $this->workshop($form, $version, 'community-leadership', [
            'description' => '<p>A free practical session.</p><img src="javascript:alert(1)">' .
                '<figure><img src="/storage/media/2026/08/community-workshop.jpg" alt="Community workshop poster"></figure>' .
                '<script>leak()</script>',
            'venue_address' => 'Community Hall, Dhaka',
            'registration_instructions' => '<p>Bring a notebook.</p><img src="javascript:alert(1)">',
        ], [
            'private_meeting_url' => 'https://private.example.test/meeting/very-secret',
        ]);
        $closed = $this->workshop($form, $version, 'closed-workshop', [], [
            'registration_closes_at' => now(),
        ]);

        app(WorkshopRegistrationService::class)->submit($workshop, [
            'applicant_name' => 'Private Applicant Name',
            'email' => 'private-applicant-8741@example.test',
            'phone' => '+880 1712 345678',
            'responses' => ['motivation' => 'Private answer 9283'],
        ]);

        $response = $this->get(route('frontend.workshops.show', 'community-leadership'));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('workshop')
            ->where('data.listing.uuid', $workshop->uuid)
            ->where(
                'data.listing.description',
                '<p>A free practical session.</p><img><figure><img src="/storage/media/2026/08/community-workshop.jpg" alt="Community workshop poster"></figure>'
            )
            ->where('data.listing.venue_address', 'Community Hall, Dhaka')
            ->where('data.listing.registration_instructions', '<p>Bring a notebook.</p><img>')
            ->has('data.listing.registration_opens_label')
            ->where('data.listing.is_open', true)
            ->has('data.form.token')
            ->missing('data.listing.private_meeting_url')
            ->missing('data.listing.capacity')
            ->missing('data.listing.registration_mode')
            ->missing('data.applicants')
            ->missing('data.registrations')
            ->missing('data.payment')
        );
        $body = $response->getContent();
        $this->assertStringNotContainsString('https://private.example.test/meeting/very-secret', $body);
        $this->assertStringNotContainsString('private-applicant-8741@example.test', $body);
        $this->assertStringNotContainsString('Private Applicant Name', $body);
        $this->assertStringNotContainsString('Private answer 9283', $body);
        $this->assertStringNotContainsString('javascript:', $body);

        $this->get(route('frontend.workshops.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.items', 1)
                ->where('data.items.0.uuid', $workshop->uuid)
                ->where('data.items.0.image_url', '/storage/media/2026/08/community-workshop.jpg')
                ->where('data.items.0.image_alt', 'Community workshop poster')
                ->where('properties.total_count', 1)
            );
        $this->get(route('frontend.workshops.show', 'closed-workshop'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.listing.uuid', $closed->uuid)
                ->where('data.listing.registration_state', 'closed')
                ->where('data.listing.is_open', false)
                ->where('data.form', null)
            );
        Mail::assertNothingSent();
    }

    public function test_job_submission_uses_public_token_latest_submission_wins_and_receipt_is_one_screen_only(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($form, $version, 'field-coordinator');
        $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));

        $first = $this->post(route('frontend.jobs.apply', 'field-coordinator'), [
            'form_token' => $token,
            'company_website' => '',
            'applicant_name' => 'First Candidate',
            'email' => 'candidate@example.test',
            'phone' => '+880 1712 345678',
            'cv' => $this->pdf('first-cv.pdf'),
            'responses' => ['motivation' => 'First application answer.'],
        ]);
        $first->assertRedirect(route('frontend.jobs.show', 'field-coordinator'));

        $this->get(route('frontend.jobs.show', 'field-coordinator'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.submission_reference')
                ->where('data.submission_status_label', 'Received')
                ->where('data.submission_updated', false)
            );
        $this->get(route('frontend.jobs.show', 'field-coordinator'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('data.submission_reference'));

        $secondToken = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));
        $second = $this->post(route('frontend.jobs.apply', 'field-coordinator'), [
            'form_token' => $secondToken,
            'company_website' => '',
            'applicant_name' => 'Updated Candidate',
            'email' => ' CANDIDATE@example.test ',
            'phone' => '+880 1812 345678',
            'cv' => $this->pdf('updated-cv.pdf'),
            'responses' => ['motivation' => 'Latest application answer.'],
        ]);
        $second->assertRedirect(route('frontend.jobs.show', 'field-coordinator'));

        $receipt = $this->get(route('frontend.jobs.show', 'field-coordinator'));
        $receipt->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('data.submission_reference')
            ->where('data.submission_status_label', 'Received')
            ->where('data.submission_updated', true)
        );
        $application = JobApplication::query()->with(['answers', 'documents'])->sole();
        $this->assertSame('Updated Candidate', $application->name);
        $this->assertSame('candidate@example.test', $application->email);
        $this->assertSame(2, $application->submission_count);
        $this->assertSame('Latest application answer.', $application->answers->sole()->value_text);
        $this->assertSame('updated-cv.pdf', $application->documents->sole()->original_name);
        $this->assertDatabaseCount('job_applications', 1);
        $this->assertStringNotContainsString('candidate@example.test', strtolower($receipt->getContent()));
        Mail::assertNothingSent();
    }

    public function test_workshop_submission_registers_without_cv_or_mail_and_reports_confirmation(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = $this->workshop($form, $version, 'digital-safety');
        $token = app(PublicFormTokenService::class)->issue('workshop', (string) $workshop->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));

        $response = $this->post(route('frontend.workshops.register', 'digital-safety'), [
            'form_token' => $token,
            'company_website' => '',
            'applicant_name' => 'Workshop Participant',
            'email' => 'participant@example.test',
            'responses' => ['motivation' => 'I want to learn.'],
        ]);
        $response->assertRedirect(route('frontend.workshops.show', 'digital-safety'));

        $this->get(route('frontend.workshops.show', 'digital-safety'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.submission_reference')
                ->where('data.submission_status_label', 'Registration confirmed')
                ->where('data.submission_updated', false)
            );
        $registration = WorkshopRegistration::query()->with('answers')->sole();
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $registration->workflow_status);
        $this->assertSame('I want to learn.', $registration->answers->sole()->value_text);
        $this->assertDatabaseCount('workshop_registration_documents', 0);
        Mail::assertNothingSent();
    }

    public function test_honeypot_rejects_before_any_application_is_saved(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($form, $version, 'protected-role');
        $this->get(route('frontend.jobs.show', ['job' => 'protected-role', 'lang' => 'bn']))
            ->assertOk()
            ->assertHeader('Content-Language', 'bn');
        $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));

        $this->from(route('frontend.jobs.show', 'protected-role'))
            ->post(route('frontend.jobs.apply', 'protected-role'), [
                'form_token' => $token,
                'company_website' => 'https://bot.example.test',
                'applicant_name' => 'Bot Applicant',
                'email' => 'bot@example.test',
                'cv' => $this->pdf('bot.pdf'),
                'responses' => ['motivation' => 'Spam'],
            ])
            ->assertRedirect(route('frontend.jobs.show', 'protected-role'))
            ->assertSessionHasErrors([
                'submission' => 'এই ফর্ম সেশনটি আর বৈধ নয়। পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।',
            ]);

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
        Mail::assertNothingSent();
    }

    public function test_post_rechecks_the_exact_server_deadline_even_with_a_valid_earlier_token(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($form, $version, 'closing-role', [], [
            'application_closes_at' => now()->addSeconds(2),
        ]);
        $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));

        $this->from(route('frontend.jobs.show', 'closing-role'))
            ->post(route('frontend.jobs.apply', 'closing-role'), [
                'form_token' => $token,
                'company_website' => '',
                'applicant_name' => 'Late Applicant',
                'email' => 'late@example.test',
                'cv' => $this->pdf('late.pdf'),
                'responses' => ['motivation' => 'Too late.'],
            ])
            ->assertRedirect(route('frontend.jobs.show', 'closing-role'))
            ->assertSessionHasErrors('submission');

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
        Mail::assertNothingSent();
    }

    public function test_submission_rate_limit_is_enforced_per_listing_and_email(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($form, $version, 'rate-limited-role');

        foreach (range(1, 3) as $attempt) {
            $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
            CarbonImmutable::setTestNow(now()->addSeconds(2));
            $this->post(route('frontend.jobs.apply', 'rate-limited-role'), [
                'form_token' => $token,
                'company_website' => '',
                'applicant_name' => "Candidate attempt {$attempt}",
                'email' => 'rate-limit@example.test',
                'cv' => $this->pdf("attempt-{$attempt}.pdf"),
                'responses' => ['motivation' => "Attempt {$attempt}"],
            ])->assertRedirect(route('frontend.jobs.show', 'rate-limited-role'));
        }

        $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));
        $this->post(route('frontend.jobs.apply', 'rate-limited-role'), [
            'form_token' => $token,
            'company_website' => '',
            'applicant_name' => 'Fourth attempt',
            'email' => 'rate-limit@example.test',
            'cv' => $this->pdf('attempt-4.pdf'),
            'responses' => ['motivation' => 'Attempt 4'],
        ])->assertTooManyRequests();

        $application = JobApplication::query()->sole();
        $this->assertSame(3, $application->submission_count);
        $this->assertSame('Candidate attempt 3', $application->name);
        Mail::assertNothingSent();
    }

    public function test_open_listings_keep_their_pinned_immutable_forms_after_new_versions_are_published(): void
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB, true);
        $job = $this->job($form, $version, 'version-pinned-role');

        $version->update(['state' => ApplicationFormVersion::STATE_RETIRED]);
        ApplicationFormVersion::query()->create([
            'application_form_id' => $form->id,
            'version' => 2,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', 'new-unpinned-version'),
            'published_at' => now(),
        ]);

        $this->get(route('frontend.jobs.show', 'version-pinned-role'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.listing.uuid', $job->uuid)
                ->where('data.listing.is_open', true)
                ->has('data.form.fields', $version->fields()->count())
            );

        $token = app(PublicFormTokenService::class)->issue('job', (string) $job->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));
        $this->post(route('frontend.jobs.apply', 'version-pinned-role'), [
            'form_token' => $token,
            'company_website' => '',
            'applicant_name' => 'Pinned Version Applicant',
            'email' => 'pinned-version@example.test',
            'cv' => $this->pdf('pinned-version.pdf'),
            'responses' => ['motivation' => 'The original form remains coherent.'],
        ])->assertRedirect(route('frontend.jobs.show', 'version-pinned-role'));

        $this->assertSame($version->id, JobApplication::query()->sole()->application_form_version_id);

        [$workshopForm, $workshopVersion] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = $this->workshop($workshopForm, $workshopVersion, 'version-pinned-workshop');
        $workshopVersion->update(['state' => ApplicationFormVersion::STATE_RETIRED]);
        ApplicationFormVersion::query()->create([
            'application_form_id' => $workshopForm->id,
            'version' => 2,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', 'new-unpinned-workshop-version'),
            'published_at' => now(),
        ]);

        $this->get(route('frontend.workshops.show', 'version-pinned-workshop'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.listing.uuid', $workshop->uuid)
                ->where('data.listing.is_open', true)
                ->has('data.form.fields', $workshopVersion->fields()->count())
            );

        $workshopToken = app(PublicFormTokenService::class)->issue('workshop', (string) $workshop->uuid);
        CarbonImmutable::setTestNow(now()->addSeconds(2));
        $this->post(route('frontend.workshops.register', 'version-pinned-workshop'), [
            'form_token' => $workshopToken,
            'company_website' => '',
            'applicant_name' => 'Pinned Workshop Registrant',
            'email' => 'pinned-workshop@example.test',
            'responses' => ['motivation' => 'The original registration form remains coherent.'],
        ])->assertRedirect(route('frontend.workshops.show', 'version-pinned-workshop'));

        $this->assertSame(
            $workshopVersion->id,
            WorkshopRegistration::query()->sole()->application_form_version_id,
        );
        Mail::assertNothingSent();
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function publishedForm(string $purpose, bool $withCv = false): array
    {
        $form = ApplicationForm::create([
            'purpose' => $purpose,
            'name' => ucfirst($purpose) . ' public form',
        ]);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);

        $position = 1;
        $this->field($version, 'full-name', ApplicationFormField::TYPE_SHORT_TEXT, $position++, true, ApplicationFormField::SYSTEM_FULL_NAME);
        $this->field($version, 'email-address', ApplicationFormField::TYPE_EMAIL, $position++, true, ApplicationFormField::SYSTEM_EMAIL);
        $this->field($version, 'phone-number', ApplicationFormField::TYPE_PHONE, $position++, false, ApplicationFormField::SYSTEM_PHONE);
        if ($withCv) {
            $this->field($version, 'cv-file', ApplicationFormField::TYPE_FILE, $position++, true, ApplicationFormField::SYSTEM_CV);
        }
        $this->field($version, 'motivation', ApplicationFormField::TYPE_LONG_TEXT, $position, true);

        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose . '-public-controller-test'),
            'published_at' => now(),
        ]);

        return [$form, $version->fresh()];
    }

    private function field(
        ApplicationFormVersion $version,
        string $key,
        string $type,
        int $position,
        bool $required,
        ?string $systemKey = null,
    ): void {
        $field = $version->fields()->create([
            'field_key' => $key,
            'system_key' => $systemKey,
            'type' => $type,
            'position' => $position,
            'is_required' => $required,
            'validation' => $type === ApplicationFormField::TYPE_FILE
                ? ['max_kb' => 5120, 'extensions' => ['pdf']]
                : null,
        ]);
        $field->translations()->create([
            'locale' => 'en',
            'label' => match ($systemKey) {
                ApplicationFormField::SYSTEM_FULL_NAME => 'Full name',
                ApplicationFormField::SYSTEM_EMAIL => 'Email address',
                ApplicationFormField::SYSTEM_PHONE => 'Phone number',
                ApplicationFormField::SYSTEM_CV => 'CV',
                default => 'Why are you interested?',
            },
        ]);
    }

    /** @param array<string, mixed> $translationOverrides
     *  @param array<string, mixed> $listingOverrides
     */
    private function job(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        string $slug,
        array $translationOverrides = [],
        array $listingOverrides = [],
    ): JobPosting {
        $posting = JobPosting::create(array_merge([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_ON_SITE,
            'vacancy_count' => 2,
        ], $listingOverrides));
        $posting->translations()->create(array_merge([
            'locale' => 'en',
            'slug' => $slug,
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'department' => 'Programs',
            'location' => 'Dhaka',
            'summary' => 'A public career opportunity.',
            'description' => '<p>Job description.</p>',
            'responsibilities' => '<p>Job responsibilities.</p>',
            'requirements' => '<p>Job requirements.</p>',
        ], $translationOverrides));

        return $posting->fresh('translations');
    }

    /** @param array<string, mixed> $translationOverrides
     *  @param array<string, mixed> $listingOverrides
     */
    private function workshop(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        string $slug,
        array $translationOverrides = [],
        array $listingOverrides = [],
    ): Workshop {
        $workshop = Workshop::create(array_merge([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'capacity' => null,
        ], $listingOverrides));
        $workshop->translations()->create(array_merge([
            'locale' => 'en',
            'slug' => $slug,
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'summary' => 'A free public workshop.',
            'description' => '<p>Workshop description.</p>',
            'facilitator_name' => 'IGF Facilitator',
            'venue_name' => 'Community Hall',
            'venue_address' => 'Dhaka',
            'registration_instructions' => '<p>Registration is free.</p>',
        ], $translationOverrides));

        return $workshop->fresh('translations');
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            ValidPdfFixture::bytes(),
        );
    }
}
