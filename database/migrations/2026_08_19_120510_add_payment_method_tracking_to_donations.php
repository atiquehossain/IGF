<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('requested_payment_method', 20)
                ->nullable()
                ->after('payment_cause');
            $table->index('requested_payment_method', 'donations_requested_payment_method_index');
        });

        Schema::table('ssl_commerz_transactions', function (Blueprint $table): void {
            $table->string('requested_payment_method', 20)
                ->nullable()
                ->after('status');
            $table->string('session_key', 100)
                ->nullable()
                ->after('requested_payment_method');
            $table->index('requested_payment_method', 'ssl_transactions_requested_method_index');
            $table->index('session_key', 'ssl_transactions_session_key_index');
        });
    }

    public function down(): void
    {
        Schema::table('ssl_commerz_transactions', function (Blueprint $table): void {
            $table->dropIndex('ssl_transactions_requested_method_index');
            $table->dropIndex('ssl_transactions_session_key_index');
            $table->dropColumn(['requested_payment_method', 'session_key']);
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropIndex('donations_requested_payment_method_index');
            $table->dropColumn('requested_payment_method');
        });
    }
};
