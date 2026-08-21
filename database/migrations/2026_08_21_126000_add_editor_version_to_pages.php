<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages') && !Schema::hasColumn('pages', 'editor_version')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->unsignedBigInteger('editor_version')->default(0)->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'editor_version')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->dropColumn('editor_version');
            });
        }
    }
};
