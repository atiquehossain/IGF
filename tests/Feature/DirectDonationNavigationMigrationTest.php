<?php

namespace Tests\Feature;

use App\Models\DonationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectDonationNavigationMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const DIRECT_CAUSE_UUID = '55555555-5555-4555-8555-000000000001';

    private const MENU_UUID = '68000000-0006-4000-8000-000000000001';

    public function test_migration_assigns_the_direct_role_and_retargets_the_existing_menu_without_duplication(): void
    {
        DonationType::query()->where('purpose_key', 'direct')->update(['purpose_key' => null]);
        $cause = DonationType::withTrashed()->where('uuid', self::DIRECT_CAUSE_UUID)->first();
        if (!$cause) {
            $cause = DonationType::create([
                'uuid' => self::DIRECT_CAUSE_UUID,
                'slug' => 'where-it-is-needed-most',
                'name' => 'Where it is needed most',
                'description' => 'Flexible support for active community priorities.',
                'destination_type' => 'unrestricted',
                'status' => 1,
            ]);
        }

        $rootId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Donate',
            'link' => 'custom',
            'slug' => '#',
            'parent_id' => null,
        ]);
        $menuId = $this->menu([
            'uuid' => self::MENU_UUID,
            'name' => 'Make a Donation',
            'description' => 'Administrator-authored navigation hint.',
            'link' => 'frontend.donate.index',
            'parent_id' => $rootId,
            'status' => 0,
        ]);

        $migration = require database_path('migrations/2026_08_29_090000_add_direct_donation_page.php');
        $migration->up();
        $migration->up();

        $this->assertSame('direct', $cause->fresh()->purpose_key);
        $this->assertDatabaseHas('page_menus', [
            'id' => $menuId,
            'uuid' => self::MENU_UUID,
            'name' => 'Make a Donation',
            'description' => 'Administrator-authored navigation hint.',
            'link' => 'frontend.donate.direct',
            'slug' => null,
            'status' => 0,
        ]);
        $this->assertSame(1, DB::table('page_menus')->where('uuid', self::MENU_UUID)->count());
    }

    public function test_migration_preserves_an_existing_direct_role_and_editor_customized_menu_destination(): void
    {
        DonationType::query()->where('purpose_key', 'direct')->update(['purpose_key' => null]);
        $replacement = DonationType::create([
            'name' => 'Administrator selected direct fund',
            'description' => 'A visitor-ready direct donation destination.',
            'purpose_key' => 'direct',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Priority Fund',
            'status' => 1,
        ]);
        $menuId = $this->menu([
            'uuid' => self::MENU_UUID,
            'name' => 'Give directly',
            'link' => 'custom',
            'slug' => '/give-directly',
            'parent_id' => null,
        ]);

        $migration = require database_path('migrations/2026_08_29_090000_add_direct_donation_page.php');
        $migration->up();

        $this->assertSame('direct', $replacement->fresh()->purpose_key);
        $this->assertDatabaseHas('page_menus', [
            'id' => $menuId,
            'name' => 'Give directly',
            'link' => 'custom',
            'slug' => '/give-directly',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function menu(array $overrides): int
    {
        return (int) DB::table('page_menus')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Menu item',
            'description' => null,
            'parent_id' => null,
            'type' => 'main',
            'link' => 'custom',
            'slug' => '#',
            'icon' => null,
            'language' => 'en',
            'banner_id' => null,
            'order_by' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
