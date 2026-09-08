<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROOT_UUIDS = [
        '67000000-0000-4000-8000-000000000005',
        '69000000-0000-4000-8000-000000000005',
    ];

    private const LABELS = [
        [
            'language' => 'en',
            'old_name' => 'News & Stories',
            'new_name' => 'Stories',
        ],
        [
            'language' => 'bn',
            'old_name' => 'সংবাদ ও গল্প',
            'new_name' => 'গল্প',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (self::LABELS as $root) {
                DB::table('page_menus')
                    ->whereIn('uuid', self::ROOT_UUIDS)
                    ->where('language', $root['language'])
                    ->where('type', 'main')
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at')
                    ->where('name', $root['old_name'])
                    ->update([
                        'name' => $root['new_name'],
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Navigation labels are editorial state,
        // so a rollback must not overwrite wording changed after deployment.
    }
};
