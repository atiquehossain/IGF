<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateTransactionIds = DB::table('donations')
            ->select('transaction_id')
            ->whereNotNull('transaction_id')
            ->where('transaction_id', '<>', '')
            ->groupBy('transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateTransactionIds) {
            throw new \RuntimeException(
                'Duplicate donation transaction IDs must be reconciled before payment integrity constraints can be installed.'
            );
        }

        Schema::table('donations', function (Blueprint $table) {
            $table->unique('transaction_id', 'donations_transaction_id_unique');
            $table->index('payment_status', 'donations_payment_status_index');
            $table->index('payment_cause', 'donations_payment_cause_index');
        });

        $this->rebuildSearchView(includeSoftDeleteFilter: true);
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropUnique('donations_transaction_id_unique');
            $table->dropIndex('donations_payment_status_index');
            $table->dropIndex('donations_payment_cause_index');
        });

        $this->rebuildSearchView(includeSoftDeleteFilter: false);
    }

    private function rebuildSearchView(bool $includeSoftDeleteFilter): void
    {
        DB::statement('DROP VIEW IF EXISTS view_search_data');

        $driver = DB::connection()->getDriverName();
        $searchText = $driver === 'sqlite'
            ? "COALESCE(name, '') || ' ' || COALESCE(sub_title, '') || ' ' || COALESCE(description, '')"
            : "CONCAT_WS(' ', name, sub_title, description)";
        $softDeleteClause = $includeSoftDeleteFilter ? ' AND deleted_at IS NULL' : '';

        DB::statement(<<<SQL
            CREATE VIEW view_search_data AS
            SELECT
                id,
                name,
                sub_title,
                description,
                language,
                slug,
                {$searchText} AS search,
                NULL AS skill_id,
                NULL AS skill_name,
                NULL AS class_id,
                NULL AS class_name,
                NULL AS subject_id,
                NULL AS subject_name,
                NULL AS package_id,
                NULL AS package_name,
                NULL AS audio_music_id,
                NULL AS audio_music_name,
                NULL AS video_content_id,
                NULL AS video_content_name,
                NULL AS you_tube_id,
                NULL AS you_tube_name,
                1 AS order_by,
                'page' AS view_type
            FROM pages
            WHERE status = 1{$softDeleteClause}
        SQL);
    }
};
