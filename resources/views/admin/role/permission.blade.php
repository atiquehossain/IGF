@extends('admin.layouts.master')

@section('content')

<?php
$permission = explode(",", $role->permission);
$actionPermission = explode(",", $role->actionPermission);

$renderActions = static function ($element, $key, $actionPermission): string {
    $html = '<span class="action_menu">';
    foreach ($element['menuAction'] as $action) {
        $actionId = 'role-action-' . (int) $action['id'];
        $checked = in_array($action['id'], $actionPermission) ? ' checked' : '';
        $html .= '<label for="' . $actionId . '">' . e($action['name']) . ' <input type="checkbox" id="' . $actionId . '"'
            . ' class="permission user_permissions_' . (int) $key . '" data-user_permissions="' . (int) $key . '"'
            . $checked . ' value="' . (int) $action['id'] . '" name="actionPermission[]"></label>';
    }

    return $html . '</span>';
};

$renderMenuRow = static function ($element, $permission, $actionPermission) use ($renderActions): string {
    $id = (int) $element['id'];
    $parentId = (int) $element['parent_id'];
    $inputId = 'role-menu-' . $id;
    $checked = in_array($element['id'], $permission) ? ' checked' : '';
    $html = '<tr class="treegrid-' . $id . ' treegrid-parent-' . $parentId . '"><td>';
    $html .= '<span class="title">' . e($element['name']) . ' <label for="' . $inputId . '">';
    $html .= '<input type="checkbox" id="' . $inputId . '" class="user_menus menus_' . $id . '" value="' . $id . '"'
        . ' aria-label="Allow access to ' . e($element['name']) . '"'
        . $checked . ' data-user_menus="' . $id . '" name="permission[]"></label></span>';
    if (count($element['menuAction']) > 0) {
        $html .= $renderActions($element, $id, $actionPermission);
    }

    return $html . '</td></tr>';
};

$renderSubMenus = static function ($children, $permission, $actionPermission) use (&$renderSubMenus, $renderMenuRow): string {
    $html = '';
    foreach ($children as $element) {
        $html .= $renderMenuRow($element, $permission, $actionPermission);
        if (count($element['children']) > 0) {
            $html .= $renderSubMenus($element['children'], $permission, $actionPermission);
        }
    }

    return $html;
};
?>

<div class="content pb-0">
    <h1 class="sr-only">Permissions for {{ $role->name }}</h1>
    @unless($canEditRolePermissions)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can inspect this role's permissions, but your role cannot change them.</div>
    @endunless
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div id="new_authMenu">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">Permissions for {{ $role->name }}</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-body">
                            <form action="{{route('role.permission.store')}}" method="post" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <input name="id"  type="hidden" value="{{$role->id}}" class="form-control" required>
                                <fieldset @unless($canEditRolePermissions) disabled @endunless>
                                @if($errors->has('permission'))
                                <small class="help-block form-text text-danger p-3">{{ $errors->first('permission') }}</small>
                                @endif
                                <table class="table" id="authMenu_table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <label for="user-menu-all" style="margin: 0">
                                                    <input type="checkbox" id="user-menu-all"class="user_menu_all">
                                                    <strong>{{ $Lang->Common->Form->All }}</strong> <strong>{{ $Lang->Common->Form->Action }}</strong>
                                                </label>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>

                                </table>

                                <table class="tree">
                                    <?php
                                    foreach ($authMenus as $key => $authMenu) {
                                        $checked = "";
                                        if (in_array($authMenu->id, $permission)) {
                                            $checked = "checked";
                                        }
                                        ?>
                                        <tr class="treegrid-{{@$authMenu->id}} treegrid-parent-{{@$authMenu->parent_id}}">
                                            <td>
                                                <span class="title"> {{@$authMenu->name}}
                                                    <label for="role-menu-{{ (int) $authMenu->id }}"><input id="role-menu-{{ (int) $authMenu->id }}" type="checkbox" class="user_menus menus_{{@$authMenu->id}}"
                                                                                   value="{{@$authMenu->id}}" {{@$checked}}
                                                                                   data-user_menus="{{@$authMenu->id}}" name="permission[]" aria-label="Allow access to {{ $authMenu->name }}">
                                                    </label>
                                                </span>
                                                <span class="action_menu">
                                                    <?php
                                                    foreach (@$authMenu->menuAction as $key2 => $action) {
                                                        $actionChecked = "";
                                                        if (in_array($action->id, $actionPermission)) {
                                                            $actionChecked = "checked";
                                                        }
                                                        ?>


                                                        <label for="role-action-{{ (int) $action->id }}">
                                                            {{@$action->name}}
                                                            <input type="checkbox" id="role-action-{{ (int) $action->id }}"
                                                                   class="permission user_permissions_{{@$authMenu->id}}" {{@$actionChecked}} data-user_permissions="{{@$authMenu->id}}"
                                                                   value="{{@$action->id}}" name="actionPermission[]">
                                                        </label>

                                                    <?php } ?>
                                                </span>
                                            </td>
                                        </tr>

                                        <?php
                                        if (count($authMenu->children) > 0) {
                                            echo $renderSubMenus($authMenu->children, $permission, $actionPermission);
                                        }
                                        ?>

                                    <?php } ?>
                                </table>



                                @if($canEditRolePermissions)
                                <div class="form-actions form-group text-right">
                                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-1"><i class="fa fa-save" aria-hidden="true"></i> Save role permissions</button>
                                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-1"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                </div>
                                @endif
                                </fieldset>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<link rel="stylesheet" href="https://maxazan.github.io/jquery-treegrid/css/jquery.treegrid.css">
<style>
    .action_menu {
        display:  inline-block;
        margin-left: 20px;
    }
    .tree .title { min-width: 100px; display: inline-block; }
    .action_menu {
        display: block;
        padding: 2px 10px;
        margin-left: 35px;
    }
    .action_menu label {
        display: inline-block;
        margin-bottom: 0;
        background: #e4e4e4;
        padding: 0 10px;
        margin-left: 10px;
    }
</style>

@endsection

@section('custom-js')

<script type="text/javascript" src="https://maxazan.github.io/jquery-treegrid/js/jquery.treegrid.min.js"></script>
<script>


$(document).ready(function () {
    $('.tree').treegrid();

    $('.user_menu_all').click(function (event) {
        if ($('.user_menu_all').is(':checked')) {
            $('input[type=checkbox]').prop('checked', 'checked');
        } else {
            $('input[type=checkbox]').prop('checked', false).removeAttr('checked');
        }
    });
    $('.user_menus').click(function (event) {
        var data = $(this).data('user_menus');
        var checked = $('.menus_' + data + ':checked').val();
        if (checked) {
            $('.menus_' + data).prop('checked', 'checked');
            $('.user_permissions_' + data).prop('checked', 'checked');
        } else {
            $('.menus_' + data).removeAttr('checked');
            $('.user_permissions_' + data).prop('checked', false).removeAttr('checked');
        }
    });

    $('.permission').click(function (event) {
        var data = $(this).data('user_permissions');
        $('.menus_' + data).prop('checked', 'checked');
    });

});
</script>

@endsection
