<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 80);
            $table->string('label')->nullable();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->boolean('show_on_mobile')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['page_id', 'sort_order']);
            $table->index(['page_id', 'is_enabled']);
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('revision');
            $table->longText('snapshot');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'revision']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 80)->default('general');
            $table->string('key', 150);
            $table->string('locale', 10)->default('*');
            $table->longText('value')->nullable();
            $table->string('type', 30)->default('text');
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['group', 'key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('page_blocks');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
