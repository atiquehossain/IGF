<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsAuthVersion = !Schema::hasColumn('admins', 'auth_version');
        $needsRememberToken = !Schema::hasColumn('admins', 'remember_token');

        if (!$needsAuthVersion && !$needsRememberToken) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) use ($needsAuthVersion, $needsRememberToken) {
            if ($needsAuthVersion) {
                $table->unsignedBigInteger('auth_version')->default(0)->after('password_changed_at');
            }

            if ($needsRememberToken) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('admins', 'auth_version') ? 'auth_version' : null,
            Schema::hasColumn('admins', 'remember_token') ? 'remember_token' : null,
        ]));

        if ($columns !== []) {
            Schema::table('admins', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
