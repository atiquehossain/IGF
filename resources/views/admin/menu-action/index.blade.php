@extends('admin.layouts.master')
@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $isPermissionOwner = $currentAdmin?->isOwner() ?? false;
    $canViewMenus = $permissions->allows($currentAdmin, 'menu.index');
    $canCreateMenuActions = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.action.create');
    $canEditMenuActions = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.action.edit');
    $canPublishMenuActions = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.action.status');
    $canDeleteMenuActions = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.action.destroy');
    $menuActionsAreReadOnly = !$canCreateMenuActions && !$canEditMenuActions && !$canPublishMenuActions && !$canDeleteMenuActions;
@endphp
@section('content')
    <?php

    use App\Helper\MyMenu;

    $menuActons = MyMenu::menuActons();
    ?>
    <div class="content pb-0">
        @if($menuActionsAreReadOnly)
            <div class="alert alert-info" role="status"><strong>Read-only access.</strong> Permission definitions can only be changed by a deployment owner.</div>
        @endif

        <div class="row">
            @if($canCreateMenuActions || $canEditMenuActions)
            <div class="col-lg-5 col-md-12">
                @if($canCreateMenuActions)
                <div id="new_authMenuAction">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-lg-7 col-md-12">
                                    <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->MenuTitle }}{{ $Lang->Common->Action }}... ( {{ @$authMenu->name }} )</strong>
                                </div>
                                <div class="col-lg-5 col-md-12">
                                    @if($canViewMenus)<a class="btn igf-btn igf-btn-secondary igf-btn-compact float-right"
                                        href="{{ route('menu.index') }}">
                                        <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                                    </a>@endif
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="card-body">
                                <form action="{{ route('menu.action.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    {{ csrf_field() }}
                                    <input name="auth_menu_id" type="hidden" value="{{ @$authMenu->id }}"
                                        class="form-control" required>
                                    <div class="form-group has-success">
                                        <label for="parent" class="control-label mb-1">{{ $Lang->MenuTitle }} {{ $Lang->Common->Form->Type }}</label>
                                        <select class="form-control chosen-select" name="type">
                                            <option value=" ">{{ $Lang->Common->Form-> Select }} {{ $Lang->MenuTitle }} {{ $Lang->Common->Form->Type }}</option>
                                            @foreach ($menuActons as $key => $value)
                                                <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('parent'))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('parent') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input name="name" type="text" value="{{ old('name') }}" class="form-control"
                                            required>
                                        @if ($errors->has('name'))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="link" class="control-label mb-1">{{ $Lang->Common->Form->Route }}</label>
                                        <input name="link" type="text" class="form-control" placeholder="action.index">
                                        @if ($errors->has('link'))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('link') }}</small>
                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="icon" class="control-label mb-1">{{ $Lang->Common->Form->Icon }}</label>
                                                <input name="icon" type="text" class="form-control"
                                                    placeholder="fa fa-edit">
                                                @if ($errors->has('icon'))
                                                    <small
                                                        class="help-block form-text text-danger">{{ $errors->first('icon') }}</small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                                <input name="order_by" type="number" class="form-control">
                                                @if ($errors->has('order_by'))
                                                    <small
                                                        class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-1"><i
                                                class="fa fa-plus" aria-hidden="true"></i> Create action</button>
                                        <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-1"><i
                                                class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if($canEditMenuActions)
                <div id="edit_authMenuAction" style="display: none">
                    <div class="card">
                        <div class="card-header">
                            <strong class="card-title">{{ $Lang->Common->Edit }} {{ $Lang->MenuTitle }}{{ $Lang->Common->Action }}... ( {{ @$authMenu->name }} )</strong>
                        </div>
                        <div class="card-body">
                            <div id="pay-invoice">
                                <div class="card-body">
                                    <form action="{{ route('menu.action.update') }}" method="POST"
                                        enctype="multipart/form-data">
                                        {{ csrf_field() }}
                                        @method('PUT')
                                        <input name="id" type="hidden" value="{{ old('id') }}" class="form-control"
                                            required>
                                        <input name="auth_menu_id" type="hidden" value="{{ @$authMenu->id }}"
                                            class="form-control" required>

                                        <div class="form-group has-success">
                                            <label for="parent" class="control-label mb-1">{{ $Lang->MenuTitle }} {{ $Lang->Common->Form->Type }}</label>
                                            <select class="form-control chosen-select" name="type">
                                                <option value=" ">{{ $Lang->Common->Form-> Select }} {{ $Lang->MenuTitle }} {{ $Lang->Common->Form->Type }}</option>
                                                @foreach ($menuActons as $key => $value)
                                                    <option value="{{ $key }}">{{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('type'))
                                                <small
                                                    class="help-block form-text text-danger">{{ $errors->first('type') }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input name="name" type="text" value="{{ old('name') }}"
                                                class="form-control" required>
                                            @if ($errors->has('name'))
                                                <small
                                                    class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="link" class="control-label mb-1">{{ $Lang->Common->Form->Route }}</label>
                                            <input name="link" type="text" class="form-control"
                                                placeholder="action.index">
                                            @if ($errors->has('link'))
                                                <small
                                                    class="help-block form-text text-danger">{{ $errors->first('link') }}</small>
                                            @endif
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group">
                                                    <label for="icon" class="control-label mb-1">{{ $Lang->Common->Form->Icon }}</label>
                                                    <input name="icon" type="text" class="form-control"
                                                        placeholder="fa fa-edit">
                                                    @if ($errors->has('icon'))
                                                        <small
                                                            class="help-block form-text text-danger">{{ $errors->first('icon') }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <div class="form-group">
                                                    <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                                    <input name="order_by" type="number" class="form-control">
                                                    @if ($errors->has('order_by'))
                                                        <small
                                                            class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-actions form-group text-right">
                                            <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-1"><i
                                                    class="fa fa-save" aria-hidden="true"></i> Save action</button>
                                            <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-1"><i
                                                    class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif
            <div class="{{ $canCreateMenuActions || $canEditMenuActions ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <strong class="card-title">{{ $Lang->MenuTitle }}{{ $Lang->Common->Action }}{{ $Lang->Common->List }} ( {{ @$authMenu->name }} )</strong>
                            </div>
                            <div class="col-md-6">
                                <form action="{{ route('menu.action.index') }}" method="get">
                                    <div class="input-group search-input-group">
                                        <input type="search" name="search" value="{{ @$search }}"
                                            class="form-control search-form-control">
                                        <span class="input-group-prepend">
                                            <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search"
                                                    aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="table-stats ov-h">
                        <table class="table" id="authMenu_table">
                            <thead>
                                <tr>
                                    <th width="25%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                    <th width="20%"><strong>{{ $Lang->Common->Form->Parent }}</strong></th>
                                    <th width="15%"><strong>{{ $Lang->Common->Form->Link }}</strong></th>
                                    <th width="10%"><strong>{{ $Lang->Common->Form->Order }}</strong></th>
                                    <th width="30%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($menuActions as $menu)
                                    <tr id="{{ @$menu->id }}">
                                        <td> <span class="name">{{ @$menu->name }}</span> </td>
                                        <td> <span class="parent">{{ @$menu->parent_name }}</span> </td>
                                        <td>{{ @$menu->link }}</td>
                                        <td> <span class="name">{{ @$menu->order_by }}</span> </td>
                                        <td>
                                            @if($canEditMenuActions)
                                            <button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact"
                                                data-id="{{ @$menu->id }}" aria-label="Edit {{ $menu->name }}" title="Edit {{ $menu->name }}">
                                                <i class="fa fa-edit" aria-hidden="true"></i> Edit
                                            </button>
                                            @endif

                                            @if($canPublishMenuActions)
                                            <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status"
                                                data-id="{{ @$menu->id }}"
                                                data-url="{{ route('menu.action.status', @$menu->id) }}"
                                                data-token="{{ csrf_token() }}" aria-label="{{ $menu->status ? 'Deactivate' : 'Activate' }} {{ $menu->name }}" title="{{ $menu->status ? 'Deactivate' : 'Activate' }} {{ $menu->name }}" aria-pressed="{{ $menu->status ? 'true' : 'false' }}">
                                                <i class="fa {{ $menu->status == 1 ? 'fa-check-square' : 'fa-square' }}"
                                                    aria-hidden="true"></i> {{ $menu->status ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            @endif

                                            @if($canDeleteMenuActions)
                                            <button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash"
                                                data-id="{{ @$menu->id }}"
                                                data-url="{{ route('menu.action.destroy', @$menu->id) }}"
                                                data-token="{{ csrf_token() }}" aria-label="Delete {{ $menu->name }}" title="Delete {{ $menu->name }}">
                                                <i class="fa fa-trash-o" aria-hidden="true"></i> Delete
                                            </button>
                                            @endif
                                            @if($menuActionsAreReadOnly)<span class="badge badge-light">View only</span>@endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No permission actions match this search.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="pagination justify-content-end">
                            {{ $menuActions->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('custom-js')
    <script>
        itemDelete({
            tableId: "authMenu_table",
            method: "DELETE"
        });
        itemStatus({
            tableId: "authMenu_table",
            method: "PUT"
        });
        $(".goBack").click(function() {
            window.history.back();
        });

        $(".cancel").click(function() {
            clear($(this).closest("form"));
        });

        function clear($form) {
            $("#edit_authMenuAction").css("display", "none");
            $("#new_authMenuAction").css("display", "block");
            if ($form.length) {
                $form.get(0).reset();
                $form.find(".chosen-select").trigger("chosen:updated");
            }
        }
        $(".edit").click(function() {
            var spinner = $('.spinner');
            spinner.show();
            var id = $(this).data('id');
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: "{{ route('menu.action.index') }}/" + id + "/edit",
                success: function(res) {
                    $("#edit_authMenuAction").css("display", "block");
                    $("#new_authMenuAction").css("display", "none");
                    if (res.data) {
                        $("input[name=id]").val(res.data.id);
                        $("input[name=name]").val(res.data.name);
                        $("input[name=link]").val(res.data.link);
                        $("select[name=type]").val(res.data.type);
                        $("input[name=icon]").val(res.data.icon);
                        $("input[name=order_by]").val(res.data.order_by);
                        $("input[name=auth_menu_id]").val(res.data.auth_menu_id);
                    }
                    spinner.hide();
                },
                error: function(err) {
                    toastrMsg('error', err.responseJSON.message);
                    spinner.hide();
                }
            });
        });
    </script>

@endsection
