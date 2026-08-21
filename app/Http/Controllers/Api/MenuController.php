<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helper\MyMenu;
use Exception;
use Illuminate\Support\Facades\Route;

class MenuController extends Controller {

    public function index(Request $request) {
        try {
            $myMenu =  MyMenu::frontMenus($request->header('locale'))->toArray();
            $menus = array_map(function ($menu) {
                $children = [];
                if(@$menu['children']){
                    $children =  array_map(function($sub_menu){
                        $sub_menu['api'] = $this->menuUrl($sub_menu);
                        return $sub_menu;
                    } ,$menu['children']);
                }
                $menu['children'] = $children;
                $menu['api'] = $this->menuUrl($menu);
                return $menu;
               },$myMenu);
            $response = [
                'status' => true,
                'data' => $menus,
            ];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['status' => false, 'message' => 'Menu not found'], 200);
        }
    }

    private function menuUrl(array $menu): string
    {
        if (($menu['link'] ?? '') === 'custom') {
            return (string) ($menu['slug'] ?? '#');
        }
        $routeName = 'api.' . ($menu['link'] ?? '');
        if (!Route::has($routeName)) {
            return '#';
        }

        return route($routeName, array_filter([(string) ($menu['slug'] ?? '')]));
    }

}
