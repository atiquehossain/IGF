<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoPublicAuthorityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_curated_static_route_seo_overrides_the_controller_fallback_in_raw_html(): void
    {
        SeoMetadata::create([
            'route_name' => 'frontend.sponsor_child',
            'route_path' => '/sponsor-child',
            'locale' => 'en',
            'title' => 'Curated sponsor search title',
            'description' => 'Curated sponsor search description.',
            'canonical_url' => url('/sponsor-child'),
            'robots_index' => false,
            'robots_follow' => true,
        ]);

        $response = $this->get('/sponsor-child')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('<title inertia>Curated sponsor search title</title>', $head);
        $this->assertStringContainsString('content="Curated sponsor search description."', $head);
        $this->assertStringContainsString('name="robots" content="noindex,follow"', $head);
        $this->assertStringNotContainsString('<title inertia>Sponsor a Child | Ignite Global Foundation</title>', $head);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.meta_title', 'Sponsor a Child | Ignite Global Foundation')
            ->where('routeSeo.meta_title', 'Curated sponsor search title')
            ->where('contentSeo', [])
        );
    }

    public function test_curated_fixed_parameter_route_seo_overrides_donate_controller_metadata_in_raw_and_inertia_heads(): void
    {
        SeoMetadata::create([
            'route_name' => 'frontend.donate.cause',
            'route_path' => '/donate/zakat',
            'locale' => 'en',
            'title' => 'Give Zakat through Ignite',
            'description' => 'A curated Zakat donation search description.',
            'canonical_url' => url('/donate/zakat'),
            'robots_index' => false,
            'robots_follow' => true,
        ]);

        $response = $this->get('/donate/zakat')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('<title inertia>Give Zakat through Ignite</title>', $head);
        $this->assertStringContainsString('content="A curated Zakat donation search description."', $head);
        $this->assertStringContainsString('name="robots" content="noindex,follow"', $head);
        $this->assertStringNotContainsString('<title inertia>Donate Securely | Ignite Global Foundation</title>', $head);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.meta_title', 'Donate Securely | Ignite Global Foundation')
            ->where('routeSeo.meta_title', 'Give Zakat through Ignite')
            ->where('routeSeo.meta_description', 'A curated Zakat donation search description.')
            ->where('routeSeo.canonical_url', url('/donate/zakat'))
        );
    }

    public function test_curated_listing_seo_is_not_applied_to_an_arbitrary_parameterized_project_path(): void
    {
        Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Education',
            'slug' => 'education',
            'description' => 'Education project information.',
            'status' => 1,
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.project',
            'route_path' => '/projects',
            'locale' => 'en',
            'title' => 'Projects listing SEO must stay on the listing',
            'description' => 'Listing-only search description.',
            'canonical_url' => url('/projects'),
            'robots_index' => false,
            'robots_follow' => false,
        ]);

        $response = $this->get('/projects/education')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('<title inertia>Education | Ignite Global Foundation</title>', $head);
        $this->assertStringNotContainsString('Projects listing SEO must stay on the listing', $head);
        $this->assertStringNotContainsString('Listing-only search description.', $head);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.meta_title', 'Education | Ignite Global Foundation')
            ->where('routeSeo', [])
        );
    }

    public function test_page_owned_home_seo_remains_authoritative_over_a_stale_route_record(): void
    {
        $home = $this->makePage('home');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $home->id,
            'locale' => 'en',
            'title' => 'Page-owned home title',
            'description' => 'Page-owned home description.',
            'canonical_url' => url('/page-owned-home'),
            'robots_index' => true,
            'robots_follow' => true,
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.home',
            'route_path' => '/',
            'locale' => 'en',
            'title' => 'Stale route home title',
            'description' => 'Stale route home description.',
            'canonical_url' => url('/stale-route-home'),
            'robots_index' => false,
            'robots_follow' => false,
        ]);

        $response = $this->get('/')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('<title inertia>Page-owned home title</title>', $head);
        $this->assertStringContainsString('name="robots" content="index,follow"', $head);
        $this->assertStringContainsString('href="' . url('/page-owned-home') . '"', $head);
        $this->assertStringNotContainsString('Stale route home title', $head);
        $this->assertStringNotContainsString('stale-route-home', $head);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('routeSeo.meta_title', 'Stale route home title')
            ->where('contentSeo.meta_title', 'Page-owned home title')
            ->where('contentSeo.canonical_url', url('/page-owned-home'))
        );
    }

    public function test_sponsor_page_emits_the_complete_page_owned_seo_pack(): void
    {
        $sponsor = $this->makePage('sponsor-a-child');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $sponsor->id,
            'locale' => 'en',
            'title' => 'Sponsor page SEO title',
            'description' => 'Sponsor page SEO description.',
            'canonical_url' => url('/sponsor-child'),
            'robots_index' => true,
            'robots_follow' => false,
            'og_title' => 'Sponsor social title',
            'og_description' => 'Sponsor social description.',
            'og_image' => url('/images/sponsor-social.jpg'),
            'twitter_card' => 'summary_large_image',
            'twitter_title' => 'Sponsor X title',
            'twitter_description' => 'Sponsor X description.',
            'twitter_image' => url('/images/sponsor-x.jpg'),
            'schema_markup' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Sponsor a child',
            ],
        ]);

        $response = $this->get('/sponsor-child')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('<title inertia>Sponsor page SEO title</title>', $head);
        $this->assertStringContainsString('property="og:title" content="Sponsor social title"', $head);
        $this->assertStringContainsString('name="twitter:title" content="Sponsor X title"', $head);
        $this->assertStringContainsString('name="robots" content="index,nofollow"', $head);
        $this->assertStringContainsString('"@type":"WebPage"', $head);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('contentSeo.meta_title', 'Sponsor page SEO title')
            ->where('contentSeo.og_title', 'Sponsor social title')
            ->where('contentSeo.twitter_title', 'Sponsor X title')
            ->where('contentSeo.schema_markup.@type', 'WebPage')
        );
    }

    public function test_unlisted_page_backed_routes_are_always_noindex(): void
    {
        foreach (['home', 'about-us', 'zakat', 'sponsor-a-child'] as $slug) {
            $page = $this->makePage($slug, 'unlisted');
            SeoMetadata::create([
                'seoable_type' => Page::class,
                'seoable_id' => $page->id,
                'locale' => 'en',
                'title' => Str::headline($slug) . ' index request',
                'robots_index' => true,
                'robots_follow' => true,
            ]);
        }

        foreach (['/', '/about-us', '/zakat', '/sponsor-child'] as $path) {
            $response = $this->get($path)->assertOk();
            $head = Str::before($response->getContent(), '</head>');

            $this->assertStringContainsString('name="robots" content="noindex,nofollow"', $head, $path);
            $response->assertInertia(fn (Assert $page) => $page
                ->where('contentSeo.robots', 'noindex,nofollow')
            );
        }
    }

    public function test_raw_and_hydrated_heads_are_wired_to_the_same_explicit_authority_contract(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));
        $app = file_get_contents(resource_path('js/layouts/App.vue'));
        $resolver = file_get_contents(resource_path('js/Shared/seoMetadata.js'));

        $bladeFallback = strpos($blade, "data_get(\$pageProps, 'meta_tag'");
        $bladeRoute = strpos($blade, "data_get(\$pageProps, 'routeSeo'");
        $bladeContent = strpos($blade, "data_get(\$pageProps, 'contentSeo'");

        $this->assertIsInt($bladeFallback);
        $this->assertIsInt($bladeRoute);
        $this->assertIsInt($bladeContent);
        $this->assertLessThan($bladeRoute, $bladeFallback);
        $this->assertLessThan($bladeContent, $bladeRoute);
        $this->assertStringNotContainsString('(array) ($meta ?? [])', $blade);

        $this->assertStringContainsString("from '../Shared/seoMetadata';", $app);
        $this->assertStringContainsString('resolveSeoMetadata', $app);
        $this->assertStringContainsString('const merged = resolveSeoMetadata({', $app);
        $this->assertStringContainsString('contentSeo: inertiaPage.props?.contentSeo', $app);

        $resolverFallback = strpos($resolver, '...(metaTag ?? {})');
        $resolverRoute = strpos($resolver, '...(routeSeo ?? {})');
        $resolverContent = strpos($resolver, '...(contentSeo ?? {})');
        $this->assertIsInt($resolverFallback);
        $this->assertIsInt($resolverRoute);
        $this->assertIsInt($resolverContent);
        $this->assertLessThan($resolverRoute, $resolverFallback);
        $this->assertLessThan($resolverContent, $resolverRoute);
    }

    private function makePage(string $slug, string $visibility = 'public'): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'Public page content.',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => $visibility,
            'language' => 'en',
            'published_at' => now()->subDay(),
        ]);
    }
}
