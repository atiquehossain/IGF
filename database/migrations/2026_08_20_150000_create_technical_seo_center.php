<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\AdminPermissionSynchronizer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('running')->index();
            $table->string('trigger', 20)->default('admin');
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('urls_checked')->default(0);
            $table->unsignedInteger('issues_found')->default(0);
            $table->json('summary')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('seo_audit_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('seo_audit_runs')->cascadeOnDelete();
            $table->char('fingerprint', 64)->index();
            $table->string('issue_type', 50)->index();
            $table->string('severity', 12)->index();
            $table->string('source_path', 1024);
            $table->string('target_path', 1024)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('message', 500);
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'fingerprint']);
        });

        Schema::create('seo_audit_ignore_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('issue_type', 50);
            $table->string('source_path', 1024);
            $table->string('target_path', 1024)->nullable();
            $table->string('reason', 300)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_not_found_hits', function (Blueprint $table): void {
            $table->id();
            $table->char('scope_hash', 64)->unique();
            $table->char('path_hash', 64)->index();
            $table->string('path', 1024);
            $table->string('locale', 10)->default('en')->index();
            $table->string('referrer_path', 1024)->nullable();
            $table->unsignedBigInteger('hits')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unsignedBigInteger('redirect_id')->nullable()->index();
            $table->timestamps();
        });

        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        $menuId = DB::table('auth_menus')->where('link', 'seo.technical.index')->value('id');
        $actionIds = DB::table('menu_actions')->whereIn('link', [
            'seo.technical.scan', 'seo.technical.ignore', 'seo.technical.redirect',
        ])->pluck('id')->map(fn ($id) => (int) $id)->all();
        DB::table('roles')->get(['id', 'permission', 'actionPermission'])->each(function (object $role) use ($menuId, $actionIds): void {
            $removeMenus = $menuId ? [(string) (int) $menuId] : [];
            $removeActions = array_map(fn (int $id): string => (string) $id, $actionIds);
            $permissions = array_values(array_filter(array_map('trim', explode(',', (string) $role->permission)),
                fn (string $id): bool => $id !== '' && !in_array($id, $removeMenus, true)));
            $actions = array_values(array_filter(array_map('trim', explode(',', (string) $role->actionPermission)),
                fn (string $id): bool => $id !== '' && !in_array($id, $removeActions, true)));
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => implode(',', $permissions),
                'actionPermission' => implode(',', $actions),
            ]);
        });
        DB::table('menu_actions')->whereIn('link', ['seo.technical.scan', 'seo.technical.ignore', 'seo.technical.redirect'])->delete();
        DB::table('auth_menus')->where('link', 'seo.technical.index')->delete();

        Schema::dropIfExists('seo_not_found_hits');
        Schema::dropIfExists('seo_audit_ignore_rules');
        Schema::dropIfExists('seo_audit_issues');
        Schema::dropIfExists('seo_audit_runs');
    }
};
