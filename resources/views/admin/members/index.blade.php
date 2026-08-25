@extends('admin.layouts.master')

@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $canCreateMembers = $permissions->allows($currentAdmin, 'latest.news.create');
    $canEditMembers = $permissions->allows($currentAdmin, 'latest.news.edit');
    $canPublishMembers = $permissions->allows($currentAdmin, 'latest.news.status');
    $canDeleteMembers = $permissions->allows($currentAdmin, 'latest.news.destroy');
    $membersAreReadOnly = !$canCreateMembers && !$canEditMembers && !$canPublishMembers && !$canDeleteMembers;
@endphp

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>
    @if($membersAreReadOnly)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review team member names, photos, and roles, but your role cannot create, edit, publish, or remove team members.</div>
    @endif
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <strong class="card-title">Team groups</strong>
                    <small class="d-block text-muted">Create the tabs visitors use to browse the team. Higher display-order numbers appear first.</small>
                </div>
                <span class="badge badge-light">{{ $groups->count() }} {{ Str::plural('group', $groups->count()) }}</span>
            </div>
        </div>
        <div class="card-body">
            @if($errors->teamGroup->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Unable to save the team group.</strong>
                    <ul class="mb-0 mt-2">@foreach($errors->teamGroup->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if($canCreateMembers)
                <form action="{{ route('latest.news.group.store') }}" method="POST" class="border rounded p-3 mb-4">
                    @csrf
                    <h2 class="h6 mb-3">Create a team group</h2>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="new-team-group-name">Group name <span>*</span></label>
                            <input id="new-team-group-name" class="form-control" name="group_name" value="{{ old('group_name') }}" maxlength="255" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="new-team-group-slug">Slug</label>
                            <input id="new-team-group-slug" class="form-control" name="group_slug" value="{{ old('group_slug') }}" maxlength="120" placeholder="operational-leads">
                            <small class="form-text text-muted">Leave blank to generate it from the name.</small>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="new-team-group-order">Display order</label>
                            <input id="new-team-group-order" class="form-control" name="group_order_by" type="number" min="0" max="999999" value="{{ old('group_order_by', 0) }}">
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button class="btn igf-btn igf-btn-primary w-100" type="submit"><i class="fa fa-plus" aria-hidden="true"></i> Create group</button>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="new-team-group-description">Description</label>
                        <textarea id="new-team-group-description" class="form-control" name="group_description" rows="2" maxlength="2000">{{ old('group_description') }}</textarea>
                    </div>
                </form>
            @endif

            <div class="row">
                @forelse($groups as $group)
                    <div class="col-xl-6 col-12 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong>{{ $group->name }}</strong>
                                    <span class="badge {{ $group->status ? 'badge-success' : 'badge-secondary' }} ml-2">{{ $group->status ? 'Visible' : 'Hidden' }}</span>
                                </div>
                                <span class="badge badge-light">{{ $group->members_count }} live @if($group->attached_members_count > $group->members_count) · {{ $group->attached_members_count - $group->members_count }} in trash @endif</span>
                            </div>

                            @if($canEditMembers)
                                <form action="{{ route('latest.news.group.update', $group) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="team-group-name-{{ $group->id }}">Group name</label>
                                            <input id="team-group-name-{{ $group->id }}" class="form-control" name="group_name" value="{{ $group->name }}" maxlength="255" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="team-group-slug-{{ $group->id }}">Slug</label>
                                            <input id="team-group-slug-{{ $group->id }}" class="form-control" name="group_slug" value="{{ $group->slug }}" maxlength="120" required>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label for="team-group-order-{{ $group->id }}">Order</label>
                                            <input id="team-group-order-{{ $group->id }}" class="form-control" name="group_order_by" type="number" min="0" max="999999" value="{{ $group->order_by }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="team-group-description-{{ $group->id }}">Description</label>
                                        <textarea id="team-group-description-{{ $group->id }}" class="form-control" name="group_description" rows="2" maxlength="2000">{{ $group->description }}</textarea>
                                    </div>
                                    <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save group</button>
                                </form>
                            @else
                                <p class="mb-1">{{ $group->description ?: 'No group description.' }}</p>
                                <small class="text-muted">Slug: {{ $group->slug }} · Display order: {{ $group->order_by }}</small>
                            @endif

                            <div class="d-flex flex-wrap mt-3" style="gap:8px">
                                @if($canPublishMembers)
                                    <form action="{{ route('latest.news.group.status', $group) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="submit"><i class="fa {{ $group->status ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i> {{ $group->status ? 'Hide group' : 'Show group' }}</button>
                                    </form>
                                @endif
                                @if($canDeleteMembers)
                                    @if($group->attached_members_count)
                                        <button class="btn igf-btn igf-btn-danger igf-btn-compact" type="button" disabled title="Move attached members before deleting this group"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete group</button>
                                    @else
                                        <form action="{{ route('latest.news.group.destroy', $group) }}" method="POST" onsubmit="return confirm('Delete this empty team group?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn igf-btn igf-btn-danger igf-btn-compact" type="submit"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete group</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><p class="alert alert-warning mb-0">Create a team group before adding members.</p></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row">
        @if($canCreateMembers)
        <div class="col-lg-5 col-md-12">
            <div id="new_latestNews">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->OurMembers }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form  class="fileUploadForm" action="{{route('latest.news.store')}}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}
                                    <div class="form-group text-center">
                                        <div class="file-upload">
                                            <label for="latestNewsimage" class="file-upload_label">
                                                <img class="file-upload_img" id="upload_img" src="{{ asset('image/no-image.png') }}"
                                                    onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                                    alt="Team member photo preview">
                                            </label>
                                            <input type="file" onchange="changefile(event, 'upload_img')" name="image" id="latestNewsimage" class="file-upload_input" accept="image/jpeg,image/png,image/webp">
                                        </div>
                                        <div style="clear: both"></div>
                                        @if($errors->has('image'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('image') }}</small>
                                        @endif
                                    </div>

                                    {{-- <div class="form-group has-success">
                                        <label for="category_id">{{ $Lang->Category }}</label>
                                        <select class="form-control form-control-danger" name="category_id">
                                            <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Category }}</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ @$category->id }}"
                                                    @if (old('category_id') == $category->id)  selected @endif>
                                                    {{ @$category->name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('category_id'))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('category_id') }}</small>
                                        @endif
                                    </div> --}}

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input id="name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                                        @if($errors->has('name'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="designation">Designation <span>*</span></label>
                                        <textarea id="designation" class="form-control form-control-danger" name="designation" rows="2" required>{{old('designation')}}</textarea>
                                        @if ($errors->has('designation'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('designation') }}</small>
                                        @endif
                                    </div>
                                    <div class="form-group has-success">
                                        <label for="team_group_id">Team group <span>*</span></label>
                                        <select id="team_group_id" class="form-control" name="team_group_id" required>
                                            <option value="">Choose a group</option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->id }}" @selected((string) old('team_group_id') === (string) $group->id)>{{ $group->name }}{{ $group->status ? '' : ' (Hidden)' }}</option>
                                            @endforeach
                                        </select>
                                        @if($errors->has('team_group_id'))<small class="help-block form-text text-danger">{{ $errors->first('team_group_id') }}</small>@endif
                                    </div>


                                    <div class="form-group has-success">
                                        <label for="qualification" class="control-label mb-1">Qualification</label>
                                        <input id="qualification" name="qualification" type="text" value="{{old('qualification')}}" class="form-control" maxlength="255">
                                        @if($errors->has('qualification'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('qualification') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="biography">Biography</label>
                                        <textarea id="biography" class="form-control form-control-danger" name="biography" rows="5" maxlength="5000">{{old('biography')}}</textarea>
                                        @if ($errors->has('biography'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('biography') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="order_by" class="control-label mb-1">Display order</label>
                                        <input id="order_by" name="order_by" type="number" min="0" max="999999" value="{{old('order_by', 0)}}" class="form-control">
                                        <small class="form-text text-muted">Higher numbers appear first.</small>
                                        @if($errors->has('order_by'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="legacy_url" class="control-label mb-1">Legacy profile URL</label>
                                        <input id="legacy_url" name="url" type="text" value="{{old('url')}}" class="form-control" maxlength="2048">
                                        <small class="form-text text-muted">Kept for existing profile links and used when no social links are provided.</small>
                                        @if($errors->has('url'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="mb-0">Social links</strong>
                                            <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact add-social-link" data-social-scope="create"><i class="fa fa-plus" aria-hidden="true"></i> Add link</button>
                                        </div>
                                        <small class="form-text text-muted mb-2">Add any public profile using a complete http:// or https:// URL.</small>
                                        <div id="create_social_links" class="social-links-editor"></div>
                                        @if($errors->has('social_links') || $errors->has('social_links.*.url'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('social_links') ?: $errors->first('social_links.*.url') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group">
                                        <div class="upload_progress">
                                            <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">100%</div>
                                        </div>
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i> Create team member</button>
                                        <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif
        <div class="{{ $canCreateMembers ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title"> {{ $Lang->OurMembers }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('latest.news.index')}}" method="get">
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="team-member-search">Search team members</label>
                                    <input id="team-member-search" type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search team members">
                                    <label class="sr-only" for="team-member-group-filter">Filter team members by group</label>
                                    <select id="team-member-group-filter" name="group_id" class="form-control" aria-label="Filter team members by group">
                                        <option value="">All groups</option>
                                        @foreach($groups as $group)<option value="{{ $group->id }}" @selected((int) $groupFilter === (int) $group->id)>{{ $group->name }}</option>@endforeach
                                    </select>
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="latestNewstable">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="20%" class="avatar"><strong>{{ $Lang->Common->Form->Avatar }} </strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="15%"><strong>Group</strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Designation }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestNews as $news)
                            <tr id="{{ @$news->id }}">
                                <td> #{{@$news->id}} </td>
                                <td class="avatar">
                                    <div class="round-img">
                                        <img class="rounded" src="{{ $news->display_image_url }}"
                                            onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                            alt="Photo of {{ $news->name }}">
                                    </div>
                                </td>

                                <td> <span class="name">{{@$news->name}}</span> </td>
                                <td><span>{{ $news->teamGroup?->name ?: 'Unassigned' }}</span></td>
                                <td> <span>{{@$news->description}}</span> </td>
                                <td>
                                    @if($canEditMembers)
                                        <button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact" data-id="{{ $news->id }}" aria-label="Edit team member" title="Edit team member"><i class="fa fa-edit" aria-hidden="true"></i> Edit</button>
                                    @endif
                                    @if($canPublishMembers)
                                        <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status" data-id="{{ $news->id }}" data-url="{{ route('latest.news.status', $news->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $news->status ? 'Unpublish' : 'Publish' }} team member" title="{{ $news->status ? 'Unpublish' : 'Publish' }} team member" aria-pressed="{{ $news->status ? 'true' : 'false' }}"><i class="fa {{ $news->status ? 'fa-check-square' : 'fa-square' }}" aria-hidden="true"></i> {{ $news->status ? 'Unpublish' : 'Publish' }}</button>
                                    @endif
                                    @if($canDeleteMembers)
                                        <button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash" data-id="{{ $news->id }}" data-url="{{ route('latest.news.destroy', $news->id) }}" data-token="{{ csrf_token() }}" data-item-label="team member {{ $news->name }}" aria-label="Delete team member" title="Delete team member"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</button>
                                    @endif
                                    @if(!$canEditMembers && !$canPublishMembers && !$canDeleteMembers)<span class="badge badge-light">View only</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $latestNews->appends(['search' => $search, 'group_id' => $groupFilter])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEditMembers)
<div class="modal fade" id="latestNewsModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="teamMemberModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="fileUploadFormEdit" action="{{route('latest.news.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="teamMemberModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->OurMembers }}</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')
                    <input name="id" id="e_id" type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group text-center">
                        <div class="file-upload">
                            <label for="elatestNewsimage" class="file-upload_label">
                                <img class="file-upload_img" id="eupload_img" src="{{ asset('image/no-image.png') }}"
                                    onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                    alt="Team member photo preview">
                            </label>
                            <input type="file" onchange="changefile(event, 'eupload_img')" name="image" id="elatestNewsimage" class="file-upload_input" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div style="clear: both"></div>
                        @if($errors->has('image'))
                        <small class="help-block form-text text-danger">{{ $errors->first('image') }}</small>
                        @endif
                    </div>

                    {{-- <div class="form-group has-success">
                        <label for="category_id">{{ $Lang->Category }}</label>
                        <select class="form-control form-control-danger" name="category_id">
                            <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Category }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ @$category->id }}"
                                    @if (old('category_id') == $category->id)  selected @endif>
                                    {{ @$category->name }}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('category_id'))
                            <small
                                class="help-block form-text text-danger">{{ $errors->first('category_id') }}</small>
                        @endif
                    </div> --}}

                    <div class="form-group has-success">
                        <label for="e_name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                        <input id="e_name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                        @if($errors->has('name'))
                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="e_designation">Designation <span>*</span></label>
                        <textarea id="e_designation" class="form-control form-control-danger" name="designation" rows="2" required>{{old('designation')}}</textarea>
                        @if ($errors->has('designation'))
                            <small class="help-block form-text text-danger">{{ $errors->first('designation') }}</small>
                        @endif
                    </div>
                    <div class="form-group has-success">
                        <label for="e_team_group_id">Team group <span>*</span></label>
                        <select id="e_team_group_id" class="form-control" name="team_group_id" required>
                            <option value="">Choose a group</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}{{ $group->status ? '' : ' (Hidden)' }}</option>
                            @endforeach
                        </select>
                        @if($errors->has('team_group_id'))<small class="help-block form-text text-danger">{{ $errors->first('team_group_id') }}</small>@endif
                    </div>


                    <div class="form-group has-success">
                        <label for="e_qualification" class="control-label mb-1">Qualification</label>
                        <input id="e_qualification" name="qualification" type="text" value="{{old('qualification')}}" class="form-control" maxlength="255">
                        @if($errors->has('qualification'))
                        <small class="help-block form-text text-danger">{{ $errors->first('qualification') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="e_biography">Biography</label>
                        <textarea id="e_biography" class="form-control form-control-danger" name="biography" rows="5" maxlength="5000">{{old('biography')}}</textarea>
                        @if ($errors->has('biography'))
                            <small class="help-block form-text text-danger">{{ $errors->first('biography') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="e_order_by" class="control-label mb-1">Display order</label>
                        <input id="e_order_by" name="order_by" type="number" min="0" max="999999" value="{{old('order_by', 0)}}" class="form-control">
                        <small class="form-text text-muted">Higher numbers appear first.</small>
                        @if($errors->has('order_by'))
                        <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="e_legacy_url" class="control-label mb-1">Legacy profile URL</label>
                        <input id="e_legacy_url" name="url" type="text" value="{{old('url')}}" class="form-control" maxlength="2048">
                        <small class="form-text text-muted">Kept for existing profile links and used when no social links are provided.</small>
                        @if($errors->has('url'))
                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="mb-0">Social links</strong>
                            <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact add-social-link" data-social-scope="edit"><i class="fa fa-plus" aria-hidden="true"></i> Add link</button>
                        </div>
                        <small class="form-text text-muted mb-2">Add any public profile using a complete http:// or https:// URL.</small>
                        <div id="edit_social_links" class="social-links-editor"></div>
                        @if($errors->has('social_links') || $errors->has('social_links.*.url'))
                        <small class="help-block form-text text-danger">{{ $errors->first('social_links') ?: $errors->first('social_links.*.url') }}</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <div class="upload_progress">
                            <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">100%</div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save team member</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('custom-js')

<script src="{{ asset('admin-assets/assets/js/jquery.form.min.js')}}"></script>
<script>
    (function ($) {
        @if($canDeleteMembers)
        itemDelete({tableId: "latestNewstable", method: "DELETE"});
        @endif
        @if($canPublishMembers)
        itemStatus({tableId: "latestNewstable", method: "PUT"});
        @endif

        var socialContainers = {
            create: '#create_social_links',
            edit: '#edit_social_links'
        };
        var socialIndexes = {create: 0, edit: 0};
        var oldSocialLinks = @json(array_values((array) old('social_links', [])));
        var oldMemberId = @json(old('id'));

        function addSocialLink(scope, link) {
            if (!socialContainers[scope]) {
                return;
            }

            link = link || {};
            var index = socialIndexes[scope]++;
            var idPrefix = scope + '_social_' + index;
            var row = $(
                '<div class="social-link-row border rounded p-2 mb-2">' +
                    '<div class="form-row align-items-end">' +
                        '<div class="form-group col-md-3 mb-2">' +
                            '<label for="' + idPrefix + '_platform">Platform</label>' +
                            '<input id="' + idPrefix + '_platform" class="form-control" type="text" maxlength="50" placeholder="LinkedIn" name="social_links[' + index + '][platform]">' +
                        '</div>' +
                        '<div class="form-group col-md-3 mb-2">' +
                            '<label for="' + idPrefix + '_label">Link label</label>' +
                            '<input id="' + idPrefix + '_label" class="form-control" type="text" maxlength="80" placeholder="LinkedIn" name="social_links[' + index + '][label]">' +
                        '</div>' +
                        '<div class="form-group col-md-5 mb-2">' +
                            '<label for="' + idPrefix + '_url">Profile URL</label>' +
                            '<input id="' + idPrefix + '_url" class="form-control" type="url" maxlength="2048" placeholder="https://www.linkedin.com/in/..." autocomplete="url" name="social_links[' + index + '][url]">' +
                        '</div>' +
                        '<div class="form-group col-md-1 mb-2">' +
                            '<button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact remove-social-link" aria-label="Remove this social link"><i class="fa fa-times" aria-hidden="true"></i> Remove</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            row.find('input[name$="[platform]"]').val(String(link.platform || ''));
            row.find('input[name$="[label]"]').val(String(link.label || ''));
            row.find('input[name$="[url]"]').val(String(link.url || ''));
            $(socialContainers[scope]).append(row);
        }

        function hydrateSocialLinks(scope, links) {
            $(socialContainers[scope]).empty();
            socialIndexes[scope] = 0;
            links = Array.isArray(links) ? links : Object.values(links || {});

            if (links.length) {
                links.forEach(function (link) { addSocialLink(scope, link); });
            } else {
                addSocialLink(scope, {});
            }
        }

        $(document).on('click', '.add-social-link', function () {
            addSocialLink($(this).data('social-scope'), {});
        });

        $(document).on('click', '.remove-social-link', function () {
            $(this).closest('.social-link-row').remove();
        });

        hydrateSocialLinks('create', oldMemberId ? [] : oldSocialLinks);
        hydrateSocialLinks('edit', oldMemberId ? oldSocialLinks : []);

        @if($canEditMembers)
        if (oldMemberId) {
            $('#new_latestNews .form-group .help-block').hide();
            $('#latestNewsModal').modal('show');
        }
        @endif

        $('.cancel').click(function () {
            var form = $(this).closest('form').get(0);
            if (form) {
                form.reset();
            }
            hydrateSocialLinks($(this).closest('.fileUploadFormEdit').length ? 'edit' : 'create', []);
        });

        @if($canEditMembers)
        $('.edit').click(function () {
            $('#latestNewsModal').modal('show');
            $('.form-group .help-block').hide();
            var spinner = $('.spinner');
            var form = $('.fileUploadFormEdit');
            spinner.show();

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: "{{ route('latest.news.index')}}/" + $(this).data('id') + "/edit",
                success: function (res) {
                    if (res.data) {
                        form.find('#e_id').val(res.data.id);
                        form.find('#e_name').val(res.data.name);
                        form.find('#e_designation').val(res.data.description || '');
                        form.find('#e_team_group_id').val(String(res.data.team_group_id || ''));
                        form.find('#e_qualification').val(res.data.qualification || '');
                        form.find('#e_biography').val(res.data.biography || '');
                        form.find('#e_order_by').val(res.data.order_by || 0);
                        form.find('#e_legacy_url').val(res.data.url || '');
                        form.find('#eupload_img').attr('src', res.data.path || "{{ asset('image/no-image.png') }}");
                        form.find('select[name=category_id]').val(res.data.category_id);
                        hydrateSocialLinks('edit', res.data.social_links || []);
                    }
                    spinner.hide();
                },
                error: function (err) {
                    toastrMsg('error', err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Unable to load the team member.');
                    spinner.hide();
                }
            });
        });
        @endif
    })(jQuery);

</script>

@endsection
