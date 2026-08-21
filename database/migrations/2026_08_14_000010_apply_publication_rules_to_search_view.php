<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild(withPublicationRules: true);
    }

    public function down(): void
    {
        $this->rebuild(withPublicationRules: false);
    }

    private function rebuild(bool $withPublicationRules): void
    {
        DB::statement('DROP VIEW IF EXISTS view_search_data');
        $searchText = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(name, '') || ' ' || COALESCE(sub_title, '') || ' ' || COALESCE(description, '')"
            : "CONCAT_WS(' ', name, sub_title, description)";
        $publication = $withPublicationRules
            ? " AND visibility = 'public' AND (publication_status = 'published' OR (publication_status = 'scheduled' AND scheduled_for IS NOT NULL AND scheduled_for <= CURRENT_TIMESTAMP))"
            : '';

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
            WHERE status = 1 AND deleted_at IS NULL{$publication}
        SQL);
    }
};
