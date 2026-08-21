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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name', 100);
            $table->string('designation', 150)->nullable();
            $table->string('photo', 255)->nullable();
            $table->text('testimonial')->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->string('language', 50)->nullable();
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
        Schema::dropIfExists('testimonials');
    }
};
