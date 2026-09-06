<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\JobPostingTranslation;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\TranslationLocale;
use App\Models\Workshop;
use App\Models\WorkshopTranslation;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpportunityClientHandoffFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_ROBOTS = 'noindex,nofollow,noarchive';

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminPermissionRegistrySeeder::class);
        config([
            'app.env' => 'production',
            'seo.robots.indexing_enabled' => true,
        ]);
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $role = Role::query()->create([
            'name' => 'Opportunity handoff owner ' . Str::random(8),
            'security_rank' => 1,
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'order_by' => 1,
            'status' => 1,
        ]);
        $this->owner = Admin::query()->create([
            'name' => 'Opportunity handoff owner',
            'username' => 'opportunity-handoff-' . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Admin-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    public function test_saved_job_and_workshop_previews_require_an_authenticated_admin(): void
    {
        $job = $this->job(JobPosting::PUBLICATION_DRAFT);
        $workshop = $this->workshop(Workshop::PUBLICATION_DRAFT);

        $this->get(route('recruitment.jobs.preview', ['job' => $job, 'locale' => 'en']))
            ->assertRedirect(route('admin.login'));
        $this->get(route('workshops.preview', ['workshop' => $workshop, 'locale' => 'bn']))
            ->assertRedirect(route('admin.login'));
    }

    public function test_job_draft_preview_uses_each_saved_locale_without_exposing_a_submission_form(): void
    {
        $job = $this->job(JobPosting::PUBLICATION_DRAFT);

        foreach ([
            'en' => [
                'title' => 'Saved English Job Draft',
                'slug' => 'saved-english-job-draft',
                'description' => '<p>Saved English job draft details.</p>',
            ],
            'bn' => [
                'title' => 'সংরক্ষিত বাংলা চাকরির খসড়া',
                'slug' => 'saved-bangla-job-draft',
                'description' => '<p>সংরক্ষিত বাংলা চাকরির খসড়ার বিস্তারিত।</p>',
            ],
        ] as $locale => $expected) {
            $response = $this->actingAs($this->owner, 'admin')->get(route(
                'recruitment.jobs.preview',
                ['job' => $job, 'locale' => $locale]
            ));

            $response->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('job')
                    ->where('locale', $locale)
                    ->where('data.listing.title', $expected['title'])
                    ->where('data.listing.slug', $expected['slug'])
                    ->where('data.listing.description', $expected['description'])
                    ->where('data.listing.is_open', false)
                    ->where('data.listing.public_url', null)
                    ->where('data.form', null)
                    ->where('meta_tag.robots', self::PRIVATE_ROBOTS)
                    ->where('contentSeo.robots', self::PRIVATE_ROBOTS));

            $this->assertPrivatePreviewHeaders($response);

            $this->assertStringNotContainsString(
                'form_token',
                json_encode($response->viewData('page')['props']['data'], JSON_THROW_ON_ERROR)
            );
        }
    }

    public function test_workshop_draft_preview_uses_each_saved_locale_without_exposing_a_submission_form(): void
    {
        $workshop = $this->workshop(Workshop::PUBLICATION_DRAFT);

        foreach ([
            'en' => [
                'title' => 'Saved English Workshop Draft',
                'slug' => 'saved-english-workshop-draft',
                'description' => '<p>Saved English workshop draft details.</p>',
            ],
            'bn' => [
                'title' => 'সংরক্ষিত বাংলা কর্মশালার খসড়া',
                'slug' => 'saved-bangla-workshop-draft',
                'description' => '<p>সংরক্ষিত বাংলা কর্মশালার খসড়ার বিস্তারিত।</p>',
            ],
        ] as $locale => $expected) {
            $response = $this->actingAs($this->owner, 'admin')->get(route(
                'workshops.preview',
                ['workshop' => $workshop, 'locale' => $locale]
            ));

            $response->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('workshop')
                    ->where('locale', $locale)
                    ->where('data.listing.title', $expected['title'])
                    ->where('data.listing.slug', $expected['slug'])
                    ->where('data.listing.description', $expected['description'])
                    ->where('data.listing.is_open', false)
                    ->where('data.listing.public_url', null)
                    ->where('data.form', null)
                    ->where('meta_tag.robots', self::PRIVATE_ROBOTS)
                    ->where('contentSeo.robots', self::PRIVATE_ROBOTS));

            $this->assertPrivatePreviewHeaders($response);

            $this->assertStringNotContainsString(
                'form_token',
                json_encode($response->viewData('page')['props']['data'], JSON_THROW_ON_ERROR)
            );
        }
    }

    public function test_search_and_sharing_workspaces_list_localized_jobs_and_workshops(): void
    {
        $job = $this->job(JobPosting::PUBLICATION_DRAFT);
        $workshop = $this->workshop(Workshop::PUBLICATION_DRAFT);
        $this->actingAs($this->owner, 'admin');

        $dashboard = $this->get(route('seo.index', [
            'locale' => 'en',
            'type' => 'job',
            'issue' => 'all',
        ]))->assertOk();
        $jobTarget = collect($dashboard->viewData('dashboardTargets'))
            ->firstWhere('owner_id', $job->id);
        $this->assertSame('job', data_get($jobTarget, 'owner_type'));
        $this->assertSame('Saved English Job Draft', data_get($jobTarget, 'label'));
        $this->assertTrue((bool) data_get($jobTarget, 'content_analysis.available'));
        $this->assertGreaterThan(0, (int) data_get($jobTarget, 'content_analysis.word_count'));
        $this->assertSame('Jobs', data_get($dashboard->viewData('dashboardTypes'), 'job'));

        $bulk = $this->get(route('seo.bulk.index', [
            'locale' => 'en',
            'type' => 'workshop',
        ]))->assertOk();
        $workshopTarget = collect($bulk->viewData('targets')->items())
            ->firstWhere('owner_id', $workshop->id);
        $this->assertSame('workshop', data_get($workshopTarget, 'owner_type'));
        $this->assertSame('Saved English Workshop Draft', data_get($workshopTarget, 'label'));
        $this->assertTrue((bool) data_get($workshopTarget, 'content_analysis.available'));
        $this->assertGreaterThan(0, (int) data_get($workshopTarget, 'content_analysis.word_count'));
    }

    public function test_job_seo_editor_uses_the_localized_translation_but_saves_each_locale_on_the_root_job(): void
    {
        $job = $this->job(JobPosting::PUBLICATION_PUBLISHED);
        $englishCanonical = route('frontend.jobs.show', ['job' => 'saved-english-job-draft']);
        $banglaCanonical = url('/careers/saved-bangla-job-draft?lang=bn');

        $this->actingAs($this->owner, 'admin');
        $englishEditor = $this->get(route('seo.content.edit', [
            'type' => 'job',
            'id' => $job->id,
            'locale' => 'en',
        ]))->assertOk();
        $this->assertSeoEditorState(
            $englishEditor->viewData('contentTitle'),
            $englishEditor->viewData('locale'),
            $englishEditor->viewData('defaultCanonical'),
            'Saved English Job Draft',
            'en',
            $englishCanonical
        );

        $staleEnglishToken = (string) data_get($englishEditor->viewData('editor'), 'seo_editor_version');
        $staleRootVersion = (int) $job->editor_version;
        $job->increment('editor_version');
        $this->put(route('seo.content.update', ['type' => 'job', 'id' => $job->id]), $this->seoPayload(
            'en',
            'Custom English job search title',
            'Custom English job search description for the public detail page.',
            $staleEnglishToken,
            $staleRootVersion
        ))->assertStatus(409);
        $this->assertRootSeoMissing($job, 'en');

        $this->saveSeo(
            'job',
            $job->fresh(),
            'en',
            'Custom English job search title',
            'Custom English job search description for the public detail page.'
        );

        $banglaEditor = $this->get(route('seo.content.edit', [
            'type' => 'job',
            'id' => $job->id,
            'locale' => 'bn',
        ]))->assertOk();
        $this->assertSeoEditorState(
            $banglaEditor->viewData('contentTitle'),
            $banglaEditor->viewData('locale'),
            $banglaEditor->viewData('defaultCanonical'),
            'সংরক্ষিত বাংলা চাকরির খসড়া',
            'bn',
            $banglaCanonical
        );
        $this->saveSeo(
            'job',
            $job->fresh(),
            'bn',
            'কাস্টম বাংলা চাকরির সার্চ শিরোনাম',
            'পাবলিক চাকরির বিস্তারিত পাতার জন্য কাস্টম বাংলা সার্চ বিবরণ।',
            $banglaEditor
        );

        $this->assertRootSeoLocales($job, ['en', 'bn']);
        $this->assertDatabaseMissing('seo_metadata', ['seoable_type' => JobPostingTranslation::class]);

        $this->get('/careers/saved-english-job-draft')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job')
                ->where('meta_tag.meta_title', 'Custom English job search title')
                ->where('contentSeo.meta_title', 'Custom English job search title')
                ->where('contentSeo.canonical_url', $englishCanonical));
        $this->get('/careers/saved-bangla-job-draft?lang=bn')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job')
                ->where('meta_tag.meta_title', 'কাস্টম বাংলা চাকরির সার্চ শিরোনাম')
                ->where('contentSeo.meta_title', 'কাস্টম বাংলা চাকরির সার্চ শিরোনাম')
                ->where('contentSeo.canonical_url', $banglaCanonical));
    }

    public function test_workshop_seo_editor_uses_the_localized_translation_but_saves_each_locale_on_the_root_workshop(): void
    {
        $workshop = $this->workshop(Workshop::PUBLICATION_PUBLISHED);
        $englishCanonical = route('frontend.workshops.show', ['workshop' => 'saved-english-workshop-draft']);
        $banglaCanonical = url('/workshops/saved-bangla-workshop-draft?lang=bn');

        $this->actingAs($this->owner, 'admin');
        $englishEditor = $this->get(route('seo.content.edit', [
            'type' => 'workshop',
            'id' => $workshop->id,
            'locale' => 'en',
        ]))->assertOk();
        $this->assertSeoEditorState(
            $englishEditor->viewData('contentTitle'),
            $englishEditor->viewData('locale'),
            $englishEditor->viewData('defaultCanonical'),
            'Saved English Workshop Draft',
            'en',
            $englishCanonical
        );
        $this->saveSeo(
            'workshop',
            $workshop,
            'en',
            'Custom English workshop search title',
            'Custom English workshop search description for the public detail page.',
            $englishEditor
        );

        $banglaEditor = $this->get(route('seo.content.edit', [
            'type' => 'workshop',
            'id' => $workshop->id,
            'locale' => 'bn',
        ]))->assertOk();
        $this->assertSeoEditorState(
            $banglaEditor->viewData('contentTitle'),
            $banglaEditor->viewData('locale'),
            $banglaEditor->viewData('defaultCanonical'),
            'সংরক্ষিত বাংলা কর্মশালার খসড়া',
            'bn',
            $banglaCanonical
        );
        $this->saveSeo(
            'workshop',
            $workshop->fresh(),
            'bn',
            'কাস্টম বাংলা কর্মশালার সার্চ শিরোনাম',
            'পাবলিক কর্মশালার বিস্তারিত পাতার জন্য কাস্টম বাংলা সার্চ বিবরণ।',
            $banglaEditor
        );

        $this->assertRootSeoLocales($workshop, ['en', 'bn']);
        $this->assertDatabaseMissing('seo_metadata', ['seoable_type' => WorkshopTranslation::class]);

        $this->get('/workshops/saved-english-workshop-draft')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workshop')
                ->where('meta_tag.meta_title', 'Custom English workshop search title')
                ->where('contentSeo.meta_title', 'Custom English workshop search title')
                ->where('contentSeo.canonical_url', $englishCanonical));
        $this->get('/workshops/saved-bangla-workshop-draft?lang=bn')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workshop')
                ->where('meta_tag.meta_title', 'কাস্টম বাংলা কর্মশালার সার্চ শিরোনাম')
                ->where('contentSeo.meta_title', 'কাস্টম বাংলা কর্মশালার সার্চ শিরোনাম')
                ->where('contentSeo.canonical_url', $banglaCanonical));
    }

    private function assertSeoEditorState(
        mixed $actualTitle,
        mixed $actualLocale,
        mixed $actualCanonical,
        string $expectedTitle,
        string $expectedLocale,
        string $expectedCanonical
    ): void {
        $this->assertSame($expectedTitle, $actualTitle);
        $this->assertSame($expectedLocale, $actualLocale);
        $this->assertSame($expectedCanonical, $actualCanonical);
    }

    private function assertPrivatePreviewHeaders(TestResponse $response): void
    {
        $directives = array_map(
            'trim',
            explode(',', (string) $response->headers->get('Cache-Control'))
        );

        $this->assertEqualsCanonicalizing(
            ['private', 'no-store', 'max-age=0'],
            $directives
        );
    }

    private function saveSeo(
        string $type,
        JobPosting|Workshop $owner,
        string $locale,
        string $title,
        string $description,
        mixed $editorResponse = null
    ): void {
        $editorResponse ??= $this->get(route('seo.content.edit', [
            'type' => $type,
            'id' => $owner->getKey(),
            'locale' => $locale,
        ]))->assertOk();
        $token = (string) data_get($editorResponse->viewData('editor'), 'seo_editor_version');

        $this->put(route('seo.content.update', ['type' => $type, 'id' => $owner->getKey()]), $this->seoPayload(
            $locale,
            $title,
            $description,
            $token,
            (int) $owner->editor_version
        ))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => $owner::class,
            'seoable_id' => $owner->getKey(),
            'route_name' => null,
            'locale' => $locale,
            'title' => $title,
            'description' => $description,
        ]);
    }

    /** @return array<string, mixed> */
    private function seoPayload(
        string $locale,
        string $title,
        string $description,
        string $expectedSeoVersion,
        int $expectedEditorVersion
    ): array {
        return [
            'locale' => $locale,
            'schema_template' => 'none',
            'expected_editor_version' => $expectedEditorVersion,
            'expected_seo_version' => $expectedSeoVersion,
            'seo' => [
                'title' => $title,
                'description' => $description,
                'focus_keyword' => '',
                'canonical_url' => '',
                'robots_index' => 1,
                'robots_follow' => 1,
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'twitter_card' => 'summary_large_image',
                'twitter_title' => '',
                'twitter_description' => '',
                'twitter_image' => '',
                'schema_markup' => '',
                'sitemap_priority' => 0.5,
                'sitemap_change_frequency' => 'monthly',
                'exclude_from_sitemap' => 0,
            ],
        ];
    }

    private function assertRootSeoMissing(JobPosting|Workshop $owner, string $locale): void
    {
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => $owner::class,
            'seoable_id' => $owner->getKey(),
            'locale' => $locale,
        ]);
    }

    /** @param list<string> $expectedLocales */
    private function assertRootSeoLocales(JobPosting|Workshop $owner, array $expectedLocales): void
    {
        $actual = SeoMetadata::query()
            ->where('seoable_type', $owner::class)
            ->where('seoable_id', $owner->getKey())
            ->orderBy('locale')
            ->pluck('locale')
            ->all();

        sort($expectedLocales);
        $this->assertSame($expectedLocales, $actual);
    }

    private function job(string $publicationStatus): JobPosting
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_JOB);
        $job = JobPosting::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => $publicationStatus,
            'visible_from_at' => now()->subDay(),
            'application_opens_at' => now()->subHour(),
            'application_closes_at' => now()->addDay(),
            'employment_type' => JobPosting::EMPLOYMENT_FULL_TIME,
            'work_arrangement' => JobPosting::WORK_HYBRID,
            'vacancy_count' => 2,
            'editor_version' => 4,
        ]);
        $job->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => 'saved-english-job-draft',
                'title' => 'Saved English Job Draft',
                'department' => 'Programmes',
                'location' => 'Dhaka',
                'summary' => 'Saved English job summary.',
                'description' => '<p>Saved English job draft details.</p>',
                'responsibilities' => '<p>Lead delivery.</p>',
                'requirements' => '<p>Relevant experience.</p>',
            ],
            [
                'locale' => 'bn',
                'slug' => 'saved-bangla-job-draft',
                'title' => 'সংরক্ষিত বাংলা চাকরির খসড়া',
                'department' => 'কর্মসূচি',
                'location' => 'ঢাকা',
                'summary' => 'সংরক্ষিত বাংলা চাকরির সারসংক্ষেপ।',
                'description' => '<p>সংরক্ষিত বাংলা চাকরির খসড়ার বিস্তারিত।</p>',
                'responsibilities' => '<p>কর্মসূচি পরিচালনা করুন।</p>',
                'requirements' => '<p>প্রাসঙ্গিক অভিজ্ঞতা।</p>',
            ],
        ]);

        return $job->fresh('translations');
    }

    private function workshop(string $publicationStatus): Workshop
    {
        [$form, $version] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = Workshop::query()->create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => $publicationStatus,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_HYBRID,
            'registration_mode' => Workshop::REGISTRATION_AUTOMATIC,
            'capacity' => 30,
            'private_meeting_url' => 'https://meet.example.test/private-room',
            'editor_version' => 6,
        ]);
        $workshop->translations()->createMany([
            [
                'locale' => 'en',
                'slug' => 'saved-english-workshop-draft',
                'title' => 'Saved English Workshop Draft',
                'summary' => 'Saved English workshop summary.',
                'description' => '<p>Saved English workshop draft details.</p>',
                'facilitator_name' => 'English Facilitator',
                'venue_name' => 'IGF Centre',
                'venue_address' => 'Dhaka',
                'registration_instructions' => '<p>Registration is free.</p>',
            ],
            [
                'locale' => 'bn',
                'slug' => 'saved-bangla-workshop-draft',
                'title' => 'সংরক্ষিত বাংলা কর্মশালার খসড়া',
                'summary' => 'সংরক্ষিত বাংলা কর্মশালার সারসংক্ষেপ।',
                'description' => '<p>সংরক্ষিত বাংলা কর্মশালার খসড়ার বিস্তারিত।</p>',
                'facilitator_name' => 'বাংলা প্রশিক্ষক',
                'venue_name' => 'আইজিএফ কেন্দ্র',
                'venue_address' => 'ঢাকা',
                'registration_instructions' => '<p>নিবন্ধন বিনামূল্যে।</p>',
            ],
        ]);

        return $workshop->fresh('translations');
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function publishedForm(string $purpose): array
    {
        $form = ApplicationForm::query()->create([
            'purpose' => $purpose,
            'name' => Str::headline($purpose) . ' preview form',
        ]);
        $version = ApplicationFormVersion::query()->create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'published_at' => now()->subDay(),
            'published_by_admin_id' => $this->owner->id,
        ]);

        return [$form, $version];
    }
}
