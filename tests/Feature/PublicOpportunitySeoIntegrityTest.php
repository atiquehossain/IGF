<?php

namespace Tests\Feature;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\SeoMetadata;
use App\Models\TranslationLocale;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicOpportunitySeoIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-10 10:00:00');
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_locale_sitemaps_publish_only_real_public_opportunity_translations(): void
    {
        [$jobForm, $jobVersion] = $this->form(ApplicationForm::PURPOSE_JOB);
        $job = $this->job($jobForm, $jobVersion, 'program-officer');
        $this->translateJob($job, 'bn', 'kormosuchi-kormokorta', 'কর্মসূচি কর্মকর্তা');
        $englishOnlyJob = $this->job($jobForm, $jobVersion, 'english-only-role');
        $closedJob = $this->job($jobForm, $jobVersion, 'closed-public-role', [
            'application_closes_at' => now()->subMinute(),
        ]);
        $draftJob = $this->job($jobForm, $jobVersion, 'draft-role', [
            'publication_status' => JobPosting::PUBLICATION_DRAFT,
        ]);
        $this->translateJob($draftJob, 'bn', 'draft-role-bn', 'খসড়া পদ');
        $futureJob = $this->job($jobForm, $jobVersion, 'future-role', [
            'visible_from_at' => now()->addMinute(),
        ]);
        $this->translateJob($futureJob, 'bn', 'future-role-bn', 'ভবিষ্যৎ পদ');

        [$workshopForm, $workshopVersion] = $this->form(ApplicationForm::PURPOSE_WORKSHOP);
        $workshop = $this->workshop($workshopForm, $workshopVersion, 'community-leadership');
        $this->translateWorkshop($workshop, 'bn', 'community-leadership-bn', 'কমিউনিটি নেতৃত্ব');
        $englishOnlyWorkshop = $this->workshop($workshopForm, $workshopVersion, 'english-only-workshop');
        $draftWorkshop = $this->workshop($workshopForm, $workshopVersion, 'draft-workshop', [
            'publication_status' => Workshop::PUBLICATION_DRAFT,
        ]);
        $this->translateWorkshop($draftWorkshop, 'bn', 'draft-workshop-bn', 'খসড়া কর্মশালা');
        $futureWorkshop = $this->workshop($workshopForm, $workshopVersion, 'future-workshop', [
            'visible_from_at' => now()->addMinute(),
        ]);
        $this->translateWorkshop($futureWorkshop, 'bn', 'future-workshop-bn', 'ভবিষ্যৎ কর্মশালা');

        $englishJobUrl = route('frontend.jobs.show', ['job' => 'program-officer']);
        $banglaJobUrl = url('/careers/kormosuchi-kormokorta?lang=bn');
        $englishWorkshopUrl = route('frontend.workshops.show', ['workshop' => 'community-leadership']);
        $banglaWorkshopUrl = url('/workshops/community-leadership-bn?lang=bn');

        $english = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . $englishJobUrl . '</loc>', $english);
        $this->assertStringContainsString('<loc>' . route('frontend.jobs.show', ['job' => $englishOnlyJob->translations->sole()->slug]) . '</loc>', $english);
        $this->assertStringContainsString('<loc>' . route('frontend.jobs.show', ['job' => $closedJob->translations->sole()->slug]) . '</loc>', $english);
        $this->assertStringContainsString('<loc>' . $englishWorkshopUrl . '</loc>', $english);
        $this->assertStringContainsString('<loc>' . route('frontend.workshops.show', ['workshop' => $englishOnlyWorkshop->translations->sole()->slug]) . '</loc>', $english);
        $this->assertStringContainsString('hreflang="bn" href="' . $banglaJobUrl . '"', $english);
        $this->assertStringContainsString('hreflang="x-default" href="' . $englishJobUrl . '"', $english);
        $this->assertStringContainsString('hreflang="bn" href="' . $banglaWorkshopUrl . '"', $english);
        $this->assertStringNotContainsString('draft-role', $english);
        $this->assertStringNotContainsString('future-role', $english);
        $this->assertStringNotContainsString('draft-workshop', $english);
        $this->assertStringNotContainsString('future-workshop', $english);

        $bangla = $this->get('/sitemap-bn.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . $banglaJobUrl . '</loc>', $bangla);
        $this->assertStringContainsString('<loc>' . $banglaWorkshopUrl . '</loc>', $bangla);
        $this->assertStringContainsString('hreflang="en" href="' . $englishJobUrl . '"', $bangla);
        $this->assertStringContainsString('hreflang="en" href="' . $englishWorkshopUrl . '"', $bangla);
        $this->assertStringNotContainsString('english-only-role', $bangla);
        $this->assertStringNotContainsString('english-only-workshop', $bangla);
        $this->assertStringNotContainsString('draft-role', $bangla);
        $this->assertStringNotContainsString('future-role', $bangla);
        $this->assertStringNotContainsString('draft-workshop', $bangla);
        $this->assertStringNotContainsString('future-workshop', $bangla);
    }

    public function test_detail_canonicals_use_selected_slugs_and_hreflang_never_invents_a_translation(): void
    {
        [$jobForm, $jobVersion] = $this->form(ApplicationForm::PURPOSE_JOB);
        $job = $this->job($jobForm, $jobVersion, 'english-program-role');
        $this->translateJob($job, 'bn', 'bangla-program-role', 'বাংলা কর্মসূচি পদ');

        $englishJobUrl = route('frontend.jobs.show', ['job' => 'english-program-role']);
        $banglaJobUrl = url('/careers/bangla-program-role?lang=bn');
        $jobResponse = $this->get('/careers/english-program-role?lang=bn')->assertOk();
        $jobResponse->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.canonical_url', $banglaJobUrl)
            ->where('contentSeo.canonical_url', $banglaJobUrl)
            ->where('seoAlternates.links', [
                ['locale' => 'en', 'url' => $englishJobUrl],
                ['locale' => 'bn', 'url' => $banglaJobUrl],
            ])
            ->where('seoAlternates.x_default', $englishJobUrl)
        );
        $jobHead = Str::before($jobResponse->getContent(), '</head>');
        $this->assertStringContainsString('rel="canonical" href="' . $banglaJobUrl . '"', $jobHead);
        $this->assertStringContainsString('hreflang="en" href="' . $englishJobUrl . '"', $jobHead);
        $this->assertStringContainsString('hreflang="bn" href="' . $banglaJobUrl . '"', $jobHead);

        [$workshopForm, $workshopVersion] = $this->form(ApplicationForm::PURPOSE_WORKSHOP);
        $this->workshop($workshopForm, $workshopVersion, 'english-only-session');
        $englishWorkshopUrl = route('frontend.workshops.show', ['workshop' => 'english-only-session']);
        $localizedFallbackUrl = url('/workshops/english-only-session?lang=bn');

        $workshopResponse = $this->get('/workshops/english-only-session?lang=bn')->assertOk();
        $workshopResponse->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.canonical_url', $localizedFallbackUrl)
            ->where('contentSeo.canonical_url', $localizedFallbackUrl)
            ->where('seoAlternates.links', [[
                'locale' => 'en',
                'url' => $englishWorkshopUrl,
            ]])
            ->where('seoAlternates.x_default', $englishWorkshopUrl)
        );
        $workshopHead = Str::before($workshopResponse->getContent(), '</head>');
        $this->assertStringContainsString('rel="canonical" href="' . $localizedFallbackUrl . '"', $workshopHead);
        $this->assertStringContainsString('hreflang="en" href="' . $englishWorkshopUrl . '"', $workshopHead);
        $this->assertStringNotContainsString('hreflang="bn"', $workshopHead);
    }

    public function test_locale_sitemap_honors_opportunity_search_visibility_controls(): void
    {
        [$jobForm, $jobVersion] = $this->form(ApplicationForm::PURPOSE_JOB);
        $visibleJob = $this->job($jobForm, $jobVersion, 'visible-search-job');
        $hiddenJob = $this->job($jobForm, $jobVersion, 'hidden-search-job');
        SeoMetadata::create([
            'seoable_type' => JobPosting::class,
            'seoable_id' => $hiddenJob->id,
            'locale' => 'en',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => true,
        ]);

        [$workshopForm, $workshopVersion] = $this->form(ApplicationForm::PURPOSE_WORKSHOP);
        $visibleWorkshop = $this->workshop($workshopForm, $workshopVersion, 'visible-search-workshop');
        $hiddenWorkshop = $this->workshop($workshopForm, $workshopVersion, 'hidden-search-workshop');
        SeoMetadata::create([
            'seoable_type' => Workshop::class,
            'seoable_id' => $hiddenWorkshop->id,
            'locale' => 'en',
            'robots_index' => true,
            'robots_follow' => true,
            'exclude_from_sitemap' => true,
        ]);

        $sitemap = $this->get('/sitemap-en.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/careers/' . $visibleJob->translations->sole()->slug, $sitemap);
        $this->assertStringNotContainsString('/careers/' . $hiddenJob->translations->sole()->slug, $sitemap);
        $this->assertStringContainsString('/workshops/' . $visibleWorkshop->translations->sole()->slug, $sitemap);
        $this->assertStringNotContainsString('/workshops/' . $hiddenWorkshop->translations->sole()->slug, $sitemap);
    }

    /** @return array{ApplicationForm, ApplicationFormVersion} */
    private function form(string $purpose): array
    {
        $form = ApplicationForm::create([
            'purpose' => $purpose,
            'name' => Str::headline($purpose) . ' SEO fixture',
        ]);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);

        return [$form, $version];
    }

    /** @param array<string, mixed> $overrides */
    private function job(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        string $slug,
        array $overrides = []
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
            'vacancy_count' => 1,
        ], $overrides));
        $this->translateJob($posting, 'en', $slug, Str::headline($slug));

        return $posting->fresh('translations');
    }

    private function translateJob(JobPosting $posting, string $locale, string $slug, string $title): void
    {
        $posting->translations()->create([
            'locale' => $locale,
            'slug' => $slug,
            'title' => $title,
            'summary' => 'A public career opportunity.',
            'description' => '<p>Career details.</p>',
        ]);
        $posting->unsetRelation('translations');
    }

    /** @param array<string, mixed> $overrides */
    private function workshop(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        string $slug,
        array $overrides = []
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
        ], $overrides));
        $this->translateWorkshop($workshop, 'en', $slug, Str::headline($slug));

        return $workshop->fresh('translations');
    }

    private function translateWorkshop(Workshop $workshop, string $locale, string $slug, string $title): void
    {
        $workshop->translations()->create([
            'locale' => $locale,
            'slug' => $slug,
            'title' => $title,
            'summary' => 'A free public workshop.',
            'description' => '<p>Workshop details.</p>',
        ]);
        $workshop->unsetRelation('translations');
    }
}
