<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ID = 49;
    private const ACTION_ID = 167;

    public function up(): void
    {
        $now = now();
        DB::table('menu_actions')->upsert([[
            'id' => self::ACTION_ID,
            'auth_menu_id' => self::MENU_ID,
            'name' => 'View public chat questions and settings',
            'type' => 0,
            'link' => 'chat.faq.index',
            'icon' => null,
            'order_by' => 8,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['auth_menu_id', 'name', 'type', 'link', 'order_by', 'status', 'updated_at']);

        DB::table('roles')->get()->each(function ($role): void {
            $existing = $this->ids($role->actionPermission);
            $managedQa = array_intersect(['163', '164', '165', '166'], $existing) !== [];
            if ($role->name === 'Super Admin' || $managedQa) {
                DB::table('roles')->where('id', $role->id)->update([
                    'actionPermission' => $this->appendId($role->actionPermission),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('roles')->get()->each(function ($role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'actionPermission' => $this->removeId($role->actionPermission),
                'updated_at' => now(),
            ]);
        });
        DB::table('menu_actions')->where('id', self::ACTION_ID)->delete();
    }

    private function appendId(?string $value): string
    {
        return collect(explode(',', (string) $value))
            ->push((string) self::ACTION_ID)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->implode(',');
    }

    private function removeId(?string $value): string
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && $id !== (string) self::ACTION_ID)
            ->implode(',');
    }

    private function ids(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};
