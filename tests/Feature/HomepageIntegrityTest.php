<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\SeoMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_published_scheduled_safe_blocks_and_page_seo(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Homepage',
            'slug' => 'home',
            'language' => 'en',
            'status' => 1,
        ]);
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'category_id' => $category->id,
            'name' => 'Ignite home',
            'sub_title' => 'Home subtitle',
            'slug' => 'home',
            'language' => 'en',
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => today(),
            'inline_css' => '.managed-home { color: #123456; }',
        ]);
        $this->block($page, [
            'type' => 'hero',
            'label' => 'Hero',
            'content' => ['heading' => 'Safe homepage', 'body' => 'Visible<script>alert(1)</script>'],
            'sort_order' => 1,
        ]);
        $this->block($page, [
            'label' => 'Future section',
            'available_from' => now()->addDay(),
            'sort_order' => 2,
        ]);
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'en',
            'title' => 'Ignite SEO title',
            'description' => 'Ignite SEO description',
            'robots_index' => true,
            'robots_follow' => true,
            'sitemap_priority' => .9,
        ]);

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('Home/home')
                ->where('meta_tag.meta_title', 'Ignite SEO title')
                ->where('meta_tag.meta_description', 'Ignite SEO description')
                ->where('data.homePage.slug', 'home')
                ->where('data.homePage.inline_css', '.managed-home{color:#123456}')
                ->has('data.homePage.visible_blocks', 1)
                ->where('data.homePage.visible_blocks.0.label', 'Hero')
                ->where('data.homePage.visible_blocks.0.content.heading', 'Safe homepage')
                ->where('data.homePage.visible_blocks.0.content.body', 'Visible')
                ->missing('data.middleRoute')
                ->missing('data.ourCauses')
                ->missing('data.awardRecognitions')
                ->missing('data.testimonials')
                ->missing('data.upcomingEvents')
            );
    }

    public function test_homepage_without_builder_content_still_renders_the_home_component(): void
    {
        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('Home/home')
                ->where('data.homePage', null)
            );
    }

    private function block(Page $page, array $overrides): PageBlock
    {
        return PageBlock::create(array_merge([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Section',
            'content' => ['body' => 'Content'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }
}
