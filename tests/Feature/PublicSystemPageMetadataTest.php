<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\TranslationLocale;
use App\Services\PublicSystemPageMetaService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSystemPageMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_bangla_system_page_titles_and_metadata_use_localized_customizer_copy(): void
    {
        $this->enableBangla();
        $this->specialPagePair(
            '91000000-0000-4000-8000-000000000001',
            'about-us',
            'About Ignite Global Foundation',
            'ইগনাইট গ্লোবাল ফাউন্ডেশন সম্পর্কে',
            'A volunteer-led movement.',
            'শেখার সুযোগ চাওয়া শিশুদের আহ্বান থেকে শুরু হওয়া একটি আন্দোলন।',
        );
        $this->specialPagePair(
            '91000000-0000-4000-8000-000000000002',
            'zakat',
            'Give your Zakat',
            'আপনার যাকাত দিন',
            'Direct eligible giving.',
            'শিক্ষা, খাদ্য ও জীবিকা নির্বাহের জন্য সরাসরি যোগ্য অনুদান।',
        );
        Page::query()
            ->where('language', 'bn')
            ->where('slug', 'sponsor-a-child')
            ->delete();

        $expectations = [
            [route('frontend.about'), 'about', 'ইগনাইট গ্লোবাল ফাউন্ডেশন সম্পর্কে', 'শেখার সুযোগ চাওয়া শিশুদের আহ্বান', 'ইগনাইট গ্লোবাল ফাউন্ডেশন সম্পর্কে'],
            [route('frontend.zakat'), 'zakat', 'আপনার যাকাত দিন', 'শিক্ষা, খাদ্য ও জীবিকা নির্বাহের জন্য'],
            [route('frontend.contactUs'), 'contactUs', 'যোগাযোগ', 'কোনো কর্মসূচি, অংশীদারিত্ব, অনুদান বা স্বেচ্ছাসেবা নিয়ে প্রশ্ন আছে?'],
            [route('frontend.annual_report.index'), 'annual-report', 'বার্ষিক প্রতিবেদন', 'আমাদের প্রকাশিত প্রতিবেদন, কর্মসূচির হালনাগাদ তথ্য'],
            [route('frontend.gallery'), 'gallery', 'ফটো গ্যালারি', 'কমিউনিটি কর্মসূচি, অংশীদারিত্ব, শেখার পরিবেশ'],
            [route('search'), 'search', 'সাইটে অনুসন্ধান করুন', 'প্রকাশিত গল্প, কর্মসূচি, প্রতিবেদন ও পৃষ্ঠা খুঁজুন।'],
            [route('frontend.project'), 'project', 'আমাদের প্রকল্প', 'বাংলাদেশজুড়ে ইগনাইট গ্লোবাল ফাউন্ডেশনের'],
            [route('frontend.events'), 'events', 'ইভেন্ট ও সর্বশেষ সংবাদ', 'আমাদের লক্ষ্যকে এগিয়ে নেওয়া মানুষ'],
            [route('frontend.sponsor_child'), 'sponsor_child', 'একটি শিশুর পৃষ্ঠপোষক হোন', 'নিয়মিত অনুদান একটি শিশুকে স্কুলে থাকতে'],
            [route('frontend.volunteer_registration.index'), 'volunteer-registration', 'ইগনাইটের সঙ্গে স্বেচ্ছাসেবা', 'স্থানীয় ধারণাকে দীর্ঘস্থায়ী পরিবর্তনে রূপ দেওয়া মানুষদের কমিউনিটিতে যুক্ত হোন।'],
        ];

        foreach ($expectations as $expectation) {
            [$url, $component, $title, $descriptionFragment] = $expectation;
            $expectedMetaTitle = $expectation[4] ?? $title.' | ইগনাইট গ্লোবাল ফাউন্ডেশন';
            $response = $this->withSession(['locale' => 'bn'])
                ->get($url.'?lang=bn')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('title', $title)
                    ->where('meta_tag.meta_title', $expectedMetaTitle)
                    ->where('meta_tag.meta_keyword', $title.', ইগনাইট গ্লোবাল ফাউন্ডেশন')
                    ->where('meta_tag.meta_description', fn ($value): bool => str_contains((string) $value, $descriptionFragment))
                );

            $this->assertStringContainsString(
                '<title inertia>'.$expectedMetaTitle.'</title>',
                $response->getContent(),
            );
        }
    }

    public function test_admin_customized_listing_copy_drives_safe_fallback_metadata(): void
    {
        $this->setting('branding', 'site_name', 'Community Ignite');
        $this->setting('gallery_page', 'title', '<b>Field stories</b>');
        $this->setting('gallery_page', 'introduction', '<p>Photos from locally led work.</p>');

        $this->get(route('frontend.gallery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gallery')
                ->where('title', 'Field stories')
                ->where('meta_tag.meta_title', 'Field stories | Community Ignite')
                ->where('meta_tag.meta_keyword', 'Field stories, Community Ignite')
                ->where('meta_tag.meta_description', 'Photos from locally led work.')
            );
    }

    public function test_curated_route_metadata_stays_separate_from_the_customizer_fallback_layer(): void
    {
        $request = Request::create('/contact-us');
        $request->attributes->set('route_seo', [
            'meta_title' => 'Curated contact title',
            'meta_description' => 'Curated contact description.',
            'meta_keyword' => 'curated contact',
        ]);

        $resolved = app(PublicSystemPageMetaService::class)->resolve(
            $request,
            'header.contact_label',
            'contact_page.introduction',
            ['title' => 'Contact', 'meta_title' => 'Contact Us', 'description' => 'Fallback description.'],
        );

        $this->assertSame('Contact', $resolved['title']);
        $this->assertSame('Contact Us | Ignite Global Foundation', $resolved['meta_tag']['meta_title']);
        $this->assertSame(
            'Questions about a program, partnership, donation, or volunteering? Send us a message and our team will respond as soon as possible.',
            $resolved['meta_tag']['meta_description'],
        );
        $this->assertSame('Contact, Ignite Global Foundation', $resolved['meta_tag']['meta_keyword']);
        $this->assertSame('Curated contact title', $request->attributes->get('route_seo')['meta_title']);
    }

    public function test_curated_archive_metadata_remains_authoritative_over_customizer_copy(): void
    {
        $this->setting('branding', 'site_name', 'Community Ignite');
        $this->setting('gallery_page', 'title', 'Field gallery');
        $this->setting('gallery_page', 'introduction', 'Fallback field-gallery description.');
        SeoMetadata::query()->create([
            'route_name' => 'frontend.gallery',
            'route_path' => '/gallery',
            'locale' => 'en',
            'title' => 'Curated gallery search title',
            'description' => 'Curated gallery search description.',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $this->get(route('frontend.gallery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gallery')
                ->where('title', 'Field gallery')
                ->where('meta_tag.meta_title', 'Curated gallery search title')
                ->where('meta_tag.meta_description', 'Curated gallery search description.')
                ->where('routeSeo.meta_title', 'Curated gallery search title')
                ->where('contentSeo.meta_title', 'Curated gallery search title')
            );
    }

    public function test_localized_content_fallbacks_use_customizable_brand_and_content_seo_stays_authoritative(): void
    {
        $this->enableBangla();
        $this->setting('branding', 'site_name', 'কমিউনিটি ইগনাইট', 'bn');

        $report = AnnualReport::query()->create([
            'title' => 'বার্ষিক প্রভাব প্রতিবেদন',
            'sub_title' => 'কমিউনিটির ফলাফলের সংক্ষিপ্তসার।',
            'slug' => 'bangla-impact-report',
            'description' => '<p>প্রকাশিত ফলাফল ও জবাবদিহি।</p>',
            'notice_type' => 'annual-report',
            'language' => 'bn',
            'published_at' => now()->subDay(),
            'status' => 1,
        ]);
        $event = NoticeBoard::query()->create([
            'title' => 'কমিউনিটি সভা',
            'sub_title' => 'স্থানীয় স্বেচ্ছাসেবীদের সঙ্গে একটি সভা।',
            'slug' => 'bangla-community-meeting',
            'description' => '<p>কমিউনিটির পরবর্তী উদ্যোগ নিয়ে আলোচনা।</p>',
            'notice_type' => 'notice-board',
            'language' => 'bn',
            'published_at' => now()->subDay(),
            'status' => 1,
        ]);
        $category = Category::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'স্বাস্থ্য কর্মসূচি',
            'slug' => 'bangla-health-programs',
            'description' => '<p>কমিউনিটির নেতৃত্বে স্বাস্থ্য সহায়তা।</p>',
            'language' => 'bn',
            'status' => 1,
        ]);
        $tag = Tag::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'জলবায়ু প্রকল্প',
            'slug' => 'bangla-climate-projects',
            'status' => 1,
        ]);

        foreach ([
            [route('frontend.annual_report.show', ['slug' => $report->slug, 'lang' => 'bn']), 'annual-report-detail', $report->title],
            [route('frontend.event', ['slug' => $event->slug, 'lang' => 'bn']), 'event', $event->title],
            [route('frontend.category', ['slug' => $category->slug, 'lang' => 'bn']), 'category', $category->name],
            [route('frontend.project', ['slug' => $tag->slug, 'lang' => 'bn']), 'project', $tag->name],
        ] as [$url, $component, $title]) {
            $this->withSession(['locale' => 'bn'])
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('title', $title)
                    ->where('meta_tag.meta_title', $title.' | কমিউনিটি ইগনাইট')
                    ->where('meta_tag.meta_keyword', $title.', কমিউনিটি ইগনাইট')
                );
        }

        SeoMetadata::query()->create([
            'seoable_type' => AnnualReport::class,
            'seoable_id' => $report->id,
            'locale' => 'bn',
            'title' => 'সম্পাদিত প্রতিবেদনের শিরোনাম',
            'description' => 'সম্পাদিত প্রতিবেদনের বিবরণ।',
            'focus_keyword' => 'সম্পাদিত প্রতিবেদন',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $this->withSession(['locale' => 'bn'])
            ->get(route('frontend.annual_report.show', ['slug' => $report->slug, 'lang' => 'bn']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta_tag.meta_title', 'সম্পাদিত প্রতিবেদনের শিরোনাম')
                ->where('meta_tag.meta_description', 'সম্পাদিত প্রতিবেদনের বিবরণ।')
                ->where('meta_tag.meta_keyword', 'সম্পাদিত প্রতিবেদন')
            );
    }

    public function test_sponsor_fallback_uses_customizer_copy_when_no_content_page_owns_the_route(): void
    {
        $routes = config('seo.routes');
        unset($routes['frontend.sponsor_child']['page_slug']);
        config()->set('seo.routes', $routes);

        $this->setting('sponsor_page', 'eyebrow', 'Sponsor brighter futures');
        $this->setting('sponsor_page', 'introduction', 'Help children continue learning with dependable support.');

        $this->get(route('frontend.sponsor_child'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sponsor_child')
                ->where('title', 'Sponsor brighter futures')
                ->where('meta_tag.meta_title', 'Sponsor brighter futures | Ignite Global Foundation')
                ->where('meta_tag.meta_description', 'Help children continue learning with dependable support.')
                ->where('contentSeo', [])
            );
    }

    public function test_bangla_defaults_are_available_without_preloaded_setting_rows(): void
    {
        $values = app(SiteSettingService::class)->values('bn', true);

        $this->assertSame('ইগনাইট গ্লোবাল ফাউন্ডেশন', $values['branding']['site_name']);
        $this->assertSame('চলুন কথা শুরু করি।', $values['contact_page']['title']);
        $this->assertSame('একটি শিশুর পৃষ্ঠপোষক হোন', $values['sponsor_page']['eyebrow']);
        $this->assertSame('ইগনাইটের সঙ্গে স্বেচ্ছাসেবা', $values['volunteer_page']['eyebrow']);
        $this->assertSame('সাইটে অনুসন্ধান করুন', $values['search_page']['title']);
        $this->assertSame('ফটো গ্যালারি', $values['gallery_page']['title']);
        $this->assertSame('বার্ষিক প্রতিবেদন', $values['reports_page']['title']);
        $this->assertSame('কমিউনিটি কর্মসূচি', $values['content_archives']['category_default_title']);
        $this->assertSame(
            'ইগনাইট গ্লোবাল ফাউন্ডেশনের কমিউনিটির নেতৃত্বাধীন কর্মসূচি ও প্রকাশিত প্রভাবের গল্পগুলো দেখুন।',
            $values['content_archives']['category_search_description'],
        );
        $this->assertSame('আমাদের প্রকল্প', $values['content_archives']['project_default_title']);
        $this->assertSame(
            'বাংলাদেশজুড়ে ইগনাইট গ্লোবাল ফাউন্ডেশনের কমিউনিটির নেতৃত্বাধীন প্রকল্পগুলো দেখুন।',
            $values['content_archives']['project_search_description'],
        );
        $this->assertSame('ইভেন্ট ও সর্বশেষ সংবাদ', $values['content_archives']['events_default_title']);

        $fallbacks = app(PublicSystemPageMetaService::class);
        $this->assertTrue($fallbacks->supportsLocalizedRouteFallback('frontend.sponsor_child', 'bn'));
        $this->assertFalse($fallbacks->supportsLocalizedRouteFallback('frontend.home', 'bn'));
    }

    private function enableBangla(): void
    {
        TranslationLocale::query()->where('locale', 'bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    private function setting(string $group, string $key, string $value, string $locale = 'en'): void
    {
        SiteSetting::updateOrCreate([
            'group' => $group,
            'key' => $key,
            'locale' => $locale,
        ], [
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }

    private function specialPagePair(
        string $uuid,
        string $slug,
        string $englishName,
        string $banglaName,
        string $englishSubtitle,
        string $banglaSubtitle,
    ): void {
        foreach ([
            'en' => [$englishName, $englishSubtitle],
            'bn' => [$banglaName, $banglaSubtitle],
        ] as $locale => [$name, $subtitle]) {
            Page::query()->create([
                'uuid' => $uuid,
                'name' => $name,
                'sub_title' => $subtitle,
                'slug' => $slug,
                'description' => '<p>'.$subtitle.'</p>',
                'language' => $locale,
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'published_at' => now()->subDay(),
            ]);
        }
    }
}
