<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('application_forms')) {
            Schema::create('application_forms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('app_forms_uuid_uq');
                $table->string('purpose', 24);
                $table->string('name', 150);
                $table->boolean('is_template')->default(false);
                $table->unsignedBigInteger('editor_version')->default(0);
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['purpose', 'is_template'], 'app_forms_purpose_tpl_ix');
                $table->foreign('created_by_admin_id', 'app_forms_created_fk')->references('id')->on('admins')->nullOnDelete();
                $table->foreign('updated_by_admin_id', 'app_forms_updated_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_versions')) {
            Schema::create('application_form_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('app_form_ver_uuid_uq');
                $table->unsignedBigInteger('application_form_id');
                $table->unsignedInteger('version');
                $table->string('state', 24)->default('draft');
                $table->char('schema_hash', 64)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('published_by_admin_id')->nullable();
                $table->timestamps();

                $table->unique(['application_form_id', 'version'], 'app_form_ver_num_uq');
                $table->index(['application_form_id', 'state'], 'app_form_ver_state_ix');
                $table->foreign('application_form_id', 'app_form_ver_form_fk')->references('id')->on('application_forms')->restrictOnDelete();
                $table->foreign('published_by_admin_id', 'app_form_ver_pub_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_fields')) {
            Schema::create('application_form_fields', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('application_form_version_id');
                $table->string('field_key', 64);
                $table->string('system_key', 64)->nullable();
                $table->string('type', 32);
                $table->unsignedInteger('position');
                $table->boolean('is_required')->default(false);
                $table->json('validation')->nullable();
                $table->timestamps();

                $table->unique(['application_form_version_id', 'field_key'], 'app_form_fld_key_uq');
                $table->unique(['application_form_version_id', 'position'], 'app_form_fld_pos_uq');
                $table->unique(['application_form_version_id', 'system_key'], 'app_form_fld_sys_uq');
                $table->foreign('application_form_version_id', 'app_form_fld_ver_fk')->references('id')->on('application_form_versions')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_field_translations')) {
            Schema::create('application_form_field_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('application_form_field_id');
                $table->string('locale', 10);
                $table->string('label', 255);
                $table->text('help_text')->nullable();
                $table->string('placeholder', 255)->nullable();
                $table->timestamps();

                $table->unique(['application_form_field_id', 'locale'], 'app_fld_tr_locale_uq');
                $table->foreign('application_form_field_id', 'app_fld_tr_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
                $table->foreign('locale', 'app_fld_tr_locale_fk')->references('locale')->on('translation_locales')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_options')) {
            Schema::create('application_form_options', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('application_form_field_id');
                $table->string('option_key', 64);
                $table->unsignedInteger('position');
                $table->timestamps();

                $table->unique(['application_form_field_id', 'option_key'], 'app_form_opt_key_uq');
                $table->unique(['application_form_field_id', 'position'], 'app_form_opt_pos_uq');
                $table->foreign('application_form_field_id', 'app_form_opt_field_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_option_translations')) {
            Schema::create('application_form_option_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('application_form_option_id');
                $table->string('locale', 10);
                $table->string('label', 255);
                $table->timestamps();

                $table->unique(['application_form_option_id', 'locale'], 'app_opt_tr_locale_uq');
                $table->foreign('application_form_option_id', 'app_opt_tr_option_fk')->references('id')->on('application_form_options')->restrictOnDelete();
                $table->foreign('locale', 'app_opt_tr_locale_fk')->references('locale')->on('translation_locales')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('application_form_conditions')) {
            Schema::create('application_form_conditions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('target_field_id');
                $table->unsignedBigInteger('source_field_id');
                $table->unsignedInteger('condition_group')->default(1);
                $table->string('boolean_connector', 8)->default('and');
                $table->string('operator', 32);
                $table->json('comparison_value')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();

                $table->unique(['target_field_id', 'condition_group', 'position'], 'app_form_cond_pos_uq');
                $table->index(['source_field_id', 'target_field_id'], 'app_form_cond_edge_ix');
                $table->foreign('target_field_id', 'app_form_cond_target_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
                $table->foreign('source_field_id', 'app_form_cond_source_fk')->references('id')->on('application_form_fields')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->refuseRollbackWithData([
            'application_form_conditions',
            'application_form_option_translations',
            'application_form_options',
            'application_form_field_translations',
            'application_form_fields',
            'application_form_versions',
            'application_forms',
        ]);

        Schema::dropIfExists('application_form_conditions');
        Schema::dropIfExists('application_form_option_translations');
        Schema::dropIfExists('application_form_options');
        Schema::dropIfExists('application_form_field_translations');
        Schema::dropIfExists('application_form_fields');
        Schema::dropIfExists('application_form_versions');
        Schema::dropIfExists('application_forms');
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
