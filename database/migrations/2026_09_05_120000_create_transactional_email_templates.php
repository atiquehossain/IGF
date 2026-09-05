<?php

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactional_email_templates')) {
            Schema::create('transactional_email_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('template_key', 80);
                $table->string('locale', 10);
                $table->string('subject', 200);
                $table->text('html_body');
                $table->text('text_body');
                $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->unique(['template_key', 'locale'], 'transactional_email_template_locale_unique');
            });
        }

        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_email_templates');

        // Permission rows remain additive so deployed role ID lists never
        // point at a different capability after rollback.
    }
};
