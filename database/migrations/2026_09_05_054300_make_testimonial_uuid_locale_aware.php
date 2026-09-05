<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_INDEX = 'testimonials_uuid_unique';

    private const LOCALIZED_INDEX = 'testimonials_uuid_language_unique';

    public function up(): void
    {
        $duplicate = DB::table('testimonials')
            ->select(['uuid', 'language'])
            ->whereNotNull('uuid')
            ->whereNotNull('language')
            ->groupBy('uuid', 'language')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException(
                'Testimonials contain duplicate UUID/language identities. Resolve them before enabling localized testimonials.'
            );
        }

        if (Schema::hasIndex('testimonials', self::LEGACY_INDEX)) {
            Schema::table('testimonials', function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_INDEX);
            });
        }

        if (!Schema::hasIndex('testimonials', self::LOCALIZED_INDEX)) {
            Schema::table('testimonials', function (Blueprint $table): void {
                $table->unique(['uuid', 'language'], self::LOCALIZED_INDEX);
            });
        }
    }

    public function down(): void
    {
        // Locale-aware testimonial identity is an application invariant. A
        // rollback must not make valid translated pairs mutually exclusive.
    }
};
