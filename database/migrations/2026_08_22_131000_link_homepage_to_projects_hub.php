<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('page_blocks')) {
            return;
        }

        DB::table('page_blocks')
            ->where('uuid', '44444444-4444-4444-8444-000000000006')
            ->whereNull('deleted_at')
            ->get(['id', 'content'])
            ->each(function (object $block): void {
                $content = json_decode((string) $block->content, true);
                if (!is_array($content)
                    || ($content['variant'] ?? null) !== 'projects'
                    || ($content['view_all_url'] ?? null) !== '/projects/current-project') {
                    return;
                }

                $content['view_all_label'] = 'View all projects';
                $content['view_all_url'] = '/projects';
                DB::table('page_blocks')->where('id', $block->id)->update([
                    'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Forward-only: the block becomes editor-owned after deployment.
    }
};
