<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('sub_title');
            $table->string('thumbnail')->nullable();
            $table->string('slug');
            $table->string('category_id', 36)->nullable();
            $table->string('banner_id', 36)->nullable();
            $table->text('description')->nullable();
            $table->text('inline_css')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_keyword')->nullable();
            $table->text('meta_description')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->tinyInteger('name_enabled')->nullable();
            $table->tinyInteger('sub_title_enabled')->nullable();
            $table->tinyInteger('is_comment')->nullable();
            $table->tinyInteger('is_relationship')->nullable();
            $table->string('uuid', 36)->nullable();
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
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pages');
    }
}
