<?php

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 180300 may already be recorded on an installation that encountered
        // the former capability-id collision. A new forward migration makes
        // the corrected registry entry deterministic without deleting roles.
        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        // Stable permission ids and existing role assignments are retained.
    }
};
