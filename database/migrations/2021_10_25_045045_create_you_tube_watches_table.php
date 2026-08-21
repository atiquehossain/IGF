<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYouTubeWatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('you_tube_watches', function (Blueprint $table) {
            $table->id();
            $table->text('video_id');
            $table->decimal('duration_time',5, 2)->nullable();
            $table->text('ip')->nullable();
            $table->integer('user_id')->length(11)->nullable();
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
        Schema::dropIfExists('you_tube_watches');
    }
}
