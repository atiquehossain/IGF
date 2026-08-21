<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const INDEX = 'annual_reports_translation_language_unique';

    public function up(): void
    {
        if (!Schema::hasColumn('annual_reports', 'translation_key')) {
            Schema::table('annual_reports', function (Blueprint $table): void {
                $table->uuid('translation_key')->nullable()->after('id');
            });
        }

        // The legacy Translation Center explicitly paired annual reports by
        // exact slug. Preserve those known pairs once, then use immutable
        // identity so later permalink edits cannot orphan a translation.
        $conflictingPair = DB::table('annual_reports')
            ->select('slug')
            ->whereNotNull('translation_key')
            ->whereNotNull('slug')
            ->where('slug', '<>', '')
            ->groupBy('slug')
            ->havingRaw('COUNT(DISTINCT translation_key) > 1')
            ->limit(1)
            ->exists();
        if ($conflictingPair) {
            throw new RuntimeException(
                'Annual-report rows previously paired by slug contain conflicting translation keys. Resolve them before continuing.'
            );
        }

        DB::table('annual_reports')
            ->select(['id', 'slug'])
            ->whereNull('translation_key')
            ->chunkById(250, function ($reports): void {
                foreach ($reports as $report) {
                    $slug = trim((string) $report->slug);
                    // Query on every row so a retry reuses any key committed
                    // before an interrupted MySQL migration. The comparison
                    // follows the database's own slug collation, matching the
                    // legacy Translation Center pairing contract.
                    $key = $slug === '' ? null : DB::table('annual_reports')
                        ->where('slug', $report->slug)
                        ->whereNotNull('translation_key')
                        ->value('translation_key');
                    $key = filled($key) ? (string) $key : (string) Str::uuid();
                    DB::table('annual_reports')
                        ->where('id', $report->id)
                        ->whereNull('translation_key')
                        ->update(['translation_key' => $key]);
                }
            }, 'id');

        $duplicate = DB::table('annual_reports')
            ->select(['translation_key', 'language'])
            ->whereNotNull('translation_key')
            ->whereNotNull('language')
            ->groupBy('translation_key', 'language')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
        if ($duplicate) {
            throw new RuntimeException(
                'Annual reports contain duplicate translation-key/language identities. Resolve them before enforcing report identity.'
            );
        }

        if (!Schema::hasIndex('annual_reports', self::INDEX)) {
            Schema::table('annual_reports', function (Blueprint $table): void {
                $table->unique(['translation_key', 'language'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        // Annual-report translation identity is persistent public URL data.
        // Rollback must not orphan existing locale pairs.
    }
};
