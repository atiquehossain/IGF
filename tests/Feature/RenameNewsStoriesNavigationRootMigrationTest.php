<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RenameNewsStoriesNavigationRootMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const EN_ROOT_UUID = '67000000-0000-4000-8000-000000000005';

    private const BN_ROOT_UUID = '69000000-0000-4000-8000-000000000005';

    public function test_it_renames_only_the_bundled_root_labels_and_preserves_navigation_structure(): void
    {
        $englishRoot = $this->menu([
            'uuid' => self::EN_ROOT_UUID,
            'name' => 'News & Stories',
            'description' => 'English navigation hint.',
            'language' => 'en',
            'order_by' => 14,
            'status' => 0,
            'created_by' => 71,
            'updated_by' => 72,
        ]);
        $banglaRoot = $this->menu([
            // Current bilingual snapshots share this logical UUID across locales.
            'uuid' => self::EN_ROOT_UUID,
            'name' => 'সংবাদ ও গল্প',
            'description' => 'বাংলা নেভিগেশন নির্দেশনা।',
            'language' => 'bn',
            'order_by' => 9,
            'status' => 1,
            'created_by' => 81,
            'updated_by' => 82,
        ]);
        $englishChild = $this->menu([
            'parent_id' => $englishRoot,
            'name' => 'Events',
            'description' => 'Keep this child unchanged.',
            'link' => 'frontend.events',
            'slug' => null,
            'language' => 'en',
            'order_by' => 3,
            'status' => 0,
        ]);
        $banglaChild = $this->menu([
            'parent_id' => $banglaRoot,
            'name' => 'সংবাদ',
            'link' => 'frontend.news',
            'slug' => null,
            'language' => 'bn',
            'order_by' => 5,
        ]);

        $englishBefore = $this->navigationState($englishRoot);
        $banglaBefore = $this->navigationState($banglaRoot);
        $englishChildBefore = $this->navigationState($englishChild);
        $banglaChildBefore = $this->navigationState($banglaChild);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame('Stories', DB::table('page_menus')->where('id', $englishRoot)->value('name'));
        $this->assertSame('গল্প', DB::table('page_menus')->where('id', $banglaRoot)->value('name'));
        $this->assertSame($englishBefore, $this->navigationState($englishRoot));
        $this->assertSame($banglaBefore, $this->navigationState($banglaRoot));
        $this->assertSame($englishChildBefore, $this->navigationState($englishChild));
        $this->assertSame($banglaChildBefore, $this->navigationState($banglaChild));
        $this->assertSame('Events', DB::table('page_menus')->where('id', $englishChild)->value('name'));
        $this->assertSame('সংবাদ', DB::table('page_menus')->where('id', $banglaChild)->value('name'));
    }

    public function test_it_preserves_custom_deleted_and_non_root_labels(): void
    {
        $customEnglish = $this->menu([
            'uuid' => self::EN_ROOT_UUID,
            'name' => 'Community updates',
            'language' => 'en',
        ]);
        $deletedBangla = $this->menu([
            'uuid' => self::BN_ROOT_UUID,
            'name' => 'সংবাদ ও গল্প',
            'language' => 'bn',
            'deleted_at' => now(),
            'deleted_by' => 91,
        ]);
        $lookalike = $this->menu([
            'name' => 'News & Stories',
            'language' => 'en',
        ]);
        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $customEnglish,
            'name' => 'Community updates',
        ]);
        $this->assertSoftDeleted('page_menus', [
            'id' => $deletedBangla,
            'name' => 'সংবাদ ও গল্প',
            'deleted_by' => 91,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $lookalike,
            'name' => 'News & Stories',
        ]);
    }

    public function test_it_preserves_a_matching_deterministic_item_when_it_is_not_a_root(): void
    {
        $parent = $this->menu([
            'name' => 'Parent',
            'language' => 'bn',
        ]);
        $deterministicChild = $this->menu([
            'uuid' => self::EN_ROOT_UUID,
            'parent_id' => $parent,
            'name' => 'সংবাদ ও গল্প',
            'language' => 'bn',
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $deterministicChild,
            'parent_id' => $parent,
            'name' => 'সংবাদ ও গল্প',
        ]);
    }

    public function test_it_supports_the_historical_alternate_root_uuids(): void
    {
        $englishRoot = $this->menu([
            'uuid' => self::BN_ROOT_UUID,
            'name' => 'News & Stories',
            'language' => 'en',
        ]);
        $banglaRoot = $this->menu([
            'uuid' => self::BN_ROOT_UUID,
            'name' => 'সংবাদ ও গল্প',
            'language' => 'bn',
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', ['id' => $englishRoot, 'name' => 'Stories']);
        $this->assertDatabaseHas('page_menus', ['id' => $banglaRoot, 'name' => 'গল্প']);
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

    /** @return array<string, mixed> */
    private function navigationState(int $id): array
    {
        $row = DB::table('page_menus')->where('id', $id)->first();

        return [
            'uuid' => $row->uuid,
            'parent_id' => $row->parent_id,
            'description' => $row->description,
            'type' => $row->type,
            'link' => $row->link,
            'slug' => $row->slug,
            'icon' => $row->icon,
            'language' => $row->language,
            'banner_id' => $row->banner_id,
            'order_by' => $row->order_by,
            'status' => $row->status,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
            'deleted_by' => $row->deleted_by,
            'deleted_at' => $row->deleted_at,
        ];
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_08_000000_rename_news_stories_navigation_root.php');
        $migration->up();
    }
}
