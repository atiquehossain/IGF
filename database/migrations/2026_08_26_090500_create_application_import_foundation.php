<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('application_import_batches')) {
            Schema::create('application_import_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('app_import_uuid_uq');
                $table->string('target_kind', 24);
                $table->unsignedBigInteger('job_posting_id')->nullable();
                $table->unsignedBigInteger('workshop_id')->nullable();
                $table->unsignedBigInteger('application_form_version_id');
                $table->char('form_schema_hash', 64);
                $table->string('state', 24)->default('uploaded');
                $table->string('source_disk', 40);
                $table->string('source_path', 255)->unique('app_import_path_uq');
                $table->string('source_name', 255);
                $table->char('source_sha256', 64);
                $table->json('column_mapping')->nullable();
                $table->json('options')->nullable();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('valid_rows')->default(0);
                $table->unsignedInteger('invalid_rows')->default(0);
                $table->unsignedInteger('duplicate_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedBigInteger('uploaded_by_admin_id')->nullable();
                $table->unsignedBigInteger('confirmed_by_admin_id')->nullable();
                $table->timestamp('previewed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index(['target_kind', 'state', 'created_at'], 'app_import_state_ix');
                $table->index(['job_posting_id', 'created_at'], 'app_import_job_ix');
                $table->index(['workshop_id', 'created_at'], 'app_import_work_ix');
                $table->foreign('job_posting_id', 'app_import_job_fk')->references('id')->on('job_postings')->restrictOnDelete();
                $table->foreign('workshop_id', 'app_import_work_fk')->references('id')->on('workshops')->restrictOnDelete();
                $table->foreign('application_form_version_id', 'app_import_form_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
                $table->foreign('uploaded_by_admin_id', 'app_import_uploader_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('confirmed_by_admin_id', 'app_import_confirmer_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('application_import_rows')) {
            Schema::create('application_import_rows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('application_import_batch_id');
                $table->unsignedInteger('row_number');
                $table->string('state', 24)->default('pending');
                $table->string('action', 24)->nullable();
                $table->json('raw_data');
                $table->json('normalized_data')->nullable();
                $table->json('validation_errors')->nullable();
                $table->uuid('imported_target_uuid')->nullable();
                $table->timestamps();

                $table->unique(['application_import_batch_id', 'row_number'], 'app_import_row_num_uq');
                $table->index(['application_import_batch_id', 'state'], 'app_import_row_state_ix');
                $table->foreign('application_import_batch_id', 'app_import_row_batch_fk')->references('id')->on('application_import_batches')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('admin_listing_preferences')) {
            Schema::create('admin_listing_preferences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('admin_id');
                $table->string('listing_key', 80);
                $table->json('visible_columns');
                $table->string('sort_column', 80)->nullable();
                $table->string('sort_direction', 4)->nullable();
                $table->timestamps();

                $table->unique(['admin_id', 'listing_key'], 'admin_list_pref_uq');
                $table->foreign('admin_id', 'admin_list_pref_admin_fk')->references('id')->on('admins')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData([
            'admin_listing_preferences',
            'application_import_rows',
            'application_import_batches',
        ]);

        Schema::dropIfExists('admin_listing_preferences');
        Schema::dropIfExists('application_import_rows');
        Schema::dropIfExists('application_import_batches');
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
