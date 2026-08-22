<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories') || !Schema::hasTable('seo_metadata')) {
            return;
        }

        DB::table('categories')
            ->where('slug', 'home')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'language'])
            ->each(function (object $category): void {
                $locale = trim((string) $category->language) ?: 'en';
                $owner = [
                    'seoable_type' => App\Models\Category::class,
                    'seoable_id' => $category->id,
                    'locale' => $locale,
                ];

                // Respect any visibility decision an editor has already made.
                if (DB::table('seo_metadata')->where($owner)->exists()) {
                    return;
                }

                DB::table('seo_metadata')->insert($owner + [
                    'robots_index' => false,
                    'robots_follow' => true,
                    'twitter_card' => 'summary_large_image',
                    'sitemap_priority' => 0.5,
                    'sitemap_change_frequency' => 'monthly',
                    'exclude_from_sitemap' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Forward-only: metadata is editor-owned after deployment, so a
        // rollback must not erase a later editorial visibility decision.
    }
};
