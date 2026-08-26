<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('private_file_cleanup_jobs')) {
            Schema::create('private_file_cleanup_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('disk', 40);
                $table->string('path', 255);
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('last_failed_at')->nullable();
                $table->string('last_error_code', 40)->nullable();
                $table->timestamps();

                $table->unique(['disk', 'path'], 'private_cleanup_target_uq');
                $table->index(['locked_at', 'id'], 'private_cleanup_claim_ix');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('private_file_cleanup_jobs')
            && DB::table('private_file_cleanup_jobs')->exists()) {
            throw new RuntimeException('Refusing to drop private_file_cleanup_jobs: pending cleanup work exists.');
        }

        Schema::dropIfExists('private_file_cleanup_jobs');
    }
};
