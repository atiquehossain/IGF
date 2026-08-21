<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthMenusTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('auth_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id')->length(11)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('link', 255)->nullable();
            $table->string('icon', 255)->nullable();
            $table->integer('order_by')->length(11)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('auth_menus');
    }

}
