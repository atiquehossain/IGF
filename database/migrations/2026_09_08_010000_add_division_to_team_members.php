<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNERSHIP_TABLE = 'igf_migration_20260908_010_ownership';

    private const ASSET = 'latest_news.division_id';

    public function up(): void
    {
        if (!Schema::hasTable('latest_news')
            || !Schema::hasTable('divisions')
            || Schema::hasColumn('latest_news', 'division_id')) {
            return;
        }

        $this->ensureOwnershipTable();
        Schema::table('latest_news', function (Blueprint $table): void {
            $table->foreignId('division_id')
                ->nullable()
                ->after('team_group_id')
                ->constrained('divisions')
                ->nullOnDelete();
        });
        DB::table(self::OWNERSHIP_TABLE)->insertOrIgnore(['asset' => self::ASSET]);
    }

    public function down(): void
    {
        $owned = Schema::hasTable(self::OWNERSHIP_TABLE)
            && DB::table(self::OWNERSHIP_TABLE)->where('asset', self::ASSET)->exists();

        if ($owned && Schema::hasTable('latest_news') && Schema::hasColumn('latest_news', 'division_id')) {
            Schema::table('latest_news', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('division_id');
            });
        }

        Schema::dropIfExists(self::OWNERSHIP_TABLE);
    }

    private function ensureOwnershipTable(): void
    {
        if (Schema::hasTable(self::OWNERSHIP_TABLE)) {
            return;
        }

        Schema::create(self::OWNERSHIP_TABLE, function (Blueprint $table): void {
            $table->string('asset', 160)->primary();
        });
    }
};
