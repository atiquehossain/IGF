<?php

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table): void {
            $table->string('review_status', 24)->default('draft')->index();
            $table->text('review_note')->nullable();
            $table->string('review_content_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('review_requested_by')->nullable()->index();
            $table->timestamp('review_requested_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
        });

        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table): void {
            $table->dropColumn([
                'review_status',
                'review_note',
                'review_content_hash',
                'review_requested_by',
                'review_requested_at',
                'reviewed_by',
                'reviewed_at',
            ]);
        });

        // Permission rows intentionally remain so deployed role CSVs never
        // point at deleted registry identifiers during a rollback.
    }
};
