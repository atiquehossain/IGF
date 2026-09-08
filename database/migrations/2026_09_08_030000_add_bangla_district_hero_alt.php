<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNERSHIP_TABLE = 'igf_migration_20260908_030_ownership';

    private const ASSET = 'districts.hero_image_alt_bn';

    public function up(): void
    {
        if (! Schema::hasTable('districts') || Schema::hasColumn('districts', 'hero_image_alt_bn')) {
            return;
        }

        $this->ensureOwnershipTable();
        Schema::table('districts', function (Blueprint $table): void {
            $table->string('hero_image_alt_bn', 255)->nullable()->after('hero_image_alt');
        });
        DB::table(self::OWNERSHIP_TABLE)->insertOrIgnore(['asset' => self::ASSET]);
    }

    public function down(): void
    {
        $owned = Schema::hasTable(self::OWNERSHIP_TABLE)
            && DB::table(self::OWNERSHIP_TABLE)->where('asset', self::ASSET)->exists();

        if ($owned && Schema::hasTable('districts') && Schema::hasColumn('districts', 'hero_image_alt_bn')) {
            Schema::table('districts', function (Blueprint $table): void {
                $table->dropColumn('hero_image_alt_bn');
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
