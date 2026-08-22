<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('donation_frequency', 20)
                ->default('one_time')
                ->after('requested_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn('donation_frequency');
        });
    }
};
