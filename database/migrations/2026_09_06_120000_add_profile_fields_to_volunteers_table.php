<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteers', function (Blueprint $table): void {
            $table->string('sex', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained('upazilas')->nullOnDelete();
            $table->string('occupation', 32)->nullable();
            $table->string('occupation_other')->nullable();
            $table->string('education_level', 64)->nullable();
            $table->string('blood_group', 3)->nullable();
            $table->boolean('emergency_response_training')->nullable();
            $table->string('skill', 32)->nullable();
            $table->string('skill_other')->nullable();
            $table->string('consent_version', 64)->nullable();
            $table->timestamp('consented_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('upazila_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn([
                'sex',
                'date_of_birth',
                'occupation',
                'occupation_other',
                'education_level',
                'blood_group',
                'emergency_response_training',
                'skill',
                'skill_other',
                'consent_version',
                'consented_at',
            ]);
        });
    }
};
