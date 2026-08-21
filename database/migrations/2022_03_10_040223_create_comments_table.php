<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('text')->nullable();
            $table->integer('page_id')->length(11)->nullable();
            $table->integer('user_id')->length(11)->nullable();
            $table->text('ip')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->integer('is_delete')->nullable();
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
        Schema::dropIfExists('comments');
    }
}
