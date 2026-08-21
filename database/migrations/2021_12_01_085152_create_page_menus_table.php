<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePageMenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('page_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id')->length(11)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('type', 50)->nullable();
            $table->text('link')->nullable();
            $table->text('slug')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('banner_id', 36)->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->tinyInteger('status')->nullable();
             $table->string('uuid', 36)->nullable();
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
        Schema::dropIfExists('page_menus');
    }
}
