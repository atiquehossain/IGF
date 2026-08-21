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
        Schema::create('annual_reports', function (Blueprint $table) {
            $table->id();
            $table->text('title')->nullable();
            $table->text('sub_title')->nullable();
            $table->text('description')->nullable();
            $table->text('inline_css')->nullable();
            $table->string('notice_type', 50)->nullable();
            $table->string('file_type', 50)->nullable();
            $table->string('file_size', 50)->nullable();
            $table->text('language')->nullable();
            $table->text('url')->nullable();
            $table->string('slug', 255)->nullable();
            $table->text('image_path')->nullable();
            $table->text('file_path')->nullable();
            $table->string('publisher_name', 100)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('ip')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->integer('order_by')->nullable();
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
        Schema::dropIfExists('annual_reports');
    }
};
