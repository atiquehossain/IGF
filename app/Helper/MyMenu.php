<?php

namespace App\Helper;

use App\Http\Middleware\Permission;
use App\Models\AuthMenu;
use App\Models\PageMenu;
use App\Support\AdminUi;

use Auth;
use Exception;
use Illuminate\Support\Facades\Route;

class MyMenu
{

    public static function menuTree()
    {
        return AuthMenu::with('children')
            ->where('status', 1)
            ->whereNull('parent_id')
            ->orderBy('order_by', 'ASC')->get();
    }

    public static function menuUi(array $excludedRoutes = [])
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return '';
        }

        $permissions = app(Permission::class);
        // Render from current active definitions and the same authorization
        // service as the routes. Legacy serialized role menus can be stale,
        // omit child-only grants, and do not represent owner bypass.
        $role_menu = self::menuTree()->toArray();
        $html = '';
        $routeName = (string) Route::currentRouteName();
        $activeMenu = self::findBestActiveLeaf(
            $role_menu,
            $routeName,
            $admin,
            $permissions,
            $excludedRoutes
        );
        $activeLeafId = $activeMenu['id'] ?? null;
        $activePath = $activeMenu['path'] ?? [];

        foreach ($role_menu as $element) {
            if (!is_array($element)) {
                continue;
            }

            $children = is_array($element['children'] ?? null) ? $element['children'] : [];
            $hasChildren = $children !== [];
            $subchild = $hasChildren
                ? self::subMenu(
                    $children,
                    0,
                    $admin,
                    $permissions,
                    $excludedRoutes,
                    $activeLeafId,
                    $activePath
                )
                : '';
            if (($hasChildren && trim($subchild) === '')
                || (!$hasChildren && !self::canRenderLeaf($element, $admin, $permissions, $excludedRoutes))) {
                continue;
            }

            $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);
            $elementId = (string) ($element['id'] ?? '');
            $isActiveBranch = in_array($elementId, $activePath, true);
            $isCurrentLeaf = $activeLeafId !== null && $elementId === $activeLeafId;
            if ($hasChildren) {
                $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                $menuName = AdminUi::label($element['name'] ?? '');
                $branchClass = $isActiveBranch ? ' active show' : '';
                $html .= '<li class="menu-item-has-children dropdown' . $branchClass . '">';
                $html .= '<a href="' . $menuLink . '" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="' . ($isActiveBranch ? 'true' : 'false') . '">';
                $html .= ' <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';

                $html .= ' <ul class="sub-menu children dropdown-menu' . $branchClass . '"> ' . $subchild . '</ul>';
                $html .= '</li>';
            } else {
                $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                $menuName = AdminUi::label($element['name'] ?? '');
                $html .= '<li class="' . ($isCurrentLeaf ? 'active' : '') . '">';
                $html .= '<a href="' . $menuLink . '"' . ($isCurrentLeaf ? ' class="active" aria-current="page"' : '') . '> <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';
                $html .= '</li>';
            }
        }
        return $html;
    }

    public static function subMenu(
        $child,
        $depth,
        $admin = null,
        ?Permission $permissions = null,
        array $excludedRoutes = [],
        ?string $activeLeafId = null,
        array $activePath = []
    )
    {
        $admin ??= Auth::guard('admin')->user();
        $permissions ??= app(Permission::class);
        if (!$admin) {
            return '';
        }

        $html = "";
        if (!empty($child)) {
            foreach ($child as $element) {
                if (!is_array($element)) {
                    continue;
                }

                $children = is_array($element['children'] ?? null) ? $element['children'] : [];
                $hasChildren = $children !== [];
                $subchild = $hasChildren
                    ? self::subMenu(
                        $children,
                        $depth + 1,
                        $admin,
                        $permissions,
                        $excludedRoutes,
                        $activeLeafId,
                        $activePath
                    )
                    : '';
                if (($hasChildren && trim($subchild) === '')
                    || (!$hasChildren && !self::canRenderLeaf($element, $admin, $permissions, $excludedRoutes))) {
                    continue;
                }

                $elementId = (string) ($element['id'] ?? '');
                $isActiveBranch = in_array($elementId, $activePath, true);
                $isCurrentLeaf = $activeLeafId !== null && $elementId === $activeLeafId;
                if ($hasChildren) {
                    $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                    $menuName = AdminUi::label($element['name'] ?? '');
                    $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);
                    $branchClass = $isActiveBranch ? ' active show' : '';

                    $html .= '<li class="menu-item-has-children dropdown' . $branchClass . '">';
                    $html .= '<a href="' . $menuLink . '" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="' . ($isActiveBranch ? 'true' : 'false') . '">';
                    $html .= ' <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';
                    $html .= '<ul class="sub-menu children dropdown-menu' . $branchClass . '">' . $subchild . '</ul>';
                    $html .= '</li>';
                } else {
                    $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                    $menuName = AdminUi::label($element['name'] ?? '');
                    $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);
                    $html .= '<li class="' . ($isCurrentLeaf ? 'active' : '') . '"><i class="fa ' . $fa_icon . '"></i><a' . ($isCurrentLeaf ? ' class="active" aria-current="page"' : '') . ' href="' . $menuLink . '">' . $menuName . '</a>';
                    $html .= '</li>';
                }
            }
        }
        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $elements
     * @param array<int, string> $ancestorIds
     * @return array{id: string, path: array<int, string>, exact: bool, prefixLength: int}|null
     */
    private static function findBestActiveLeaf(
        array $elements,
        string $currentRouteName,
        $admin,
        Permission $permissions,
        array $excludedRoutes,
        array $ancestorIds = []
    ): ?array {
        if ($currentRouteName === '') {
            return null;
        }

        $best = null;
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $elementId = (string) ($element['id'] ?? '');
            $path = [...$ancestorIds, $elementId];
            $children = is_array($element['children'] ?? null) ? $element['children'] : [];
            if ($children !== []) {
                $candidate = self::findBestActiveLeaf(
                    $children,
                    $currentRouteName,
                    $admin,
                    $permissions,
                    $excludedRoutes,
                    $path
                );
                if (self::isBetterActiveMatch($candidate, $best)) {
                    $best = $candidate;
                }
                continue;
            }

            if (!self::canRenderLeaf($element, $admin, $permissions, $excludedRoutes)) {
                continue;
            }

            $leafRouteName = trim((string) ($element['link'] ?? ''));
            $isExact = $leafRouteName === $currentRouteName;
            $prefix = self::activeRoutePrefix($leafRouteName);
            if (!$isExact && ($prefix === '' || !str_starts_with($currentRouteName, $prefix . '.'))) {
                continue;
            }

            $candidate = [
                'id' => $elementId,
                'path' => $path,
                'exact' => $isExact,
                'prefixLength' => strlen($prefix),
            ];
            if (self::isBetterActiveMatch($candidate, $best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * An index menu represents its complete resource route namespace. Custom
     * destinations (for example report.youtubeMeta) keep their full route as
     * the prefix so unrelated siblings do not become current accidentally.
     */
    private static function activeRoutePrefix(string $routeName): string
    {
        return str_ends_with($routeName, '.index')
            ? substr($routeName, 0, -strlen('.index'))
            : $routeName;
    }

    /**
     * @param array{exact: bool, prefixLength: int}|null $candidate
     * @param array{exact: bool, prefixLength: int}|null $current
     */
    private static function isBetterActiveMatch(?array $candidate, ?array $current): bool
    {
        if ($candidate === null) {
            return false;
        }
        if ($current === null) {
            return true;
        }
        if ($candidate['exact'] !== $current['exact']) {
            return $candidate['exact'];
        }

        return $candidate['prefixLength'] > $current['prefixLength'];
    }

    private static function canRenderLeaf(array $element, $admin, Permission $permissions, array $excludedRoutes = []): bool
    {
        $routeName = trim((string) ($element['link'] ?? ''));

        return $routeName !== ''
            && !in_array($routeName, $excludedRoutes, true)
            && Route::has($routeName)
            && $permissions->allows($admin, $routeName);
    }

    private static function permittedRouteUrl(mixed $routeName, $admin, Permission $permissions): string
    {
        $routeName = trim((string) $routeName);

        return $routeName !== '' && Route::has($routeName) && $permissions->allows($admin, $routeName)
            ? route($routeName)
            : '#';
    }

    public static function firstMenuBySlug($locale = null, $nav = '')
    {
        $defaultData = [
            'id' => 1,
            'name' => 'Home',
            'description' => null,
            'link' => 'frontend.home',
            'parent_id' => null,
            'slug' => null,
            'icon' => null,
            'banner_id' => null,
            'uuid' => null,
            'language' => $locale,
        ];
        try {
            $locale = empty($locale) ? app()->getLocale() : $locale;
            $name = empty($nav) ? \Route::currentRouteName() : $nav;
            $data = PageMenu::select('id', 'name', 'description', 'link')
                ->selectRaw("IFNULL(parent_id, '') as parent_id")
                ->selectRaw("IFNULL(slug, '') as slug")
                ->selectRaw("IFNULL(icon, '') as icon")
                ->where('status', 1)
                ->where('link', $name)
                ->where('language', $locale)
                ->whereNull('parent_id')
                ->orderBy('order_by', 'ASC')
                ->first();
            if (empty($data)) {
                return $defaultData;
            }
            return $data;
        } catch (Exception $e) {
            return $defaultData;
        }
    }

    public static function frontMenus($locale = null, $type = 'main')
    {
        $locale = empty($locale) ? app()->getLocale() : $locale;
        $pageMenus = PageMenu::select('id', 'uuid', 'name', 'description', 'link')
            ->selectRaw("IFNULL(parent_id, '') as parent_id")
            ->selectRaw("IFNULL(slug, '') as slug")
            ->selectRaw("IFNULL(icon, '') as icon")
            ->with('children')
            ->where('status', 1)
            ->where('type', $type)
            ->where('language', $locale)
            ->whereNull('parent_id')
            ->orderBy('order_by', 'ASC')
            ->get();
        return $pageMenus;
    }

    protected static $flatMenu = [];

    public static function flattenMenu($items, string $parentName = '')
    {
        foreach ($items as $item) {
            $fullName = $parentName ? $parentName . ' > ' . $item['name'] : $item['name'];

            // Build the entry and remove children
            $entry = $item;
            $entry['name'] = $fullName;
            if (isset($entry['children'])) {
                unset($entry['children']);
            }

            self::$flatMenu[] = $entry;

            // Recurse if children exist
            if (!empty($item['children'])) {
                self::flattenMenu($item['children'], $fullName);
            }
        }

        return self::$flatMenu;
    }


    // Optional: if you want to reset the static variable before a new run
    public static function reset()
    {
        self::$flatMenu = [];
    }

    public static function menuActons()
    {
        $action = array(
            '1' => 'Add',
            '2' => 'Edit',
            '3' => 'Publication Status',
            '4' => 'Delete',
            '5' => 'Permission',
            '6' => 'Changepassword',
            '7' => 'View PopUp',
            '8' => 'View',
            '9' => 'Shipping Status',
            '10' => 'Product List',
            '11' => 'View PDF',
            '12' => 'Status');
        return $action;
    }

}
