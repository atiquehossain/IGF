<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssl_commerz_transactions')) {
            return;
        }

        $allowed = array_flip([
            'tran_id', 'val_id', 'bank_tran_id', 'status', 'amount', 'total_amount',
            'store_amount', 'currency', 'currency_type', 'currency_amount',
            'currency_rate', 'base_fair', 'card_type', 'card_brand',
            'card_issuer_country_code', 'risk_level', 'risk_title',
            'value_a', 'value_b', 'value_c', 'value_d', 'opt_a', 'opt_b', 'opt_c', 'opt_d',
        ]);

        DB::table('ssl_commerz_transactions')
            ->select(['id', 'raw_response'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($allowed) {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->raw_response, true);
                    $safe = is_array($decoded) ? array_intersect_key($decoded, $allowed) : [];

                    DB::table('ssl_commerz_transactions')
                        ->where('id', $row->id)
                        ->update([
                            'card_no' => null,
                            'cus_name' => null,
                            'cus_email' => null,
                            'cus_phone' => null,
                            'raw_response' => $safe === [] ? null : json_encode($safe),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Sensitive payment data is intentionally not recoverable after scrubbing.
    }
};
