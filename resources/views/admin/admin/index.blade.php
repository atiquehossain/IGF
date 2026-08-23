@extends('admin.layouts.master')

@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $canCreateAdmins = $permissions->allows($currentAdmin, 'admin.create');
    $canEditAdmins = $permissions->allows($currentAdmin, 'admin.edit');
    $canPublishAdmins = $permissions->allows($currentAdmin, 'admin.status');
    $canDeleteAdmins = $permissions->allows($currentAdmin, 'admin.destroy');
    $canResetAdminPasswords = $permissions->allows($currentAdmin, 'admin.reset');
    $canUseAdminEditor = $canCreateAdmins || $canEditAdmins;
    $adminsAreReadOnly = !$canCreateAdmins && !$canEditAdmins && !$canPublishAdmins && !$canDeleteAdmins && !$canResetAdminPasswords;
@endphp

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>
    @if(session('temporary_password'))
        <div class="alert alert-warning" role="status">
            <strong>Copy the one-time password for {{ session('temporary_password_admin') }} now:</strong>
            <code class="d-block mt-2" style="font-size:1.05rem; user-select:all">{{ session('temporary_password') }}</code>
            <small>It will not be shown again. Share it through an approved secure channel; the administrator must replace it at first sign-in.</small>
        </div>
    @endif
    @if($adminsAreReadOnly)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review administrator accounts and assigned contact details, but your role cannot create, edit, activate, reset passwords, or remove accounts.</div>
    @endif
    <div class="row">
        @if($canUseAdminEditor)
        <div class="col-lg-5 col-md-12">
            @if($canCreateAdmins)
            <div id="new_admin">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">Create administrator</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-body">
                            <form action="{{route('admin.store')}}" method="post" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group has-success">
                                            <label for="new-admin-role" class="control-label mb-1">{{ $Lang->RoleTitle }}<span>*</span></label>
                                            <select id="new-admin-role" name="role" class="form-control" required>
                                                <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                                @foreach($roles as $role)
                                                <option value="{{$role->id}}">{{$role->name}}</option>
                                                @endforeach
                                            </select>
                                            @if($errors->has('role'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('role') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group has-success">
                                            <label for="new-admin-name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input id="new-admin-name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                                            @if($errors->has('name'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                            @endif
                                        </div>

                                    </div>
                                </div>

                                <div class="form-group has-success">
                                    <label for="username" class="control-label mb-1">{{ $Lang->Common->Form->UserName }} <span>*</span></label>
                                    <input id="username" name="username" type="text" class="form-control" value="{{old('username')}}" autocomplete="username" aria-label="{{ $Lang->Common->Form->UserName }}" required>
                                    @if($errors->has('username'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('username') }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="mobile" class="control-label mb-1">{{ $Lang->Common->Form->Mobile }} </label>
                                    <input id="mobile" name="mobile" type="tel" maxlength="30" class="form-control" value="{{old('mobile')}}" aria-label="{{ $Lang->Common->Form->Mobile }}">
                                    @if($errors->has('mobile'))
                                    <small class="help-block form-text text-danger">{{ $errors->first('mobile') }}</small>
                                    @endif
                                </div>
                                <div class="alert alert-light border" role="note">
                                    A cryptographically random one-time password will be generated after creation. The new account starts disabled and must replace that password at first sign-in.
                                </div>

                                <div class="row">
                                    <div class="col-8">
                                        <div class="form-group">
                                            <label for="new-admin-address" class="control-label mb-1"> {{ $Lang->Common->Form->Address }}</label>
                                            <textarea id="new-admin-address" name="address" rows="3" placeholder="Address" class="form-control">{{old('address')}}</textarea>
                                            @if($errors->has('address'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('address') }}</small>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <div class="form-group text-center mt-4">
                                            <div class="file-upload">
                                                <label for="admin_image" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_img" src="{{ asset('/')}}image/no-image.png" alt="Administrator profile image preview">
                                                </label>
                                                <input type="file" onchange="changefile(event, 'upload_img')" name="image" id="admin_image" class="file-upload_input" accept="image/jpeg,image/png,image/webp" aria-describedby="admin-image-help" aria-label="Administrator profile image">
                                            </div>
                                            <small id="admin-image-help" class="form-text text-muted">JPEG, PNG, or WebP; maximum 2 MB and 4096×4096 pixels.</small>
                                            <div style="clear: both"></div>
                                            @if($errors->has('image'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('image') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions form-group text-right">
                                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-1"><i class="fa fa-times" aria-hidden="true"></i> Cancel</button>
                                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-1"><i class="fa fa-user-plus" aria-hidden="true"></i> Create administrator</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if($canEditAdmins)
            <div id="edit_admin" style="display: none">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">Edit administrator</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{route('admin.store')}}" method="POST" enctype="multipart/form-data">
                                    {{ csrf_field() }}
                                    @method('PUT')
                                    <input name="id"  type="hidden" value="{{old('id')}}" class="form-control" required>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="edit-admin-role" class="control-label mb-1">{{ $Lang->RoleTitle }}<span>*</span></label>
                                                <select id="edit-admin-role" name="role" class="form-control" required>
                                                    <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                                    @foreach($roles as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                                @if($errors->has('role'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('role') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="edit-admin-name" class="control-label mb-1">{{ $Lang->Common->Form->Name }}  <span>*</span></label>
                                                <input id="edit-admin-name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                                                @if($errors->has('name'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                                @endif
                                            </div>

                                        </div>
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="edit-admin-username" class="control-label mb-1">{{ $Lang->Common->Form->UserName }} <span>*</span></label>
                                        <input id="edit-admin-username" name="username" type="text" class="form-control" placeholder="" readonly>
                                        @if($errors->has('username'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('username') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="edit-admin-mobile" class="control-label mb-1">{{ $Lang->Common->Form->Mobile }} </label>
                                        <input id="edit-admin-mobile" name="mobile" type="tel" maxlength="30" class="form-control">
                                        @if($errors->has('mobile'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('mobile') }}</small>
                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col-8">
                                            <div class="form-group">
                                                <label for="edit-admin-address" class="control-label mb-1"> {{ $Lang->Common->Form->Address }}</label>
                                                <textarea id="edit-admin-address" name="address" rows="3" placeholder="Address" class="form-control"></textarea>
                                                @if($errors->has('address'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('address') }}</small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-4">
                                            <div class="form-group text-center mt-4">
                                                <div class="file-upload">
                                                    <label for="euser_image" class="file-upload_label">
                                                        <img class="file-upload_img" id="eupload_img" src="{{ asset('/')}}image/no-image.png" alt="Administrator profile image preview">
                                                    </label>
                                                    <input type="file" onchange="changefile(event, 'eupload_img')" name="image" id="euser_image" class="file-upload_input" accept="image/jpeg,image/png,image/webp" aria-describedby="edit-admin-image-help" aria-label="Administrator profile image">
                                                </div>
                                                <small id="edit-admin-image-help" class="form-text text-muted">JPEG, PNG, or WebP; maximum 2 MB and 4096×4096 pixels.</small>
                                                <div style="clear: both"></div>
                                                @if($errors->has('image'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('image') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-1"><i class="fa fa-times" aria-hidden="true"></i> Cancel</button>
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-1"><i class="fa fa-check" aria-hidden="true"></i> Save administrator</button>
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
        <div class="{{ $canUseAdminEditor ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->Common->AdminUser }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('admin.search')}}" method="post">
                                @csrf
                                <div class="input-group search-input-group">
                                    <input type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search administrators">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                                    </span>
                                </div>
                            </form>
                            @if($search !== '')
                                <form action="{{ route('admin.search.clear') }}" method="post" class="mt-1 text-right">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary igf-btn-compact">Clear search</button></form>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="admin_table">
                        <thead>
                            <tr>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->UserName }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Mobile }} </strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($admins as $admin)
                            <tr id="{{ @$admin->id }}">
                                <td> <span class="name">{{@$admin->name}}</span> </td>
                                <td> <span class="parent">{{@$admin->username}}</span> </td>
                                <td> <span class="parent">{{@$admin->mobile}}</span> </td>
                                <td><span class="igf-action-group" role="group" aria-label="Actions for {{ $admin->name }}">
                                    @if($canEditAdmins && $admin->can_be_managed)
                                        <button type="button" class="edit btn igf-icon-btn igf-btn-secondary" data-id="{{ $admin->id }}" aria-label="Edit administrator" title="Edit administrator"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                    @endif
                                    @if($canResetAdminPasswords && $admin->can_be_managed)
                                        <a href="{{ route('admin.reset', $admin->id) }}" class="btn igf-icon-btn igf-btn-secondary" aria-label="Reset administrator password" title="Reset administrator password"><i class="fa fa-key" aria-hidden="true"></i></a>
                                    @endif
                                    @if($canPublishAdmins && $admin->can_be_managed)
                                        <button type="button" class="btn igf-icon-btn igf-btn-secondary status" data-id="{{ $admin->id }}" data-url="{{ route('admin.status', $admin->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $admin->status ? 'Deactivate' : 'Activate' }} administrator" title="{{ $admin->status ? 'Deactivate' : 'Activate' }} administrator" aria-pressed="{{ $admin->status ? 'true' : 'false' }}"><i class="fa {{ $admin->status ? 'fa-check-square' : 'fa-square' }}" aria-hidden="true"></i></button>
                                    @endif
                                    @if($canDeleteAdmins && $admin->can_be_managed)
                                        <span class="igf-danger-action"><button type="button" class="btn igf-icon-btn igf-btn-danger trash" data-id="{{ $admin->id }}" data-url="{{ route('admin.destroy', $admin->id) }}" data-token="{{ csrf_token() }}" data-item-label="administrator {{ $admin->name }}" aria-label="Delete administrator" title="Delete administrator"><i class="fa fa-trash-o" aria-hidden="true"></i></button></span>
                                    @endif
                                    @if(!$admin->can_be_managed)
                                        <span class="badge badge-light" title="Self, owner, and equal or higher-ranked administrators are protected.">Protected</span>
                                    @elseif(!$canEditAdmins && !$canResetAdminPasswords && !$canPublishAdmins && !$canDeleteAdmins)
                                        <span class="badge badge-light">View only</span>
                                    @endif
                                </span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $admins->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('custom-js')
<script>
    @if($canDeleteAdmins)
    itemDelete({tableId: "admin_table", method: "DELETE"});
    @endif
    @if($canPublishAdmins)
    itemStatus({tableId: "admin_table", method: "PUT"});
    @endif

    $(".cancel").click(function () {
        clear($(this).closest("form"));
    });

    function clear($form) {
        $("#edit_admin").css("display", "none");
        $("#new_admin").css("display", "block");
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }
    @if($canEditAdmins)
    $(".edit").click(function () {
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('admin.index')}}/" + id + "/edit",
            success: function (res) {
                $("#edit_admin").css("display", "block");
                $("#new_admin").css("display", "none");
                if (res.data) {
                    $("input[name=id]").val(res.data.id);
                    $("input[name=name]").val(res.data.name);
                    $("select[name=role]").val(res.data.role);
                    $("input[name=username]").val(res.data.username);
                    $("input[name=mobile]").val(res.data.mobile);
                    $("textarea[name=address]").val(res.data.address);
                    $('#eupload_img').attr('src', res.data.path);
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
