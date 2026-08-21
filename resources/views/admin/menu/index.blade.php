@extends('admin.layouts.master')

@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $isPermissionOwner = $currentAdmin?->isOwner() ?? false;
    $canCreateMenus = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.create');
    $canEditMenus = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.edit');
    $canPublishMenus = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.status');
    $canDeleteMenus = $isPermissionOwner && $permissions->allows($currentAdmin, 'menu.destroy');
    $canViewMenuActions = $permissions->allows($currentAdmin, 'menu.action.index');
    $menusAreReadOnly = !$canCreateMenus && !$canEditMenus && !$canPublishMenus && !$canDeleteMenus;
@endphp

@section('content')

<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>
    @if($menusAreReadOnly)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> Permission definitions can only be changed by a deployment owner.</div>
    @endif
    <div class="row">
        @if($canCreateMenus || $canEditMenus)
        <div class="col-lg-5 col-md-12">
            @if($canCreateMenus)
            <div id="new_authMenu">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->MenuTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-body">
                            <form action="{{route('menu.store')}}" method="post" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <div class="form-group has-success">
                                    <label for="parent" class="control-label mb-1">{{ $Lang->Common->Form->Parent }}</label>
                                    <select name="parent" type="text" class="form-control" aria-label="{{ $Lang->Common->Form->Parent }}">
                                        <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                        @foreach($menuList as $menu)
                                        <option value="{{$menu->id}}" {{ (old('parent') == $menu->id) ? "selected":"" }}>{{$menu->name}}</option>
                                        @endforeach
                                    </select>
                                    @if($errors->has('parent'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('parent') }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                    <input name="name" type="text" value="{{old('name')}}" class="form-control" aria-label="{{ $Lang->Common->Form->Name }}" required>
                                    @if($errors->has('name'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="link" class="control-label mb-1">{{ $Lang->Common->Form->Route }}</label>
                                    <input name="link" type="text" class="form-control" placeholder="menu.index" aria-label="{{ $Lang->Common->Form->Route }}">
                                    @if($errors->has('link'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('link') }}</small>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="icon" class="control-label mb-1">{{ $Lang->Common->Form->Icon }}</label>
                                            <input name="icon" type="text" class="form-control" placeholder="fa fa-edit" aria-label="{{ $Lang->Common->Form->Icon }}">
                                            @if($errors->has('icon'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('icon') }}</small>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                            <input name="order_by" type="number" class="form-control" aria-label="{{ $Lang->Common->Form->OrderBy }}">
                                            @if($errors->has('order_by'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions form-group text-right">
                                    <button type="submit" class="btn btn-info submit_ mt-1"><i class="fa fa-lock fa-lg"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                                    <button type="button" class="btn btn-danger cancel mt-1"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if($canEditMenus)
            <div id="edit_authMenu" style="display: none">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->Edit }} {{ $Lang->MenuTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{route('menu.store')}}" method="POST" enctype="multipart/form-data">
                                    {{ csrf_field() }}
                                    @method('PUT')
                                    <input name="id"  type="hidden" value="{{old('id')}}" class="form-control" required>

                                    <div class="form-group has-success">
                                        <label for="parent" class="control-label mb-1">{{ $Lang->Common->Form->Parent }}</label>
                                        <select name="parent" class="form-control" aria-label="{{ $Lang->Common->Form->Parent }}">
                                            <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                            @foreach($menuList as $menu)
                                            <option value="{{$menu->id}}">{{$menu->name}}</option>
                                            @endforeach
                                        </select>
                                        @if($errors->has('parent'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('parent') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input name="name" type="text" value="{{old('name')}}" class="form-control" aria-label="{{ $Lang->Common->Form->Name }}" required>
                                        @if($errors->has('name'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="link" class="control-label mb-1">{{ $Lang->Common->Form->Route }}</label>
                                        <input name="link" type="text" class="form-control" placeholder="menu.index" aria-label="{{ $Lang->Common->Form->Route }}">
                                        @if($errors->has('link'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('link') }}</small>
                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="icon" class="control-label mb-1">{{ $Lang->Common->Form->Icon }}</label>
                                                <input name="icon" type="text" class="form-control" placeholder="fa fa-edit" aria-label="{{ $Lang->Common->Form->Icon }}">
                                                @if($errors->has('icon'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('icon') }}</small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                                <input name="order_by" type="number" class="form-control" aria-label="{{ $Lang->Common->Form->OrderBy }}">
                                                @if($errors->has('order_by'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn btn-info submit_ mt-1"><i class="fa fa-magic"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                                        <button type="button" class="btn btn-danger cancel mt-1"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
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
        <div class="{{ $canCreateMenus || $canEditMenus ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->MenuTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('menu.index')}}" method="get">
                                <div class="input-group search-input-group">
                                    <input type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search administrator menu items">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
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
                            @forelse($authMenus as $menu)
                            <tr id="{{ @$menu->id }}">
                                <td> <span class="name">{{@$menu->name}}</span> </td>
                                <td> <span class="parent">{{@$menu->parent->name}}</span> </td>
                                <td>{{@$menu->link}} </td>
                                <td> <span class="name">{{@$menu->order_by}}</span> </td>
                                <td>
                                    @if($canEditMenus)
                                    <button type="button" class="edit btn btn-info btn-sm1" data-id="{{@$menu->id}}" aria-label="Edit {{ $menu->name }}" title="Edit {{ $menu->name }}">
                                        <i class="fa fa-edit" aria-hidden="true"></i>
                                    </button>
                                    @endif

                                    @if($canViewMenuActions)
                                    <a href="{{ route('menu.action.index',@$menu->id) }}" class="btn btn-danger btn-sm1" aria-label="View actions for {{ $menu->name }}" title="View actions for {{ $menu->name }}">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </a>
                                    @endif

                                    @if($canPublishMenus)
                                    <button type="button" class="btn btn-warning btn-sm1 status" data-id="{{ @$menu->id }}"
                                       data-url="{{ route('menu.status',@$menu->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $menu->status ? 'Deactivate' : 'Activate' }} {{ $menu->name }}" title="{{ $menu->status ? 'Deactivate' : 'Activate' }} {{ $menu->name }}" aria-pressed="{{ $menu->status ? 'true' : 'false' }}">
                                        <i class="fa text-white {{($menu->status==1) ?'fa-check-square':'fa-square'}}" aria-hidden="true"></i>
                                    </button>
                                    @endif

                                    @if($canDeleteMenus)
                                    <button type="button" class="btn btn-danger btn-sm1 trash" data-id="{{ @$menu->id }}"
                                       data-url="{{ route('menu.destroy',@$menu->id) }}" data-token="{{ csrf_token() }}" aria-label="Delete {{ $menu->name }}" title="Delete {{ $menu->name }}">
                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                    </button>
                                    @endif
                                    @if($menusAreReadOnly && !$canViewMenuActions)<span class="badge badge-light">View only</span>@endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No permission menus match this search.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $authMenus->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('custom-js')
<script>
    itemDelete({tableId: "authMenu_table", method: "DELETE"});
    itemStatus({tableId: "authMenu_table", method: "PUT"});

    $(".cancel").click(function () {
        clear($(this).closest("form"));
    });

    function clear($form) {
        $("#edit_authMenu").css("display", "none");
        $("#new_authMenu").css("display", "block");
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }
    $(".edit").click(function () {
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('menu.index')}}/" + id + "/edit",
            success: function (res) {
                $("#edit_authMenu").css("display", "block");
                $("#new_authMenu").css("display", "none");
                if (res.data) {
                    $("input[name=id]").val(res.data.id);
                    $("input[name=name]").val(res.data.name);
                    $("input[name=link]").val(res.data.link);
                    $("select[name=parent]").val(res.data.parent_id);
                    $("input[name=icon]").val(res.data.icon);
                    $("input[name=order_by]").val(res.data.order_by);
//                    $('[name=parent]').val( '1' );
                }
                spinner.hide();
            },
            error: function (err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
</script>

@endsection
