<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('donation_allocations')) {
            return;
        }
        if (!Schema::hasColumn('donation_allocations', 'allocated_by_name_snapshot')) {
            Schema::table('donation_allocations', function (Blueprint $table): void {
                $table->string('allocated_by_name_snapshot')->nullable()->after('allocated_by');
            });
        }

        DB::table('donation_allocations')
            ->whereNull('allocated_by_name_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($allocations): void {
            foreach ($allocations as $allocation) {
                $name = DB::table('admins')->where('id', $allocation->allocated_by)->value('name');
                DB::table('donation_allocations')->where('id', $allocation->id)->update([
                    'allocated_by_name_snapshot' => trim((string) $name) ?: 'Historical administrator',
                ]);
            }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('donation_allocations')
            || !Schema::hasColumn('donation_allocations', 'allocated_by_name_snapshot')) {
            return;
        }
        if (DB::table('donation_allocations')->whereNotNull('allocated_by_name_snapshot')->exists()) {
            throw new RuntimeException(
                'Rollback refused: allocator identity snapshots are part of the append-only donation audit trail.'
            );
        }

        Schema::table('donation_allocations', function (Blueprint $table): void {
            $table->dropColumn('allocated_by_name_snapshot');
        });
    }
};
