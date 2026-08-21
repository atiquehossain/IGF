<?php

namespace Database\Seeders;

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Seeder;

class AdminPermissionRegistrySeeder extends Seeder
{
    public function run(): void
    {
        app(AdminPermissionSynchronizer::class)->synchronize();
    }
}
