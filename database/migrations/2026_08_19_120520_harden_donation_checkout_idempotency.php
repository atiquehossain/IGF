<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ssl_commerz_transactions', 'session_key')) {
            Schema::table('ssl_commerz_transactions', function (Blueprint $table): void {
                if (Schema::hasIndex('ssl_commerz_transactions', 'ssl_transactions_session_key_index')) {
                    $table->dropIndex('ssl_transactions_session_key_index');
                }

                $table->dropColumn('session_key');
            });
        }

        Schema::table('ssl_commerz_transactions', function (Blueprint $table): void {
            $table->string('checkout_key', 110)->nullable()->after('requested_payment_method');
            $table->char('request_fingerprint', 64)->nullable()->after('checkout_key');
            $table->string('initialization_status', 20)->default('INITIALIZING')->after('request_fingerprint');
            $table->text('gateway_redirect_url')->nullable()->after('initialization_status');
            $table->string('initialization_error', 255)->nullable()->after('gateway_redirect_url');
            $table->timestamp('initialization_completed_at')->nullable()->after('initialization_error');

            $table->unique('checkout_key', 'ssl_transactions_checkout_key_unique');
            $table->index('initialization_status', 'ssl_transactions_initialization_status_index');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->string('review_reason', 255)->nullable()->after('payment_status');
            $table->timestamp('review_resolved_at')->nullable()->after('review_reason');
            $table->unsignedBigInteger('review_resolved_by')->nullable()->after('review_resolved_at');
            $table->text('review_resolution_note')->nullable()->after('review_resolved_by');
            $table->index('review_resolved_by', 'donations_review_resolved_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropIndex('donations_review_resolved_by_index');
            $table->dropColumn([
                'review_reason',
                'review_resolved_at',
                'review_resolved_by',
                'review_resolution_note',
            ]);
        });

        Schema::table('ssl_commerz_transactions', function (Blueprint $table): void {
            $table->dropUnique('ssl_transactions_checkout_key_unique');
            $table->dropIndex('ssl_transactions_initialization_status_index');
            $table->dropColumn([
                'checkout_key',
                'request_fingerprint',
                'initialization_status',
                'gateway_redirect_url',
                'initialization_error',
                'initialization_completed_at',
            ]);
            $table->string('session_key', 100)->nullable()->after('requested_payment_method');
            $table->index('session_key', 'ssl_transactions_session_key_index');
        });
    }
};
