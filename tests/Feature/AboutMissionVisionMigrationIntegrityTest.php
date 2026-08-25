<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutMissionVisionMigrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE_UUID = '22222222-2222-4222-8222-000000000010';

    private const BLOCK_UUID = '69000000-0000-4000-8000-000000000007';

    public function test_migration_adds_the_dynamic_block_once_without_reordering_editor_content(): void
    {
        $page = $this->makeAboutPage();
        $editorBlock = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => '69999999-0000-4000-8000-000000000099',
            'type' => 'rich_text',
            'label' => 'Editor block',
            'content' => ['heading' => 'Editor-owned section'],
            'settings' => [],
            'sort_order' => 7,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertSame(1, PageBlock::withTrashed()->where('uuid', self::BLOCK_UUID)->count());
        $this->assertSame(7, $editorBlock->fresh()->sort_order);

        $block = PageBlock::where('uuid', self::BLOCK_UUID)->firstOrFail();
        $this->assertSame($page->id, $block->page_id);
        $this->assertSame(0, $block->sort_order);
        $this->assertSame('cards', $block->type);
        $this->assertSame('about-pillars', $block->content['variant']);
        $this->assertSame(['Our mission', 'Our vision'], array_column($block->content['items'], 'eyebrow'));
    }

    public function test_migration_does_not_resurrect_an_editor_deleted_block(): void
    {
        $page = $this->makeAboutPage();
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => self::BLOCK_UUID,
            'type' => 'cards',
            'label' => 'Mission and Vision',
            'content' => ['variant' => 'about-pillars', 'items' => []],
            'settings' => [],
            'sort_order' => 3,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $block->delete();

        $this->migration()->up();

        $stored = PageBlock::withTrashed()->where('uuid', self::BLOCK_UUID)->firstOrFail();
        $this->assertTrue($stored->trashed());
        $this->assertSame(1, PageBlock::withTrashed()->where('uuid', self::BLOCK_UUID)->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_25_110000_add_about_mission_and_vision_block.php');
    }

    private function makeAboutPage(): Page
    {
        return Page::create([
            'uuid' => self::PAGE_UUID,
            'name' => 'About Ignite Global Foundation',
            'sub_title' => 'A volunteer-led movement.',
            'slug' => 'about-us',
            'status' => 1,
            'language' => 'en',
        ]);
    }
}
