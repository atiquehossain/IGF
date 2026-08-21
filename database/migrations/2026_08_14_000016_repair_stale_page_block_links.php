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
            ->where('content', 'like', '%/page/community-health-outreach%')
            ->orderBy('id')
            ->get(['id', 'content'])
            ->each(function ($block): void {
                $content = json_decode((string) $block->content, true);
                if (!is_array($content)) {
                    return;
                }

                $updated = $this->replaceUrl($content);
                if ($updated === $content) {
                    return;
                }

                DB::table('page_blocks')->where('id', $block->id)->update([
                    'content' => json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Content migrations are intentionally not reversed: the old URL is a known 404.
    }

    private function replaceUrl(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'url' && $item === '/page/community-health-outreach') {
                $value[$key] = '/category/our-causes';
            } elseif (is_array($item)) {
                $value[$key] = $this->replaceUrl($item);
            }
        }

        return $value;
    }
};
