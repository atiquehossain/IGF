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

        $actionButtons = [];
        $safeId = htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8');
        $safeItemLabel = AdminUi::label(trim((string) $itemLabel) ?: 'record #'.$safeId);
        $isPublished = (int) $status === 1;

        if (!empty(@$menuAction)) {
            foreach (@$menuAction as $action) {
                if (Route::has($action->link) && $permissions->allows($admin, $action->link)) {
                    // Edit
                    if ($action->type == 2) {
                        $label = 'Edit ' . $safeItemLabel;
                        $actionButtons[] = '<button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact" data-id="' . $safeId . '" aria-label="' . $label . '" title="' . $label . '"><i class="fa fa-edit" aria-hidden="true"></i><span>Edit</span></button>';
                    }

                    // View
                    if ($action->type == 8) {
                        $label = 'View ' . $safeItemLabel;
                        $actionButtons[] = '<a href="' . AdminUi::label(route($action->link, $id)) . '" class="btn igf-btn igf-btn-secondary igf-btn-compact" aria-label="' . $label . '" title="' . $label . '"><i class="fa fa-eye" aria-hidden="true"></i><span>View</span></a>';
                    }

                    // Publication Status
                    if ($action->type == 3) {
                        $verb = $isPublished ? 'Unpublish' : 'Publish';
                        $icon = $isPublished ? 'fa-eye-slash' : 'fa-eye';
                        $label = $verb . ' ' . $safeItemLabel;
                        $actionButtons[] = '<button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status" aria-label="' . $label . '" title="' . $label . '" aria-pressed="' . ($isPublished ? 'true' : 'false') . '" data-item-label="' . $safeItemLabel . '" data-active-action="Unpublish" data-inactive-action="Publish" data-active-icon="fa-eye-slash" data-inactive-icon="fa-eye" data-id="' . $safeId . '"' .
                                ' data-url="' . AdminUi::label(route($action->link, $id)) . '" data-token="' . AdminUi::label(csrf_token()) . '">' .
                                '<i class="fa ' . $icon . '" aria-hidden="true"></i><span>' . $verb . '</span>' .
                                '</button>';
                    }

                    // Delete
                    if ($action->type == 4) {
                        $label = 'Delete ' . $safeItemLabel;
                        $actionButtons[] = '<span class="igf-danger-action"><button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash" aria-label="' . $label . '" title="' . $label . '" data-item-label="' . $safeItemLabel . '" data-id="' . $safeId . '"' .
                                ' data-url="' . AdminUi::label(route($action->link, $id)) . '" data-token="' . AdminUi::label(csrf_token()) . '">' .
                                '<i class="fa fa-trash-o" aria-hidden="true"></i><span>Delete</span>' .
                                '</button></span>';
                    }
                }
            }
        }

        if ($actionButtons === []) {
            return '';
        }

        return '<span class="igf-action-group" role="group" aria-label="Actions for ' . $safeItemLabel . '">' . implode('', $actionButtons) . '</span>';
    }

}
