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

    public static function menuUi()
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
        $routeName = (string) \Request::route()?->getName();
        $userMenuAction = AuthMenu::where('link', $routeName)->first();
        if (!empty($userMenuAction->parent_id)) {
            $userMenuAction = AuthMenu::where('id', $userMenuAction->parent_id)->first();
        }

        foreach ($role_menu as $key => $element) {
            if (!is_array($element)) {
                continue;
            }

            $children = is_array($element['children'] ?? null) ? $element['children'] : [];
            $hasChildren = $children !== [];
            $subchild = $hasChildren ? self::subMenu($children, 0, $admin, $permissions) : '';
            if (($hasChildren && trim($subchild) === '')
                || (!$hasChildren && !self::canRenderLeaf($element, $admin, $permissions))) {
                continue;
            }

            $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);
            $parentMenuActive = '';
            $rooMenuActive = '';
            $userMenu = AuthMenu::where('id', @$userMenuAction->id)->first();
            if (@$userMenu->id == $element['id']) {
                $parentMenuActive = 'active show';
                $rooMenuActive = 'active';
            }
            if ($hasChildren) {
                $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                $menuName = AdminUi::label($element['name'] ?? '');
                $html .= '<li class="menu-item-has-children dropdown ' . $parentMenuActive . '">';
                $html .= '<a href="' . $menuLink . '" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $html .= ' <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';

                $html .= ' <ul class="sub-menu children dropdown-menu ' . $parentMenuActive . '"> ' . $subchild . '</ul>';
                $html .= '</li>';
            } else {
                $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                $menuName = AdminUi::label($element['name'] ?? '');
                $active = ($key == 0) ? $rooMenuActive : "";
                $html .= '<li class="' . $active . '">';
                $html .= '<a  href="' . $menuLink . '"> <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';
                $html .= '</li>';
            }
        }
        return $html;
    }

    public static function subMenu($child, $depth, $admin = null, ?Permission $permissions = null)
    {
        $admin ??= Auth::guard('admin')->user();
        $permissions ??= app(Permission::class);
        if (!$admin) {
            return '';
        }

        $html = "";
        $routeName = (string) \Request::route()?->getName();
        if (!empty($child)) {
            foreach ($child as $key => $element) {
                if (!is_array($element)) {
                    continue;
                }

                $children = is_array($element['children'] ?? null) ? $element['children'] : [];
                $hasChildren = $children !== [];
                $subchild = $hasChildren ? self::subMenu($children, $depth + 1, $admin, $permissions) : '';
                if (($hasChildren && trim($subchild) === '')
                    || (!$hasChildren && !self::canRenderLeaf($element, $admin, $permissions))) {
                    continue;
                }

                $userMenu = AuthMenu::where('link', @$routeName)->first();
                $childActive = '';
                if (@$userMenu->id == $element['id']) {
                    $childActive = 'active';
                }
                if ($hasChildren) {
                    $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                    $menuName = AdminUi::label($element['name'] ?? '');
                    $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);

                    $html .= '<li class="menu-item-has-children dropdown">';
                    $html .= '<a href="' . $menuLink . '" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                    $html .= ' <i class="menu-icon fa ' . $fa_icon . '"></i>' . $menuName . '</a>';
                    $html .= '<ul class="sub-menu children dropdown-menu">' . $subchild . '</ul>';
                    $html .= '</li>';
                } else {
                    $fa_icon = AdminUi::iconClass($element['icon'] ?? null);
                    $menuName = AdminUi::label($element['name'] ?? '');
                    $menuLink = self::permittedRouteUrl($element['link'] ?? null, $admin, $permissions);
                    $html .= '<li><i class="fa ' . $fa_icon . '"></i><a class="' . $childActive . '" href="' . $menuLink . '">' . $menuName . '</a>';
                    $html .= '</li>';
                }
            }
        }
        return $html;
    }

    private static function canRenderLeaf(array $element, $admin, Permission $permissions): bool
    {
        $routeName = trim((string) ($element['link'] ?? ''));

        return $routeName !== ''
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
        $pageMenus = PageMenu::select('id', 'name', 'description', 'link')
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
