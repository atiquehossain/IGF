<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageBlock;
use Database\Seeders\IgniteParityContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryLandingPageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_category_resolves_only_its_selected_localized_child_page(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $category = Category::where('slug', 'visit-ignite-school')->where('language', 'en')->firstOrFail();
        $school = Page::where('slug', 'ignite-school-bawnia-campus')->where('language', 'en')->firstOrFail();

        $this->get('/category/visit-ignite-school')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.landing_page.uuid', $school->uuid)
            ->has('data.landing_page.visible_blocks', 7)
        );

        $foreignPage = Page::where('slug', 'education')->where('language', 'en')->firstOrFail();
        $category->update(['landing_page_uuid' => $foreignPage->uuid]);

        $this->get('/category/visit-ignite-school')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.landing_page', null)
            ->has('data.items', 1)
        );

        $category->update([
            'display_mode' => 'archive',
            'landing_page_uuid' => $school->uuid,
        ]);

        $this->get('/category/visit-ignite-school')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.landing_page', null)
            ->has('data.items', 1)
        );
    }

    public function test_landing_page_blocks_are_resolved_and_sanitized_before_rendering(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $block = PageBlock::where('uuid', '69400000-0000-4000-8000-000000000001')->firstOrFail();
        $content = $block->content;
        $content['body'] = '<p>Safe school copy.</p><script>alert(1)</script><a href="javascript:alert(2)">Unsafe</a>';
        $block->update(['content' => $content]);

        $this->get('/category/visit-ignite-school')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.landing_page.visible_blocks.1.content.body', function ($body): bool {
                return str_contains($body, 'Safe school copy.')
                    && !str_contains(strtolower($body), '<script')
                    && !str_contains(strtolower($body), 'javascript:');
            })
        );
    }

    public function test_landing_page_fallback_image_is_absolute_in_raw_and_hydrated_social_metadata(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $image = url('/storage/media/ignite-live/53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg');
        $response = $this->get('/category/visit-ignite-school')->assertOk();
        $head = Str::before($response->getContent(), '</head>');

        $this->assertStringContainsString('property="og:image" content="' . $image . '"', $head);
        $this->assertStringContainsString('name="twitter:image" content="' . $image . '"', $head);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('meta_tag.og_image', $image)
            ->where('meta_tag.twitter_image', $image)
        );
    }

    public function test_school_metric_correction_is_translation_safe_reversible_and_preserves_editor_keys(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IgniteParityContentSeeder::class);

        $source = PageBlock::where('uuid', '69400000-0000-4000-8000-000000000002')->firstOrFail();
        $sourceContent = $source->content;
        $sourceContent['items'][1] = [
            'value' => '100+',
            'label' => 'Graduates',
            'icon' => 'report',
            'accent' => 'editor-owned',
        ];
        $source->update(['content' => $sourceContent, 'settings' => []]);

        $translatedPage = Page::where('uuid', $source->page->uuid)
            ->where('language', 'bn')
            ->first();
        if (!$translatedPage) {
            $translatedPage = $source->page->replicate();
            $translatedPage->id = null;
            $translatedPage->language = 'bn';
            $translatedPage->slug = 'ignite-school-bn';
            $translatedPage->save();
        }

        $translated = $source->replicate();
        $translated->uuid = (string) Str::uuid();
        $translated->translation_key = $source->uuid;
        $translated->page_id = $translatedPage->id;
        $translated->content = [
            'items' => [
                ['value' => '120+', 'label' => 'বর্তমান শিক্ষার্থী', 'icon' => 'child'],
                ['value' => '100+', 'label' => 'স্নাতক', 'icon' => 'report', 'accent' => 'অনুবাদক-নির্ধারিত'],
                ['value' => '2016', 'label' => 'প্রতিষ্ঠিত', 'icon' => 'school'],
            ],
        ];
        $translated->settings = [];
        $translated->save();

        $migration = require database_path('migrations/2026_08_19_120200_remove_unverified_school_graduate_metric.php');
        $migration->up();

        $source->refresh();
        $translated->refresh();
        $this->assertSame('35', $source->content['items'][1]['value']);
        $this->assertSame('Children at launch', $source->content['items'][1]['label']);
        $this->assertSame('editor-owned', $source->content['items'][1]['accent']);
        $this->assertCount(2, $translated->content['items']);
        $this->assertNotContains('100+', array_column($translated->content['items'], 'value'));

        $migration->down();

        $source->refresh();
        $translated->refresh();
        $this->assertSame('100+', $source->content['items'][1]['value']);
        $this->assertSame('editor-owned', $source->content['items'][1]['accent']);
        $this->assertSame('100+', $translated->content['items'][1]['value']);
        $this->assertSame('স্নাতক', $translated->content['items'][1]['label']);
        $this->assertSame('অনুবাদক-নির্ধারিত', $translated->content['items'][1]['accent']);
    }

}
