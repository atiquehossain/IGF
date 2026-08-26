<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('job_app_uuid_uq');
                $table->string('reference_number', 40)->unique('job_app_ref_uq');
                $table->unsignedBigInteger('job_posting_id');
                $table->unsignedBigInteger('application_form_version_id');
                $table->string('name', 255);
                $table->string('email', 254);
                $table->char('email_hash', 64);
                $table->string('phone', 40)->nullable();
                $table->string('workflow_status', 32)->default('new');
                $table->unsignedBigInteger('assigned_to_admin_id')->nullable();
                $table->unsignedInteger('submission_count')->default(1);
                $table->timestamp('first_submitted_at');
                $table->timestamp('last_submitted_at');
                $table->string('source', 24)->default('public');
                $table->unsignedBigInteger('last_import_batch_id')->nullable();
                $table->timestamp('status_changed_at')->nullable();
                $table->unsignedBigInteger('status_changed_by_admin_id')->nullable();
                $table->timestamp('anonymized_at')->nullable();
                $table->unsignedBigInteger('anonymized_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['job_posting_id', 'email_hash'], 'job_app_post_email_uq');
                $table->index(['job_posting_id', 'workflow_status', 'last_submitted_at'], 'job_app_status_ix');
                $table->index(['assigned_to_admin_id', 'workflow_status'], 'job_app_assignee_ix');
                $table->foreign('job_posting_id', 'job_app_post_fk')->references('id')->on('job_postings')->restrictOnDelete();
                $table->foreign('application_form_version_id', 'job_app_form_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
                $table->foreign('assigned_to_admin_id', 'job_app_assignee_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('status_changed_by_admin_id', 'job_app_status_by_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('anonymized_by_admin_id', 'job_app_anon_by_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('job_application_answers')) {
            Schema::create('job_application_answers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_application_id');
                $table->unsignedBigInteger('application_form_field_id');
                $table->longText('value_text')->nullable();
                $table->decimal('value_number', 18, 4)->nullable();
                $table->date('value_date')->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();

                $table->unique(['job_application_id', 'application_form_field_id'], 'job_ans_app_field_uq');
                $table->index(['application_form_field_id', 'value_date'], 'job_ans_field_date_ix');
                $table->foreign('job_application_id', 'job_ans_app_fk')->references('id')->on('job_applications')->restrictOnDelete();
                $table->foreign('application_form_field_id', 'job_ans_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('job_application_documents')) {
            Schema::create('job_application_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('job_doc_uuid_uq');
                $table->unsignedBigInteger('job_application_id');
                $table->unsignedBigInteger('application_form_field_id')->nullable();
                $table->string('document_kind', 32)->default('cv');
                $table->string('disk', 40);
                $table->string('path', 255)->unique('job_doc_path_uq');
                $table->string('original_name', 255);
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('bytes');
                $table->char('sha256', 64);
                $table->timestamps();

                $table->unique(['job_application_id', 'application_form_field_id'], 'job_doc_app_field_uq');
                $table->index(['job_application_id', 'document_kind'], 'job_doc_kind_ix');
                $table->foreign('job_application_id', 'job_doc_app_fk')->references('id')->on('job_applications')->restrictOnDelete();
                $table->foreign('application_form_field_id', 'job_doc_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('job_application_notes')) {
            Schema::create('job_application_notes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('job_note_uuid_uq');
                $table->unsignedBigInteger('job_application_id');
                $table->unsignedBigInteger('author_admin_id')->nullable();
                $table->string('author_name_snapshot', 100)->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index(['job_application_id', 'created_at'], 'job_note_app_time_ix');
                $table->foreign('job_application_id', 'job_note_app_fk')->references('id')->on('job_applications')->restrictOnDelete();
                $table->foreign('author_admin_id', 'job_note_author_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('job_application_status_events')) {
            Schema::create('job_application_status_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_application_id');
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->string('actor_name_snapshot', 100)->nullable();
                $table->string('source', 24)->default('admin');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['job_application_id', 'created_at'], 'job_status_app_time_ix');
                $table->foreign('job_application_id', 'job_status_app_fk')->references('id')->on('job_applications')->restrictOnDelete();
                $table->foreign('actor_admin_id', 'job_status_actor_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('job_scorecard_criteria')) {
            Schema::create('job_scorecard_criteria', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('job_crit_uuid_uq');
                $table->unsignedBigInteger('job_posting_id');
                $table->string('label', 255);
                $table->text('description')->nullable();
                $table->decimal('maximum_score', 8, 2);
                $table->unsignedInteger('position');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['job_posting_id', 'position'], 'job_crit_post_pos_uq');
                $table->foreign('job_posting_id', 'job_crit_post_fk')->references('id')->on('job_postings')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('job_application_scores')) {
            Schema::create('job_application_scores', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_application_id');
                $table->unsignedBigInteger('job_scorecard_criterion_id');
                $table->unsignedBigInteger('reviewer_admin_id')->nullable();
                $table->decimal('score', 8, 2);
                $table->string('criterion_label_snapshot', 255);
                $table->decimal('maximum_score_snapshot', 8, 2);
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique(['job_application_id', 'job_scorecard_criterion_id', 'reviewer_admin_id'], 'job_score_review_uq');
                $table->index(['job_application_id', 'reviewer_admin_id'], 'job_score_app_reviewer_ix');
                $table->foreign('job_application_id', 'job_score_app_fk')->references('id')->on('job_applications')->restrictOnDelete();
                $table->foreign('job_scorecard_criterion_id', 'job_score_crit_fk')->references('id')->on('job_scorecard_criteria')->restrictOnDelete();
                $table->foreign('reviewer_admin_id', 'job_score_reviewer_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData([
            'job_application_scores',
            'job_scorecard_criteria',
            'job_application_status_events',
            'job_application_notes',
            'job_application_documents',
            'job_application_answers',
            'job_applications',
        ]);

        Schema::dropIfExists('job_application_scores');
        Schema::dropIfExists('job_scorecard_criteria');
        Schema::dropIfExists('job_application_status_events');
        Schema::dropIfExists('job_application_notes');
        Schema::dropIfExists('job_application_documents');
        Schema::dropIfExists('job_application_answers');
        Schema::dropIfExists('job_applications');
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
