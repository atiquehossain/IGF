<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DonationType;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\SeoRedirect;
use App\Services\SeoMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalSeoIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_robots_endpoint_is_not_shadowed_by_a_public_static_file(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap:');
    }

    public function test_route_metadata_supports_social_robots_canonical_and_schema_fields(): void
    {
        $service = app(SeoMetadataService::class);
        $service->updateForRoute('frontend.home', '/', 'en', [
            'title' => 'Ignite Global Foundation | Community-led change',
            'description' => 'Partner with communities creating lasting change.',
            'focus_keyword' => 'community-led development',
            'canonical_url' => 'https://ignite.test/',
            'robots_index' => true,
            'robots_follow' => false,
            'og_title' => 'Ignite change together',
            'twitter_card' => 'summary_large_image',
            'schema_markup' => json_encode(['@context' => 'https://schema.org', '@type' => 'NGO'], JSON_THROW_ON_ERROR),
            'sitemap_priority' => 1,
            'sitemap_change_frequency' => 'daily',
            'exclude_from_sitemap' => false,
        ]);

        $meta = $service->metaForRoute('frontend.home', 'en');

        $this->assertSame('Ignite Global Foundation | Community-led change', $meta['meta_title']);
        $this->assertSame('index,nofollow', $meta['robots']);
        $this->assertSame('Ignite change together', $meta['og_title']);
        $this->assertSame('NGO', $meta['schema_markup']['@type']);
    }

    public function test_active_redirect_runs_before_the_fallback_and_tracks_hits(): void
    {
        $redirect = SeoRedirect::create([
            'from_path' => '/old-campaign',
            'to_url' => '/page/current-campaign',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/old-campaign')
            ->assertStatus(301)
            ->assertRedirect('/page/current-campaign');

        $this->assertSame(1, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_sitemap_includes_published_pages_and_honors_seo_exclusion(): void
    {
        $included = $this->makePage('included-story');
        $excluded = $this->makePage('excluded-story');

        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $excluded->id,
            'locale' => 'en',
            'exclude_from_sitemap' => true,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/page/' . $included->slug, false);
        $response->assertDontSee('/page/' . $excluded->slug, false);
    }

    public function test_sitemap_includes_every_active_operational_donation_cause_and_honors_cause_seo_exclusion(): void
    {
        DonationType::query()->forceDelete();
        $education = DonationType::create([
            'name' => 'Education',
            'description' => 'Reviewed education support.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Education Fund',
            'status' => 1,
        ]);
        $relief = DonationType::create([
            'name' => 'Emergency Relief',
            'description' => 'Reviewed emergency support.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Emergency Relief Fund',
            'status' => 1,
        ]);
        $excluded = DonationType::create([
            'name' => 'Private Campaign',
            'description' => 'Reviewed private campaign.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Private Campaign Fund',
            'status' => 1,
        ]);
        $draft = DonationType::create([
            'name' => 'Draft Cause',
            'description' => 'Not yet public.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Draft Fund',
            'status' => 0,
        ]);
        $broken = DonationType::create([
            'name' => 'Broken Project Cause',
            'description' => 'Linked to a missing project.',
            'destination_type' => 'page',
            'destination_page_uuid' => (string) Str::uuid(),
            'status' => 1,
        ]);
        SeoMetadata::create([
            'seoable_type' => DonationType::class,
            'seoable_id' => $education->id,
            'locale' => 'en',
            'title' => 'Education giving in Bangladesh',
            'description' => 'Support education through an accountable community donation.',
            'canonical_url' => route('frontend.donate.cause', ['cause' => $education->slug]),
        ]);
        SeoMetadata::create([
            'seoable_type' => DonationType::class,
            'seoable_id' => $excluded->id,
            'locale' => 'en',
            'exclude_from_sitemap' => true,
        ]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>' . route('frontend.donate.cause', ['cause' => $education->slug]) . '</loc>', $content);
        $this->assertStringContainsString('<loc>' . route('frontend.donate.cause', ['cause' => $relief->slug]) . '</loc>', $content);
        $this->assertStringNotContainsString('/donate/' . $excluded->slug, $content);
        $this->assertStringNotContainsString('/donate/' . $draft->slug, $content);
        $this->assertStringNotContainsString('/donate/' . $broken->slug, $content);
    }

    public function test_robots_file_points_crawlers_to_the_sitemap_and_blocks_admin(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('/sitemap.xml', false);
    }

    public function test_specialized_pages_have_one_canonical_public_url(): void
    {
        $about = $this->makePage('about-us');
        $zakat = $this->makePage('zakat');

        $this->get('/page/about-us')->assertRedirect('/about-us');
        $this->get('/page/zakat')->assertRedirect('/zakat');

        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $sitemap->assertSee(url('/about-us'), false);
        $sitemap->assertSee(url('/zakat'), false);
        $sitemap->assertDontSee(url('/page/about-us'), false);
        $sitemap->assertDontSee(url('/page/zakat'), false);

        $this->assertNotNull($about);
        $this->assertNotNull($zakat);
    }

    public function test_home_page_has_one_canonical_url_and_old_page_path_redirects(): void
    {
        $this->makePage('home');

        $this->get('/page/home')->assertRedirect('/');
        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<loc>' . url('/') . '</loc>'));
        $this->assertStringNotContainsString('/page/home', $content);
    }

    public function test_dynamic_page_metadata_cannot_be_overridden_by_one_route_record(): void
    {
        $page = $this->makePage('item-specific-page');
        $page->update([
            'meta_title' => 'Item-specific title',
            'meta_description' => 'Item-specific description.',
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.page',
            'route_path' => '/page/{slug?}',
            'locale' => 'en',
            'title' => 'Wrong global page title',
            'description' => 'Wrong global page description.',
        ]);

        $response = $this->get('/page/item-specific-page')->assertOk();
        $response->assertSee('<title inertia>Item-specific title</title>', false);
        $response->assertSee('content="Item-specific description."', false);
        $response->assertSee('rel="canonical" href="' . url('/page/item-specific-page') . '"', false);
        $response->assertDontSee('Wrong global page title', false);
    }

    public function test_public_content_links_back_to_its_real_category_hub(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community projects',
            'slug' => 'community-projects',
            'status' => 1,
            'language' => 'en',
        ]);
        $project = $this->makePage('project-alpha');
        $project->update(['category_id' => $category->id]);

        $this->get('/page/project-alpha')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.page.category_name', 'Community projects')
            ->where('data.page.category_url', route('frontend.category', ['slug' => 'community-projects']))
        );

        $giving = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Giving',
            'slug' => 'giving',
            'status' => 1,
            'language' => 'en',
        ]);
        $zakat = $this->makePage('zakat');
        $zakat->update(['category_id' => $giving->id]);

        $this->get('/zakat')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.category.name', 'Giving')
            ->where('data.category.url', route('frontend.category', ['slug' => 'giving']))
        );
    }

    public function test_primary_public_routes_have_default_canonicals_and_sitemap_entries(): void
    {
        foreach (['/contact-us', '/gallery', '/sponsor-child', '/events', '/careers', '/workshops', '/volunteer/register', '/donate', '/annual-report'] as $path) {
            $this->get($path)->assertOk()->assertSee('rel="canonical" href="' . url($path) . '"', false);
        }

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (['/contact-us', '/gallery', '/events', '/careers', '/workshops', '/volunteer/register', '/donate', '/annual-report'] as $path) {
            $this->assertStringContainsString('<loc>' . url($path) . '</loc>', $sitemap);
        }
        // Sponsor is configured as Page-backed. Rendering its safe fallback is
        // allowed, but it must not be advertised until its published Page
        // record actually exists in this language.
        $this->assertStringNotContainsString('<loc>' . url('/sponsor-child') . '</loc>', $sitemap);
    }

    public function test_legacy_career_category_is_a_permanent_alias_and_never_a_second_sitemap_url(): void
    {
        Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Careers',
            'slug' => 'career',
            'description' => 'Legacy career category content retained for editors.',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->get('/category/career')
            ->assertStatus(301)
            ->assertRedirect('/careers');
        $this->get('/category/career?lang=bn')
            ->assertStatus(301)
            ->assertRedirect('/careers?lang=bn');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertSame(1, substr_count($sitemap, '<loc>' . url('/careers') . '</loc>'));
        $this->assertStringNotContainsString('/category/career', $sitemap);
    }

    private function makePage(string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'A public impact story.',
            'slug' => $slug,
            'status' => 1,
            'language' => 'en',
            'published_at' => now()->subDay(),
        ]);
    }
}
