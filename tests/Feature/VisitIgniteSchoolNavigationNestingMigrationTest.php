<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitIgniteSchoolNavigationNestingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const EDUCATION_UUID = '68000000-0003-4000-8000-000000000002';

    private const VISIT_SCHOOL_UUID = '68000000-0003-4000-8000-000000000003';

    public function test_it_nests_each_localized_visit_link_under_education_and_is_idempotent(): void
    {
        [$englishRoot, $englishEducation] = $this->hierarchy('en');
        [$banglaRoot, $banglaEducation] = $this->hierarchy('bn');
        $englishVisit = $this->visitMenu('en', $englishRoot, 'Visit our school');
        $banglaVisit = $this->visitMenu('bn', $banglaRoot, 'আমাদের স্কুল দেখুন');

        $this->runMigration();
        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $englishVisit,
            'parent_id' => $englishEducation,
            'name' => 'Visit our school',
            'link' => 'frontend.category',
            'slug' => 'visit-ignite-school',
            'order_by' => 0,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $banglaVisit,
            'parent_id' => $banglaEducation,
            'name' => 'আমাদের স্কুল দেখুন',
            'order_by' => 0,
            'status' => 1,
        ]);

        DB::table('page_menus')->where('id', $englishVisit)->update([
            'name' => 'Administrator label',
            'order_by' => 7,
            'status' => 0,
        ]);
        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $englishVisit,
            'parent_id' => $englishEducation,
            'name' => 'Administrator label',
            'order_by' => 7,
            'status' => 0,
        ]);
    }

    public function test_it_does_not_create_an_unsupported_fourth_navigation_level(): void
    {
        [$root, $education] = $this->hierarchy('en');
        $visit = $this->visitMenu('en', $root, 'Visit Ignite School');
        $child = $this->menu([
            'parent_id' => $visit,
            'name' => 'Campus details',
            'slug' => '/campus-details',
            'order_by' => 0,
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', ['id' => $visit, 'parent_id' => $root]);
        $this->assertDatabaseHas('page_menus', ['id' => $child, 'parent_id' => $visit]);
        $this->assertDatabaseHas('page_menus', ['id' => $education, 'parent_id' => $root]);
    }

    public function test_it_upgrades_the_historical_navigation_uuids(): void
    {
        $root = $this->menu([
            'uuid' => '69000000-0000-4000-8000-000000000003',
            'name' => 'Our Work',
            'language' => 'en',
            'order_by' => 2,
        ]);
        $education = $this->menu([
            'uuid' => '69000000-0003-4000-8000-000000000002',
            'parent_id' => $root,
            'name' => 'Inclusive Education',
            'link' => 'frontend.page',
            'slug' => 'education',
            'language' => 'en',
            'order_by' => 1,
        ]);
        $visit = $this->menu([
            'uuid' => '69000000-0003-4000-8000-000000000003',
            'parent_id' => $root,
            'name' => 'Visit Ignite School',
            'link' => 'frontend.category',
            'slug' => 'visit-ignite-school',
            'language' => 'en',
            'order_by' => 2,
        ]);

        $this->runMigration();

        $this->assertDatabaseHas('page_menus', [
            'id' => $visit,
            'parent_id' => $education,
            'order_by' => 0,
        ]);
    }

    public function test_it_leaves_deleted_visit_links_and_missing_education_locales_untouched(): void
    {
        [$root] = $this->hierarchy('en');
        $deletedVisit = $this->visitMenu('en', $root, 'Deleted school link', ['deleted_at' => now()]);
        $frenchRoot = $this->menu([
            'uuid' => (string) Str::uuid(),
            'name' => 'Notre travail',
            'language' => 'fr',
            'order_by' => 2,
        ]);
        $frenchVisit = $this->visitMenu('fr', $frenchRoot, 'Visiter notre école');

        $this->runMigration();

        $this->assertSoftDeleted('page_menus', [
            'id' => $deletedVisit,
            'parent_id' => $root,
        ]);
        $this->assertDatabaseHas('page_menus', ['id' => $frenchVisit, 'parent_id' => $frenchRoot]);
    }

    /** @return array{int, int} */
    private function hierarchy(string $locale): array
    {
        $root = $this->menu([
            'uuid' => '67000000-0000-4000-8000-000000000003',
            'name' => $locale === 'bn' ? 'আমাদের কাজ' : 'Our Work',
            'language' => $locale,
            'order_by' => 2,
        ]);
        $education = $this->menu([
            'uuid' => self::EDUCATION_UUID,
            'parent_id' => $root,
            'name' => $locale === 'bn' ? 'অন্তর্ভুক্তিমূলক শিক্ষা' : 'Inclusive Education',
            'link' => 'frontend.page',
            'slug' => 'education',
            'language' => $locale,
            'order_by' => 1,
        ]);

        return [$root, $education];
    }

    /** @param array<string, mixed> $overrides */
    private function visitMenu(string $locale, int $parentId, string $name, array $overrides = []): int
    {
        return $this->menu(array_merge([
            'uuid' => self::VISIT_SCHOOL_UUID,
            'parent_id' => $parentId,
            'name' => $name,
            'link' => 'frontend.category',
            'slug' => 'visit-ignite-school',
            'language' => $locale,
            'order_by' => 2,
        ], $overrides));
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

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_07_010000_nest_visit_ignite_school_under_education.php');
        $migration->up();
    }
}
