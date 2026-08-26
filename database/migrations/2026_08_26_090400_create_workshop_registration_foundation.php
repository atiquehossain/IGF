<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workshop_registrations')) {
            Schema::create('workshop_registrations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('work_reg_uuid_uq');
                $table->string('reference_number', 40)->unique('work_reg_ref_uq');
                $table->unsignedBigInteger('workshop_id');
                $table->unsignedBigInteger('application_form_version_id');
                $table->string('name', 255);
                $table->string('email', 254);
                $table->char('email_hash', 64);
                $table->string('phone', 40)->nullable();
                $table->string('workflow_status', 32)->default('pending');
                $table->unsignedBigInteger('assigned_to_admin_id')->nullable();
                $table->unsignedInteger('submission_count')->default(1);
                $table->timestamp('first_submitted_at');
                $table->timestamp('last_submitted_at');
                $table->timestamp('waitlisted_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('source', 24)->default('public');
                $table->unsignedBigInteger('last_import_batch_id')->nullable();
                $table->timestamp('status_changed_at')->nullable();
                $table->unsignedBigInteger('status_changed_by_admin_id')->nullable();
                $table->timestamp('anonymized_at')->nullable();
                $table->unsignedBigInteger('anonymized_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['workshop_id', 'email_hash'], 'work_reg_email_uq');
                $table->index(['workshop_id', 'workflow_status', 'last_submitted_at'], 'work_reg_status_ix');
                $table->index(['workshop_id', 'workflow_status', 'waitlisted_at'], 'work_reg_waitlist_ix');
                $table->index(['assigned_to_admin_id', 'workflow_status'], 'work_reg_assignee_ix');
                $table->foreign('workshop_id', 'work_reg_workshop_fk')->references('id')->on('workshops')->restrictOnDelete();
                $table->foreign('application_form_version_id', 'work_reg_form_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
                $table->foreign('assigned_to_admin_id', 'work_reg_assignee_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('status_changed_by_admin_id', 'work_reg_status_by_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('anonymized_by_admin_id', 'work_reg_anon_by_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('workshop_registration_answers')) {
            Schema::create('workshop_registration_answers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workshop_registration_id');
                $table->unsignedBigInteger('application_form_field_id');
                $table->longText('value_text')->nullable();
                $table->decimal('value_number', 18, 4)->nullable();
                $table->date('value_date')->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();

                $table->unique(['workshop_registration_id', 'application_form_field_id'], 'work_ans_reg_field_uq');
                $table->index(['application_form_field_id', 'value_date'], 'work_ans_field_date_ix');
                $table->foreign('workshop_registration_id', 'work_ans_reg_fk')->references('id')->on('workshop_registrations')->restrictOnDelete();
                $table->foreign('application_form_field_id', 'work_ans_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('workshop_registration_documents')) {
            Schema::create('workshop_registration_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('work_doc_uuid_uq');
                $table->unsignedBigInteger('workshop_registration_id');
                $table->unsignedBigInteger('application_form_field_id')->nullable();
                $table->string('document_kind', 32)->default('attachment');
                $table->string('disk', 40);
                $table->string('path', 255)->unique('work_doc_path_uq');
                $table->string('original_name', 255);
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('bytes');
                $table->char('sha256', 64);
                $table->timestamps();

                $table->unique(['workshop_registration_id', 'application_form_field_id'], 'work_doc_reg_field_uq');
                $table->foreign('workshop_registration_id', 'work_doc_reg_fk')->references('id')->on('workshop_registrations')->restrictOnDelete();
                $table->foreign('application_form_field_id', 'work_doc_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('workshop_registration_notes')) {
            Schema::create('workshop_registration_notes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('work_note_uuid_uq');
                $table->unsignedBigInteger('workshop_registration_id');
                $table->unsignedBigInteger('author_admin_id')->nullable();
                $table->string('author_name_snapshot', 100)->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index(['workshop_registration_id', 'created_at'], 'work_note_reg_time_ix');
                $table->foreign('workshop_registration_id', 'work_note_reg_fk')->references('id')->on('workshop_registrations')->restrictOnDelete();
                $table->foreign('author_admin_id', 'work_note_author_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('workshop_registration_status_events')) {
            Schema::create('workshop_registration_status_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workshop_registration_id');
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->string('actor_name_snapshot', 100)->nullable();
                $table->string('source', 24)->default('admin');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['workshop_registration_id', 'created_at'], 'work_status_reg_time_ix');
                $table->foreign('workshop_registration_id', 'work_status_reg_fk')->references('id')->on('workshop_registrations')->restrictOnDelete();
                $table->foreign('actor_admin_id', 'work_status_actor_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData([
            'workshop_registration_status_events',
            'workshop_registration_notes',
            'workshop_registration_documents',
            'workshop_registration_answers',
            'workshop_registrations',
        ]);

        Schema::dropIfExists('workshop_registration_status_events');
        Schema::dropIfExists('workshop_registration_notes');
        Schema::dropIfExists('workshop_registration_documents');
        Schema::dropIfExists('workshop_registration_answers');
        Schema::dropIfExists('workshop_registrations');
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
