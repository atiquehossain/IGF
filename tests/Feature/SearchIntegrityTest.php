<?php

namespace Tests\Feature;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\Page;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\JobPosting;
use App\Models\NoticeBoard;
use App\Models\PageBlock;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_migration_search_view_returns_published_page_contract(): void
    {
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Clean Water Initiative',
            'sub_title' => 'Safe water for rural families',
            'description' => '<p>A community water program.</p>',
            'slug' => 'clean-water-initiative',
            'status' => 1,
            'language' => 'en',
        ]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=rural%20families')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'search')
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.name', 'Clean Water Initiative')
            ->assertJsonPath('props.data.pages.0.view_type', 'page')
            ->assertJsonPath('props.data.pages.0.slug', 'clean-water-initiative');
    }

    public function test_search_indexes_visible_page_builder_copy_but_not_hidden_sections(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community learning page',
            'sub_title' => '',
            'description' => '',
            'slug' => 'community-learning-page',
            'status' => 1,
            'language' => 'en',
        ]);
        $page->blocks()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Mentorship story',
            'content' => ['heading' => 'Learning together', 'body' => '<p>Aurora mentorship circle 9472.</p>'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $page->blocks()->create([
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Hidden draft',
            'content' => ['body' => '<p>Hidden nebula phrase 8341.</p>'],
            'settings' => [],
            'sort_order' => 2,
            'is_enabled' => false,
        ]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=aurora%20mentorship')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.name', 'Community learning page')
            ->assertJsonPath('props.data.pages.0.result_url', '/page/community-learning-page')
            ->assertJsonPath('props.data.pages.0.view_type', 'page');

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=hidden%20nebula')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 0);
    }

    public function test_search_includes_active_donation_causes_with_localized_result_contract(): void
    {
        $cause = DonationType::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'solar-scholarship-microfund',
            'name' => 'Solar scholarship microfund 9472',
            'description' => 'Learning after sunset in remote communities.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Solar learning fund',
            'status' => 1,
        ]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=microfund%209472')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.name', $cause->name)
            ->assertJsonPath('props.data.pages.0.view_type', 'donation')
            ->assertJsonPath('props.data.pages.0.result_url', '/donate/solar-scholarship-microfund');

        $cause->update(['status' => 0]);
        $this->withHeaders($this->inertiaHeaders())->get('/search?search=microfund%209472')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 0);
    }

    public function test_search_includes_public_job_and_workshop_details(): void
    {
        [$jobForm, $jobVersion] = $this->publishedForm(ApplicationForm::PURPOSE_JOB);
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
        $job->translations()->create([
            'locale' => 'en',
            'slug' => 'community-archivist',
            'title' => 'Community Archivist',
            'summary' => 'Preserve the unique Atlas 7391 archive.',
            'description' => '<p>Public role description.</p>',
        ]);

        [$workshopForm, $workshopVersion] = $this->publishedForm(ApplicationForm::PURPOSE_WORKSHOP);
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
        $workshop->translations()->create([
            'locale' => 'en',
            'slug' => 'river-mapping-lab',
            'title' => 'River Mapping Lab',
            'summary' => 'Practice the unique Delta 6284 method.',
            'description' => '<p>Public workshop description.</p>',
        ]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Atlas%207391')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.view_type', 'job')
            ->assertJsonPath('props.data.pages.0.result_url', '/careers/community-archivist');

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Delta%206284')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.view_type', 'workshop')
            ->assertJsonPath('props.data.pages.0.result_url', '/workshops/river-mapping-lab');
    }

    public function test_trashed_page_is_removed_from_search_results(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Temporary Campaign',
            'sub_title' => 'Remove this campaign',
            'slug' => 'temporary-campaign',
            'status' => 1,
            'language' => 'en',
        ]);
        $page->delete();

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Temporary')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 0);
    }

    public function test_search_returns_programs_events_reports_and_gallery_content_with_public_urls(): void
    {
        Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Vision Program', 'slug' => 'vision-program', 'description' => 'Vision work', 'language' => 'en', 'status' => 1]);
        NoticeBoard::create(['title' => 'Vision Gathering', 'slug' => 'vision-gathering', 'description' => 'Vision story', 'notice_type' => 'notice-board', 'language' => 'en', 'order_by' => 40, 'status' => 1]);
        AnnualReport::create(['title' => 'Vision Annual Report', 'slug' => 'vision-report', 'description' => 'Vision reporting', 'notice_type' => 'annual-report', 'language' => 'en', 'order_by' => 30, 'status' => 1]);
        Gallery::create(['uuid' => (string) Str::uuid(), 'name' => 'Vision in Pictures', 'type' => 'gallery', 'description' => 'Vision photo', 'language' => 'en', 'order_by' => 20, 'status' => 1]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Vision')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 4)
            ->assertJsonPath('props.data.pages.0.view_type', 'event')
            ->assertJsonPath('props.data.pages.0.result_url', '/event/vision-gathering')
            ->assertJsonPath('props.data.pages.1.view_type', 'report')
            ->assertJsonPath('props.data.pages.1.result_url', '/annual-report/vision-report')
            ->assertJsonPath('props.data.pages.2.view_type', 'gallery')
            ->assertJsonPath('props.data.pages.2.result_url', '/gallery')
            ->assertJsonPath('props.data.pages.3.view_type', 'program')
            ->assertJsonPath('props.data.pages.3.result_url', '/category/vision-program');
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
        ]);
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function publishedForm(string $purpose): array
    {
        $form = ApplicationForm::create([
            'purpose' => $purpose,
            'name' => ucfirst($purpose).' search fixture',
        ]);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', $purpose.'-search-fixture'),
            'published_at' => now(),
        ]);

        return [$form, $version];
    }
}
