<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('donation_allocations')) {
            return;
        }

        Schema::create('donation_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('request_token', 110)->unique();
            $table->foreignId('donation_id')->constrained('donations')->restrictOnDelete();
            $table->uuid('page_uuid');
            $table->string('page_name_snapshot');
            $table->uuid('category_uuid_snapshot')->nullable();
            $table->string('category_name_snapshot')->nullable();
            $table->decimal('amount', 10, 2);
            $table->text('note');
            $table->unsignedBigInteger('allocated_by');
            $table->timestamps();

            $table->index(['donation_id', 'created_at'], 'donation_allocations_history_index');
            $table->index('page_uuid', 'donation_allocations_page_index');
            $table->index('allocated_by', 'donation_allocations_admin_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('donation_allocations')
            && DB::table('donation_allocations')->exists()) {
            throw new RuntimeException(
                'Rollback refused: the donation allocation ledger contains append-only financial history.'
            );
        }

        Schema::dropIfExists('donation_allocations');
    }
};
