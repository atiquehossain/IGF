<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sub_title');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('slug');
            $table->string('project_category_id', 36)->nullable();
            $table->string('project_sector_id', 36)->nullable();
            $table->string('banner_id', 36)->nullable();
            $table->text('image')->nullable();
            $table->text('path')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->string('uuid', 36)->nullable();
            $table->text('inline_css')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_keyword')->nullable();
            $table->text('meta_description')->nullable();
            $table->tinyInteger('name_enabled')->nullable();
            $table->tinyInteger('sub_title_enabled')->nullable();
            $table->tinyInteger('highlights_enabled')->nullable();
            $table->tinyInteger('is_comment')->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('publish_by', 50)->nullable();
            $table->string('language', 10)->nullable();
            $table->integer('created_by')->length(11)->nullable();
            $table->integer('updated_by')->length(11)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
