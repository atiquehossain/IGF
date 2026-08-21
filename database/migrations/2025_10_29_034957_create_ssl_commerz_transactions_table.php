<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_commerz_transactions', function (Blueprint $table) {
            $table->id();

            // ---- Core identifiers ----------------------------------------
            $table->string('tran_id')->unique()->comment('Your local transaction ID');
            $table->string('val_id')->nullable()->comment('SSLCommerz validation ID');
            $table->string('bank_tran_id')->nullable()->comment('Bank/gateway transaction ID');
            $table->string('subscription_id')->nullable();

            // ---- Payment details -----------------------------------------
            $table->string('status')->default('PENDING')
                ->comment('PENDING | VALID | VALIDATED | FAILED | CANCELLED | REFUNDED');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('store_amount', 12, 2)->nullable()->comment('Amount credited to merchant after fees');
            $table->string('currency', 10)->default('BDT');

            // ---- Currency conversion (when paying in non-BDT) ------------
            $table->string('currency_type', 10)->nullable();
            $table->decimal('currency_amount', 12, 4)->nullable();
            $table->decimal('currency_rate', 12, 6)->nullable();
            $table->decimal('base_fair', 12, 2)->nullable();

            // ---- Card info -----------------------------------------------
            $table->string('card_type')->nullable();
            $table->string('card_no')->nullable();
            $table->string('card_issuer')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_issuer_country')->nullable();
            $table->string('card_issuer_country_code', 10)->nullable();

            // ---- Risk -------------------------------------------------------
            $table->string('risk_level')->nullable();
            $table->string('risk_title')->nullable();

            // ---- Customer info -------------------------------------------
            $table->string('cus_name')->nullable();
            $table->string('cus_email')->nullable();
            $table->string('cus_phone')->nullable();

            // ---- Pass-through metadata -----------------------------------
            $table->string('opted_a')->nullable();
            $table->string('opted_b')->nullable();
            $table->string('opted_c')->nullable();
            $table->string('opted_d')->nullable();

            // ---- Raw payload ---------------------------------------------
            $table->longText('raw_response')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('cus_email');
            $table->index('bank_tran_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_commerz_transactions');
    }
};
