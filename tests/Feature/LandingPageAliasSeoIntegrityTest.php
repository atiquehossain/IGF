<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\TranslationLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingPageAliasSeoIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_landing_page_alias_permanently_redirects_to_its_category_seo_owner(): void
    {
        $pageUuid = (string) Str::uuid();
        $category = $this->makeLandingCategory('visit-our-school', 'en', $pageUuid);
        $page = $this->makePage('school-campus', 'en', $pageUuid, [
            'category_id' => (string) $category->id,
            'visibility' => 'unlisted',
        ]);

        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
            'title' => 'Page alias must not own search results',
            'canonical_url' => url('/page/school-campus'),
            'robots_index' => true,
            'robots_follow' => true,
        ]);
        SeoMetadata::create([
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
            'locale' => 'en',
            'title' => 'Visit our school',
            'canonical_url' => url('/category/visit-our-school'),
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        // An editor may later make the backing page public. Its category must
        // still remain the single public and canonical SEO owner.
        $page->update(['visibility' => 'public']);

        $this->get('/page/school-campus')
            ->assertStatus(301)
            ->assertRedirect(url('/category/visit-our-school'));

        $this->get('/category/visit-our-school')
            ->assertOk()
            ->assertSee('<title inertia>Visit our school</title>', false)
            ->assertSee('rel="canonical" href="' . url('/category/visit-our-school') . '"', false)
            ->assertDontSee('Page alias must not own search results', false);
    }

    public function test_landing_page_alias_redirect_uses_the_real_category_slug_for_each_locale(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $pageUuid = (string) Str::uuid();
        $categoryUuid = (string) Str::uuid();
        $englishCategory = $this->makeLandingCategory(
            'visit-our-school',
            'en',
            $pageUuid,
            ['uuid' => $categoryUuid]
        );
        $banglaCategory = $this->makeLandingCategory(
            'amader-school-dekhun',
            'bn',
            $pageUuid,
            ['uuid' => $categoryUuid]
        );
        $this->makePage('school-campus', 'en', $pageUuid, [
            'category_id' => (string) $englishCategory->id,
        ]);
        $this->makePage('bangla-school-campus', 'bn', $pageUuid, [
            'category_id' => (string) $banglaCategory->id,
        ]);

        $this->get('/page/school-campus')
            ->assertStatus(301)
            ->assertRedirect(url('/category/visit-our-school'));
        $this->get('/page/bangla-school-campus?lang=bn')
            ->assertStatus(301)
            ->assertRedirect(url('/category/amader-school-dekhun?lang=bn'));
    }

    public function test_locale_sitemaps_exclude_landing_alias_pages_without_affecting_ordinary_pages(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $pageUuid = (string) Str::uuid();
        $category = $this->makeLandingCategory('community-hub', 'en', $pageUuid);
        $this->makePage('community-hub-layout', 'en', $pageUuid, [
            'category_id' => (string) $category->id,
            'visibility' => 'public',
        ]);
        $this->makePage('ordinary-public-page', 'en', (string) Str::uuid());

        $banglaPageUuid = (string) Str::uuid();
        $banglaCategory = $this->makeLandingCategory('bangla-community-hub', 'bn', $banglaPageUuid);
        $this->makePage('bangla-community-layout', 'bn', $banglaPageUuid, [
            'category_id' => (string) $banglaCategory->id,
            'visibility' => 'public',
        ]);
        $this->makePage('bangla-ordinary-page', 'bn', (string) Str::uuid());

        $this->get('/sitemap-en.xml')
            ->assertOk()
            ->assertSee('<loc>' . url('/category/community-hub') . '</loc>', false)
            ->assertDontSee('/page/community-hub-layout', false)
            ->assertSee('<loc>' . url('/page/ordinary-public-page') . '</loc>', false);

        $this->get('/sitemap-bn.xml')
            ->assertOk()
            ->assertSee('<loc>' . url('/category/bangla-community-hub?lang=bn') . '</loc>', false)
            ->assertDontSee('/page/bangla-community-layout', false)
            ->assertSee('<loc>' . url('/page/bangla-ordinary-page?lang=bn') . '</loc>', false);

        $this->get('/page/ordinary-public-page')->assertOk();
        $this->get('/page/bangla-ordinary-page?lang=bn')->assertOk();
    }

    public function test_an_alias_cannot_become_indexable_when_its_current_locale_category_is_missing(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $pageUuid = (string) Str::uuid();
        $this->makeLandingCategory('bangla-only-owner', 'bn', $pageUuid);
        $this->makePage('english-backing-page', 'en', $pageUuid);

        $this->get('/page/english-backing-page')
            ->assertStatus(301)
            ->assertRedirect(url('/category/bangla-only-owner?lang=bn'));
        $this->get('/sitemap-en.xml')
            ->assertOk()
            ->assertDontSee('/page/english-backing-page', false);
    }

    private function makeLandingCategory(
        string $slug,
        string $locale,
        string $landingPageUuid,
        array $overrides = []
    ): Category {
        return Category::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'description' => 'Public landing-page category.',
            'display_mode' => 'landing_page',
            'landing_page_uuid' => $landingPageUuid,
            'language' => $locale,
            'status' => 1,
        ], $overrides));
    }

    private function makePage(
        string $slug,
        string $locale,
        string $uuid,
        array $overrides = []
    ): Page {
        return Page::create(array_merge([
            'uuid' => $uuid,
            'name' => Str::headline($slug),
            'sub_title' => 'Public page content.',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => $locale,
            'published_at' => now()->subDay(),
        ], $overrides));
    }
}
