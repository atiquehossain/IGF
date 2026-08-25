<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FounderLetterNavigationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_idempotently_disables_known_founder_letter_menu_items_only(): void
    {
        $uuids = [
            '68000000-0002-4000-8000-000000000002',
            '69000000-0002-4000-8000-000000000002',
        ];

        foreach ($uuids as $index => $uuid) {
            DB::table('page_menus')->updateOrInsert(['uuid' => $uuid], [
                'name' => "Founder's Letter",
                'type' => 'main',
                'link' => 'frontend.page',
                'slug' => "founder's-letter",
                'parent_id' => null,
                'language' => 'en',
                'order_by' => 1,
                'status' => $index === 0 ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('page_menus')->updateOrInsert(['uuid' => 'test-unrelated-main-menu-item'], [
            'name' => 'Who We Are',
            'type' => 'main',
            'link' => 'frontend.about',
            'slug' => null,
            'parent_id' => null,
            'language' => 'en',
            'order_by' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_25_090000_remove_founders_letter_from_primary_navigation.php');
        $migration->up();
        $migration->up();

        $migration->down();
        $this->assertSame(0, DB::table('page_menus')->whereIn('uuid', $uuids)->where('status', 1)->count());
        $this->assertDatabaseHas('page_menus', ['uuid' => 'test-unrelated-main-menu-item', 'status' => 1]);

        $this->assertSame(0, DB::table('page_menus')->whereIn('uuid', $uuids)->where('status', 1)->count());
        $this->assertDatabaseHas('page_menus', ['uuid' => 'test-unrelated-main-menu-item', 'status' => 1]);
    }
}
