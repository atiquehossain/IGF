@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">

  <div class="row justify-content-md-center justify-content-lg-center">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-md-6">
              <h4 class="card-title">{{ $title }}</h4>
            </div>
            <div class="col-md-6">
              <a class="btn btn-sm btn-secondary float-right" href="{{ route('user-approval.index') }}" id="go-back">
                <i class="fa fa-arrow-circle-left"></i> {{ $Lang->Common->GoBack }}
              </a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="form">
                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">User Name</label>
                  <p>{{@$user->name}}</p>
                </div>

                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">Phone Number</label>
                  <p>{{@$user->phone_no}}</p>
                </div>

                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">User Email</label>
                  <p>{{@$user->email}}</p>
                </div>

                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">Current Organization</label>
                  <p>{{@$user->org}}</p>
                </div>

                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">Designation</label>
                  <p>{{@$user->designation}}</p>
                </div>

                <div class="form-group has-success">
                  <label for="type" class="control-label mb-1">Current Status</label>
                  <p>
                    <?php 
                      if(@$user->is_approved === 0) echo 'Pending'; 
                      else if(@$user->is_approved === 1) echo 'Approved';
                      else if(@$user->is_approved === 2) echo 'Rejected';
                      else echo 'Pending';
                    ?>
                  </p>
                </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection