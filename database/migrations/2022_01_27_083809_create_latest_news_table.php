<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLatestNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('latest_news', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50)->nullable();
            $table->integer('category_id')->length(11)->nullable();
            $table->text('image')->nullable();
            $table->text('path')->nullable();
            $table->text('description')->nullable();
            $table->string('email', 50)->nullable();
            $table->text('url')->nullable();
            $table->string('language',10)->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->integer('created_by')->length(11)->nullable();
            $table->integer('updated_by')->length(11)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('latest_news');
    }
}
