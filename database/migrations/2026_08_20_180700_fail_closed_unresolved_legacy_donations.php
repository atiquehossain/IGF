<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('donations', 'destination_type_snapshot')) {
            return;
        }

        // 180100 originally used this exact generated fallback for a donation
        // whose cause could not be resolved. Correct only that known signature;
        // never rewrite a manually reconciled or otherwise populated snapshot.
        DB::table('donations')
            ->where('cause_slug_snapshot', 'unspecified-legacy-donation')
            ->where('cause_name_snapshot', 'Unspecified legacy donation')
            ->whereNull('cause_uuid_snapshot')
            ->whereNull('purpose_key_snapshot')
            ->where('destination_type_snapshot', 'unrestricted')
            ->whereNull('destination_uuid_snapshot')
            ->where('destination_name_snapshot', 'Where it is needed most')
            ->whereNull('project_uuid_snapshot')
            ->whereNull('project_name_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($donations): void {
                foreach ($donations as $donation) {
                    $identity = trim((string) ($donation->cause_uuid_snapshot ?: $donation->payment_cause));
                    if ($identity !== '' && DB::table('donation_types')->where('uuid', $identity)->exists()) {
                        continue;
                    }

                    DB::table('donations')->where('id', $donation->id)->update([
                        'cause_uuid_snapshot' => null,
                        'cause_slug_snapshot' => 'unresolved-legacy-gift',
                        'cause_name_snapshot' => 'Unresolved legacy gift',
                        'purpose_key_snapshot' => null,
                        'destination_type_snapshot' => 'legacy_unspecified',
                        'destination_uuid_snapshot' => null,
                        'destination_name_snapshot' => 'Unresolved legacy designation — allocation blocked',
                        'project_uuid_snapshot' => null,
                        'project_name_snapshot' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Fail-closed classification is financial history. Never turn an
        // unresolved gift back into an unrestricted allocation automatically.
    }
};
