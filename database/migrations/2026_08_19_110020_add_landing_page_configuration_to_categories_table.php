<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('display_mode', 20)->default('archive')->after('type');
            $table->uuid('landing_page_uuid')->nullable()->after('display_mode')->index();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['landing_page_uuid']);
            $table->dropColumn(['landing_page_uuid', 'display_mode']);
        });
    }
};
