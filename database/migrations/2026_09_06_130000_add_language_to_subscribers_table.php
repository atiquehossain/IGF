<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscribers', 'language')) {
            Schema::table('subscribers', function (Blueprint $table): void {
                $table->string('language', 10)->default('en')->after('email')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscribers', 'language')) {
            Schema::table('subscribers', function (Blueprint $table): void {
                $table->dropColumn('language');
            });
        }
    }
};
