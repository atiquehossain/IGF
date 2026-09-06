<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->string('image_alt', 420)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->dropColumn('image_alt');
        });
    }
};
