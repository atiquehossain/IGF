@extends('admin.layouts.master')

@section('custom-css')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')

<!-- ============================================================== -->
<!-- Start Page Content -->
<!-- ============================================================== -->

<div class="content pb-0">
    <div class="row">
        <div class="col-12 d-flex justify-content-center">
            <div class="card">
                <div class="card-body ">
                    <form class="" action="{{ route('admin.password.update') }}" method="POST" enctype="multipart/form-data"
                          id="newProduct" name="newProduct">
                        {{ csrf_field() }}
                        @method('PUT')
                        <div class="modal-body">
                            <div class="row d-flex justify-content-center">
                                @if(!$users->must_change_password)
                                <div class="col-md-6">
                                    <div class="form-group {{ $errors->has('current_password') ? ' has-danger' : '' }}">
                                        <label for="current_password">Current password:</label>
                                        <input id="current_password" type="password" class="form-control" name="current_password" autocomplete="current-password" required>
                                        @error('current_password')<div class="help-block form-text text-danger">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                @else
                                <div class="col-md-12 alert alert-warning">Your temporary password must be replaced before you can continue.</div>
                                @endif
                                <div class="col-md-6 ml-2">
                                    <div class="form-group {{ $errors->has('password') ? ' has-danger' : '' }}">
                                        <label for="password">{{ $Lang->Password }} :</label>
                                        <input type="password" class="form-control" placeholder="Password" name="password"
                                               value="" autocomplete="new-password" minlength="12" required>
                                        @if ($errors->has('password'))
                                        @foreach ($errors->get('password') as $error)
                                        <div class="help-block form-text text-danger">{{ $error }}</div>
                                        @endforeach
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        class="form-group {{ $errors->has('password_confirmation') ? ' has-danger' : '' }}">
                                        <label for="password_confirmation" class="ml-1">{{ $Lang->ConfirmPassword ?? 'Confirm password' }} :</label>
                                        <input id="password_confirmation" type="password" class="form-control ml-2" placeholder="{{ $Lang->ConfirmPassword ?? 'Confirm password' }}"
                                               name="password_confirmation" autocomplete="new-password" minlength="12" required>
                                        @if ($errors->has('password_confirmation'))
                                        @foreach ($errors->get('password_confirmation') as $error)
                                        <div class="help-block form-text text-danger">{{ $error }}</div>
                                        @endforeach
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-7 text-center">
                                    <button type="submit" class="btn btn-success btn-sm ml-2">
                                        <i class="fa fa-exchange"> </i> {{ $Lang->Common->ChangePassword }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <!-- /.modal-dialog -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
