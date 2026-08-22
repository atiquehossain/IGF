<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audit_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('seo_audit_runs')->cascadeOnDelete();
            $table->string('alert_type', 32);
            $table->string('severity', 12)->default('high');
            $table->string('title', 160);
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->string('email_status', 16)->default('disabled');
            $table->timestamp('email_attempted_at')->nullable();
            $table->string('email_failure', 300)->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'alert_type']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_alerts');
    }
};
