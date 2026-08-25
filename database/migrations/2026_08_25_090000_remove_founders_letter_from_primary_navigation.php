<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KNOWN_UUIDS = [
        '68000000-0002-4000-8000-000000000002',
        '69000000-0002-4000-8000-000000000002',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        $this->foundersLetterItems()->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally irreversible because the prior editor-controlled status is unknown.
    }

    private function foundersLetterItems()
    {
        return DB::table('page_menus')
            ->where('type', 'main')
            ->where(function ($query): void {
                $query->whereIn('uuid', self::KNOWN_UUIDS)
                    ->orWhere(function ($item): void {
                        $item->where('link', 'frontend.page')
                            ->where('slug', "founder's-letter");
                    });
            });
    }
};
