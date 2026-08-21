<?php

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        // Keep the additive capability row so deployed role permission IDs
        // never point at a different action after a rollback.
    }
};
