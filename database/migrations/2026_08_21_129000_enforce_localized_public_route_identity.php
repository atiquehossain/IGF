<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const ROUTE_INDEXES = [
        'pages' => 'pages_language_slug_unique',
        'categories' => 'categories_language_slug_unique',
        'notice_boards' => 'notice_boards_language_slug_unique',
        'annual_reports' => 'annual_reports_language_slug_unique',
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const LOGICAL_INDEXES = [
        'categories' => ['uuid', 'categories_uuid_language_unique'],
        'notice_boards' => ['translation_key', 'notice_boards_translation_language_unique'],
        'page_menus' => ['uuid', 'page_menus_uuid_language_unique'],
    ];

    public function up(): void
    {
        foreach (['notice_boards', 'annual_reports'] as $table) {
            if (DB::table($table)->whereRaw('LENGTH(language) > 10')->exists()) {
                throw new RuntimeException(
                    "{$table} contains a locale longer than 10 characters. Normalize it before enforcing route identity."
                );
            }
        }

        foreach (self::ROUTE_INDEXES as $table => $index) {
            if (Schema::hasIndex($table, $index)) {
                continue;
            }

            $duplicate = DB::table($table)
                ->select(['language', 'slug'])
                ->whereNotNull('language')
                ->whereNotNull('slug')
                ->groupBy('language', 'slug')
                ->havingRaw('COUNT(*) > 1')
                ->limit(1)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    "{$table} contains duplicate locale/slug routes. Resolve them before enforcing public route identity."
                );
            }
        }

        foreach (self::LOGICAL_INDEXES as $table => [$identity, $index]) {
            if (Schema::hasIndex($table, $index)) {
                continue;
            }

            $duplicate = DB::table($table)
                ->select([$identity, 'language'])
                ->whereNotNull($identity)
                ->whereNotNull('language')
                ->groupBy($identity, 'language')
                ->havingRaw('COUNT(*) > 1')
                ->limit(1)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    "{$table} contains duplicate {$identity}/language identities. Resolve them before enforcing translation identity."
                );
            }
        }

        // These legacy tables used TEXT for a short locale code. A bounded
        // VARCHAR is portable and can participate in a MySQL unique index.
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->string('language', 10)->nullable()->change();
        });
        Schema::table('annual_reports', function (Blueprint $table): void {
            $table->string('language', 10)->nullable()->change();
        });

        foreach (self::ROUTE_INDEXES as $table => $index) {
            if (!Schema::hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->unique(['language', 'slug'], $index);
                });
            }
        }


        foreach (self::LOGICAL_INDEXES as $table => [$identity, $index]) {
            if (!Schema::hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($identity, $index): void {
                    $blueprint->unique([$identity, 'language'], $index);
                });
            }
        }
    }

    public function down(): void
    {
        // Localized route identity is a public URL invariant. Rollback must
        // not reopen ambiguous routes or races between concurrent editors.
    }
};
