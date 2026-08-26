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
        // Permission rows remain additive so deployed role ID lists never
        // point at a different capability after rollback.
    }
};
