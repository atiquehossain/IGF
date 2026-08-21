<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $normalizedById = [];
        $ownersByHash = [];

        foreach (DB::table('seo_redirects')->orderBy('id')->get(['id', 'from_path']) as $redirect) {
            $normalized = $this->normalizeLegacyPath((string) $redirect->from_path);
            $hash = hash('sha256', $normalized);

            if (isset($ownersByHash[$hash])) {
                throw new RuntimeException(sprintf(
                    'SEO redirects %d and %d normalize to the same source path. Reconcile them before installing redirect uniqueness.',
                    $ownersByHash[$hash],
                    $redirect->id
                ));
            }

            $ownersByHash[$hash] = $redirect->id;
            $normalizedById[(int) $redirect->id] = [$normalized, $hash];
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->text('normalized_from_path')->nullable()->after('from_path');
            // A fixed-size digest gives every path length a portable unique
            // key without relying on database-specific TEXT prefix indexes.
            $table->char('from_path_hash', 64)->nullable()->after('normalized_from_path');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            $table->unsignedBigInteger('restored_by')->nullable()->after('deleted_by');
            $table->timestamp('restored_at')->nullable()->after('restored_by');
        });

        foreach ($normalizedById as $id => [$normalized, $hash]) {
            DB::table('seo_redirects')->where('id', $id)->update([
                'normalized_from_path' => $normalized,
                'from_path_hash' => $hash,
            ]);
        }

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->unique('from_path_hash', 'seo_redirects_from_path_hash_unique');
            $table->index(['is_active', 'from_path_hash'], 'seo_redirects_active_source_hash_index');
        });

        // A stable singleton row serializes graph-changing transactions even
        // when seo_redirects is empty (where SELECT ... FOR UPDATE locks no
        // rows on PostgreSQL and would otherwise permit concurrent chains).
        Schema::create('seo_redirect_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('seo_redirect_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirect_locks');

        Schema::table('seo_redirects', function (Blueprint $table): void {
            $table->dropIndex('seo_redirects_active_source_hash_index');
            $table->dropUnique('seo_redirects_from_path_hash_unique');
            $table->dropColumn([
                'normalized_from_path',
                'from_path_hash',
                'deleted_by',
                'restored_by',
                'restored_at',
            ]);
        });
    }

    private function normalizeLegacyPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        $decoded = rawurldecode($path);
        $normalized = preg_replace('#/+#', '/', $decoded) ?: '/';

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
};
