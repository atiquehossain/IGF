<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table): void {
            $table->string('social_image_alt', 420)->nullable()->after('og_image');
        });
    }

    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table): void {
            $table->dropColumn('social_image_alt');
        });
    }
};
