<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('donation_types') || Schema::hasColumn('donation_types', 'purpose_key')) {
            return;
        }

        Schema::table('donation_types', function (Blueprint $table): void {
            $table->string('purpose_key', 50)->nullable()->after('uuid');
        });

        // Preserve the established Zakat destination once, then use the
        // semantic purpose for every future lookup. The name fallback supports
        // installations whose launch content used a different UUID.
        $zakat = DB::table('donation_types')
            ->where('uuid', '84ae0875-0656-494a-b3a2-9c9477397465')
            ->first()
            ?? DB::table('donation_types')
                ->whereRaw('LOWER(name) LIKE ?', ['%zakat%'])
                ->orderBy('id')
                ->first();

        if ($zakat) {
            DB::table('donation_types')->where('id', $zakat->id)->update([
                'purpose_key' => 'zakat',
                'updated_at' => now(),
            ]);
        }

        Schema::table('donation_types', function (Blueprint $table): void {
            $table->unique('purpose_key', 'donation_types_purpose_key_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('donation_types') || !Schema::hasColumn('donation_types', 'purpose_key')) {
            return;
        }

        Schema::table('donation_types', function (Blueprint $table): void {
            $table->dropUnique('donation_types_purpose_key_unique');
            $table->dropColumn('purpose_key');
        });
    }
};
