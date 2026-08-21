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
        // Permission rows are retained so deployed role CSVs never point at a
        // deleted identifier during rollback.
    }
};
