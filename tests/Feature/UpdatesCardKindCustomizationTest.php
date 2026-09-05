<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdatesCardKindCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_normalizes_legacy_updates_without_reading_editable_or_translated_copy(): void
    {
        $content = PageBlock::normalizeUpdatesContent([
            'variant' => 'updates',
            'items' => [
                ['eyebrow' => 'Latest news', 'heading' => 'First legacy item'],
                ['kind' => 'event', 'eyebrow' => 'Latest news', 'heading' => 'Explicit event'],
                ['kind' => 'news', 'eyebrow' => 'Upcoming event', 'heading' => 'Explicit news'],
                ['eyebrow' => 'আসন্ন অনুষ্ঠান', 'heading' => 'Last legacy item'],
            ],
        ]);

        $this->assertSame(
            ['event', 'event', 'news', 'news'],
            array_column($content['items'], 'kind'),
        );
    }

    public function test_data_migration_backfills_page_and_reusable_updates_idempotently_and_rolls_back_conservatively(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Updates migration page',
            'sub_title' => '',
            'slug' => 'updates-migration-page',
            'language' => 'bn',
            'status' => 1,
        ]);
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'cards',
            'label' => 'হালনাগাদ',
            'content' => [
                'variant' => 'updates',
                'items' => [
                    ['eyebrow' => 'যেকোনো লেখা', 'heading' => 'প্রথম'],
                    ['eyebrow' => 'আরেকটি লেখা', 'heading' => 'দ্বিতীয়'],
                ],
            ],
            'settings' => ['section_presentation' => 'standard'],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reusable updates',
            'type' => 'cards',
            'locale' => 'en',
            'content' => [
                'variant' => 'updates',
                'items' => [['content_kind' => 'article', 'heading' => 'Managed article']],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $migration = require database_path(
            'migrations/2026_09_05_150000_backfill_updates_card_item_kinds.php'
        );

        $migration->up();
        $this->assertSame(['event', 'news'], array_column($block->fresh()->content['items'], 'kind'));
        $this->assertSame('news', $reusable->fresh()->content['items'][0]['kind']);
        $this->assertArrayHasKey('_migration_updates_item_kind_v1', $block->fresh()->settings);
        $this->assertArrayNotHasKey('_migration_updates_item_kind_v1', $block->fresh()->resolvedSettings());

        $firstRun = $block->fresh()->getRawOriginal('content');
        $migration->up();
        $this->assertSame($firstRun, $block->fresh()->getRawOriginal('content'));

        $content = $block->fresh()->content;
        $content['items'][0]['kind'] = 'news';
        $block->update(['content' => $content]);
        $migration->down();

        $rolledBack = $block->fresh();
        $this->assertSame('news', $rolledBack->content['items'][0]['kind']);
        $this->assertArrayNotHasKey('kind', $rolledBack->content['items'][1]);
        $this->assertArrayNotHasKey('_migration_updates_item_kind_v1', $rolledBack->settings);
        $this->assertArrayNotHasKey('kind', $reusable->fresh()->content['items'][0]);
    }
}
