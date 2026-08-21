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
        // Retain the capability row so existing role assignments never point
        // at a deleted permission identifier during a rollback.
    }
};
