<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYouTubesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('you_tubes', function (Blueprint $table) {
            $table->id();
             $table->string('uuid', 36)->nullable();
            $table->text('name')->nullable();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('video_id', 30);
            $table->decimal('activision_time', 5, 2)->nullable();
            $table->decimal('duration_time', 5, 2)->nullable();
            $table->text('ip')->nullable();
            $table->text('image')->nullable();
            $table->string('language', 10)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->integer('order_by')->nullable();
            // $table->unique(['video_id', 'language']);
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
        Schema::dropIfExists('you_tubes');
    }
}
