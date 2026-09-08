<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\Album;
use App\Models\Category;
use App\Models\District;
use App\Models\Division;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageMenu;
use App\Models\SeoMetadata;
use App\Models\TeamGroup;
use App\Models\Testimonial;
use Database\Seeders\IgniteParityContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IgniteParityContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bangla_heroes_navigation_is_seeded_once_and_then_remains_admin_managed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Album::create([
            'uuid' => '65000000-0000-4000-8000-000000000001',
            'name' => 'কমিউনিটি কর্মসূচি',
            'language' => 'bn',
            'status' => 1,
        ]);

        PageMenu::create([
            'uuid' => '67000000-0000-4000-8000-000000000002',
            'name' => 'আমাদের সম্পর্কে',
            'type' => 'main',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'bn',
            'order_by' => 1,
            'status' => 1,
        ]);

        $this->seed(IgniteParityContentSeeder::class);
        $menu = PageMenu::query()
            ->where('uuid', '68000000-0002-4000-8000-000000000007')
            ->where('language', 'bn')
            ->firstOrFail();
        $this->assertSame('পরিবর্তনের নায়কেরা', $menu->name);
        $this->assertSame('frontend.heroes.index', $menu->link);
        $this->assertDatabaseHas('albums', [
            'uuid' => '65000000-0000-4000-8000-000000000001',
            'language' => 'bn',
            'name' => 'কমিউনিটি কর্মসূচি',
        ]);

        $menu->update(['name' => 'আমাদের নায়কেরা', 'order_by' => 2]);
        $this->seed(IgniteParityContentSeeder::class);
        $this->assertSame(1, PageMenu::withTrashed()
            ->where('uuid', $menu->uuid)
            ->where('language', 'bn')
            ->count());
        $this->assertSame('আমাদের নায়কেরা', $menu->fresh()->name);
        $this->assertSame(2, $menu->fresh()->order_by);

        $menu->delete();
        $this->seed(IgniteParityContentSeeder::class);
        $this->assertTrue($menu->fresh()->trashed());
    }

    public function test_reference_content_navigation_and_dynamic_sections_are_complete(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $this->assertSame(14, NoticeBoard::where('status', 1)->count());
        $this->assertSame(3, Testimonial::where('status', 1)->count());
        $this->assertSame(2, AnnualReport::where('status', 1)->count());
        $this->assertSame(16, Gallery::where('status', 1)->count());
        $this->assertSame(18, LatestNews::where('type', 'our-members')->where('status', 1)->count());
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
        $ourWorkMenu = PageMenu::where('uuid', '67000000-0000-4000-8000-000000000003')->firstOrFail();
        $educationMenu = PageMenu::where('uuid', '68000000-0003-4000-8000-000000000002')->firstOrFail();
        $visitSchoolMenu = PageMenu::where('uuid', '68000000-0003-4000-8000-000000000003')->firstOrFail();
        $youthDevelopmentMenu = PageMenu::where('uuid', '68000000-0003-4000-8000-000000000004')->firstOrFail();
        $workshopMenu = PageMenu::where('link', 'frontend.workshops.index')->firstOrFail();
        $storiesMenu = PageMenu::where('uuid', '67000000-0000-4000-8000-000000000005')->firstOrFail();
        $heroesMenu = PageMenu::query()
            ->where('uuid', '68000000-0002-4000-8000-000000000007')
            ->where('language', 'en')
            ->firstOrFail();
        $aboutMenu = PageMenu::query()
            ->where('uuid', '67000000-0000-4000-8000-000000000002')
            ->where('language', 'en')
            ->firstOrFail();
        $directDonationMenu = PageMenu::where('uuid', '68000000-0006-4000-8000-000000000001')->firstOrFail();
        $this->assertSame($ourWorkMenu->id, $educationMenu->parent_id);
        $this->assertSame($educationMenu->id, $visitSchoolMenu->parent_id);
        $this->assertSame(0, $visitSchoolMenu->order_by);
        $this->assertSame($ourWorkMenu->id, $youthDevelopmentMenu->parent_id);
        $this->assertSame($youthDevelopmentMenu->id, $workshopMenu->parent_id);
        $this->assertSame('68000000-0304-4000-8000-000000000001', $workshopMenu->uuid);
        $this->assertSame('Workshop', $workshopMenu->name);
        $this->assertSame(0, $workshopMenu->order_by);
        $this->assertSame('Stories', $storiesMenu->name);
        $this->assertSame($aboutMenu->id, $heroesMenu->parent_id);
        $this->assertSame('Meet the Heroes', $heroesMenu->name);
        $this->assertSame('frontend.heroes.index', $heroesMenu->link);
        $this->assertSame(5, $heroesMenu->order_by);
        $this->assertSame('frontend.donate.direct', $directDonationMenu->link);
        $this->assertNull($directDonationMenu->slug);

        $demoGroup = TeamGroup::query()->where('slug', 'regional-heroes-demo')->firstOrFail();
        $regionalDemoMembers = LatestNews::query()
            ->where('type', 'our-members')
            ->where('team_group_id', $demoGroup->id)
            ->whereNotNull('division_id')
            ->whereNotNull('district_id')
            ->get();
        $this->assertCount(10, $regionalDemoMembers);
        $this->assertSame(8, $regionalDemoMembers->pluck('division_id')->unique()->count());
        $this->assertSame(10, $regionalDemoMembers->pluck('district_id')->unique()->count());
        $this->assertTrue($regionalDemoMembers->every(fn (LatestNews $member): bool =>
            str_ends_with($member->name, '(Demo)')
            && blank($member->qualification)
            && blank($member->biography)
            && filled($member->image)
            && filled($member->path)
            && count($member->social_links ?? []) === 2
        ));
        $nationalDemoMember = LatestNews::query()
            ->where('team_group_id', $demoGroup->id)
            ->where('name', 'Samira Chowdhury (Demo)')
            ->whereNull('division_id')
            ->whereNull('district_id')
            ->firstOrFail();
        $this->assertNotEmpty($nationalDemoMember->image);
        $this->assertNotEmpty($nationalDemoMember->path);
        $this->assertStringContainsString("\n\n", (string) $nationalDemoMember->biography);
        $this->assertCount(2, $nationalDemoMember->social_links ?? []);
        $rangpurDivision = Division::query()->where('slug', 'rangpur')->firstOrFail();
        $this->assertSame(3, $regionalDemoMembers->where('division_id', $rangpurDivision->id)->count());
        $this->assertSame(3, $regionalDemoMembers->where('division_id', $rangpurDivision->id)
            ->filter(fn (LatestNews $member): bool => filled($member->image))->count());
        $this->assertSame(8, Division::query()->whereNotNull('description')->count());
        $this->assertSame(10, District::query()->whereNotNull('hero_image')->count());
        $this->assertSame(10, District::query()
            ->where('hero_image', '/storage/media/ignite-live/dms2sp0pfxgane9lzjpco3enlkyd4xjeygndfbym-24b5036254cd.jpg')
            ->count());
        $this->assertSame(1, NoticeBoard::query()
            ->where('slug', 'light-in-their-hands')
            ->where('image_path', '/storage/media/ignite-live/y3oylo6tegbtxwlpcbsromnlhmn1gdlwtehqu9sl-30d4e0add7c6.jpg')
            ->count());
        $this->assertSame(8, NoticeBoard::query()->where('title', 'like', '%(Demo)')->count());
        $rangpurActivities = NoticeBoard::query()
            ->where('division_id', $rangpurDivision->id)
            ->where('title', 'like', '%(Demo)')
            ->get();
        $this->assertCount(4, $rangpurActivities);
        $this->assertSame(3, $rangpurActivities->whereNotNull('district_id')->count());
        $this->assertSame(4, $rangpurActivities->pluck('published_at')->unique()->count());

        $heroesBlock = PageBlock::query()
            ->where('uuid', '69000000-0000-4000-8000-00000000d001')
            ->firstOrFail();
        $this->assertSame('heroes_showcase', $heroesBlock->content['team_presentation'] ?? null);
        $this->assertSame('manual', $heroesBlock->content['selection_mode'] ?? null);
        $this->assertCount(10, $heroesBlock->content['selected_items'] ?? []);

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/home')
                ->has('appMenus', 6)
                ->where('appMenus.4.name', 'Stories')
                ->has('appMenus.1.children', 6)
                ->where('appMenus.1.children.0.name', 'Who We Are')
                ->where('appMenus.1.children.1.name', 'Awards & Recognition')
                ->where('appMenus.1.children.2.name', 'Photo Gallery')
                ->where('appMenus.1.children.3.name', 'Annual Reports')
                ->where('appMenus.1.children.4.name', 'Contact Us')
                ->where('appMenus.1.children.5.name', 'Meet the Heroes')
                ->where('appMenus.1.children.5.link', 'frontend.heroes.index')
                ->has('appMenus.2.children.1.children', 1)
                ->where('appMenus.2.children.1.name', 'Inclusive Education')
                ->where('appMenus.2.children.1.children.0.name', 'Visit Ignite School')
                ->where('appMenus.2.children.1.children.0.link', 'frontend.category')
                ->where('appMenus.2.children.1.children.0.slug', 'visit-ignite-school')
                ->has('appMenus.2.children.2.children', 1)
                ->where('appMenus.2.children.2.name', 'Youth Development')
                ->where('appMenus.2.children.2.children.0.name', 'Workshop')
                ->where('appMenus.2.children.2.children.0.link', 'frontend.workshops.index')
                ->has('data.homePage.visible_blocks', 13)
                ->has('data.homePage.visible_blocks.0.content.slides', 8)
                ->where('data.homePage.visible_blocks.1.content.items.0.value', '23,000+')
                ->has('data.homePage.visible_blocks.3.content.items', 3)
                ->where('data.homePage.visible_blocks.6.type', 'events_news')
                ->has('data.homePage.visible_blocks.6.content.upcoming_events', 0)
                ->where('data.homePage.visible_blocks.6.content.featured_news.heading', 'Together for Their Tomorrow')
                ->has('data.homePage.visible_blocks.7.content.items', 3)
                ->has('data.homePage.visible_blocks.8.content.items', 3)
                ->where('data.homePage.visible_blocks.1.content.items.3.value', '400+')
            );

        $this->get('/about-us')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('data.about_us.visible_blocks', 8)
            ->where('data.about_us.visible_blocks.0.type', 'cards')
            ->where('data.about_us.visible_blocks.0.content.variant', 'about-pillars')
            ->where('data.about_us.visible_blocks.0.content.items.0.eyebrow', 'Our mission')
            ->where('data.about_us.visible_blocks.0.content.items.0.url', '/page/our-mission')
            ->where('data.about_us.visible_blocks.0.content.items.0.link_label', 'Read more')
            ->where('data.about_us.visible_blocks.0.content.items.1.eyebrow', 'Our vision')
            ->where('data.about_us.visible_blocks.0.content.items.1.url', '/page/our-vision')
            ->where('data.about_us.visible_blocks.0.content.items.1.link_label', 'Read more')
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
            ->where('data.about_us.visible_blocks.7.type', 'team')
            ->where('data.about_us.visible_blocks.7.content.team_presentation', 'heroes_showcase')
            ->has('data.about_us.visible_blocks.7.content.items', 10)
        );

        $this->get('/page/our-mission')->assertOk();
        $this->get('/page/our-vision')->assertOk();

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
            '/category/awards-&-recognition',
            '/projects/current-project',
            '/projects/completed-project',
            '/events',
            '/annual-report',
            '/gallery',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
        $this->get('/category/career')
            ->assertStatus(301)
            ->assertRedirect('/careers');

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
        $this->assertStringContainsString("'aria-expanded': String(expanded)", $navigation);
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

    public function test_navigation_seed_preserves_an_edited_or_deleted_workshop_menu(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $workshop = PageMenu::where('link', 'frontend.workshops.index')->firstOrFail();
        $originalId = $workshop->id;
        $workshop->update([
            'name' => 'Community learning sessions',
            'description' => 'An editor-managed navigation label.',
            'order_by' => 37,
            'status' => 0,
        ]);
        $opportunities = PageMenu::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Opportunities',
            'type' => 'main',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'en',
            'order_by' => 90,
            'status' => 1,
        ]);
        $career = PageMenu::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'parent_id' => $opportunities->id,
            'name' => 'Fellowships',
            'type' => 'main',
            'link' => 'custom',
            'slug' => '/fellowships',
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
        ]);

        $this->seed(IgniteParityContentSeeder::class);

        $workshop = PageMenu::findOrFail($originalId);
        $youthDevelopment = PageMenu::where('uuid', '68000000-0003-4000-8000-000000000004')->firstOrFail();
        $this->assertSame('Community learning sessions', $workshop->name);
        $this->assertSame('An editor-managed navigation label.', $workshop->description);
        $this->assertSame(37, $workshop->order_by);
        $this->assertSame(0, $workshop->status);
        $this->assertSame($youthDevelopment->id, $workshop->parent_id);
        $this->assertDatabaseHas('page_menus', ['id' => $opportunities->id, 'status' => 1]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $career->id,
            'parent_id' => $opportunities->id,
            'status' => 1,
        ]);

        $workshop->delete();
        $this->seed(IgniteParityContentSeeder::class);

        $this->assertSoftDeleted('page_menus', ['id' => $originalId]);
        $this->assertSame(0, PageMenu::where('link', 'frontend.workshops.index')->count());
        $this->assertDatabaseHas('page_menus', ['id' => $opportunities->id, 'status' => 1]);
        $this->assertDatabaseHas('page_menus', ['id' => $career->id, 'status' => 1]);
    }
}
