<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SplitEventsAndNewsArchivesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const EN_ROOT_UUID = '67000000-0000-4000-8000-000000000005';

    private const BN_ROOT_UUID = '69000000-0000-4000-8000-000000000005';

    private const EN_EVENT_UUID = '68000000-0005-4000-8000-000000000002';

    private const BN_EVENT_UUID = '69000000-0005-4000-8000-000000000002';

    private const EN_NEWS_UUID = '68000000-0005-4000-8000-000000000003';

    private const BN_NEWS_UUID = '69000000-0005-4000-8000-000000000003';

    public function test_it_splits_the_deterministic_english_and_bangla_menus_idempotently_without_overwriting_editor_choices(): void
    {
        $englishRoot = $this->newsRoot('en');
        $banglaRoot = $this->newsRoot('bn');
        $englishEvent = $this->combinedMenu('en', $englishRoot, [
            'description' => 'Keep this English navigation hint.',
            'order_by' => 7,
            'status' => 0,
        ]);
        $banglaEvent = $this->combinedMenu('bn', $banglaRoot, [
            'link' => 'custom',
            'slug' => '/events?kind=event',
            'order_by' => 4,
            'status' => 1,
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $englishEvent,
            'parent_id' => $englishRoot,
            'name' => 'Events',
            'description' => 'Keep this English navigation hint.',
            'link' => 'frontend.events',
            'slug' => null,
            'order_by' => 7,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $banglaEvent,
            'parent_id' => $banglaRoot,
            'name' => 'ইভেন্ট',
            'link' => 'frontend.events',
            'slug' => null,
            'order_by' => 4,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::EN_NEWS_UUID,
            'parent_id' => $englishRoot,
            'name' => 'News',
            'link' => 'frontend.news',
            'slug' => null,
            'language' => 'en',
            'order_by' => 8,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::BN_NEWS_UUID,
            'parent_id' => $banglaRoot,
            'name' => 'সংবাদ',
            'link' => 'frontend.news',
            'slug' => null,
            'language' => 'bn',
            'order_by' => 5,
            'status' => 1,
        ]);

        $englishNews = (int) DB::table('page_menus')
            ->where('uuid', self::EN_NEWS_UUID)
            ->where('language', 'en')
            ->value('id');
        DB::table('page_menus')->where('id', $englishEvent)->update([
            'name' => 'Community calendar',
            'order_by' => 31,
            'status' => 1,
        ]);
        DB::table('page_menus')->where('id', $englishNews)->update([
            'name' => 'Field newsroom',
            'description' => 'Editor-authored News description.',
            'order_by' => 42,
            'status' => 0,
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $englishEvent,
            'name' => 'Community calendar',
            'order_by' => 31,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $englishNews,
            'parent_id' => $englishRoot,
            'name' => 'Field newsroom',
            'description' => 'Editor-authored News description.',
            'link' => 'frontend.news',
            'slug' => null,
            'order_by' => 42,
            'status' => 0,
        ]);
        $this->assertSame(1, DB::table('page_menus')->where('uuid', self::EN_NEWS_UUID)->count());
        $this->assertSame(1, DB::table('page_menus')->where('uuid', self::BN_NEWS_UUID)->count());
    }

    public function test_it_does_not_recreate_a_news_destination_when_the_bundled_combined_item_was_deleted(): void
    {
        $root = $this->newsRoot('en');
        $combined = $this->combinedMenu('en', $root, [
            'deleted_at' => now(),
            'deleted_by' => 99,
        ]);

        $this->runMigration();
        $this->runMigration();

        $this->assertSoftDeleted('page_menus', [
            'id' => $combined,
            'uuid' => self::EN_EVENT_UUID,
        ]);
        $this->assertSame(
            0,
            DB::table('page_menus')
                ->where('parent_id', $root)
                ->where('link', 'frontend.news')
                ->whereNull('deleted_at')
                ->count()
        );
        $this->assertDatabaseMissing('page_menus', [
            'uuid' => self::EN_NEWS_UUID,
            'language' => 'en',
        ]);
    }

    public function test_it_reuses_an_editor_created_news_sibling_instead_of_adding_a_duplicate(): void
    {
        $root = $this->newsRoot('en');
        $this->combinedMenu('en', $root);
        $editorNews = $this->menu([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $root,
            'name' => 'Community newsroom',
            'description' => 'Created before the deployment.',
            'link' => 'frontend.news',
            'slug' => null,
            'language' => 'en',
            'order_by' => 19,
            'status' => 0,
        ]);

        $this->runMigration();
        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $editorNews,
            'parent_id' => $root,
            'name' => 'Community newsroom',
            'description' => 'Created before the deployment.',
            'link' => 'frontend.news',
            'order_by' => 19,
            'status' => 0,
        ]);
        $this->assertSame(
            1,
            DB::table('page_menus')
                ->where('parent_id', $root)
                ->where('link', 'frontend.news')
                ->whereNull('deleted_at')
                ->count()
        );
        $this->assertDatabaseMissing('page_menus', [
            'uuid' => self::EN_NEWS_UUID,
            'language' => 'en',
        ]);
    }

    public function test_it_upgrades_only_exact_managed_urls_and_the_exact_shared_setting(): void
    {
        $page = Page::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Archive migration fixture',
            'sub_title' => '',
            'slug' => 'archive-migration-fixture',
            'status' => 1,
            'language' => 'en',
        ]);
        $legacyContent = [
            'events_view_all_url' => '/events?kind=event',
            'news_view_all_url' => '/events?kind=article',
            'heading' => 'Keep this authored heading',
        ];
        $customContent = [
            'events_view_all_url' => '/community-calendar',
            'news_view_all_url' => 'https://news.example.org/latest',
            'heading' => 'Keep this custom destination',
        ];
        $pageBlock = PageBlock::query()->create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'events_news',
            'label' => 'Legacy archive links',
            'content' => $legacyContent,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $customPageBlock = PageBlock::query()->create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'events_news',
            'label' => 'Custom archive links',
            'content' => $customContent,
            'settings' => [],
            'sort_order' => 2,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $unrelatedPageBlock = PageBlock::query()->create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'text',
            'label' => 'Unrelated block',
            'content' => $legacyContent,
            'settings' => [],
            'sort_order' => 3,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $reusable = ReusableBlock::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy reusable archive links',
            'type' => 'events_news',
            'locale' => '*',
            'content' => $legacyContent,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $customReusable = ReusableBlock::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Custom reusable archive links',
            'type' => 'events_news',
            'locale' => '*',
            'content' => $customContent,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $exactSetting = SiteSetting::query()->create([
            'group' => 'shared_blocks',
            'key' => 'updates_news_url',
            'locale' => '*',
            'value' => '/events',
            'type' => 'url_or_path',
            'is_public' => true,
        ]);
        $customSetting = SiteSetting::query()->create([
            'group' => 'shared_blocks',
            'key' => 'updates_news_url',
            'locale' => 'en',
            'value' => '/editor-newsroom',
            'type' => 'url_or_path',
            'is_public' => true,
        ]);

        $this->runMigration();
        $afterFirstRun = [
            'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
            'custom_page' => DB::table('page_blocks')->where('id', $customPageBlock->id)->value('content'),
            'unrelated_page' => DB::table('page_blocks')->where('id', $unrelatedPageBlock->id)->value('content'),
            'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
            'custom_reusable' => DB::table('reusable_blocks')->where('id', $customReusable->id)->value('content'),
        ];
        $this->runMigration();

        $expectedUpgrade = $legacyContent;
        $expectedUpgrade['events_view_all_url'] = '/events';
        $expectedUpgrade['news_view_all_url'] = '/news';
        $this->assertSame($expectedUpgrade, $pageBlock->fresh()->content);
        $this->assertSame($expectedUpgrade, $reusable->fresh()->content);
        $this->assertSame($customContent, $customPageBlock->fresh()->content);
        $this->assertSame($customContent, $customReusable->fresh()->content);
        $this->assertSame($legacyContent, $unrelatedPageBlock->fresh()->content);
        $this->assertSame($afterFirstRun, [
            'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
            'custom_page' => DB::table('page_blocks')->where('id', $customPageBlock->id)->value('content'),
            'unrelated_page' => DB::table('page_blocks')->where('id', $unrelatedPageBlock->id)->value('content'),
            'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
            'custom_reusable' => DB::table('reusable_blocks')->where('id', $customReusable->id)->value('content'),
        ]);
        $this->assertSame('/news', $exactSetting->fresh()->value);
        $this->assertSame('/editor-newsroom', $customSetting->fresh()->value);
    }

    private function newsRoot(string $locale): int
    {
        return $this->menu([
            'uuid' => $locale === 'bn' ? self::BN_ROOT_UUID : self::EN_ROOT_UUID,
            'name' => $locale === 'bn' ? 'সংবাদ ও গল্প' : 'News & Stories',
            'language' => $locale,
            'order_by' => 4,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function combinedMenu(string $locale, int $parentId, array $overrides = []): int
    {
        return $this->menu(array_merge([
            'uuid' => $locale === 'bn' ? self::BN_EVENT_UUID : self::EN_EVENT_UUID,
            'parent_id' => $parentId,
            'name' => $locale === 'bn' ? 'ইভেন্ট ও সংবাদ' : 'Events & News',
            'link' => 'frontend.events',
            'slug' => null,
            'language' => $locale,
            'order_by' => 1,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function menu(array $overrides): int
    {
        return (int) DB::table('page_menus')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'parent_id' => null,
            'name' => 'Menu item',
            'description' => null,
            'type' => 'main',
            'link' => 'custom',
            'slug' => '#',
            'icon' => null,
            'language' => 'en',
            'banner_id' => null,
            'order_by' => 0,
            'status' => 1,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ], $overrides));
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_07_020000_split_events_and_news_archives.php');
        $migration->up();
    }
}
