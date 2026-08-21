<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_metadata')) {
            return;
        }

        $addEditorVersion = !Schema::hasColumn('seo_metadata', 'editor_version');
        $addReviewVersion = !Schema::hasColumn('seo_metadata', 'review_request_version');
        if ($addEditorVersion || $addReviewVersion) {
            Schema::table('seo_metadata', function (Blueprint $table) use ($addEditorVersion, $addReviewVersion): void {
                if ($addEditorVersion) {
                    $table->unsignedBigInteger('editor_version')->default(0)->after('locale');
                }
                if ($addReviewVersion) {
                    $table->unsignedBigInteger('review_request_version')->default(0)->after('review_content_hash');
                }
            });
        }

        DB::table('seo_metadata')
            ->where('review_status', 'pending')
            ->where('review_request_version', 0)
            ->update(['review_request_version' => 1]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('seo_metadata')) {
            return;
        }

        $columns = array_values(array_filter(
            ['editor_version', 'review_request_version'],
            fn (string $column): bool => Schema::hasColumn('seo_metadata', $column)
        ));
        if ($columns !== []) {
            Schema::table('seo_metadata', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
