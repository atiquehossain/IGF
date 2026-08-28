<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopNavigationNestingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const WORKSHOP_UUID = '68000000-0304-4000-8000-000000000001';

    private const WORKSHOP_FALLBACK_UUID = '68000000-0304-4000-8000-000000000002';

    public function test_it_adds_the_missing_grandchild_on_a_clean_navigation_and_is_idempotent(): void
    {
        [, $youthId] = $this->hierarchy('en');
        $opportunitiesId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Opportunities',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'en',
            'status' => 1,
        ]);
        $careerId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Careers',
            'link' => 'frontend.jobs.index',
            'parent_id' => $opportunitiesId,
            'language' => 'en',
            'status' => 1,
        ]);
        $unrelatedId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Donor portal',
            'link' => 'custom',
            'slug' => '/donor-portal',
            'language' => 'en',
            'order_by' => 88,
            'status' => 1,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_UUID,
            'language' => 'en',
            'name' => 'Workshop',
            'link' => 'frontend.workshops.index',
            'parent_id' => $youthId,
            'order_by' => 0,
            'status' => 1,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, $this->workshopRows('en')->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('page_menus', ['id' => $opportunitiesId, 'status' => 1]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $careerId,
            'parent_id' => $opportunitiesId,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $unrelatedId,
            'name' => 'Donor portal',
            'parent_id' => null,
            'order_by' => 88,
            'status' => 1,
        ]);
    }

    public function test_it_reuses_a_customized_legacy_workshop_without_overwriting_editor_fields(): void
    {
        [, $youthId] = $this->hierarchy('en');
        $opportunitiesId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Opportunities',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'en',
        ]);
        $workshopId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community learning sessions',
            'description' => 'An administrator-authored label and hint.',
            'link' => 'custom',
            'slug' => '/workshops',
            'parent_id' => $opportunitiesId,
            'language' => 'en',
            'order_by' => 41,
            'status' => 0,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'id' => $workshopId,
            'name' => 'Community learning sessions',
            'description' => 'An administrator-authored label and hint.',
            'link' => 'custom',
            'slug' => '/workshops',
            'parent_id' => $youthId,
            'order_by' => 41,
            'status' => 0,
        ]);
        $this->assertSame(1, $this->workshopRows('en')->whereNull('deleted_at')->count());
        $this->assertDatabaseMissing('page_menus', ['uuid' => self::WORKSHOP_UUID, 'language' => 'en']);
    }

    public function test_it_creates_the_same_logical_workshop_identity_for_each_existing_locale(): void
    {
        [, $englishYouthId] = $this->hierarchy('en');
        [, $banglaYouthId] = $this->hierarchy('bn');

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_UUID,
            'language' => 'en',
            'name' => 'Workshop',
            'parent_id' => $englishYouthId,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_UUID,
            'language' => 'bn',
            'name' => 'কর্মশালা',
            'parent_id' => $banglaYouthId,
        ]);
        $this->assertSame(2, DB::table('page_menus')->where('uuid', self::WORKSHOP_UUID)->count());
    }

    public function test_it_uses_one_globally_compatible_uuid_when_the_primary_identity_is_owned_in_another_locale(): void
    {
        [, $englishYouthId] = $this->hierarchy('en');
        [, $banglaYouthId] = $this->hierarchy('bn');
        $ownedId = $this->menu([
            'uuid' => self::WORKSHOP_UUID,
            'name' => 'Policies',
            'link' => 'custom',
            'slug' => '/policies',
            'language' => 'fr',
            'status' => 1,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'id' => $ownedId,
            'uuid' => self::WORKSHOP_UUID,
            'language' => 'fr',
            'name' => 'Policies',
            'slug' => '/policies',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_FALLBACK_UUID,
            'language' => 'en',
            'parent_id' => $englishYouthId,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_FALLBACK_UUID,
            'language' => 'bn',
            'parent_id' => $banglaYouthId,
        ]);
        $this->assertSame(2, DB::table('page_menus')->where('uuid', self::WORKSHOP_FALLBACK_UUID)->count());
        $this->assertSame(0, $this->workshopRows('en')->where('uuid', self::WORKSHOP_UUID)->count());
        $this->assertSame(0, $this->workshopRows('bn')->where('uuid', self::WORKSHOP_UUID)->count());
    }

    public function test_it_does_nothing_when_the_required_our_work_or_youth_parent_is_missing(): void
    {
        $unrelatedId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community resources',
            'link' => 'custom',
            'slug' => '/resources',
            'language' => 'en',
            'status' => 1,
        ]);
        $this->menu([
            'uuid' => '67000000-0000-4000-8000-000000000003',
            'name' => 'Our Work',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'bn',
            'status' => 1,
        ]);

        $this->runMigrationTwice();

        $this->assertSame(0, $this->workshopRows('en')->count());
        $this->assertSame(0, $this->workshopRows('bn')->count());
        $this->assertDatabaseHas('page_menus', [
            'id' => $unrelatedId,
            'name' => 'Community resources',
            'status' => 1,
        ]);
    }

    public function test_it_preserves_a_workshop_tree_when_reparenting_would_create_a_fourth_level(): void
    {
        [, $youthId] = $this->hierarchy('en');
        $opportunitiesId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Opportunities',
            'link' => 'custom',
            'slug' => '#',
            'language' => 'en',
            'status' => 1,
        ]);
        $workshopId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Workshop',
            'link' => 'frontend.workshops.index',
            'parent_id' => $opportunitiesId,
            'language' => 'en',
            'order_by' => 9,
            'status' => 1,
        ]);
        $sessionId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Leadership sessions',
            'link' => 'custom',
            'slug' => '/workshops/leadership',
            'parent_id' => $workshopId,
            'language' => 'en',
            'status' => 1,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'id' => $workshopId,
            'parent_id' => $opportunitiesId,
            'order_by' => 9,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $sessionId,
            'parent_id' => $workshopId,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', ['id' => $opportunitiesId, 'status' => 1]);
        $this->assertDatabaseMissing('page_menus', [
            'link' => 'frontend.workshops.index',
            'parent_id' => $youthId,
        ]);
        $this->assertSame(2, DB::table('page_menus')->whereIn('id', [$workshopId, $sessionId])->count());
    }

    public function test_it_disables_only_duplicate_known_workshop_destinations(): void
    {
        [, $youthId] = $this->hierarchy('en');
        $preferredId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Workshop calendar',
            'link' => 'frontend.workshops.index',
            'language' => 'en',
            'order_by' => 2,
            'status' => 1,
        ]);
        $duplicateId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old workshop shortcut',
            'link' => 'custom',
            'slug' => '/workshops',
            'language' => 'en',
            'order_by' => 8,
            'status' => 1,
        ]);
        $similarButUnrelatedId = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Workshop resources',
            'link' => 'custom',
            'slug' => '/workshop-resources',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'id' => $preferredId,
            'parent_id' => $youthId,
            'name' => 'Workshop calendar',
            'order_by' => 2,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $duplicateId,
            'name' => 'Old workshop shortcut',
            'order_by' => 8,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $similarButUnrelatedId,
            'slug' => '/workshop-resources',
            'status' => 1,
        ]);
    }

    public function test_it_respects_a_workshop_tombstone_and_never_repurposes_an_owned_stable_uuid(): void
    {
        $this->hierarchy('en');
        $deletedId = $this->menu([
            'uuid' => self::WORKSHOP_UUID,
            'name' => 'Workshop',
            'link' => 'frontend.workshops.index',
            'language' => 'en',
            'deleted_at' => now(),
            'deleted_by' => 123,
        ]);

        [, $frenchYouthId] = $this->hierarchy('fr');
        $ownedStableId = $this->menu([
            'uuid' => self::WORKSHOP_UUID,
            'name' => 'Policies',
            'link' => 'custom',
            'slug' => '/policies',
            'language' => 'fr',
            'status' => 0,
        ]);

        $this->runMigrationTwice();

        $this->assertDatabaseHas('page_menus', [
            'id' => $deletedId,
            'uuid' => self::WORKSHOP_UUID,
            'deleted_by' => 123,
        ]);
        $this->assertSame(0, $this->workshopRows('en')->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('page_menus', [
            'id' => $ownedStableId,
            'name' => 'Policies',
            'slug' => '/policies',
            'status' => 0,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => self::WORKSHOP_FALLBACK_UUID,
            'language' => 'fr',
            'link' => 'frontend.workshops.index',
            'parent_id' => $frenchYouthId,
        ]);
    }

    public function test_down_is_an_explicit_no_op_for_the_irreversible_content_upgrade(): void
    {
        [, $youthId] = $this->hierarchy('en');
        $migration = require database_path('migrations/2026_08_28_110000_nest_workshop_under_youth_development.php');
        $migration->up();
        $workshop = $this->workshopRows('en')->whereNull('deleted_at')->first();

        $migration->down();

        $this->assertNotNull($workshop);
        $this->assertDatabaseHas('page_menus', [
            'id' => $workshop->id,
            'uuid' => self::WORKSHOP_UUID,
            'parent_id' => $youthId,
            'status' => 1,
        ]);
    }

    /** @return array{int, int} */
    private function hierarchy(string $locale): array
    {
        $ourWorkId = $this->menu([
            'uuid' => '67000000-0000-4000-8000-000000000003',
            'name' => $locale === 'bn' ? 'আমাদের কাজ' : 'Our Work',
            'link' => 'custom',
            'slug' => '#',
            'language' => $locale,
            'order_by' => 2,
        ]);
        $youthId = $this->menu([
            'uuid' => '68000000-0003-4000-8000-000000000004',
            'name' => $locale === 'bn' ? 'যুব উন্নয়ন' : 'Youth Development',
            'link' => 'frontend.page',
            'slug' => 'youth-development',
            'parent_id' => $ourWorkId,
            'language' => $locale,
            'order_by' => 3,
        ]);

        return [$ourWorkId, $youthId];
    }

    /** @param array<string, mixed> $overrides */
    private function menu(array $overrides): int
    {
        return (int) DB::table('page_menus')->insertGetId(array_merge([
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
            'uuid' => (string) Str::uuid(),
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ], $overrides));
    }

    private function workshopRows(string $locale)
    {
        return DB::table('page_menus')
            ->where('language', $locale)
            ->where('type', 'main')
            ->where(function ($query): void {
                $query->where('link', 'frontend.workshops.index')
                    ->orWhere(function ($legacy): void {
                        $legacy->where('link', 'custom')->whereIn('slug', ['/workshops', 'workshops']);
                    })
                    ->orWhereIn('link', ['/workshops', 'workshops']);
            });
    }

    private function runMigrationTwice(): void
    {
        $migration = require database_path('migrations/2026_08_28_110000_nest_workshop_under_youth_development.php');
        $migration->up();
        $migration->up();
    }
}
