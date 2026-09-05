<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_types', function (Blueprint $table): void {
            $table->unsignedBigInteger('content_editor_version')->default(1);
        });

        Schema::table('donation_cause_sections', function (Blueprint $table): void {
            $table->json('video_title')->nullable()->after('video_url');
            $table->json('video_transcript')->nullable()->after('video_title');
        });
    }

    public function down(): void
    {
        Schema::table('donation_cause_sections', function (Blueprint $table): void {
            $table->dropColumn(['video_title', 'video_transcript']);
        });

        Schema::table('donation_types', function (Blueprint $table): void {
            $table->dropColumn('content_editor_version');
        });
    }
};
