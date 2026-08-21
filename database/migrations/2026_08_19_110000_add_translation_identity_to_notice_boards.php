<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->uuid('translation_key')->nullable()->after('id');
            $table->index('translation_key', 'notice_boards_translation_key_index');
        });

        // Existing events are not assumed to be translations merely because
        // their slugs happen to match. Give every existing row its own stable
        // identity; editors can deliberately connect a translated counterpart.
        DB::table('notice_boards')
            ->whereNull('translation_key')
            ->orderBy('id')
            ->chunkById(250, function ($notices): void {
                foreach ($notices as $notice) {
                    DB::table('notice_boards')
                        ->where('id', $notice->id)
                        ->whereNull('translation_key')
                        ->update(['translation_key' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->dropIndex('notice_boards_translation_key_index');
            $table->dropColumn('translation_key');
        });
    }
};
