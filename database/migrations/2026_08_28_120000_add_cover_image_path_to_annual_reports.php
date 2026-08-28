<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('annual_reports', 'cover_image_path')) {
            Schema::table('annual_reports', function (Blueprint $table): void {
                // The legacy image_path column stores the private PDF. Keep a
                // separate public Media Library path for the report cover.
                $table->text('cover_image_path')->nullable()->after('image_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('annual_reports', 'cover_image_path')) {
            Schema::table('annual_reports', function (Blueprint $table): void {
                $table->dropColumn('cover_image_path');
            });
        }
    }
};
