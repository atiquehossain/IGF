<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('mobile')->nullable();
            $table->text('role')->nullable();
            $table->text('image')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->text('password')->nullable();
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
        Schema::dropIfExists('admins');
    }
}
