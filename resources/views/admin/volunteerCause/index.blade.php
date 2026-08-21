@extends('admin.layouts.master')

@section('content')
@php($hasMutationAccess = $canCreateOpportunity || $canEditOpportunity || $canPublishOpportunity || $canDeleteOpportunity)
<div class="content pb-0">
    <div class="mb-3">
        <h1 class="h3 mb-1">Volunteer Opportunities</h1>
        <p class="text-muted mb-0">Manage the choices visitors see on the volunteer sign-up form. New opportunities stay hidden until someone with publishing access approves them.</p>
    </div>

    @unless($hasMutationAccess)
        <div class="alert alert-warning" role="status">
            <strong>Read-only access.</strong> You can review volunteer opportunities, but your role cannot create, edit, publish, or remove them.
        </div>
    @endunless

    <div class="row">
        @if($canCreateOpportunity)
            <div class="col-lg-5 col-md-12">
                <div class="card" id="new_volunteer_opportunity">
                    <div class="card-header">
                        <strong class="card-title">Add a volunteer opportunity</strong>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('volunteerCause.store') }}" method="post" data-volunteer-create>
                            @csrf
                            <div class="form-group">
                                <label for="name" class="control-label mb-1">Opportunity name <span aria-hidden="true">*</span></label>
                                <input id="name" name="name" type="text" value="{{ old('id') ? '' : old('name') }}" class="form-control" maxlength="255" required>
                                @if(!old('id'))
                                    @error('name')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="description" class="control-label mb-1">Short description <span class="text-muted">(optional)</span></label>
                                <textarea id="description" name="description" class="form-control" rows="4" maxlength="2000">{{ old('id') ? '' : old('description') }}</textarea>
                                <small class="form-text text-muted">Use a short internal explanation of the role or activity.</small>
                                @if(!old('id'))
                                    @error('description')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                                @endif
                            </div>
                            <div class="alert alert-light border" role="note">
                                This will be saved as a <strong>draft</strong>. Publish it from the list when it is ready to appear publicly.
                            </div>
                            <div class="form-actions d-flex justify-content-end" style="gap:8px">
                                <button type="reset" class="btn btn-outline-secondary">Clear</button>
                                <button type="submit" class="btn btn-info"><i class="fa fa-save" aria-hidden="true"></i> Save draft</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="{{ $canCreateOpportunity ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong class="card-title">Volunteer opportunity list</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('volunteerCause.index') }}" method="get">
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="volunteer-opportunity-search">Search opportunities</label>
                                    <input id="volunteer-opportunity-search" type="search" name="search" value="{{ $search }}" class="form-control search-form-control" placeholder="Search opportunities">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="volunteer_opportunity_table">
                        <thead>
                            <tr>
                                <th scope="col">Opportunity</th>
                                <th scope="col">Public status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($volunteerCauses as $volunteerCause)
                                <tr id="{{ $volunteerCause->id }}">
                                    <td>
                                        <strong>{{ $volunteerCause->name }}</strong>
                                        @if($volunteerCause->description)
                                            <div class="small text-muted mt-1">{{ $volunteerCause->description }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($volunteerCause->status)
                                            <span class="badge badge-success">Published</span>
                                            <small class="d-block text-muted mt-1">Visible on the sign-up form</small>
                                        @else
                                            <span class="badge badge-secondary">Draft</span>
                                            <small class="d-block text-muted mt-1">Hidden from visitors</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap align-items-center" style="gap:6px">
                                            @if($canEditOpportunity)
                                                <button type="button" class="edit btn btn-info btn-sm" data-id="{{ $volunteerCause->id }}" data-url="{{ route('volunteerCause.edit', $volunteerCause->id) }}" aria-label="Edit {{ $volunteerCause->name }}">
                                                    <i class="fa fa-edit" aria-hidden="true"></i> Edit
                                                </button>
                                            @endif
                                            @if($canPublishOpportunity)
                                                <form method="post" action="{{ route('volunteerCause.status', $volunteerCause->id) }}" class="m-0" data-volunteer-publish>
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="btn btn-warning btn-sm" aria-label="{{ $volunteerCause->status ? 'Unpublish' : 'Publish' }} {{ $volunteerCause->name }}">
                                                        <i class="fa {{ $volunteerCause->status ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i> {{ $volunteerCause->status ? 'Unpublish' : 'Publish' }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if($canDeleteOpportunity)
                                                <form method="post" action="{{ route('volunteerCause.destroy', $volunteerCause->id) }}" class="m-0" data-volunteer-delete onsubmit="return confirm('Move this volunteer opportunity to trash?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete {{ $volunteerCause->name }}">
                                                        <i class="fa fa-trash-o" aria-hidden="true"></i> Delete
                                                    </button>
                                                </form>
                                            @endif
                                            @unless($canEditOpportunity || $canPublishOpportunity || $canDeleteOpportunity)
                                                <span class="text-muted small">View only</span>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-5 text-muted">No volunteer opportunities match this search.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    {{ $volunteerCauses->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@if($canEditOpportunity)
    <div class="modal fade" id="volunteerOpportunityModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="volunteerOpportunityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <form action="{{ route('volunteerCause.update') }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="h5 modal-title" id="volunteerOpportunityModalLabel">Edit volunteer opportunity</h2>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input name="id" id="edit-opportunity-id" type="hidden" value="{{ old('id') }}" required>
                        <div class="form-group">
                            <label for="edit-opportunity-name" class="control-label mb-1">Opportunity name <span aria-hidden="true">*</span></label>
                            <input id="edit-opportunity-name" name="name" type="text" value="{{ old('id') ? old('name') : '' }}" class="form-control" maxlength="255" required>
                            @if(old('id'))
                                @error('name')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="edit-opportunity-description" class="control-label mb-1">Short description <span class="text-muted">(optional)</span></label>
                            <textarea id="edit-opportunity-description" name="description" class="form-control" rows="4" maxlength="2000">{{ old('id') ? old('description') : '' }}</textarea>
                            @if(old('id'))
                                @error('description')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                            @endif
                        </div>
                        <p class="small text-muted mb-0">Editing the wording does not change whether this opportunity is published.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info"><i class="fa fa-save" aria-hidden="true"></i> Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@if($canEditOpportunity)
    @section('custom-js')
    <script>
        $(function () {
            const modal = $('#volunteerOpportunityModal');

            @if(old('id'))
                modal.modal('show');
            @endif

            $('.edit').on('click', function () {
                const button = $(this);
                const spinner = $('.spinner');
                spinner.show();

                $.ajax({
                    type: 'get',
                    url: button.data('url'),
                    success: function (response) {
                        $('#edit-opportunity-id').val(response.data.id);
                        $('#edit-opportunity-name').val(response.data.name || '');
                        $('#edit-opportunity-description').val(response.data.description || '');
                        modal.modal('show');
                        spinner.hide();
                    },
                    error: function (error) {
                        toastrMsg('error', error.responseJSON?.message || 'The volunteer opportunity could not be loaded.');
                        spinner.hide();
                    }
                });
            });
        });
    </script>
    @endsection
@endif
