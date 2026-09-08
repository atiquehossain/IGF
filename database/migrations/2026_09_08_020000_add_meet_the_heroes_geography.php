<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const OWNERSHIP_TABLE = 'igf_migration_20260908_020_ownership';

    public function up(): void
    {
        $this->ensureOwnershipTable();
        $this->addDivisionContent();
        $this->addDistrictContent();
        $this->addMemberDistrict();
        $this->addPublicationGeography();
        $this->backfillSlugs('divisions', 'division');
        $this->backfillSlugs('districts', 'district');
        $this->addSlugIndexes();
    }

    public function down(): void
    {
        if (Schema::hasTable('notice_boards')) {
            $dropDivisionIndex = $this->owns('notice_boards.index.division_public');
            $dropDistrictIndex = $this->owns('notice_boards.index.district_public');
            $dropDistrict = $this->owns('notice_boards.district_id');
            $dropDivision = $this->owns('notice_boards.division_id');
            Schema::table('notice_boards', function (Blueprint $table) use ($dropDivisionIndex, $dropDistrictIndex, $dropDistrict, $dropDivision): void {
                if ($dropDivisionIndex && Schema::hasIndex('notice_boards', 'notice_boards_heroes_division_public_index')) {
                    $table->dropIndex('notice_boards_heroes_division_public_index');
                }
                if ($dropDistrictIndex && Schema::hasIndex('notice_boards', 'notice_boards_heroes_district_public_index')) {
                    $table->dropIndex('notice_boards_heroes_district_public_index');
                }
                if ($dropDistrict && Schema::hasColumn('notice_boards', 'district_id')) {
                    $table->dropConstrainedForeignId('district_id');
                }
                if ($dropDivision && Schema::hasColumn('notice_boards', 'division_id')) {
                    $table->dropConstrainedForeignId('division_id');
                }
            });
        }

        if ($this->owns('latest_news.district_id')
            && Schema::hasTable('latest_news')
            && Schema::hasColumn('latest_news', 'district_id')) {
            Schema::table('latest_news', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('district_id');
            });
        }

        if (Schema::hasTable('districts')) {
            $dropSlugIndex = $this->owns('districts.index.slug_unique');
            $ownedColumns = array_values(array_filter(
                ['hero_image_alt', 'hero_image', 'description_bn', 'description', 'slug'],
                fn (string $column): bool => $this->owns('districts.'.$column)
                    && Schema::hasColumn('districts', $column)
            ));
            Schema::table('districts', function (Blueprint $table) use ($dropSlugIndex, $ownedColumns): void {
                if ($dropSlugIndex && Schema::hasIndex('districts', 'districts_slug_unique')) {
                    $table->dropUnique('districts_slug_unique');
                }
                foreach ($ownedColumns as $column) {
                    $table->dropColumn($column);
                }
            });
        }

        if (Schema::hasTable('divisions')) {
            $dropSlugIndex = $this->owns('divisions.index.slug_unique');
            $ownedColumns = array_values(array_filter(
                ['description_bn', 'description', 'slug'],
                fn (string $column): bool => $this->owns('divisions.'.$column)
                    && Schema::hasColumn('divisions', $column)
            ));
            Schema::table('divisions', function (Blueprint $table) use ($dropSlugIndex, $ownedColumns): void {
                if ($dropSlugIndex && Schema::hasIndex('divisions', 'divisions_slug_unique')) {
                    $table->dropUnique('divisions_slug_unique');
                }
                foreach ($ownedColumns as $column) {
                    $table->dropColumn($column);
                }
            });
        }

        Schema::dropIfExists(self::OWNERSHIP_TABLE);
    }

    private function addDivisionContent(): void
    {
        if (!Schema::hasTable('divisions')) {
            return;
        }

        $missing = array_values(array_filter(
            ['slug', 'description', 'description_bn'],
            fn (string $column): bool => ! Schema::hasColumn('divisions', $column)
        ));
        Schema::table('divisions', function (Blueprint $table) use ($missing): void {
            if (in_array('slug', $missing, true)) {
                $table->string('slug', 160)->nullable()->after('name');
            }
            if (in_array('description', $missing, true)) {
                $table->text('description')->nullable()->after('slug');
            }
            if (in_array('description_bn', $missing, true)) {
                $table->text('description_bn')->nullable()->after('description');
            }
        });
        foreach ($missing as $column) {
            $this->recordOwnership('divisions.'.$column);
        }
    }

    private function addDistrictContent(): void
    {
        if (!Schema::hasTable('districts')) {
            return;
        }

        $missing = array_values(array_filter(
            ['slug', 'description', 'description_bn', 'hero_image', 'hero_image_alt'],
            fn (string $column): bool => ! Schema::hasColumn('districts', $column)
        ));
        Schema::table('districts', function (Blueprint $table) use ($missing): void {
            if (in_array('slug', $missing, true)) {
                $table->string('slug', 160)->nullable()->after('name');
            }
            if (in_array('description', $missing, true)) {
                $table->text('description')->nullable()->after('slug');
            }
            if (in_array('description_bn', $missing, true)) {
                $table->text('description_bn')->nullable()->after('description');
            }
            if (in_array('hero_image', $missing, true)) {
                $table->text('hero_image')->nullable()->after('description_bn');
            }
            if (in_array('hero_image_alt', $missing, true)) {
                $table->string('hero_image_alt', 255)->nullable()->after('hero_image');
            }
        });
        foreach ($missing as $column) {
            $this->recordOwnership('districts.'.$column);
        }
    }

    private function addMemberDistrict(): void
    {
        if (!Schema::hasTable('latest_news')
            || !Schema::hasTable('districts')
            || Schema::hasColumn('latest_news', 'district_id')) {
            return;
        }

        Schema::table('latest_news', function (Blueprint $table): void {
            $table->foreignId('district_id')
                ->nullable()
                ->after('division_id')
                ->constrained('districts')
                ->nullOnDelete();
        });
        $this->recordOwnership('latest_news.district_id');
    }

    private function addPublicationGeography(): void
    {
        if (!Schema::hasTable('notice_boards')) {
            return;
        }

        $addDivision = Schema::hasTable('divisions') && ! Schema::hasColumn('notice_boards', 'division_id');
        $addDistrict = Schema::hasTable('districts') && ! Schema::hasColumn('notice_boards', 'district_id');
        Schema::table('notice_boards', function (Blueprint $table) use ($addDivision, $addDistrict): void {
            if ($addDivision) {
                $table->foreignId('division_id')
                    ->nullable()
                    ->after('content_kind')
                    ->constrained('divisions')
                    ->nullOnDelete();
            }
            if ($addDistrict) {
                $table->foreignId('district_id')
                    ->nullable()
                    ->after('division_id')
                    ->constrained('districts')
                    ->nullOnDelete();
            }
        });
        if ($addDivision) {
            $this->recordOwnership('notice_boards.division_id');
        }
        if ($addDistrict) {
            $this->recordOwnership('notice_boards.district_id');
        }

        $addDivisionIndex = Schema::hasColumn('notice_boards', 'division_id')
            && ! Schema::hasIndex('notice_boards', 'notice_boards_heroes_division_public_index');
        $addDistrictIndex = Schema::hasColumn('notice_boards', 'district_id')
            && ! Schema::hasIndex('notice_boards', 'notice_boards_heroes_district_public_index');
        Schema::table('notice_boards', function (Blueprint $table) use ($addDivisionIndex, $addDistrictIndex): void {
            if ($addDivisionIndex) {
                $table->index(
                    ['division_id', 'status', 'published_at'],
                    'notice_boards_heroes_division_public_index'
                );
            }
            if ($addDistrictIndex) {
                $table->index(
                    ['district_id', 'status', 'published_at'],
                    'notice_boards_heroes_district_public_index'
                );
            }
        });
        if ($addDivisionIndex) {
            $this->recordOwnership('notice_boards.index.division_public');
        }
        if ($addDistrictIndex) {
            $this->recordOwnership('notice_boards.index.district_public');
        }
    }

    private function backfillSlugs(string $table, string $fallbackPrefix): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'slug')) {
            return;
        }

        $used = [];
        foreach (DB::table($table)->orderBy('id')->get(['id', 'name', 'slug']) as $row) {
            $base = Str::slug((string) ($row->slug ?: $row->name));
            $base = mb_substr($base !== '' ? $base : $fallbackPrefix . '-' . $row->id, 0, 150);
            $slug = $base;
            if (isset($used[$slug])) {
                $suffix = '-' . $row->id;
                $slug = mb_substr($base, 0, 160 - strlen($suffix)) . $suffix;
            }
            $used[$slug] = true;

            if ((string) $row->slug !== $slug) {
                DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
            }
        }
    }

    private function addSlugIndexes(): void
    {
        if (Schema::hasTable('divisions')
            && Schema::hasColumn('divisions', 'slug')
            && !Schema::hasIndex('divisions', 'divisions_slug_unique')) {
            Schema::table('divisions', function (Blueprint $table): void {
                $table->unique('slug', 'divisions_slug_unique');
            });
            $this->recordOwnership('divisions.index.slug_unique');
        }

        // District URLs do not contain a division segment, so a district slug
        // must be globally unique rather than merely unique inside its parent.
        if (Schema::hasTable('districts')
            && Schema::hasColumn('districts', 'slug')
            && !Schema::hasIndex('districts', 'districts_slug_unique')) {
            Schema::table('districts', function (Blueprint $table): void {
                $table->unique('slug', 'districts_slug_unique');
            });
            $this->recordOwnership('districts.index.slug_unique');
        }
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

    private function recordOwnership(string $asset): void
    {
        DB::table(self::OWNERSHIP_TABLE)->insertOrIgnore(['asset' => $asset]);
    }

    private function owns(string $asset): bool
    {
        return Schema::hasTable(self::OWNERSHIP_TABLE)
            && DB::table(self::OWNERSHIP_TABLE)->where('asset', $asset)->exists();
    }
};
