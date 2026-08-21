<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['page_blocks', 'reusable_blocks'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('type', 'cta')
                ->orderBy('id')
                ->eachById(function (object $block) use ($table): void {
                    $content = json_decode((string) $block->content, true);
                    if (!is_array($content) || ($content['variant'] ?? null) !== 'campaign') {
                        return;
                    }

                    foreach ([
                        'frequency_label', 'one_time_label', 'monthly_label',
                        'raised', 'target', 'currency', 'amounts',
                        'raised_label', 'target_label', 'funded_label',
                    ] as $key) {
                        unset($content[$key]);
                    }
                    if (isset($content['body']) && is_string($content['body'])) {
                        $content['body'] = str_ireplace('one-time or monthly', 'one-time', $content['body']);
                    }

                    DB::table($table)->where('id', $block->id)->update([
                        'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Editorial text and protected payment semantics are not safely
        // reversible after an administrator has edited the campaign.
    }
};
