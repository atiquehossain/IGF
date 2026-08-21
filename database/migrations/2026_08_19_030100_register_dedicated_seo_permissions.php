<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $menuId = $this->ensureRegistryRow('auth_menus', 'seo.index', 50, [
            'parent_id' => null,
            'name' => 'SEO Pack',
            'icon' => 'fa-search',
            'order_by' => 53,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $metadataActionId = $this->ensureRegistryRow('menu_actions', 'seo.metadata.manage', 168, [
            'auth_menu_id' => $menuId,
            'name' => 'Manage SEO metadata',
            'type' => 2,
            'icon' => null,
            'order_by' => 1,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $redirectActionId = $this->ensureRegistryRow('menu_actions', 'seo.redirects.manage', 169, [
            'auth_menu_id' => $menuId,
            'name' => 'Manage SEO redirects',
            'type' => 2,
            'icon' => null,
            'order_by' => 2,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('roles')
            ->whereRaw('LOWER(name) = ?', ['super admin'])
            ->get(['id', 'permission', 'actionPermission'])
            ->each(function (object $role) use ($menuId, $metadataActionId, $redirectActionId): void {
                DB::table('roles')->where('id', $role->id)->update([
                    'permission' => $this->appendCsvIds($role->permission, [$menuId]),
                    'actionPermission' => $this->appendCsvIds(
                        $role->actionPermission,
                        [$metadataActionId, $redirectActionId]
                    ),
                ]);
            });
    }

    public function down(): void
    {
        $menuId = DB::table('auth_menus')->where('link', 'seo.index')->value('id');
        $actionIds = DB::table('menu_actions')
            ->whereIn('link', ['seo.metadata.manage', 'seo.redirects.manage'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($menuId || $actionIds !== []) {
            DB::table('roles')->get(['id', 'permission', 'actionPermission'])
                ->each(function (object $role) use ($menuId, $actionIds): void {
                    DB::table('roles')->where('id', $role->id)->update([
                        'permission' => $this->removeCsvIds($role->permission, $menuId ? [(int) $menuId] : []),
                        'actionPermission' => $this->removeCsvIds($role->actionPermission, $actionIds),
                    ]);
                });
        }

        DB::table('menu_actions')->whereIn('link', ['seo.metadata.manage', 'seo.redirects.manage'])->delete();
        DB::table('auth_menus')->where('link', 'seo.index')->delete();
    }

    private function ensureRegistryRow(string $table, string $link, int $preferredId, array $values): int
    {
        $existing = DB::table($table)->where('link', $link)->first();
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($values);
            return (int) $existing->id;
        }

        if (!DB::table($table)->where('id', $preferredId)->exists()) {
            DB::table($table)->insert(array_merge($values, ['id' => $preferredId, 'link' => $link]));
            return $preferredId;
        }

        return (int) DB::table($table)->insertGetId(array_merge($values, ['link' => $link]));
    }

    private function appendCsvIds(?string $value, array $ids): string
    {
        return collect(explode(',', (string) $value))
            ->filter(fn ($id) => trim($id) !== '')
            ->map(fn ($id) => (string) (int) $id)
            ->merge(array_map(fn ($id) => (string) (int) $id, $ids))
            ->unique()
            ->implode(',');
    }

    private function removeCsvIds(?string $value, array $ids): string
    {
        $remove = array_map(fn ($id) => (string) (int) $id, $ids);

        return collect(explode(',', (string) $value))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '' && !in_array($id, $remove, true))
            ->implode(',');
    }
};
