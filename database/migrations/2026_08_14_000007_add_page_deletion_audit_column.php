<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pages', 'deleted_by')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pages', 'deleted_by')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('deleted_by');
            });
        }
    }
};
