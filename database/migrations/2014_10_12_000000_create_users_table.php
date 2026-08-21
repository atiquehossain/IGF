<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name',50)->nullable();
            $table->string('phone_no', 30)->nullable();
            $table->string('email', 50)->unique();
            $table->string('verify_code', 6)->nullable();
            $table->string('gender',10)->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('nationalid', 20)->nullable();
            $table->string('study_type')->nullable();
            $table->string('institute_name')->nullable();
            $table->integer('division_id')->length(11)->nullable();
            $table->integer('district_id')->length(11)->nullable();
            $table->integer('upazila_id')->length(11)->nullable();
            $table->string('post_code', 15)->nullable();
            $table->text('avatar')->nullable();
            $table->text('device_id')->nullable();
            $table->text('provider_type')->nullable();
            $table->text('social_id')->nullable();
            $table->integer('points')->length(11)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
