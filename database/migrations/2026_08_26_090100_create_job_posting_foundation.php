<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('job_postings')) {
            Schema::create('job_postings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('job_post_uuid_uq');
                $table->unsignedBigInteger('application_form_id');
                $table->unsignedBigInteger('current_form_version_id');
                $table->string('publication_status', 24)->default('draft');
                $table->timestamp('visible_from_at')->nullable();
                $table->timestamp('application_opens_at');
                $table->timestamp('application_closes_at');
                $table->string('employment_type', 32);
                $table->string('work_arrangement', 24)->default('on_site');
                $table->unsignedInteger('vacancy_count')->default(1);
                $table->unsignedBigInteger('editor_version')->default(0);
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->unsignedBigInteger('published_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['publication_status', 'visible_from_at', 'application_closes_at'], 'job_post_public_ix');
                $table->foreign('application_form_id', 'job_post_form_fk')->references('id')->on('application_forms')->restrictOnDelete();
                $table->foreign('current_form_version_id', 'job_post_form_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
                $table->foreign('created_by_admin_id', 'job_post_created_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('updated_by_admin_id', 'job_post_updated_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('published_by_admin_id', 'job_post_pub_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('job_posting_translations')) {
            Schema::create('job_posting_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_posting_id');
                $table->string('locale', 10);
                $table->string('slug', 190);
                $table->string('title', 255);
                $table->string('department', 150)->nullable();
                $table->string('location', 255)->nullable();
                $table->text('summary')->nullable();
                $table->longText('description')->nullable();
                $table->longText('responsibilities')->nullable();
                $table->longText('requirements')->nullable();
                $table->timestamps();

                $table->unique(['job_posting_id', 'locale'], 'job_post_tr_locale_uq');
                $table->unique(['locale', 'slug'], 'job_post_tr_slug_uq');
                $table->foreign('job_posting_id', 'job_post_tr_post_fk')->references('id')->on('job_postings')->restrictOnDelete();
                $table->foreign('locale', 'job_post_tr_locale_fk')->references('locale')->on('translation_locales')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData(['job_posting_translations', 'job_postings']);

        Schema::dropIfExists('job_posting_translations');
        Schema::dropIfExists('job_postings');
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
