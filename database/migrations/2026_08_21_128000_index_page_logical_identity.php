<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'pages_uuid_language_unique';

    public function up(): void
    {
        if (Schema::hasIndex('pages', self::INDEX)) {
            return;
        }

        $duplicate = DB::table('pages')
            ->select(['uuid', 'language'])
            ->whereNotNull('uuid')
            ->whereNotNull('language')
            ->groupBy('uuid', 'language')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get()
            ->isNotEmpty();
        if ($duplicate) {
            throw new RuntimeException(
                'Pages contain duplicate UUID/language identities. Resolve them before adding the editor-lock index.'
            );
        }

        if (!Schema::hasIndex('pages', self::INDEX)) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->unique(['uuid', 'language'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        // Logical page identity is an application invariant. A rollback must
        // not reopen duplicate translations or unsafe editor locking scans.
    }
};
