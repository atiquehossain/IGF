<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGalleriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50)->nullable();
            $table->text('image')->nullable();
            $table->text('path')->nullable();
            $table->text('description')->nullable();
            $table->string('language', 10)->nullable();
            $table->text('url')->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->string('album_id', 36)->nullable();
             $table->string('uuid', 36)->nullable();
            $table->integer('grid_column')->length(2)->nullable();
            $table->integer('grid_row')->length(2)->nullable();
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
        Schema::dropIfExists('galleries');
    }
}
