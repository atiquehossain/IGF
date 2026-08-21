<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void 
    {
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->integer('number_of_children');
            $table->string('contribution_interval');
            $table->decimal('sponsorship_amount', 10, 2);
            $table->string('transaction_id');
            $table->string('payment_status')->default('Pending');
            $table->timestamps();
            
            // Add indexes
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void 
    {
        Schema::dropIfExists('sponsorships');
    }
};