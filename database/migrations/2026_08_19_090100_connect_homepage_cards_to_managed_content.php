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
                ->where('type', 'cards')
                ->orderBy('id')
                ->eachById(function (object $block) use ($table): void {
                    $content = json_decode((string) $block->content, true);
                    if (!is_array($content)) {
                        return;
                    }

                    $variant = (string) ($content['variant'] ?? '');
                    if ($variant === 'projects') {
                        $content = array_replace($content, [
                            'content_source' => 'projects',
                            'tag_slug' => $content['tag_slug'] ?? 'current-project',
                            'selection_mode' => $content['selection_mode'] ?? 'automatic',
                            'selected_items' => $content['selected_items'] ?? [],
                            'sort' => $content['sort'] ?? 'featured',
                            'limit' => $content['limit'] ?? 3,
                            'item_link_label' => $content['item_link_label'] ?? 'Read more',
                            'empty_state' => $content['empty_state'] ?? 'Published projects will appear here automatically.',
                        ]);
                    } elseif ($variant === 'awards') {
                        $content = array_replace($content, [
                            'content_source' => 'category',
                            'category_slug' => $content['category_slug'] ?? 'awards-&-recognition',
                            'selection_mode' => $content['selection_mode'] ?? 'automatic',
                            'selected_items' => $content['selected_items'] ?? [],
                            'sort' => $content['sort'] ?? 'featured',
                            'limit' => $content['limit'] ?? 3,
                            'item_link_label' => $content['item_link_label'] ?? 'Learn more',
                            'empty_state' => $content['empty_state'] ?? 'Published awards will appear here automatically.',
                        ]);
                    } else {
                        return;
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
        // This is an editorial data upgrade. A rollback deliberately preserves
        // any source/filter choices an administrator made after deployment.
    }
};
