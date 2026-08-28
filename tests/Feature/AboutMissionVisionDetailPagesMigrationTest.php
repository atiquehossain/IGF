<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutMissionVisionDetailPagesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const ABOUT_PAGE_UUID = '22222222-2222-4222-8222-000000000010';

    private const ABOUT_BLOCK_UUID = '69000000-0000-4000-8000-000000000007';

    public function test_migration_creates_public_detail_pages_and_wires_each_card_once(): void
    {
        $about = $this->makeAboutPage();
        $this->makeAboutBlock($about);

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertSame(1, Page::withTrashed()->where('slug', 'our-mission')->where('language', 'en')->count());
        $this->assertSame(1, Page::withTrashed()->where('slug', 'our-vision')->where('language', 'en')->count());

        $mission = Page::where('slug', 'our-mission')->firstOrFail();
        $vision = Page::where('slug', 'our-vision')->firstOrFail();
        $this->assertTrue($mission->status);
        $this->assertSame('published', $mission->publication_status);
        $this->assertSame('public', $mission->visibility);
        $this->assertStringContainsString('Turn compassion into practical opportunity', $mission->description);
        $this->assertStringContainsString('A more equitable and compassionate society', $vision->description);

        $items = PageBlock::where('uuid', self::ABOUT_BLOCK_UUID)->firstOrFail()->content['items'];
        $this->assertSame('/page/our-mission', $items[0]['url']);
        $this->assertSame('Read more', $items[0]['link_label']);
        $this->assertSame('/page/our-vision', $items[1]['url']);
        $this->assertSame('Read more', $items[1]['link_label']);

        $this->get('/page/our-mission')->assertOk();
        $this->get('/page/our-vision')->assertOk();
    }

    public function test_migration_preserves_admin_owned_pages_links_and_labels(): void
    {
        $about = $this->makeAboutPage();
        $block = $this->makeAboutBlock($about, [
            [
                'eyebrow' => 'Our mission',
                'heading' => 'Admin mission',
                'url' => '/custom-mission',
                'link_label' => 'Explore mission',
            ],
            [
                'eyebrow' => 'Our vision',
                'heading' => 'Admin vision',
                'url' => '',
                'link_label' => '',
            ],
        ]);
        $mission = $this->makeDetailPage('our-mission', 'Admin-owned mission');
        $this->makeDetailPage('our-vision', 'Admin-owned vision');

        $this->migration()->up();

        $this->assertSame('Admin-owned mission', $mission->fresh()->name);
        $items = $block->fresh()->content['items'];
        $this->assertSame('/custom-mission', $items[0]['url']);
        $this->assertSame('Explore mission', $items[0]['link_label']);
        $this->assertSame('', $items[1]['url']);
        $this->assertSame('', $items[1]['link_label']);
    }

    public function test_migration_does_not_restore_or_link_an_admin_deleted_page(): void
    {
        $about = $this->makeAboutPage();
        $block = $this->makeAboutBlock($about);
        $mission = $this->makeDetailPage('our-mission', 'Deleted mission');
        $mission->delete();

        $this->migration()->up();

        $storedMission = Page::withTrashed()->where('slug', 'our-mission')->firstOrFail();
        $this->assertTrue($storedMission->trashed());
        $this->assertArrayNotHasKey('url', $block->fresh()->content['items'][0]);
        $this->assertSame('/page/our-vision', $block->fresh()->content['items'][1]['url']);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_28_090000_add_mission_and_vision_detail_pages.php');
    }

    private function makeAboutPage(): Page
    {
        return Page::create([
            'uuid' => self::ABOUT_PAGE_UUID,
            'name' => 'About Ignite Global Foundation',
            'sub_title' => 'A volunteer-led movement.',
            'slug' => 'about-us',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     */
    private function makeAboutBlock(Page $page, ?array $items = null): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'uuid' => self::ABOUT_BLOCK_UUID,
            'type' => 'cards',
            'label' => 'Mission and Vision',
            'content' => [
                'variant' => 'about-pillars',
                'items' => $items ?? [
                    ['eyebrow' => 'Our mission', 'heading' => 'Mission'],
                    ['eyebrow' => 'Our vision', 'heading' => 'Vision'],
                ],
            ],
            'settings' => [],
            'sort_order' => 0,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }

    private function makeDetailPage(string $slug, string $name): Page
    {
        return Page::create([
            'uuid' => $slug === 'our-mission'
                ? '6a000000-0000-4000-8000-000000000001'
                : '6a000000-0000-4000-8000-000000000002',
            'name' => $name,
            'sub_title' => 'Admin-owned subtitle.',
            'slug' => $slug,
            'description' => '<p>Admin-owned content.</p>',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
    }
}
