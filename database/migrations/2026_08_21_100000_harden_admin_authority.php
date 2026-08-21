<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every validation that can stop this migration must happen before
        // MySQL's auto-committed DDL. This keeps a failed upgrade rerunnable.
        $duplicateUsername = DB::table('admins')
            ->selectRaw('LOWER(TRIM(username)) AS normalized_username, COUNT(*) AS aggregate')
            ->whereNotNull('username')
            ->groupByRaw('LOWER(TRIM(username))')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        $duplicateEmail = DB::table('admins')
            ->selectRaw('LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS aggregate')
            ->whereNotNull('email')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateUsername || $duplicateEmail) {
            throw new RuntimeException('Duplicate administrator usernames or emails must be reconciled before authority hardening can be installed.');
        }

        $ownerRoleId = $this->resolveRecoverableOwnerRoleId();
        $addedSecurityRank = !Schema::hasColumn('roles', 'security_rank');

        if ($addedSecurityRank) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unsignedSmallInteger('security_rank')->default(100)->after('parent_id');
            });
        }
        if (!Schema::hasColumn('roles', 'is_owner')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->boolean('is_owner')->default(false)->after('security_rank');
            });
        }
        if (!Schema::hasIndex('roles', 'roles_owner_status_index')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->index(['is_owner', 'status'], 'roles_owner_status_index');
            });
        }
        if (!Schema::hasIndex('roles', 'roles_rank_status_index')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->index(['security_rank', 'status'], 'roles_rank_status_index');
            });
        }

        if (!Schema::hasTable('admin_audit_events')) {
            Schema::create('admin_audit_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_uuid')->unique();
                $table->unsignedBigInteger('actor_admin_id')->nullable()->index();
                $table->string('actor_name_snapshot', 100)->nullable();
                $table->string('action', 100)->index();
                $table->string('target_type', 100)->nullable();
                $table->string('target_id', 64)->nullable();
                $table->string('target_label_snapshot', 150)->nullable();
                $table->string('outcome', 16)->default('success')->index();
                $table->json('changes')->nullable();
                $table->json('context')->nullable();
                $table->char('ip_hash', 64)->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
                $table->index(['target_type', 'target_id'], 'admin_audit_target_index');
            });
        }

        if (!Schema::hasIndex('admins', 'admins_username_unique')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->unique('username', 'admins_username_unique');
            });
        }
        if (!Schema::hasIndex('admins', 'admins_email_unique')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->unique('email', 'admins_email_unique');
            });
        }

        // Recompute the legacy rank mapping on every unrecorded attempt. A
        // previous MySQL attempt may have auto-committed the column DDL and
        // failed before reaching this data backfill; guarding only on whether
        // the column was added in this process would then leave every role at
        // the default rank.
        $roles = DB::table('roles')->orderBy('id')->get(['id', 'order_by']);
        foreach ($roles as $role) {
            $order = is_numeric($role->order_by) ? (int) $role->order_by : (int) $role->id;
            DB::table('roles')->where('id', $role->id)->update([
                'security_rank' => min(65535, max(100, 100 + $order)),
            ]);
        }

        if (!$ownerRoleId) {
            $ownerRoleId = DB::table('roles')->insertGetId([
                'name' => 'Deployment Owner',
                'permission' => '',
                'actionPermission' => '',
                'serial' => '[]',
                'order_by' => 0,
                'status' => 1,
                'security_rank' => 0,
                'is_owner' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('roles')->where('id', '!=', $ownerRoleId)->where('is_owner', true)->update(['is_owner' => false]);
        DB::table('roles')->where('id', '!=', $ownerRoleId)->where('security_rank', 0)->update(['security_rank' => 100]);
        DB::table('roles')->where('id', $ownerRoleId)->update([
            'security_rank' => 0,
            'is_owner' => true,
            'status' => 1,
        ]);
    }

    public function down(): void
    {
        // Intentionally one-way. Rolling back must never erase the append-only
        // security ledger or reopen duplicate-identity and owner-continuity
        // vulnerabilities. Restore a verified pre-migration backup instead.
    }

    private function resolveRecoverableOwnerRoleId(): ?int
    {
        $hasOwnerColumn = Schema::hasColumn('roles', 'is_owner');
        $activeAssignments = DB::table('admins')
            ->join('roles', 'roles.id', '=', 'admins.role')
            ->where('admins.status', 1)
            ->orderBy('admins.id')
            ->get(array_filter([
                'roles.id',
                'roles.name',
                'admins.username',
                'admins.password',
                $hasOwnerColumn ? 'roles.is_owner' : null,
            ]))
            ->filter(fn (object $assignment): bool => $this->isLoginCapableAdmin($assignment))
            ->values();

        if (DB::table('admins')->exists()) {
            if ($activeAssignments->isEmpty()) {
                throw new RuntimeException(
                    'At least one existing administrator must be active, login-capable, and assigned to a valid role before authority hardening can be installed.'
                );
            }

            $preferred = $hasOwnerColumn
                ? $activeAssignments->first(fn (object $role): bool => (bool) ($role->is_owner ?? false))
                : null;
            $preferred ??= $activeAssignments->first(fn (object $role): bool => in_array(
                strtolower(trim((string) $role->name)),
                ['deployment owner', 'super admin'],
                true
            ));

            return (int) ($preferred?->id ?? $activeAssignments->first()->id);
        }

        if ($hasOwnerColumn) {
            $existingOwner = DB::table('roles')->where('is_owner', true)->orderBy('id')->value('id');
            if ($existingOwner) {
                return (int) $existingOwner;
            }
        }

        $named = DB::table('roles')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), ['deployment owner', 'super admin'])
            ->orderBy('id')
            ->value('id');

        return $named ? (int) $named : (DB::table('roles')->orderBy('id')->value('id') ?: null);
    }

    private function isLoginCapableAdmin(object $assignment): bool
    {
        $username = (string) ($assignment->username ?? '');
        $password = (string) ($assignment->password ?? '');
        $passwordInfo = password_get_info($password);

        return $username !== ''
            && hash_equals($username, trim($username))
            && ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
    }
};
