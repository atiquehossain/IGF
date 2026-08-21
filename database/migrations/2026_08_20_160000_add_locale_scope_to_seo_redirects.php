<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('from_path_hash');
            $table->char('source_scope_hash', 64)->nullable()->after('locale');
        });

        foreach (DB::table('seo_redirects')->orderBy('id')->get(['id', 'from_path_hash']) as $redirect) {
            DB::table('seo_redirects')->where('id', $redirect->id)->update([
                'source_scope_hash' => $this->scopeHash((string) $redirect->from_path_hash, null),
            ]);
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            // The source path is unique within a language scope. Keeping a
            // separate path index lets runtime resolution prefer the current
            // language and then fall back to an intentionally global rule.
            $table->dropIndex('seo_redirects_active_source_hash_index');
            $table->dropUnique('seo_redirects_from_path_hash_unique');
            $table->index('from_path_hash', 'seo_redirects_from_path_hash_index');
            $table->unique('source_scope_hash', 'seo_redirects_source_scope_hash_unique');
            $table->index(['is_active', 'from_path_hash', 'locale'], 'seo_redirects_active_source_locale_index');
        });
    }

    public function down(): void
    {
        // Never collapse language-specific rules destructively. An operator
        // must first reconcile duplicate paths before rolling back to the old
        // path-global schema.
        $duplicatePath = DB::table('seo_redirects')
            ->select('from_path_hash')
            ->whereNotNull('from_path_hash')
            ->groupBy('from_path_hash')
            ->havingRaw('COUNT(*) > 1')
            ->value('from_path_hash');

        if ($duplicatePath !== null) {
            throw new \RuntimeException('Language-aware SEO redirects must be reconciled before this migration can be rolled back safely.');
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->dropIndex('seo_redirects_active_source_locale_index');
            $table->dropUnique('seo_redirects_source_scope_hash_unique');
            $table->dropIndex('seo_redirects_from_path_hash_index');
            $table->unique('from_path_hash', 'seo_redirects_from_path_hash_unique');
            $table->index(['is_active', 'from_path_hash'], 'seo_redirects_active_source_hash_index');
            $table->dropColumn(['locale', 'source_scope_hash']);
        });
    }

    private function scopeHash(string $sourceHash, ?string $locale): string
    {
        return hash('sha256', $sourceHash . "\0" . ($locale ?: '*'));
    }
};
