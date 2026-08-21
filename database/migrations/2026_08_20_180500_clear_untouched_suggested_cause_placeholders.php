<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UNTOUCHED = [
        '6e3c1d7a-8b01-4f01-8a01-000000000001' => [
            'Draft giving option. Review its description, destination and image before publishing.',
            'Draft giving option. Review its destination, wording and image before publishing.',
        ],
        '6e3c1d7a-8b01-4f01-8a01-000000000002' => [
            'Draft giving option. Connect it to the correct fund or program before publishing.',
        ],
        '6e3c1d7a-8b01-4f01-8a01-000000000003' => [
            'Draft giving option. Confirm its current relief destination before publishing.',
        ],
        '6e3c1d7a-8b01-4f01-8a01-000000000004' => [
            'Draft giving option. Review safeguarding wording, destination and image before publishing.',
            'Draft giving option. Review safeguarding wording and destination before publishing.',
        ],
    ];

    public function up(): void
    {
        foreach (self::UNTOUCHED as $uuid => $messages) {
            DB::table('donation_types')
                ->where('uuid', $uuid)
                ->where('status', 0)
                ->whereIn('description', $messages)
                ->update([
                    'description' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally keep the safer blank draft copy. Rollback must not put
        // internal review instructions back into visitor-facing content.
    }
};
