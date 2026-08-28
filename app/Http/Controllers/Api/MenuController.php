<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helper\MyMenu;
use App\Services\ContentSanitizer;
use Exception;
use Illuminate\Support\Facades\Route;

class MenuController extends Controller {

    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    public function index(Request $request) {
        try {
            $myMenu =  MyMenu::frontMenus($request->header('locale'))->toArray();
            $menus = array_map(fn (array $menu): array => $this->decorateMenu($menu), $myMenu);
            $response = [
                'status' => true,
                'data' => $menus,
            ];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['status' => false, 'message' => 'Menu not found'], 200);
        }
    }

    private function decorateMenu(array $menu): array
    {
        $menu['children'] = array_map(
            fn (array $child): array => $this->decorateMenu($child),
            is_array($menu['children'] ?? null) ? $menu['children'] : []
        );
        if (($menu['link'] ?? '') === 'custom') {
            $menu['slug'] = $this->safeCustomUrl($menu['slug'] ?? null);
        }
        $menu['api'] = $this->menuUrl($menu);

        return $menu;
    }

    private function menuUrl(array $menu): string
    {
        if (($menu['link'] ?? '') === 'custom') {
            return $this->safeCustomUrl($menu['slug'] ?? null);
        }
        $routeName = 'api.' . ($menu['link'] ?? '');
        if (!Route::has($routeName)) {
            return '#';
        }

        return route($routeName, array_filter([(string) ($menu['slug'] ?? '')]));
    }

    private function safeCustomUrl(mixed $value): string
    {
        return $this->sanitizer->sanitizeUrl($value) ?: '#';
    }

}
