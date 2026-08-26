<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workshops')) {
            Schema::create('workshops', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('workshop_uuid_uq');
                $table->unsignedBigInteger('application_form_id');
                $table->unsignedBigInteger('current_form_version_id');
                $table->string('publication_status', 24)->default('draft');
                $table->timestamp('visible_from_at')->nullable();
                $table->timestamp('registration_opens_at');
                $table->timestamp('registration_closes_at');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('attendance_mode', 24)->default('offline');
                $table->string('registration_mode', 24)->default('automatic');
                $table->unsignedInteger('capacity')->nullable();
                $table->text('private_meeting_url')->nullable();
                $table->unsignedBigInteger('editor_version')->default(0);
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->unsignedBigInteger('published_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['publication_status', 'visible_from_at', 'registration_closes_at'], 'workshop_public_ix');
                $table->index(['starts_at', 'ends_at'], 'workshop_schedule_ix');
                $table->foreign('application_form_id', 'workshop_form_fk')->references('id')->on('application_forms')->restrictOnDelete();
                $table->foreign('current_form_version_id', 'workshop_form_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
                $table->foreign('created_by_admin_id', 'workshop_created_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('updated_by_admin_id', 'workshop_updated_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('published_by_admin_id', 'workshop_pub_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('workshop_translations')) {
            Schema::create('workshop_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workshop_id');
                $table->string('locale', 10);
                $table->string('slug', 190);
                $table->string('title', 255);
                $table->text('summary')->nullable();
                $table->longText('description')->nullable();
                $table->string('facilitator_name', 255)->nullable();
                $table->string('venue_name', 255)->nullable();
                $table->text('venue_address')->nullable();
                $table->longText('registration_instructions')->nullable();
                $table->timestamps();

                $table->unique(['workshop_id', 'locale'], 'workshop_tr_locale_uq');
                $table->unique(['locale', 'slug'], 'workshop_tr_slug_uq');
                $table->foreign('workshop_id', 'workshop_tr_workshop_fk')->references('id')->on('workshops')->restrictOnDelete();
                $table->foreign('locale', 'workshop_tr_locale_fk')->references('locale')->on('translation_locales')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData(['workshop_translations', 'workshops']);

        Schema::dropIfExists('workshop_translations');
        Schema::dropIfExists('workshops');
    }

    private function refuseRollbackWithData(array $tables): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to drop {$table}: production data exists.");
            }
        }
    }
};
