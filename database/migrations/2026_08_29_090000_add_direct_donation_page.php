<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DIRECT_CAUSE_UUID = '55555555-5555-4555-8555-000000000001';

    private const MAKE_DONATION_MENU_UUIDS = [
        '68000000-0006-4000-8000-000000000001',
        '69000000-0006-4000-8000-000000000001',
    ];

    public function up(): void
    {
        if (Schema::hasTable('donation_types') && Schema::hasColumn('donation_types', 'purpose_key')) {
            DB::transaction(function (): void {
                $hasDirectCause = DB::table('donation_types')
                    ->where('purpose_key', 'direct')
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$hasDirectCause) {
                    DB::table('donation_types')
                        ->where('purpose_key', 'direct')
                        ->whereNotNull('deleted_at')
                        ->update(['purpose_key' => null, 'updated_at' => now()]);

                    DB::table('donation_types')
                        ->whereNull('deleted_at')
                        ->where(function ($query): void {
                            $query->where('uuid', self::DIRECT_CAUSE_UUID)
                                ->orWhere('slug', 'where-it-is-needed-most');
                        })
                        ->update([
                            'purpose_key' => 'direct',
                            'updated_at' => now(),
                        ]);
                }
            });
        }

        if (!Schema::hasTable('page_menus')) {
            return;
        }

        DB::transaction(function (): void {
            $stableRows = DB::table('page_menus')
                ->whereIn('uuid', self::MAKE_DONATION_MENU_UUIDS)
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->where('link', 'frontend.donate.index')
                        ->orWhere(function ($custom): void {
                            $custom->where('link', 'custom')->where('slug', '/donate');
                        });
                });

            $updated = $stableRows->update([
                'link' => 'frontend.donate.direct',
                'slug' => null,
                'updated_at' => now(),
            ]);

            if ($updated > 0) {
                return;
            }

            DB::table('page_menus')
                ->whereNull('deleted_at')
                ->where('name', 'Make a Donation')
                ->where('link', 'frontend.donate.index')
                ->whereIn('parent_id', function ($query): void {
                    $query->select('id')
                        ->from('page_menus')
                        ->whereNull('deleted_at')
                        ->where('type', 'main')
                        ->where('name', 'Donate');
                })
                ->update([
                    'link' => 'frontend.donate.direct',
                    'slug' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('page_menus')) {
            DB::table('page_menus')
                ->whereIn('uuid', self::MAKE_DONATION_MENU_UUIDS)
                ->whereNull('deleted_at')
                ->where('link', 'frontend.donate.direct')
                ->update([
                    'link' => 'frontend.donate.index',
                    'slug' => null,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('donation_types') && Schema::hasColumn('donation_types', 'purpose_key')) {
            DB::table('donation_types')
                ->where('purpose_key', 'direct')
                ->update([
                    'purpose_key' => null,
                    'updated_at' => now(),
                ]);
        }
    }
};
