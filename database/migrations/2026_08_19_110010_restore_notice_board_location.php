<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('notice_boards', 'location')) {
            Schema::table('notice_boards', function (Blueprint $table): void {
                // The Event admin and Translation Center have long exposed
                // this field, but the legacy table never stored it.
                $table->text('location')->nullable()->after('url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notice_boards', 'location')) {
            Schema::table('notice_boards', function (Blueprint $table): void {
                $table->dropColumn('location');
            });
        }
    }
};
