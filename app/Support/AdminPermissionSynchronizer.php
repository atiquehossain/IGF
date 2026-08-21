<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminPermissionSynchronizer
{
    private const OWNER_ROLE_NAMES = ['super admin', 'deployment owner'];

    public function synchronize(): void
    {
        DB::transaction(function (): void {
            $initialMenuLinks = DB::table('auth_menus')->pluck('link', 'id')->all();
            $initialActionLinks = DB::table('menu_actions')->pluck('link', 'id')->all();

            $menuIds = $this->synchronizeMenus();
            $actionIds = $this->synchronizeActions($menuIds);

            $this->backfillRoles(
                $menuIds,
                $actionIds,
                $initialMenuLinks,
                $initialActionLinks
            );
        });
    }

    /** @return array<string, int> */
    private function synchronizeMenus(): array
    {
        $ids = [];
        foreach (AdminPermissionRegistry::menus() as $link => $definition) {
            $existing = DB::table('auth_menus')->where('link', $link)->orderBy('id')->first();
            $insert = [
                'parent_id' => $definition['parent_id'],
                'name' => $definition['name'],
                'link' => $link,
                'icon' => $definition['icon'],
                'order_by' => $definition['order_by'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($existing) {
                // Registry synchronization is additive. Once a definition
                // exists, its label, placement, ordering and enabled state are
                // administrator-owned and must survive later migrations.
                $ids[$link] = (int) $existing->id;
                continue;
            }

            $preferredId = (int) $definition['id'];
            if (!DB::table('auth_menus')->where('id', $preferredId)->exists()) {
                DB::table('auth_menus')->insert(array_merge($insert, ['id' => $preferredId]));
                $ids[$link] = $preferredId;
                continue;
            }

            $ids[$link] = (int) DB::table('auth_menus')->insertGetId($insert);
        }

        return $ids;
    }

    /** @param array<string, int> $menuIds
     *  @return array<string, int>
     */
    private function synchronizeActions(array $menuIds): array
    {
        $ids = [];
        foreach (AdminPermissionRegistry::actions() as $link => $definition) {
            $existing = DB::table('menu_actions')->where('link', $link)->orderBy('id')->first();
            if (!$existing && isset($definition['rename_from'])) {
                $existing = DB::table('menu_actions')
                    ->where('link', $definition['rename_from'])
                    ->orderBy('id')
                    ->first();
                if ($existing) {
                    DB::table('menu_actions')->where('id', $existing->id)->update(['link' => $link]);
                }
            }
            $insert = [
                'auth_menu_id' => $menuIds[$definition['menu']],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'link' => $link,
                'icon' => null,
                'order_by' => $definition['order_by'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($existing) {
                // Preserve owner-managed metadata and disabled state. An
                // explicit `rename_from` above is the sole in-place mutation.
                $ids[$link] = (int) $existing->id;
                continue;
            }

            $preferredId = (int) $definition['id'];
            if (!DB::table('menu_actions')->where('id', $preferredId)->exists()) {
                DB::table('menu_actions')->insert(array_merge($insert, ['id' => $preferredId]));
                $ids[$link] = $preferredId;
                continue;
            }

            $ids[$link] = (int) DB::table('menu_actions')->insertGetId($insert);
        }

        return $ids;
    }

    /**
     * @param array<string, int> $menuIds
     * @param array<string, int> $actionIds
     * @param array<int|string, string|null> $initialMenuLinks
     * @param array<int|string, string|null> $initialActionLinks
     */
    private function backfillRoles(
        array $menuIds,
        array $actionIds,
        array $initialMenuLinks,
        array $initialActionLinks
    ): void {
        $menuDefinitions = AdminPermissionRegistry::menus();
        $actionDefinitions = AdminPermissionRegistry::actions();
        $existingMenuLinks = array_fill_keys(array_filter($initialMenuLinks, 'is_string'), true);
        $existingActionLinks = array_fill_keys(array_filter($initialActionLinks, 'is_string'), true);
        $hasOwnerColumn = Schema::hasColumn('roles', 'is_owner');
        $legacyOwnerRoleId = $hasOwnerColumn ? null : $this->legacyOwnerRoleId();

        DB::table('roles')->orderBy('id')->get()->each(function (object $role) use (
            $menuIds,
            $actionIds,
            $initialMenuLinks,
            $initialActionLinks,
            $menuDefinitions,
            $actionDefinitions,
            $existingMenuLinks,
            $existingActionLinks,
            $hasOwnerColumn,
            $legacyOwnerRoleId
        ): void {
            $permissions = $this->ids($role->permission);
            $actionPermissions = $this->ids($role->actionPermission);
            $initialCapabilities = [];

            foreach ($permissions as $id) {
                $link = $initialMenuLinks[$id] ?? $initialMenuLinks[(int) $id] ?? null;
                if (is_string($link) && $link !== '') {
                    $initialCapabilities[] = $link;
                }
            }
            foreach ($actionPermissions as $id) {
                $link = $initialActionLinks[$id] ?? $initialActionLinks[(int) $id] ?? null;
                if (is_string($link) && $link !== '') {
                    $initialCapabilities[] = $link;
                }
            }
            $initialCapabilities = array_values(array_unique($initialCapabilities));
            $isOwner = $hasOwnerColumn
                ? (bool) ($role->is_owner ?? false)
                : (int) $role->id === $legacyOwnerRoleId;

            foreach ($menuDefinitions as $link => $definition) {
                if ($isOwner || (!isset($existingMenuLinks[$link]) && $this->hasAny($initialCapabilities, $definition['grant_from']))) {
                    $permissions[] = (string) $menuIds[$link];
                }
            }
            foreach ($actionDefinitions as $link => $definition) {
                if ($isOwner || (!isset($existingActionLinks[$link]) && $this->hasAny($initialCapabilities, $definition['grant_from']))) {
                    $actionPermissions[] = (string) $actionIds[$link];
                }
            }

            $permissions = array_values(array_unique($permissions));
            $actionPermissions = array_values(array_unique($actionPermissions));
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => implode(',', $permissions),
                'actionPermission' => implode(',', $actionPermissions),
                'serial' => $this->menuTreeJson($permissions),
                'updated_at' => now(),
            ]);
        });
    }

    /** @param list<string> $selectedIds */
    private function menuTreeJson(array $selectedIds): string
    {
        $selected = array_fill_keys($selectedIds, true);
        $rows = DB::table('auth_menus')
            ->where('status', 1)
            ->orderByRaw('COALESCE(order_by, 999999)')
            ->orderBy('id')
            ->get()
            ->filter(fn (object $menu): bool => isset($selected[(string) $menu->id]));
        $byParent = [];
        foreach ($rows as $row) {
            $parent = $row->parent_id === null ? 'root' : (string) $row->parent_id;
            $byParent[$parent][] = $row;
        }

        $build = function (string $parent) use (&$build, $byParent): array {
            return array_map(function (object $menu) use (&$build): array {
                return [
                    'id' => $menu->id,
                    'parent_id' => $menu->parent_id,
                    'name' => $menu->name,
                    'link' => $menu->link,
                    'icon' => $menu->icon,
                    'order_by' => $menu->order_by,
                    'status' => $menu->status,
                    'children' => $build((string) $menu->id),
                ];
            }, $byParent[$parent] ?? []);
        };

        return json_encode($build('root'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /** @return list<string> */
    private function ids(?string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn (string $id): bool => $id !== ''
        )));
    }

    /** @param list<string> $current
     *  @param list<string> $candidates
     */
    private function hasAny(array $current, array $candidates): bool
    {
        return array_intersect($current, $candidates) !== [];
    }

    private function legacyOwnerRoleId(): int
    {
        $activeRoles = DB::table('admins')
            ->join('roles', 'roles.id', '=', 'admins.role')
            ->where('admins.status', 1)
            ->orderBy('admins.id')
            ->get(['roles.id', 'roles.name', 'admins.username', 'admins.password'])
            ->filter(fn (object $assignment): bool => $this->isLoginCapableAdmin($assignment))
            ->unique('id')
            ->values();

        if (DB::table('admins')->exists() && $activeRoles->isEmpty()) {
            throw new \RuntimeException(
                'At least one existing administrator must be active, login-capable, and assigned to a valid role before permission synchronization can continue.'
            );
        }

        $namedActiveRole = $activeRoles->first(fn (object $role): bool => in_array(
            strtolower(trim((string) $role->name)),
            self::OWNER_ROLE_NAMES,
            true
        ));

        return (int) ($namedActiveRole?->id ?: $activeRoles->first()?->id ?: DB::table('roles')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), self::OWNER_ROLE_NAMES)
            ->orderBy('id')
            ->value('id') ?: 0);
    }

    private function isLoginCapableAdmin(object $assignment): bool
    {
        $username = (string) ($assignment->username ?? '');
        $password = (string) ($assignment->password ?? '');
        $passwordInfo = password_get_info($password);

        return $username !== ''
            && hash_equals($username, trim($username))
            && ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
    }
}
