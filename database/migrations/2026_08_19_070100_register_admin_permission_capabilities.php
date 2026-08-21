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
        // Authorization data is intentionally not deleted on rollback. Removing
        // capability rows would leave deployed role CSVs pointing at missing IDs.
    }
};
