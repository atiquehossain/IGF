@extends('admin.layouts.master')

@section('content')
    @php
        $canReviewApplications = app(\App\Http\Middleware\Permission::class)
            ->allows(auth('admin')->user(), 'user-approval.edit');
    @endphp
    <div class="content pb-0">
        <h1 class="sr-only">Member applications</h1>
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-5">
                                <strong class="card-title">Member Applications {{ $Lang->Common->List }}</strong>
                                @unless($canReviewApplications)
                                    <small class="d-block text-muted mt-1">Read-only access. You can review applicant details, but only a member approver can approve or reject an application.</small>
                                @endunless
                            </div>
                            <div class="col-md-7">
                                <div class="input-group d-flex justify-content-end">
                                    <form action="{{ route('user-approval.search') }}" method="post" role="search">@csrf
                                        <div class="input-group search-input-group">
                                            <label class="sr-only" for="member-application-search">Search member applications</label>
                                            <input type="search" name="search" value="{{ @$search }}"
                                                id="member-application-search" class="form-control search-form-control"
                                                maxlength="100" autocomplete="off" required
                                                placeholder="Name, email, phone or organization">
                                            <span class="input-group-prepend">
                                                <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search"
                                                        aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                            </span>
                                        </div>
                                    </form>
                                    @if($search !== '')
                                        <form action="{{ route('user-approval.search.clear') }}" method="post" class="ml-1">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary"><i class="fa fa-undo" aria-hidden="true"></i> Clear</button></form>
                                    @endif
                                    <?php if (!empty($addNewLink)) { ?>
                                    <a class="btn igf-btn igf-btn-primary igf-btn-compact ml-1 pull-right" href="{{ route($addNewLink) }}">
                                        <i class="fa fa-plus" aria-hidden="true"></i> Add member application
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-stats ov-h">
                        <table class="table" id="member_application_table">
                            <thead>
                                <tr>
                                    <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                    <th width="40%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                    <th width="35%"><strong>Phone No</strong></th>
                                    <th width="35%"><strong>Approval Status</strong></th>
                                    <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $key=> $user)
                                    <tr id="{{ @$user->id }}">
                                        <td>{{ @$key + 1 }} </td>
                                        <td>
                                            <span class="name">{{ @$user->name }}</span>
                                        </td>
                                        <td>
                                            <span class="name">{{ @$user->phone_no }}</span>
                                        </td>
                                        <td>
                                            <?php 
                                              if(@$user->is_approved === 0) echo '<p class="text text-warning">Pending</p>'; 
                                              else if(@$user->is_approved === 1) echo '<p class="text text-success">Approved</p>';
                                              else if(@$user->is_approved === 2) echo '<p class="text text-danger">Rejected</p>';
                                              else echo '<p class="text text-warning">Pending</p>';
                                            ?>
                                        </td>
                                        <td class="d-flex" style="column-gap: 8px">
                                            <a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route('user-approval.show',['id'=> @$user->id]) }}" aria-label="View application from {{ $user->name }}" title="View application"><i class="fa fa-eye" aria-hidden="true"></i> View</a>
                                            @if($canReviewApplications)
                                            <form action="{{ route('user-approval.update.approve',['id'=> @$user->id]) }}" method="post">
                                              @method('PUT')
                                              @csrf  
                                              <button type="submit" class="btn igf-btn igf-btn-primary igf-btn-compact" aria-label="Approve {{ $user->name }}" title="Approve application"><i class="fa fa-check" aria-hidden="true"></i> Approve</button>
                                            </form>

                                            <form action="{{ route('user-approval.update.reject',['id'=> @$user->id]) }}" method="post">
                                              @method('PUT')
                                              @csrf  
                                              <button type="submit" class="btn igf-btn igf-btn-danger igf-btn-compact" aria-label="Reject {{ $user->name }}" title="Reject application"><i class="fa fa-times" aria-hidden="true"></i> Reject</button>
                                            </form>
                                            @else
                                                <span class="badge badge-light">View only</span>
                                            @endif
                                            
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="pagination justify-content-end">
                            {{ $users->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
