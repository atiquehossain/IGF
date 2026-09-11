<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\TranslationLocale;
use App\Services\SeoMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogPageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const BLOG_CATEGORY_UUID = '61000000-0000-4000-8000-000000000007';

    public function test_blog_archive_lists_only_public_listed_due_pages_from_its_category_and_locale(): void
    {
        $blog = $this->makeBlogCategory('en');
        $otherCategory = $this->makeCategory('Field stories', 'field-stories', 'en');

        $published = $this->makePage($blog, 'Published blog post', 'published-blog-post');
        $scheduledDue = $this->makePage($blog, 'Scheduled and due post', 'scheduled-due-post', [
            'publication_status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->makePage($blog, 'Draft blog post', 'draft-blog-post', [
            'status' => 0,
            'publication_status' => 'draft',
        ]);
        $this->makePage($blog, 'Private blog post', 'private-blog-post', [
            'visibility' => 'private',
        ]);
        $this->makePage($blog, 'Unlisted blog post', 'unlisted-blog-post', [
            'visibility' => 'unlisted',
        ]);
        $this->makePage($blog, 'Scheduled future post', 'scheduled-future-post', [
            'publication_status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        $this->makePage($blog, 'Future dated post', 'future-dated-post', [
            'published_at' => now()->addDay(),
        ]);
        $this->makePage($otherCategory, 'Other category post', 'other-category-post');

        $banglaBlog = $this->makeBlogCategory('bn');
        $this->makePage($banglaBlog, 'বাংলা ব্লগ পোস্ট', 'bangla-blog-post', [
            'language' => 'bn',
        ]);

        $expectedIds = collect([$published->id, $scheduledDue->id])->sort()->values()->all();

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog')
                ->where('properties.total_count', 2)
                ->where('data.category.uuid', self::BLOG_CATEGORY_UUID)
                ->where('data.items', function ($items) use ($expectedIds): bool {
                    $actualIds = collect($items)->pluck('id')->sort()->values()->all();

                    return $actualIds === $expectedIds
                        && collect($items)->every(
                            fn ($item): bool => str_starts_with((string) $item['public_url'], url('/blog/'))
                        );
                })
            );
    }

    public function test_blog_detail_is_scoped_to_public_blog_pages_and_legacy_page_urls_redirect(): void
    {
        $blog = $this->makeBlogCategory('en');
        $otherCategory = $this->makeCategory('Programs', 'programs', 'en');
        $post = $this->makePage($blog, 'A public blog post', 'a-public-blog-post');
        $ordinaryPage = $this->makePage($otherCategory, 'An ordinary page', 'an-ordinary-page');
        $draft = $this->makePage($blog, 'An unpublished blog draft', 'an-unpublished-blog-draft', [
            'status' => 0,
            'publication_status' => 'draft',
        ]);
        $private = $this->makePage($blog, 'A private blog post', 'a-private-blog-post', [
            'visibility' => 'private',
        ]);
        $future = $this->makePage($blog, 'A future blog post', 'a-future-blog-post', [
            'published_at' => now()->addDay(),
        ]);

        $this->get('/blog/' . $post->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog-post')
                ->where('title', $post->name)
                ->where('contentSeo.canonical_url', url('/blog/' . $post->slug))
            );

        foreach ([$ordinaryPage, $draft, $private, $future] as $ineligible) {
            $this->get('/blog/' . $ineligible->slug)->assertNotFound();
        }

        $this->get('/page/' . $post->slug)
            ->assertMovedPermanently()
            ->assertRedirect(url('/blog/' . $post->slug));

        $this->get('/page/' . $ordinaryPage->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('page'));
    }

    public function test_blog_locale_variants_use_translated_slugs_public_urls_and_seo_metadata(): void
    {
        $this->enableEnglishAndBangla();

        $englishCategory = $this->makeBlogCategory('en');
        $banglaCategory = $this->makeBlogCategory('bn');
        $pageUuid = (string) Str::uuid();

        $english = $this->makePage($englishCategory, 'English blog post', 'english-blog-post', [
            'uuid' => $pageUuid,
        ]);
        $bangla = $this->makePage($banglaCategory, 'বাংলা ব্লগ পোস্ট', 'bangla-translated-blog-post', [
            'uuid' => $pageUuid,
            'language' => 'bn',
        ]);

        SeoMetadata::create([
            'route_name' => 'frontend.blog',
            'route_path' => '/blog',
            'locale' => 'bn',
            'title' => 'বাংলা ব্লগ | ইগনাইট',
            'description' => 'ইগনাইটের সর্বশেষ বাংলা ব্লগ পোস্ট।',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $this->assertSame(
            'বাংলা ব্লগ | ইগনাইট',
            app(SeoMetadataService::class)->metaForRoute('frontend.blog', 'bn')['meta_title'] ?? null,
        );
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $bangla->id,
            'locale' => 'bn',
            'title' => 'বাংলা পোস্ট | ইগনাইট',
            'description' => 'বাংলা ব্লগ পোস্টের সারসংক্ষেপ।',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $englishUrl = url('/blog/' . $english->slug);
        $banglaUrl = url('/blog/' . $bangla->slug . '?lang=bn');

        $this->get('/blog?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog')
                ->where('properties.total_count', 1)
                ->where('contentSeo.meta_title', 'বাংলা ব্লগ | ইগনাইট')
                ->where('contentSeo.canonical_url', url('/blog?lang=bn'))
                ->where('data.items.0.id', $bangla->id)
                ->where('data.items.0.public_url', $banglaUrl)
            );

        $this->get('/blog/' . $bangla->slug . '?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog-post')
                ->where('title', $bangla->name)
                ->where('contentSeo.meta_title', 'বাংলা পোস্ট | ইগনাইট')
                ->where('contentSeo.canonical_url', $banglaUrl)
                ->where('seoAlternates.links', function ($links) use ($englishUrl, $banglaUrl): bool {
                    $byLocale = collect($links)->pluck('url', 'locale');

                    return $byLocale->get('en') === $englishUrl
                        && $byLocale->get('bn') === $banglaUrl;
                })
            );

        $this->get('/blog/' . $english->slug . '?lang=bn')->assertNotFound();

        $this->get('/page/' . $bangla->slug . '?lang=bn')
            ->assertMovedPermanently()
            ->assertRedirect($banglaUrl);
    }

    public function test_blog_api_and_sitemap_follow_publication_and_locale_availability_rules(): void
    {
        $this->enableEnglishAndBangla();
        $englishCategory = $this->makeBlogCategory('en');
        $banglaCategory = $this->makeBlogCategory('bn');
        $listed = $this->makePage($englishCategory, 'Listed API post', 'listed-api-post');
        $this->makePage($englishCategory, 'Unlisted API post', 'unlisted-api-post', [
            'visibility' => 'unlisted',
        ]);
        $this->makePage($englishCategory, 'Future API post', 'future-api-post', [
            'published_at' => now()->addDay(),
        ]);

        $this->withHeader('locale', 'en')
            ->getJson('/api/v1/blog')
            ->assertOk()
            ->assertJsonPath('data.uuid', self::BLOG_CATEGORY_UUID)
            ->assertJsonCount(1, 'data.page')
            ->assertJsonPath('data.page.0.id', $listed->id);

        $sitemap = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . url('/blog') . '</loc>', $sitemap);
        $this->assertStringContainsString(
            'hreflang="bn" href="' . e(url('/blog?lang=bn')) . '"',
            $sitemap,
        );

        $banglaCategory->update(['status' => 0]);
        $withoutBangla = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>' . url('/blog') . '</loc>', $withoutBangla);
        $this->assertStringNotContainsString(
            'hreflang="bn" href="' . e(url('/blog?lang=bn')) . '"',
            $withoutBangla,
        );

        $englishCategory->update(['status' => 0]);
        $withoutBlog = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('<loc>' . url('/blog') . '</loc>', $withoutBlog);
        $this->get('/blog')->assertNotFound();
    }

    private function makeBlogCategory(string $locale): Category
    {
        return Category::query()->updateOrCreate([
            'uuid' => self::BLOG_CATEGORY_UUID,
            'language' => $locale,
        ], [
            'name' => $locale === 'bn' ? 'ব্লগ' : 'Blog',
            'slug' => 'blog',
            'description' => $locale === 'bn' ? 'বাংলা ব্লগ আর্কাইভ।' : 'Blog archive.',
            'display_mode' => 'archive',
            'status' => 1,
        ]);
    }

    private function makeCategory(
        string $name,
        string $slug,
        string $locale,
        ?string $uuid = null,
    ): Category {
        return Category::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' archive.',
            'display_mode' => 'archive',
            'language' => $locale,
            'status' => 1,
        ]);
    }

    private function makePage(Category $category, string $name, string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'category_id' => (string) $category->id,
            'name' => $name,
            'sub_title' => $name . ' summary.',
            'slug' => $slug,
            'description' => '<p>' . $name . ' body.</p>',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'publish_by' => 'Ignite Editorial Team',
            'language' => $category->language,
        ], $overrides));
    }

    private function enableEnglishAndBangla(): void
    {
        TranslationLocale::query()->updateOrCreate(['locale' => 'en'], [
            'name' => 'English',
            'native_name' => 'English',
            'is_default' => true,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        TranslationLocale::query()->updateOrCreate(['locale' => 'bn'], [
            'name' => 'Bangla',
            'native_name' => 'বাংলা',
            'is_default' => false,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }
}
