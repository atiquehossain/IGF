<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('donations')
            ->where('destination_type_snapshot', 'page')
            ->whereNull('project_uuid_snapshot')
            ->whereNotNull('destination_uuid_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($donations): void {
                foreach ($donations as $donation) {
                    $updates = [
                        'project_uuid_snapshot' => $donation->destination_uuid_snapshot,
                    ];
                    if (trim((string) $donation->project_name_snapshot) === '') {
                        $updates['project_name_snapshot'] = $donation->destination_name_snapshot ?: 'Historical project';
                    }
                    DB::table('donations')->where('id', $donation->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Attribution is financial history. Never erase a completed backfill
        // merely because application code is rolled back.
    }
};
