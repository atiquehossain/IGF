<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_cause_amounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('donation_type_id')
                ->constrained('donation_types')
                ->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->json('impact')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['donation_type_id', 'amount'],
                'donation_cause_amounts_cause_amount_unique'
            );
            $table->index(
                ['donation_type_id', 'enabled', 'display_order'],
                'donation_cause_amounts_public_index'
            );
        });

        Schema::create('donation_cause_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('donation_type_id')
                ->constrained('donation_types')
                ->cascadeOnDelete();
            $table->string('layout', 24)->default('text');
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->uuid('image_media_uuid')->nullable();
            $table->json('image_alt')->nullable();
            $table->uuid('video_media_uuid')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('image_media_uuid', 'donation_cause_sections_image_media_foreign')
                ->references('uuid')
                ->on('media_assets')
                ->nullOnDelete();
            $table->foreign('video_media_uuid', 'donation_cause_sections_video_media_foreign')
                ->references('uuid')
                ->on('media_assets')
                ->nullOnDelete();
            $table->index(
                ['donation_type_id', 'enabled', 'display_order'],
                'donation_cause_sections_public_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_cause_sections');
        Schema::dropIfExists('donation_cause_amounts');
    }
};
