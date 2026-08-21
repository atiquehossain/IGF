<?php

namespace App\Http\Middleware;

use App\Helper\Translation;
use App\Models\AuthMenu;
use App\Models\Admin;
use App\Models\MenuAction;
use App\Models\Role;
use App\Support\AdminPermissionRegistry;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Permission
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $language = json_decode(json_encode(Translation::language()), false);
            $request->Lang = (object) ($language->admin ?? []);
        } catch (Exception) {
            $request->Lang = (object) [];
        }

        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get(Admin::SESSION_AUTH_VERSION);
        if (!(bool) $admin->status
            || !$request->session()->has(Admin::SESSION_AUTH_VERSION)
            || !hash_equals((string) $admin->auth_version, (string) $sessionVersion)) {
            $expectsJson = $request->expectsJson();

            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($expectsJson) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        $routeName = (string) $request->route()?->getName();
        if ($admin->must_change_password && !in_array($routeName, [
            'admin.password',
            'admin.password.update',
            'admin.image',
            'admin.logout',
        ], true)) {
            return redirect(route('admin.password'));
        }

        if (AdminPermissionRegistry::isEssentialRoute($routeName)) {
            return $next($request);
        }

        abort_unless($this->allows($admin, $routeName), 403, 'You do not have permission to perform this administrator action.');

        return $next($request);
    }

    public function allows(?Admin $admin, string $routeName): bool
    {
        if (!$admin) {
            return false;
        }
        if (AdminPermissionRegistry::isEssentialRoute($routeName)) {
            return true;
        }

        $permissionRoutes = AdminPermissionRegistry::capabilitiesForRoute($routeName);
        if ($permissionRoutes === []) {
            return false;
        }
        $role = Role::whereKey($admin->role)->where('status', 1)->first();
        if (!$role) {
            return false;
        }
        if ((bool) $role->is_owner) {
            return true;
        }

        $menus = AuthMenu::whereIn('link', $permissionRoutes)->where('status', 1)->get();
        $actions = MenuAction::whereIn('link', $permissionRoutes)
            ->where('status', 1)
            ->whereHas('authMenu', fn ($query) => $query->where('status', 1))
            ->get();
        if ($menus->isEmpty() && $actions->isEmpty()) {
            return false;
        }

        $menuPermissions = $this->ids($role->permission);
        $actionPermissions = $this->ids($role->actionPermission);

        return $menus->contains(fn (AuthMenu $menu) => in_array((string) $menu->id, $menuPermissions, true))
            || $actions->contains(fn (MenuAction $action) => in_array((string) $action->id, $actionPermissions, true));
    }

    private function ids(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }
}
