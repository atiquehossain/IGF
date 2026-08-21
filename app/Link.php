<?php

namespace App;

use Intervention\Image\ImageManagerStatic as Image;
use File;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Support\AdminUi;
use App\Http\Middleware\Permission;
use Auth;
use Illuminate\Support\Facades\Route;

class Link {

    public static function action($id = null, $status = 0, $itemLabel = null) {
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return '';
        }
        $routeName = \Request::route()->getName();
        $userMenus = AuthMenu::where('link', $routeName)->where('status', 1)->first();
        if (!$userMenus) {
            return '';
        }
        $menuAction = MenuAction::where('auth_menu_id', $userMenus->id)->where('status', 1)->orderBy('order_by', 'asc')->get();
        $permissions = app(Permission::class);

        $data_link = '';
        $safeId = htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8');
        $safeItemLabel = AdminUi::label(trim((string) $itemLabel) ?: 'record #'.$safeId);

        if (!empty(@$menuAction)) {
            foreach (@$menuAction as $action) {
                if (Route::has($action->link) && $permissions->allows($admin, $action->link)) {
                    $fallbackLabel = match ((int) $action->type) {
                        2 => 'Edit item',
                        3 => 'Change publication status',
                        4 => 'Delete item',
                        8 => 'View item',
                        default => 'Item action',
                    };
                    $actionLabel = AdminUi::label(trim((string) $action->name) ?: $fallbackLabel);

                    // Edit
                    if ($action->type == 2) {
                        $edit_icon = AdminUi::iconClass($action->icon, 'fa-edit');
                        $data_link .= '<button type="button" class="edit btn btn-info btn-sm1" data-id="' . $safeId . '" aria-label="' . $actionLabel . '" title="' . $actionLabel . '"><i class="fa ' . $edit_icon . '" aria-hidden="true"></i></button> ';
                    }

                    // View
                     if ($action->type == 8) {
                        $view_icon = AdminUi::iconClass($action->icon, 'fa-eye');
                        $data_link .= '<a href="' . route($action->link, $id) . '" class="btn btn-danger btn-sm1" aria-label="' . $actionLabel . '" title="' . $actionLabel . '"><i class="fa ' . $view_icon . '" aria-hidden="true"></i></a> ';
                    }

                    // Publication Status
                    if ($action->type == 3) {
                        $icon = ($status == 1) ? 'fa-check-square' : 'fa-square';
                        $data_link .= '<button type="button" class="btn btn-warning btn-sm1 status" aria-label="' . $actionLabel . '" title="' . $actionLabel . '" aria-pressed="' . ($status == 1 ? 'true' : 'false') . '" data-id="' . $safeId . '"' .
                                'data-url="' . route($action->link, $id) . '" data-token="' . csrf_token() . '">' .
                                '<i class="fa text-white ' . $icon . '" aria-hidden="true"></i>' .
                                '</button> ';
                    }

                    // Delete
                    if ($action->type == 4) {
                        $data_link .= '<button type="button" class="btn btn-danger btn-sm1 trash" aria-label="' . $actionLabel . '" title="' . $actionLabel . '" data-item-label="' . $safeItemLabel . '" data-id="' . $safeId . '"
                                       data-url="' . route($action->link, $id) . '" data-token="' . csrf_token() . '">
                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                    </button> ';
                    }
                }
            }
        }



        return $data_link;
    }

}
