<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageMenu;
use App\Models\SeoMetadata;
use App\Models\Testimonial;
use Database\Seeders\IgniteParityContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IgniteParityContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_content_navigation_and_dynamic_sections_are_complete(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $this->assertSame(6, NoticeBoard::where('status', 1)->count());
        $this->assertSame(3, Testimonial::where('status', 1)->count());
        $this->assertSame(2, AnnualReport::where('status', 1)->count());
        $this->assertSame(16, Gallery::where('status', 1)->count());
        $this->assertSame(7, LatestNews::where('type', 'our-members')->where('status', 1)->count());
        $this->assertSame(5, Page::whereHas('pageTags.tag', fn ($query) => $query->where('slug', 'current-project'))->count());
        $this->assertSame(2, Page::whereHas('pageTags.tag', fn ($query) => $query->where('slug', 'completed-project'))->count());

        $awards = Category::where('slug', 'awards-&-recognition')->where('language', 'en')->firstOrFail();
        $awardNames = Page::publiclyAvailable()
            ->whereIn('category_id', [$awards->id, $awards->uuid])
            ->where('language', 'en')
            ->orderByDesc('order_by')
            ->pluck('name')
            ->all();
        $this->assertSame([
            'The Diana Award',
            'UN Best Volunteer Award',
            'ILA Global 30 Under 30',
            'VSO National Volunteer Award',
            'The Hero Award',
        ], $awardNames);

        $this->get('/category/awards-&-recognition')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('properties.total_count', 5)
            ->has('data.items', 5)
            ->where('data.items.0.name', 'The Diana Award')
            ->where('data.items.3.name', 'VSO National Volunteer Award')
            ->where('data.items.4.name', 'The Hero Award')
        );

        $adminManagedAward = Page::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'category_id' => $awards->id,
            'name' => 'Administrator Managed Award',
            'sub_title' => 'A published recognition created through the managed content workflow.',
            'slug' => 'administrator-managed-award',
            'description' => '<p>Managed award details.</p>',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'name_enabled' => 1,
            'sub_title_enabled' => 1,
            'order_by' => 60,
            'published_at' => today(),
            'language' => 'en',
        ]);
        $this->get('/category/awards-&-recognition')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('properties.total_count', 6)
            ->has('data.items', 6)
            ->where('data.items.0.name', 'Administrator Managed Award')
        );
        $adminManagedAward->delete();
        $this->assertSoftDeleted($adminManagedAward);
        $this->get('/category/awards-&-recognition')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('properties.total_count', 5)
            ->has('data.items', 5)
        );

        $this->assertTrue(Page::where('slug', 'ignite-school-bawnia-campus')->where('publication_status', 'published')->exists());
        $home = Page::where('slug', 'home')->where('language', 'en')->firstOrFail();
        $this->assertSame('Ignite Global Foundation | Building Lasting Change', $home->meta_title);
        $this->assertNotEmpty($home->meta_description);
        $this->assertNotEmpty($home->getRawOriginal('thumbnail'));
        $homeSeo = SeoMetadata::query()
            ->where('seoable_type', Page::class)
            ->where('seoable_id', $home->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->assertSame('Ignite Global Foundation | Building Lasting Change', $homeSeo->title);
        $this->assertNotEmpty($homeSeo->description);
        $this->assertNotEmpty($homeSeo->og_image);
        $homeCategory = Category::where('slug', 'home')->where('language', 'en')->firstOrFail();
        $homeCategorySeo = SeoMetadata::query()
            ->where('seoable_type', Category::class)
            ->where('seoable_id', $homeCategory->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->assertFalse($homeCategorySeo->robots_index);
        $this->assertTrue($homeCategorySeo->robots_follow);
        $this->assertTrue($homeCategorySeo->exclude_from_sitemap);
        $this->assertSame(0, MediaAsset::where('path', 'like', 'media/ignite-live/%')->whereNull('alt_text')->count());
        $this->assertSame(6, PageMenu::where('type', 'main')->where('status', 1)->whereNull('parent_id')->count());
        $this->assertFalse(PageMenu::where('type', 'main')->where('status', 1)->where('slug', "founder's-letter")->exists());

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/home')
                ->has('appMenus', 6)
                ->has('appMenus.1.children', 5)
                ->where('appMenus.1.children.0.name', 'Who We Are')
                ->where('appMenus.1.children.1.name', 'Awards & Recognition')
                ->where('appMenus.1.children.2.name', 'Photo Gallery')
                ->where('appMenus.1.children.3.name', 'Annual Reports')
                ->where('appMenus.1.children.4.name', 'Contact Us')
                ->has('data.homePage.visible_blocks', 13)
                ->has('data.homePage.visible_blocks.0.content.slides', 8)
                ->where('data.homePage.visible_blocks.1.content.items.0.value', '23,000+')
                ->has('data.homePage.visible_blocks.3.content.items', 3)
                ->has('data.homePage.visible_blocks.6.content.items', 3)
                ->has('data.homePage.visible_blocks.7.content.items', 3)
                ->has('data.homePage.visible_blocks.8.content.items', 3)
                ->where('data.homePage.visible_blocks.1.content.items.3.value', '400+')
            );

        $this->get('/about-us')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('data.about_us.visible_blocks', 7)
            ->where('data.about_us.visible_blocks.0.type', 'cards')
            ->where('data.about_us.visible_blocks.0.content.variant', 'about-pillars')
            ->where('data.about_us.visible_blocks.0.content.items.0.eyebrow', 'Our mission')
            ->where('data.about_us.visible_blocks.0.content.items.1.eyebrow', 'Our vision')
            ->has('data.about_us.visible_blocks.0.content.items', 2)
            ->has('data.about_us.visible_blocks.3.content.items', 4)
            ->has('data.about_us.visible_blocks.4.content.items', 7)
            ->where('data.about_us.visible_blocks.5.type', 'partners')
            ->where('data.about_us.visible_blocks.5.content.eyebrow', '')
            ->where('data.about_us.visible_blocks.5.content.heading', 'Partner Organizations')
            ->where('data.about_us.visible_blocks.5.content.body', '')
            ->has('data.about_us.visible_blocks.5.content.items', 20)
            ->where('data.about_us.visible_blocks.5.content.items.0.heading', 'Bangladesh Brand Forum')
            ->where('data.about_us.visible_blocks.5.content.items.19.heading', 'What’s On Guide')
        );

        $this->get('/page/education')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('data.page.visible_blocks', 4)
            ->has('data.page.visible_blocks.1.content.items', 4)
        );

        $this->get('/category/visit-ignite-school')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.category.slug', 'visit-ignite-school')
            ->where('data.category.display_mode', 'landing_page')
            ->where('data.landing_page.slug', 'ignite-school-bawnia-campus')
            ->has('data.landing_page.visible_blocks', 7)
            ->where('data.landing_page.visible_blocks.0.type', 'hero')
            ->where('data.landing_page.visible_blocks.1.content.variant', 'campus-intro')
            ->where('data.landing_page.visible_blocks.1.content.body', '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports <strong>nearly 120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>')
            ->where('data.landing_page.visible_blocks.2.content.variant', 'campus-stats')
            ->has('data.landing_page.visible_blocks.2.content.items', 3)
            ->where('data.landing_page.visible_blocks.2.content.items.0.value', 'Nearly 120')
            ->where('data.landing_page.visible_blocks.2.content.items.0.label', 'Learners supported')
            ->where('data.landing_page.visible_blocks.3.content.variant', 'initiatives')
            ->has('data.landing_page.visible_blocks.3.content.items', 6)
            ->where('data.landing_page.visible_blocks.4.content.variant', 'contributions')
            ->has('data.landing_page.visible_blocks.4.content.items', 5)
            ->where('data.landing_page.visible_blocks.5.content.variant', 'campus-actions')
            ->where('data.landing_page.visible_blocks.6.content.variant', 'campus-gallery')
            ->has('data.items', 1)
        );
        $this->get('/page/ignite-school-bawnia-campus')
            ->assertStatus(301)
            ->assertRedirect(route('frontend.category', ['slug' => 'visit-ignite-school']));

        foreach ([
            '/page/youth-development',
            '/page/disaster-response-and-resilience',
            '/page/refund-policy',
            '/category/career',
            '/category/awards-&-recognition',
            '/projects/current-project',
            '/projects/completed-project',
            '/events',
            '/annual-report',
            '/gallery',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/annual-report/download/ignite-foundation-annual-report-2024')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $header = file_get_contents(resource_path('js/layouts/AppHeader.vue'));
        $navigation = file_get_contents(resource_path('js/layouts/AppNav.vue'));
        $footer = file_get_contents(resource_path('js/layouts/AppFooter.vue'));
        $blocks = file_get_contents(resource_path('js/Shared/PageBlocks.vue'));
        $this->assertStringContainsString('aria-label="YouTube"', $header);
        $this->assertStringNotContainsString('social.tiktok', $header);
        $this->assertStringContainsString('header.sponsorLabel', $navigation);
        $this->assertStringContainsString('desktop-nav__trigger', $navigation);
        $this->assertStringContainsString('mobile-nav__submenu', $navigation);
        $this->assertStringContainsString(':aria-expanded="openDesktop === index"', $navigation);
        $this->assertStringContainsString('aria-label="YouTube"', $footer);
        $this->assertStringNotContainsString('social.tiktok', $footer);
        $this->assertStringContainsString('branding.tagline', $footer);
        $this->assertStringContainsString('footer.newsletterTitle', $footer);
        $this->assertStringContainsString("router.post(route('frontend.subscribe')", $footer);
        $this->assertStringContainsString("block.type === 'testimonials'", $blocks);
        $this->assertStringContainsString("block.type === 'events'", $blocks);
        $this->assertStringContainsString("block.type === 'causes'", $blocks);
        foreach (['team', 'partners', 'faq', 'timeline', 'gallery', 'video'] as $type) {
            $this->assertStringContainsString("block.type === '{$type}'", $blocks);
            $this->assertArrayHasKey($type, config('page-builder.simple_sections'));
        }
    }
}
