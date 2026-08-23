@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
  <h1 class="sr-only">{{ $title }}</h1>

  <div class="row justify-content-md-center justify-content-lg-center">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-md-6">
              <h4 class="card-title">{{ $title }}</h4>
            </div>
            <div class="col-md-6">
              <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('user-approval.index') }}" id="go-back">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
              </a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <dl class="form mb-0">
                <div class="form-group has-success">
                  <dt class="control-label mb-1">User name</dt>
                  <dd>{{@$user->name}}</dd>
                </div>

                <div class="form-group has-success">
                  <dt class="control-label mb-1">Phone number</dt>
                  <dd>{{@$user->phone_no}}</dd>
                </div>

                <div class="form-group has-success">
                  <dt class="control-label mb-1">Email</dt>
                  <dd>{{@$user->email}}</dd>
                </div>

                <div class="form-group has-success">
                  <dt class="control-label mb-1">Current organization</dt>
                  <dd>{{@$user->org}}</dd>
                </div>

                <div class="form-group has-success">
                  <dt class="control-label mb-1">Designation</dt>
                  <dd>{{@$user->designation}}</dd>
                </div>

                <div class="form-group has-success">
                  <dt class="control-label mb-1">Current status</dt>
                  <dd>
                    <?php 
                      if(@$user->is_approved === 0) echo 'Pending'; 
                      else if(@$user->is_approved === 1) echo 'Approved';
                      else if(@$user->is_approved === 2) echo 'Rejected';
                      else echo 'Pending';
                    ?>
                  </dd>
                </div>
          </dl>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
