<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\LatestNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamMemberDivisionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nullable_division_migration_is_reentrant_and_preserves_existing_members(): void
    {
        $migration = require database_path(
            'migrations/2026_09_08_010000_add_division_to_team_members.php'
        );

        $migration->down();
        $this->assertFalse(Schema::hasColumn('latest_news', 'division_id'));

        $member = LatestNews::create([
            'name' => 'Existing national team member',
            'type' => 'our-members',
            'description' => 'Coordinator',
            'language' => 'en',
            'status' => 1,
        ]);

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('latest_news', 'division_id'));
        $this->assertDatabaseHas('latest_news', [
            'id' => $member->id,
            'name' => 'Existing national team member',
            'division_id' => null,
        ]);

        $division = Division::create(['name' => 'Directory Test Division', 'status' => 1]);
        $member->update(['division_id' => $division->id]);
        $this->assertSame($division->id, $member->fresh()->division?->id);

        $division->delete();
        $this->assertNull($member->fresh()->division_id);
    }

    public function test_rollback_preserves_a_division_column_the_migration_did_not_create(): void
    {
        $migration = require database_path(
            'migrations/2026_09_08_010000_add_division_to_team_members.php'
        );
        $migration->down();

        Schema::table('latest_news', function (Blueprint $table): void {
            $table->unsignedBigInteger('division_id')->nullable();
        });

        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('latest_news', 'division_id'));
    }
}
