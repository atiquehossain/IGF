<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\TranslationLocale;
use App\Services\SeoRouteRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoPublicOutputIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'production', 'seo.robots.indexing_enabled' => true]);
    }

    public function test_initial_head_is_single_managed_safe_and_uses_the_same_route_precedence_as_navigation(): void
    {
        $home = $this->makePage('home', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $home->id,
            'locale' => 'en',
            'title' => 'Page-level title',
            'description' => 'Page-level description.',
            'canonical_url' => url('/page-authoritative-home'),
            'robots_index' => true,
            'robots_follow' => true,
            'schema_markup' => ['@context' => 'https://schema.org', 'name' => '</script><script>alert(1)</script>'],
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.home',
            'route_path' => '/',
            'locale' => 'en',
            'title' => 'Route-level title',
            'description' => 'Route-level description.',
            'canonical_url' => url('/stale-route-home'),
            'robots_index' => true,
            'robots_follow' => false,
        ]);

        $content = $this->get('/')->assertOk()->getContent();
        $head = Str::before($content, '</head>');

        $this->assertStringContainsString('<title inertia>Page-level title</title>', $head);
        $this->assertStringContainsString('inertia="description" name="description" content="Page-level description."', $head);
        $this->assertStringContainsString('inertia="robots" name="robots" content="index,follow"', $head);
        $this->assertStringContainsString('rel="canonical" href="' . url('/page-authoritative-home') . '"', $head);
        $this->assertStringNotContainsString('stale-route-home', $head);
        $this->assertStringNotContainsString('<meta name="keywords"', $head);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $head);

        foreach ([
            'name="description"',
            'name="robots"',
            'rel="canonical"',
            'property="og:title"',
            'property="og:type"',
            'property="og:url"',
            'property="og:description"',
            'property="og:site_name"',
            'name="twitter:card"',
            'name="twitter:title"',
            'name="twitter:description"',
            'type="application/ld+json"',
        ] as $signature) {
            $this->assertSame(1, substr_count($head, $signature), $signature . ' must occur exactly once in initial HTML.');
        }
    }

    public function test_dynamic_item_metadata_wins_over_a_legacy_parameterized_route_record(): void
    {
        $page = $this->makePage('item-authority', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
            'title' => 'Item authority title',
            'canonical_url' => url('/page/item-authority'),
            'robots_index' => true,
            'robots_follow' => true,
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.page',
            'route_path' => '/page/{slug?}',
            'locale' => 'en',
            'title' => 'Stale route title',
            'canonical_url' => url('/wrong-route-canonical'),
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $this->get('/page/item-authority')
            ->assertOk()
            ->assertSee('<title inertia>Item authority title</title>', false)
            ->assertSee('rel="canonical" href="' . url('/page/item-authority') . '"', false)
            ->assertDontSee('Stale route title', false)
            ->assertDontSee('wrong-route-canonical', false)
            ->assertInertia(fn ($inertia) => $inertia
                ->where('meta_tag.meta_title', 'Item authority title')
                ->where('meta_tag.canonical_url', url('/page/item-authority'))
                ->where('routeSeo', [])
            );
    }

    public function test_enabled_bangla_has_a_stable_canonical_and_complete_hreflang_cluster(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $englishHome = $this->makePage('home', 'en');
        $banglaHome = $this->makePage('home', 'bn', ['uuid' => $englishHome->uuid]);
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $banglaHome->id,
            'locale' => 'bn',
            'title' => 'ইগনাইট গ্লোবাল ফাউন্ডেশন',
            'robots_index' => true,
            'robots_follow' => true,
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.home',
            'route_path' => '/',
            'locale' => 'bn',
            'title' => 'Stale Bangla route title',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $bangla = $this->get('/?lang=bn')
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertSee('<title inertia>ইগনাইট গ্লোবাল ফাউন্ডেশন</title>', false)
            ->assertSee('rel="canonical" href="' . url('/?lang=bn') . '"', false)
            ->assertSee('hreflang="en" href="' . url('/') . '"', false)
            ->assertSee('hreflang="bn" href="' . url('/?lang=bn') . '"', false)
            ->assertSee('hreflang="x-default" href="' . url('/') . '"', false);

        $bangla->assertInertia(fn ($page) => $page
            ->where('locale', 'bn')
            ->where('seoLocale.current', 'bn')
            ->where('seoLocale.default', 'en')
            ->where('seoLocale.public', ['en', 'bn'])
            ->where('seoAlternates.links', [
                ['locale' => 'en', 'url' => url('/')],
                ['locale' => 'bn', 'url' => url('/?lang=bn')],
            ])
            ->where('seoAlternates.x_default', url('/'))
        );

        $this->get('/?lang=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('rel="canonical" href="' . url('/') . '"', false)
            ->assertDontSee('rel="canonical" href="' . url('/?lang=en') . '"', false);
    }

    public function test_page_hreflang_uses_uuid_translation_identity_and_each_real_slug(): void
    {
        $this->enableBangla();
        $uuid = (string) Str::uuid();
        $this->makePage('english-impact-story', 'en', ['uuid' => $uuid]);
        $this->makePage('bangla-impact-story', 'bn', ['uuid' => $uuid]);

        $englishResponse = $this->get('/page/english-impact-story')->assertOk();
        $englishHead = Str::before($englishResponse->getContent(), '</head>');
        $this->assertStringContainsString(
            'hreflang="bn" href="' . url('/page/bangla-impact-story?lang=bn') . '"',
            $englishHead
        );
        $this->assertStringNotContainsString('href="' . url('/page/english-impact-story?lang=bn') . '"', $englishHead);
        $englishResponse->assertInertia(fn ($page) => $page->where('seoAlternates.links', [
            ['locale' => 'en', 'url' => url('/page/english-impact-story')],
            ['locale' => 'bn', 'url' => url('/page/bangla-impact-story?lang=bn')],
        ]));

        $banglaHead = Str::before($this->get('/page/bangla-impact-story?lang=bn')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString(
            'hreflang="en" href="' . url('/page/english-impact-story') . '"',
            $banglaHead
        );
        $this->assertStringContainsString(
            'hreflang="bn" href="' . url('/page/bangla-impact-story?lang=bn') . '"',
            $banglaHead
        );

        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertSee('<loc>' . url('/page/bangla-impact-story?lang=bn') . '</loc>', false)
            ->assertDontSee('/page/english-impact-story?lang=bn', false);
    }

    public function test_non_page_backed_route_hreflang_is_default_locale_first(): void
    {
        $this->enableBangla();

        $this->get('/contact-us?lang=bn')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seoAlternates.links', [
                    ['locale' => 'en', 'url' => url('/contact-us')],
                    ['locale' => 'bn', 'url' => url('/contact-us?lang=bn')],
                ])
                ->where('seoAlternates.x_default', url('/contact-us'))
            );
    }

    public function test_missing_page_translation_is_not_advertised_as_a_hreflang_or_page_backed_sitemap_route(): void
    {
        $this->enableBangla();
        $home = $this->makePage('home', 'en');
        $this->makePage('english-only-story', 'en');

        $head = Str::before($this->get('/page/english-only-story')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString('hreflang="en" href="' . url('/page/english-only-story') . '"', $head);
        $this->assertStringNotContainsString('hreflang="bn"', $head);

        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertDontSee('<loc>' . url('/?lang=bn') . '</loc>', false);

        $this->makePage('home', 'bn', ['uuid' => $home->uuid]);
        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertSee('<loc>' . url('/?lang=bn') . '</loc>', false);
    }

    public function test_missing_special_page_translation_returns_not_found_instead_of_an_indexable_fallback(): void
    {
        $this->enableBangla();
        $this->makePage('home', 'en');
        $this->makePage('sponsor-a-child', 'en');

        $this->get('/?lang=bn')->assertNotFound();
        $this->get('/sponsor-child?lang=bn')->assertNotFound();
    }

    public function test_category_hreflang_uses_shared_uuid_and_the_translated_slug(): void
    {
        $this->enableBangla();
        $uuid = (string) Str::uuid();
        $this->makeCategory('community-programs', 'en', $uuid);
        $this->makeCategory('bangla-community-programs', 'bn', $uuid);

        $head = Str::before($this->get('/category/community-programs')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString(
            'hreflang="bn" href="' . url('/category/bangla-community-programs?lang=bn') . '"',
            $head
        );
        $this->assertStringNotContainsString('href="' . url('/category/community-programs?lang=bn') . '"', $head);
    }

    public function test_global_tag_has_locale_variants_but_notice_records_are_never_paired_by_slug(): void
    {
        $this->enableBangla();
        Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Education',
            'slug' => 'education',
            'status' => 1,
        ]);
        $tagHead = Str::before($this->get('/projects/education')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString('hreflang="en" href="' . url('/projects/education') . '"', $tagHead);
        $this->assertStringContainsString('hreflang="bn" href="' . url('/projects/education?lang=bn') . '"', $tagHead);

        $this->makeNotice('shared-looking-slug', 'en', 'English event');
        $this->makeNotice('shared-looking-slug', 'bn', 'Bangla event');
        $eventHead = Str::before($this->get('/event/shared-looking-slug')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString('hreflang="en" href="' . url('/event/shared-looking-slug') . '"', $eventHead);
        $this->assertStringNotContainsString('hreflang="bn"', $eventHead);
    }

    public function test_event_hreflang_and_locale_sitemaps_use_deliberate_translation_identity(): void
    {
        $this->enableBangla();
        $translationKey = (string) Str::uuid();
        $this->makeNotice('english-community-day', 'en', 'English community day', $translationKey);
        $this->makeNotice('bangla-community-day', 'bn', 'Bangla community day', $translationKey);

        $englishHead = Str::before($this->get('/event/english-community-day')->assertOk()->getContent(), '</head>');
        $this->assertStringContainsString(
            'hreflang="bn" href="' . url('/event/bangla-community-day?lang=bn') . '"',
            $englishHead
        );

        $this->get('/sitemap-en.xml')
            ->assertOk()
            ->assertSee(url('/event/english-community-day'), false)
            ->assertSee('hreflang="bn" href="' . url('/event/bangla-community-day?lang=bn') . '"', false);
        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertSee(url('/event/bangla-community-day?lang=bn'), false)
            ->assertDontSee('<loc>' . url('/event/english-community-day') . '</loc>', false);
    }

    public function test_sitemap_obeys_noindex_excludes_external_canonicals_and_uses_the_newest_lastmod(): void
    {
        $home = $this->makePage('home', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $home->id,
            'locale' => 'en',
            'robots_index' => false,
            'exclude_from_sitemap' => false,
        ]);

        $hidden = $this->makePage('hidden-from-search', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $hidden->id,
            'locale' => 'en',
            'robots_index' => false,
            'exclude_from_sitemap' => false,
        ]);

        $external = $this->makePage('external-canonical-copy', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $external->id,
            'locale' => 'en',
            'canonical_url' => 'https://outside.example/authoritative-copy',
            'robots_index' => true,
            'exclude_from_sitemap' => false,
        ]);

        $public = $this->makePage('same-origin-canonical', 'en');
        $public->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $seoUpdatedAt = now()->subDay()->startOfSecond();
        $publicSeo = SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $public->id,
            'locale' => 'en',
            'canonical_url' => url('/preferred-local-canonical'),
            'robots_index' => true,
            'exclude_from_sitemap' => false,
        ]);
        $publicSeo->forceFill(['updated_at' => $seoUpdatedAt])->saveQuietly();

        SeoMetadata::create([
            'route_name' => 'seo.robots',
            'route_path' => '/robots.txt',
            'locale' => 'en',
            'canonical_url' => url('/operational-route-must-not-index'),
            'robots_index' => true,
            'exclude_from_sitemap' => false,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('<loc>' . url('/') . '</loc>', $content);
        $this->assertStringNotContainsString('/page/hidden-from-search', $content);
        $this->assertStringNotContainsString('/page/external-canonical-copy', $content);
        $this->assertStringNotContainsString('outside.example', $content);
        $this->assertStringContainsString('<loc>' . url('/preferred-local-canonical') . '</loc>', $content);
        $this->assertStringNotContainsString('operational-route-must-not-index', $content);
        $this->assertStringContainsString('<lastmod>' . $seoUpdatedAt->toAtomString() . '</lastmod>', $content);
        $this->assertStringNotContainsString('<priority>', $content);
        $this->assertStringNotContainsString('<changefreq>', $content);
        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=', (string) $response->headers->get('Cache-Control'));

        $this->withHeader('If-None-Match', $response->headers->get('ETag'))
            ->get('/sitemap.xml')
            ->assertStatus(304);
    }

    public function test_locale_sitemap_index_discovers_every_enabled_language(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->makePage('bangla-story', 'bn');

        $this->get('/sitemap-index.xml')
            ->assertOk()
            ->assertSee(url('/sitemap-en.xml'), false)
            ->assertSee(url('/sitemap-bn.xml'), false);

        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertSee(url('/page/bangla-story?lang=bn'), false);

        $this->get('/sitemap-fr.xml')->assertNotFound();
    }

    public function test_page_backed_route_sitemap_uses_page_authority_before_stale_route_metadata(): void
    {
        $home = $this->makePage('home', 'en');
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $home->id,
            'locale' => 'en',
            'canonical_url' => url('/page-owned-home'),
            'robots_index' => true,
            'exclude_from_sitemap' => false,
        ]);
        SeoMetadata::create([
            'route_name' => 'frontend.home',
            'route_path' => '/',
            'locale' => 'en',
            'canonical_url' => url('/stale-route-home'),
            'robots_index' => false,
            'exclude_from_sitemap' => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>' . url('/page-owned-home') . '</loc>', false)
            ->assertDontSee('stale-route-home', false);

        $home->update(['visibility' => 'unlisted']);
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('page-owned-home', false);
    }

    public function test_robots_is_fail_closed_outside_explicitly_opted_in_production(): void
    {
        config(['app.env' => 'staging', 'seo.robots.indexing_enabled' => true]);
        $staging = $this->get('/contact-us')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $staging->assertSee('name="robots" content="noindex,nofollow,noarchive"', false)
            ->assertInertia(fn ($page) => $page->where('seoPolicy.robots', 'noindex,nofollow,noarchive'));
        $this->assertContains('Allow: /', explode("\n", $this->get('/robots.txt')->assertOk()->getContent()));

        config(['app.env' => 'production', 'seo.robots.indexing_enabled' => false]);
        $this->get('/contact-us')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('name="robots" content="noindex,nofollow,noarchive"', false);
        $this->assertContains('Allow: /', explode("\n", $this->get('/robots.txt')->assertOk()->getContent()));

        config(['app.env' => 'production', 'seo.robots.indexing_enabled' => true]);
        $lines = explode("\n", $this->get('/robots.txt')->assertOk()->getContent());
        $this->assertContains('Allow: /', $lines);
        $this->assertNotContains('Disallow: /', $lines);
        $this->assertContains('Disallow: /admin', $lines);
        $this->assertTrue(collect($lines)->contains(fn (string $line) => str_contains($line, '/sitemap-index.xml')));
        $this->get('/contact-us')->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('name="robots" content="index,follow"', false);
    }

    public function test_cacheable_crawler_endpoints_are_stateless_and_never_set_cookies(): void
    {
        foreach (['/robots.txt', '/sitemap.xml', '/sitemap-index.xml', '/sitemap-en.xml'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertSame([], $response->headers->getCookies(), $path . ' must not set cookies.');
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_language_switch_never_redirects_to_an_external_referer(): void
    {
        $this->get('/language/en', ['Referer' => 'https://evil.example/phish'])
            ->assertRedirect(route('frontend.home'));

        $safe = route('frontend.contactUs') . '?from=language-switch';
        $this->get('/language/en', ['Referer' => $safe])
            ->assertRedirect($safe);
    }

    public function test_disabled_session_locale_is_revalidated_against_public_locales(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => false,
            'enabled_at' => null,
        ]);

        $response = $this->withSession(['locale' => 'bn'])
            ->get('/contact-us')
            ->assertOk();

        $response->assertHeader('Content-Language', 'en');
        $this->assertSame('en', session('locale'));
    }

    public function test_the_shared_route_registry_excludes_operational_endpoints(): void
    {
        $routes = app(SeoRouteRegistry::class);

        $this->assertTrue($routes->has('frontend.home'));
        $this->assertSame('/', $routes->path('frontend.home'));
        $this->assertFalse($routes->has('seo.sitemap'));
        $this->assertFalse($routes->has('seo.robots'));
        $this->assertFalse($routes->has('chat.bootstrap'));
        $this->assertFalse($routes->has('frontend.donation.payment.success'));
    }

    public function test_server_and_client_head_owners_use_matching_keys(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));
        $vue = file_get_contents(resource_path('js/layouts/App.vue'));
        $structured = file_get_contents(resource_path('js/Shared/StructuredData.js'));

        foreach ([
            'description',
            'robots',
            'canonical',
            'og:title',
            'og:type',
            'og:url',
            'og:description',
            'og:site_name',
            'twitter:card',
            'twitter:title',
            'twitter:description',
        ] as $key) {
            $this->assertStringContainsString('inertia="' . $key . '"', $blade);
            $this->assertStringContainsString('head-key="' . $key . '"', $vue);
        }
        $this->assertStringContainsString('inertia="structured-data"', $blade);
        $this->assertStringContainsString("'head-key': 'structured-data'", $structured);
        $this->assertStringNotContainsString('name="keywords"', $blade);
        $this->assertStringNotContainsString('head-key="keywords"', $vue);

        $header = file_get_contents(resource_path('js/layouts/AppHeader.vue'));
        $localeSwitcher = file_get_contents(resource_path('js/Shared/composables/publicLocaleSwitcher.js'));
        $this->assertStringContainsString('v-for="(link, index) in localeLinks"', $header);
        $this->assertStringContainsString('usePublicLocaleSwitcher', $header);
        $this->assertStringContainsString('resolveSeoAlternates', $localeSwitcher);
        $this->assertStringContainsString('resolveSeoAlternates', $vue);
        $this->assertStringContainsString('seoAlternates', $vue);
        $this->assertStringNotContainsString('href="/language/en"', $header);
        $this->assertStringNotContainsString('href="/language/bn"', $header);
    }

    private function makePage(string $slug, string $locale, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'A public impact story.',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => $locale,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function makeCategory(string $slug, string $locale, string $uuid): Category
    {
        return Category::create([
            'uuid' => $uuid,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'description' => 'Public program information.',
            'language' => $locale,
            'status' => 1,
        ]);
    }

    private function makeNotice(string $slug, string $locale, string $title, ?string $translationKey = null): NoticeBoard
    {
        return NoticeBoard::create([
            'translation_key' => $translationKey,
            'title' => $title,
            'slug' => $slug,
            'language' => $locale,
            'status' => 1,
            'published_at' => now()->subDay(),
        ]);
    }

    private function enableBangla(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }
}
