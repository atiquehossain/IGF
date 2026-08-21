@extends('admin.layouts.master')

@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $canCreateRoles = $permissions->allows($currentAdmin, 'role.create');
    $canEditRoles = $permissions->allows($currentAdmin, 'role.edit');
    $canPublishRoles = $permissions->allows($currentAdmin, 'role.status');
    $canDeleteRoles = $permissions->allows($currentAdmin, 'role.destroy');
    $canViewRolePermissions = $permissions->allows($currentAdmin, 'role.permission');
    $rolesAreReadOnly = !$canCreateRoles && !$canEditRoles && !$canPublishRoles && !$canDeleteRoles;
@endphp

@section('content')

<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    @if($rolesAreReadOnly)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review administrator roles{{ $canViewRolePermissions ? ' and inspect their assigned permissions' : '' }}, but your role cannot create, edit, publish, or remove roles.</div>
    @endif
    <div class="alert alert-light border" role="note"><strong>Authority ranks:</strong> lower numbers have more authority. You can only create and manage roles whose rank number is greater than {{ $actorRank }}. The deployment-owner role is reserved and cannot be edited here.</div>

    <div class="row">
        @if($canCreateRoles)
        <div class="col-lg-5 col-md-12">
            <div id="new_role">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->RoleTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-body">
                            <form action="{{route('role.store')}}" method="post" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <div class="form-group has-success">
                                    <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                    <input name="name" type="text" value="{{old('name')}}" class="form-control" aria-label="{{ $Lang->Common->Form->Name }}" required>
                                    @if($errors->has('name'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                    <input name="order_by" type="number" class="form-control" value="{{old('order_by')}}" aria-label="{{ $Lang->Common->Form->OrderBy }}">
                                    @if($errors->has('order_by'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="new-role-security-rank" class="control-label mb-1">Authority rank <span>*</span></label>
                                    <input id="new-role-security-rank" name="security_rank" type="number" min="{{ $actorRank + 1 }}" max="65535" class="form-control" value="{{ old('security_rank', min(65535, $actorRank + 100)) }}" required aria-describedby="new-role-rank-help">
                                    <small id="new-role-rank-help" class="form-text text-muted">Must be greater than your rank ({{ $actorRank }}). Larger numbers mean less authority.</small>
                                    @error('security_rank')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
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
        </div>
        @endif

        <div class="{{ $canCreateRoles ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->RoleTitle }} {{ $Lang->Common->List }}
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('role.index')}}" method="get">
                                <div class="input-group search-input-group">
                                    <input type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search roles">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="role_table">
                        <thead>
                            <tr>
                                <th width="55%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="15%"><strong>Rank</strong></th>
                                <th width="30%" align="center"><strong style="text-align: center;display: block;">{{ $Lang->Common->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                            <tr id="{{ @$role->id }}">
                                <td><span class="name">{{ $role->name }}</span> @if($role->is_owner)<span class="badge badge-warning">Reserved owner</span>@endif</td>
                                <td>{{ $role->security_rank }}</td>
                                <td align="center">
                                    @if($canEditRoles && $role->can_be_managed)
                                        <button type="button" class="edit btn btn-info btn-sm1" data-id="{{ $role->id }}" aria-label="Edit role" title="Edit role"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                    @endif
                                    @if($canViewRolePermissions && $role->can_be_managed)
                                        <a href="{{ route('role.permission', $role->id) }}" class="btn btn-secondary btn-sm1" aria-label="View role permissions" title="View role permissions"><i class="fa fa-shield" aria-hidden="true"></i></a>
                                    @endif
                                    @if($canPublishRoles && $role->can_be_managed)
                                        <button type="button" class="btn btn-warning btn-sm1 status" data-id="{{ $role->id }}" data-url="{{ route('role.status', $role->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $role->status ? 'Deactivate' : 'Activate' }} role" title="{{ $role->status ? 'Deactivate' : 'Activate' }} role" aria-pressed="{{ $role->status ? 'true' : 'false' }}"><i class="fa text-white {{ $role->status ? 'fa-check-square' : 'fa-square' }}" aria-hidden="true"></i></button>
                                    @endif
                                    @if($canDeleteRoles && $role->can_be_managed)
                                        <button type="button" class="btn btn-danger btn-sm1 trash" data-id="{{ $role->id }}" data-url="{{ route('role.destroy', $role->id) }}" data-token="{{ csrf_token() }}" data-item-label="role {{ $role->name }}" aria-label="Delete role" title="Delete role"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                                    @endif
                                    @if(!$role->can_be_managed)
                                        <span class="badge badge-light" title="Owner, equal-ranked, and higher-ranked roles are protected.">Protected</span>
                                    @elseif(!$canEditRoles && !$canViewRolePermissions && !$canPublishRoles && !$canDeleteRoles)
                                        <span class="badge badge-light">View only</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $roles->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEditRoles)
<div class="modal fade" id="roleModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="mediumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{route('role.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <strong class="card-title">{{ $Lang->Common->Edit }} {{ $Lang->RoleTitle }}</strong>
                    <button type="button" class="close cancel" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')

                    <input name="id"  type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                        <input name="name" type="text" value="{{old('name')}}" class="form-control" aria-label="{{ $Lang->Common->Form->Name }}" required>
                        @if($errors->has('name'))
                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                        <input name="order_by" type="number" class="form-control" value="{{old('order_by')}}" aria-label="{{ $Lang->Common->Form->OrderBy }}">
                        @if($errors->has('order_by'))
                        <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="edit-role-security-rank" class="control-label mb-1">Authority rank <span>*</span></label>
                        <input id="edit-role-security-rank" name="security_rank" type="number" min="{{ $actorRank + 1 }}" max="65535" class="form-control" value="{{ old('security_rank') }}" required aria-describedby="edit-role-rank-help">
                        <small id="edit-role-rank-help" class="form-text text-muted">Must remain greater than your rank ({{ $actorRank }}).</small>
                        @error('security_rank')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info submit_ mt-3"><i class="fa fa-magic"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                    <button type="button" class="btn btn-danger cancel mt-3" data-dismiss="modal"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('custom-js')
<script>
    @if($canDeleteRoles)
    itemDelete({tableId: "role_table", method: "DELETE"});
    @endif
    @if($canPublishRoles)
    itemStatus({tableId: "role_table", method: "PUT"});
    @endif

    $(".cancel").click(function () {
        clear($(this).closest("form"));
    });

    function clear($form) {
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }

    @if($canEditRoles)
    var is_edit = "{{old('id')}}";
    if (is_edit) {
        $('#new_role .form-group .help-block').hide();
        $("#new_role input:not([type=hidden])").val("");
        $('#roleModal').modal('show');
    }

    $(".edit").click(function () {
        $('#roleModal').modal('show');
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('role.index')}}/" + id + "/edit",
            success: function (res) {
                if (res.data) {
                    $(".modal input[name=id]").val(res.data.id);
                    $(".modal input[name=name]").val(res.data.name);
                    $(".modal input[name=order_by]").val(res.data.order_by);
                    $(".modal input[name=security_rank]").val(res.data.security_rank);
                }
                spinner.hide();
            },
            error: function (err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
    @endif
</script>

@endsection
