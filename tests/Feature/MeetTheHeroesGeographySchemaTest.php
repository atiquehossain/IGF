<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MeetTheHeroesGeographySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_columns_indexes_and_null_on_delete_relations_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('divisions', [
            'slug', 'description', 'description_bn',
        ]));
        $this->assertTrue(Schema::hasColumns('districts', [
            'slug', 'description', 'description_bn', 'hero_image', 'hero_image_alt', 'hero_image_alt_bn',
        ]));
        $this->assertTrue(Schema::hasColumn('latest_news', 'district_id'));
        $this->assertTrue(Schema::hasColumns('notice_boards', ['division_id', 'district_id']));
        $this->assertTrue(Schema::hasIndex('divisions', 'divisions_slug_unique'));
        $this->assertTrue(Schema::hasIndex('districts', 'districts_slug_unique'));
        $this->assertTrue(Schema::hasIndex('notice_boards', 'notice_boards_heroes_division_public_index'));
        $this->assertTrue(Schema::hasIndex('notice_boards', 'notice_boards_heroes_district_public_index'));

        $division = Division::query()->where('slug', 'dhaka')->firstOrFail();
        $district = District::query()
            ->where('division_id', $division->id)
            ->where('slug', 'dhaka')
            ->firstOrFail();
        $member = LatestNews::create([
            'name' => 'Scoped member',
            'type' => 'our-members',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'language' => 'en',
            'status' => 1,
        ]);
        $activity = NoticeBoard::create([
            'title' => 'Scoped activity',
            'slug' => 'scoped-activity',
            'content_kind' => 'article',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'language' => 'en',
            'status' => 1,
        ]);

        $district->delete();

        $this->assertNull($member->fresh()->district_id);
        $this->assertNull($activity->fresh()->district_id);
        $this->assertSame($division->id, $member->fresh()->division_id);
        $this->assertSame($division->id, $activity->fresh()->division_id);

        $division->delete();

        $this->assertNull($member->fresh()->division_id);
        $this->assertNull($activity->fresh()->division_id);
    }

    public function test_migration_backfills_stable_global_slugs_for_canonical_locations(): void
    {
        $this->assertSame(8, Division::query()->whereNotNull('slug')->distinct()->count('slug'));
        $this->assertSame(64, District::query()->whereNotNull('slug')->distinct()->count('slug'));
        $this->assertSame('chattogram', Division::query()->where('name', 'Chattogram')->value('slug'));
        $this->assertSame('coxs-bazar', District::query()->where("name", "Cox's Bazar")->value('slug'));
    }

    public function test_geography_rollback_preserves_columns_it_did_not_create(): void
    {
        $migration = require database_path(
            'migrations/2026_09_08_020000_add_meet_the_heroes_geography.php'
        );
        $migration->down();

        Schema::table('divisions', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });

        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('divisions', 'description'));
        $this->assertFalse(Schema::hasColumn('divisions', 'slug'));

        // Leave the standard migrated schema in place for test runners that
        // reuse the in-memory connection across multiple test cases.
        $migration->up();
    }
}
