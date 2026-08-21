<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSearchView extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        DB::statement($this->dropView());
        DB::statement($this->createView());
    }

    public function down()
    {
        DB::statement($this->dropView());
    }

    private function createView(): string
    {
        $searchExpression = DB::getDriverName() === 'sqlite'
            ? "COALESCE(name, '') || ' ' || COALESCE(sub_title, '') || ' ' || COALESCE(description, '')"
            : "CONCAT_WS(' ', name, sub_title, description)";

        return <<<SQL
            CREATE VIEW view_search_data AS
            SELECT
                id,
                name,
                sub_title,
                description,
                language,
                slug,
                {$searchExpression} AS search,
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
            WHERE status = 1
        SQL;
    }

    private function dropView(): string
    {
        return 'DROP VIEW IF EXISTS view_search_data';
    }
}
