<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Project groups use the legacy tags table in this application.
        if (!Schema::hasColumn('tags', 'description')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tags', 'description')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }
    }
};
