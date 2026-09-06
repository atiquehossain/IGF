<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteers', function (Blueprint $table): void {
            $table->string('consent_locale', 12)->nullable()->after('consent_version');
            $table->char('consent_text_hash', 64)->nullable()->after('consent_locale');
            $table->text('consent_text_snapshot')->nullable()->after('consent_text_hash');
        });
    }

    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table): void {
            $table->dropColumn([
                'consent_locale',
                'consent_text_hash',
                'consent_text_snapshot',
            ]);
        });
    }
};
