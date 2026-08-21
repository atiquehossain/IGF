<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalizedRouteIdentityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_slug_is_unique_within_a_locale_but_reusable_in_another_locale(): void
    {
        $cases = [
            'pages' => 'pages_language_slug_unique',
            'categories' => 'categories_language_slug_unique',
            'notice_boards' => 'notice_boards_language_slug_unique',
            'annual_reports' => 'annual_reports_language_slug_unique',
        ];

        foreach ($cases as $table => $index) {
            $slug = 'route-' . Str::lower(Str::random(12));

            $this->assertTrue(Schema::hasIndex($table, $index), "Missing {$index}.");
            DB::table($table)->insert($this->row($table, 'en', $slug));
            DB::table($table)->insert($this->row($table, 'bn', $slug));

            try {
                DB::table($table)->insert($this->row($table, 'en', $slug));
                $this->fail("{$table} accepted a duplicate English public route.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_translated_category_event_and_menu_identity_is_unique_per_locale(): void
    {
        $cases = [
            'categories' => ['uuid', 'categories_uuid_language_unique'],
            'notice_boards' => ['translation_key', 'notice_boards_translation_language_unique'],
            'annual_reports' => ['translation_key', 'annual_reports_translation_language_unique'],
            'page_menus' => ['uuid', 'page_menus_uuid_language_unique'],
        ];

        foreach ($cases as $table => [$identityColumn, $index]) {
            $identity = (string) Str::uuid();
            $this->assertTrue(Schema::hasIndex($table, $index), "Missing {$index}.");

            DB::table($table)->insert($this->identityRow($table, $identityColumn, $identity, 'en'));
            DB::table($table)->insert($this->identityRow($table, $identityColumn, $identity, 'bn'));

            try {
                DB::table($table)->insert($this->identityRow($table, $identityColumn, $identity, 'en'));
                $this->fail("{$table} accepted a duplicate English translation identity.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_annual_report_identity_backfill_reuses_a_key_committed_before_retry(): void
    {
        $key = (string) Str::uuid();
        $slug = 'retry-safe-report';
        DB::table('annual_reports')->insert($this->row('annual_reports', 'en', $slug) + [
            'translation_key' => $key,
        ]);
        DB::table('annual_reports')->insert($this->row('annual_reports', 'bn', $slug) + [
            'translation_key' => null,
        ]);

        $migration = require database_path('migrations/2026_08_21_129100_add_annual_report_translation_identity.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            [$key],
            DB::table('annual_reports')->where('slug', $slug)->pluck('translation_key')->unique()->values()->all()
        );
    }

    /** @return array<string, mixed> */
    private function row(string $table, string $locale, string $slug): array
    {
        $common = [
            'language' => $locale,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return match ($table) {
            'pages' => $common + [
                'uuid' => (string) Str::uuid(),
                'name' => 'Route identity page',
                'sub_title' => '',
                'status' => 0,
            ],
            'categories' => $common + [
                'uuid' => (string) Str::uuid(),
                'name' => 'Route identity category',
                'status' => 0,
            ],
            'notice_boards' => $common + [
                'translation_key' => (string) Str::uuid(),
                'title' => 'Route identity event',
                'status' => 0,
            ],
            'annual_reports' => $common + [
                'title' => 'Route identity report',
                'status' => 0,
            ],
        };
    }

    /** @return array<string, mixed> */
    private function identityRow(
        string $table,
        string $identityColumn,
        string $identity,
        string $locale,
    ): array {
        if ($table === 'page_menus') {
            return [
                $identityColumn => $identity,
                'language' => $locale,
                'name' => 'Navigation identity',
                'type' => 'main',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $row = $this->row($table, $locale, 'identity-' . Str::lower(Str::random(10)));
        $row[$identityColumn] = $identity;

        return $row;
    }
}
