@extends('admin.layouts.master')

@php
    $permissions = app(\App\Http\Middleware\Permission::class);
    $currentAdmin = auth('admin')->user();
    $canCreateDonationTypes = $permissions->allows($currentAdmin, 'donationType.create');
    $canEditDonationTypes = $permissions->allows($currentAdmin, 'donationType.edit');
    $canPublishDonationTypes = $permissions->allows($currentAdmin, 'donationType.status');
    $canDeleteDonationTypes = $permissions->allows($currentAdmin, 'donationType.destroy');
    $donationTypesAreReadOnly = !$canCreateDonationTypes && !$canEditDonationTypes && !$canPublishDonationTypes && !$canDeleteDonationTypes;
@endphp

@section('content')
<style>
    .donation-type-table-scroll{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    .donation-type-table-scroll:focus{outline:3px solid rgba(255,117,0,.35);outline-offset:-3px}
    .donation-type-table-scroll .table{min-width:1080px;margin-bottom:0}
    .donation-type-table-scroll td:last-child{white-space:nowrap}
</style>
<div class="content pb-0">

    <header class="mb-4">
        <h1 class="h3 mb-1">Donation causes</h1>
        <p class="text-muted mb-0">Manage the choices donors see and where each gift is assigned.</p>
    </header>

    @if($donationTypesAreReadOnly)
        <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review donation causes, but your role cannot create, edit, publish, or remove them.</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <strong class="card-title">Donation cause groups</strong>
                    <small class="d-block text-muted">Manage the tabs visitors use to browse causes. Lower display-order numbers appear first. Hiding a tab never hides its causes from “All causes”.</small>
                </div>
                <span class="badge badge-light">{{ $causeGroups->count() }} {{ Str::plural('group', $causeGroups->count()) }}</span>
            </div>
        </div>
        <div class="card-body">
            @if($errors->donationCauseGroup->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Unable to save the donation cause group.</strong>
                    <ul class="mb-0 mt-2">@foreach($errors->donationCauseGroup->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if($canCreateDonationTypes)
                <form action="{{ route('donationType.group.store') }}" method="POST" class="border rounded p-3 mb-4">
                    @csrf
                    <h2 class="h6 mb-3">Create a donation cause group</h2>
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label for="new-donation-group-name">Group name <span>*</span></label>
                            <input id="new-donation-group-name" class="form-control" name="group_name" value="{{ old('group_name') }}" maxlength="255" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="new-donation-group-order">Display order <span>*</span></label>
                            <input id="new-donation-group-order" class="form-control" name="group_display_order" type="number" min="0" max="100000" value="{{ old('group_display_order', $nextGroupDisplayOrder) }}" required>
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button class="btn igf-btn igf-btn-primary w-100" type="submit"><i class="fa fa-plus" aria-hidden="true"></i> Create group</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new-donation-group-description">Short description</label>
                        <textarea id="new-donation-group-description" class="form-control" name="group_description" rows="2" maxlength="2000">{{ old('group_description') }}</textarea>
                        <small class="form-text text-muted">Shown with the active tab when provided.</small>
                    </div>
                    <small class="text-muted">The stable internal slug is generated automatically. Translate the visitor-facing group name in the Translation Center.</small>
                </form>
            @endif

            <div class="row">
                @forelse($causeGroups as $group)
                    <div class="col-xl-6 col-12 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong>{{ $group->name }}</strong>
                                    <span class="badge {{ $group->status ? 'badge-success' : 'badge-secondary' }} ml-2">{{ $group->status ? 'Visible tab' : 'Hidden tab' }}</span>
                                </div>
                                <span class="badge badge-light">{{ $group->published_causes_count }} published · {{ $group->attached_causes_count }} attached</span>
                            </div>

                            @if($canEditDonationTypes)
                                <form action="{{ route('donationType.group.update', $group) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-row">
                                        <div class="form-group col-md-8">
                                            <label for="donation-group-name-{{ $group->id }}">Group name</label>
                                            <input id="donation-group-name-{{ $group->id }}" class="form-control" name="group_name" value="{{ $group->name }}" maxlength="255" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="donation-group-order-{{ $group->id }}">Display order</label>
                                            <input id="donation-group-order-{{ $group->id }}" class="form-control" name="group_display_order" type="number" min="0" max="100000" value="{{ $group->display_order }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="donation-group-description-{{ $group->id }}">Short description</label>
                                        <textarea id="donation-group-description-{{ $group->id }}" class="form-control" name="group_description" rows="2" maxlength="2000">{{ $group->description }}</textarea>
                                    </div>
                                    <small class="d-block text-muted mb-3">Stable slug: {{ $group->slug }}</small>
                                    <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save group</button>
                                </form>
                            @else
                                @if($group->description)<p class="mb-2">{{ $group->description }}</p>@endif
                                <small class="text-muted">Stable slug: {{ $group->slug }} · Display order: {{ $group->display_order }}</small>
                            @endif

                            <div class="d-flex flex-wrap mt-3" style="gap:8px">
                                @if($canPublishDonationTypes)
                                    <form action="{{ route('donationType.group.status', $group) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="submit"><i class="fa {{ $group->status ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i> {{ $group->status ? 'Hide tab' : 'Show tab' }}</button>
                                    </form>
                                @endif
                                @if($canDeleteDonationTypes)
                                    @if($group->attached_causes_count)
                                        <button class="btn igf-btn igf-btn-danger igf-btn-compact" type="button" disabled title="Move attached causes before deleting this group"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete group</button>
                                    @else
                                        <form action="{{ route('donationType.group.destroy', $group) }}" method="POST" onsubmit="return confirm('Delete this empty donation cause group?')">
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
                    <div class="col-12"><p class="alert alert-warning mb-0">No category tabs are configured. Causes remain available under “All causes”.</p></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row">
        @if($canCreateDonationTypes)
        <div class="col-lg-5 col-md-12">
            <div id="new_donation_type">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->DonationTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{route('donationType.store')}}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input id="name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                                        @if($errors->has('name'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group">
                                        <label for="description" class="control-label mb-1">Short description</label>
                                        <textarea id="description" name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                                    </div>

                                    @include('admin.donationType._destination-fields', ['prefix' => 'new_', 'defaultDisplayOrder' => $nextDisplayOrder])

                                    <div class="form-group">
                                        <input id="new_purpose_key" type="hidden" name="purpose_key" value="">
                                        <div class="alert alert-info small mb-0"><strong>New causes start as regular drafts.</strong> Review the visitor wording, managed image, and funding destination; ask a publisher to publish it; then edit the published cause if it should become the Zakat page cause.</div>
                                        @error('purpose_key')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i> Create donation cause</button>
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
        <div class="{{ $canCreateDonationTypes ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title" id="donation-causes-table-title">{{ $Lang->DonationTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('donationType.index')}}" method="get">
                                <label for="donation-type-search" class="control-label d-block mb-1">Search donation causes</label>
                                <div class="input-group search-input-group">
                                    <input id="donation-type-search" type="search" name="search" value="{{@$search}}" class="form-control search-form-control">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats">
                    <div class="donation-type-table-scroll" role="region" aria-labelledby="donation-causes-table-title" tabindex="0">
                    <table class="table" id="donation_type_table">
                        <caption class="sr-only">Donation causes, their public role, funding destination, readiness, and available actions. Each record also controls its card order and fallback icon.</caption>
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th><strong>Card</strong></th>
                                <th><strong>Donation page role</strong></th>
                                <th><strong>Funding destination</strong></th>
                                <th><strong>Readiness</strong></th>
                                <th><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($donationTypes as $donationType)
                            <tr id="{{ @$donationType->id }}">
                                <td> #{{@$donationType->id}} </td>
                                <td><span class="name"><strong>{{ $donationType->name }}</strong></span><br><small class="text-muted">/donate/{{ $donationType->slug }}</small></td>
                                <td><strong>Order {{ $donationType->display_order ?? '—' }}</strong><br><small>{{ $iconOptions[$donationType->icon_key] ?? 'Automatic icon' }}</small><br><small>Tab: {{ $donationType->causeGroup?->name ?? 'All causes only' }}</small></td>
                                <td>{{ $purposeOptions[$donationType->purpose_key ?? ''] ?? 'Regular donation cause' }}</td>
                                <td><strong>{{ $destinationOptions[$donationType->destination_type] ?? 'Needs review' }}</strong><br><small>{{ $donationType->destination_label }}</small></td>
                                <td>
                                    @if(!$donationType->description_ready)<span class="badge badge-warning">Description needs review</span>
                                    @elseif($donationType->status && $donationType->destination_ready)<span class="badge badge-success">Published and ready</span>
                                    @elseif($donationType->destination_ready)<span class="badge badge-secondary">Draft — ready to publish</span>
                                    @else<span class="badge badge-warning">Destination needs attention</span>@endif
                                </td>
                                <td>
                                    @if($canEditDonationTypes)
                                        <a href="javascript:void(0)" class="edit btn igf-btn igf-btn-secondary igf-btn-compact" data-id="{{ $donationType->id }}" aria-label="Edit donation cause" title="Edit donation cause"><i class="fa fa-edit" aria-hidden="true"></i> Edit</a>
                                    @endif
                                    @if($canPublishDonationTypes)
                                        <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status" data-id="{{ $donationType->id }}" data-url="{{ route('donationType.status', $donationType->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $donationType->status ? 'Unpublish' : 'Publish' }} donation cause {{ $donationType->name }}" title="{{ $donationType->status ? 'Unpublish' : 'Publish' }} donation cause" aria-pressed="{{ $donationType->status ? 'true' : 'false' }}"><i class="fa {{ $donationType->status ? 'fa-check-square' : 'fa-square' }}" aria-hidden="true"></i> {{ $donationType->status ? 'Unpublish' : 'Publish' }}</button>
                                    @endif
                                    @if($canDeleteDonationTypes)
                                        <a href="javascript:void(0)" class="btn igf-btn igf-btn-danger igf-btn-compact trash" data-id="{{ $donationType->id }}" data-url="{{ route('donationType.destroy', $donationType->id) }}" data-token="{{ csrf_token() }}" aria-label="Delete donation cause" title="Delete donation cause"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</a>
                                    @endif
                                    @if(!$canEditDonationTypes && !$canPublishDonationTypes && !$canDeleteDonationTypes)<span class="badge badge-light">View only</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="pagination justify-content-end">
                        {{ $donationTypes->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEditDonationTypes)
<div class="modal fade" id="donationTypeModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="donationTypeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{route('donationType.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="donationTypeModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->DonationTitle }}</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')
                    <input name="id" id="e_id" type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="e_name" class="control-label mb-1">{{ $Lang->Common->Form->Name }}<span>*</span></label>
                        <input id="e_name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                        @if($errors->has('name'))
                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="e_description" class="control-label mb-1">Short description</label>
                        <textarea id="e_description" name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="e_slug" class="control-label mb-1">Stable donation link</label>
                        <input id="e_slug" type="text" class="form-control" readonly aria-describedby="e_slug_help">
                        <small id="e_slug_help" class="form-text text-muted">This link stays the same when the display name changes.</small>
                    </div>

                    @include('admin.donationType._destination-fields', ['prefix' => 'e_'])

                    <div class="form-group">
                        <label for="e_purpose_key" class="control-label mb-1">Donation page role</label>
                        <select id="e_purpose_key" name="purpose_key" class="form-control">
                            @foreach($purposeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('purpose_key', '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Only one cause can be assigned to the Zakat donation page.</small>
                        @error('purpose_key')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save donation cause</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif



@endsection

@section('custom-js')
<script>
    @if($canDeleteDonationTypes)
    itemDelete({tableId: "donation_type_table",method: "DELETE"});
    @endif
    @if($canPublishDonationTypes)
    $('#donation_type_table tbody').on('click', '.status', function () {
        var button = $(this);
        if (button.prop('disabled')) return;
        var spinner = $('.spinner');
        button.prop('disabled', true).attr('aria-busy', 'true');
        spinner.show();
        $.ajax({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            type: 'PUT',
            url: button.data('url'),
            success: function (res) {
                toastrMsg('success', res.message);
                window.location.reload();
            },
            error: function (err) {
                toastrMsg('error', err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Publication status could not be changed.');
                button.prop('disabled', false).removeAttr('aria-busy');
                spinner.hide();
            }
        });
    });
    @endif

    $(".cancel").click(function () {
        clear($(this).closest('form'));
    });

    function clear(form) {
        var target = form && form.length ? form : $('#new_donation_type form');
        if (!target.length) return;
        target.trigger('reset');
        target.find('.help-block').hide();
        synchronizeDonationTypeForms();
    }

    @if($canEditDonationTypes)
    var is_edit = "{{old('id')}}";
    if (is_edit) {
        $('#new_donation_type .form-group .help-block').hide();
        $("#new_donation_type input").val("");
        $('#donationTypeModal').modal('show');
    }

    $(".edit").click(function () {
        $('#donationTypeModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('donationType.index')}}/" + id + "/edit",
            success: function (res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $('.modal #e_name').val(res.data.name);
                    $('.modal #e_description').val(res.data.description || '');
                    $('.modal #e_purpose_key').val(res.data.purpose_key || '');
                    $('.modal #e_slug').val('/donate/' + (res.data.slug || ''));
                    $('.modal #e_destination_type').val(res.data.destination_type || 'restricted_fund');
                    $('.modal #e_destination_name').val(res.data.destination_name || '');
                    $('.modal #e_destination_category_uuid').val(res.data.destination_category_uuid || '');
                    $('.modal #e_destination_page_uuid').val(res.data.destination_page_uuid || '');
                    $('.modal #e_image_media_uuid').val(res.data.image_media_uuid || '');
                    $('.modal #e_display_order').val(res.data.display_order ?? '');
                    $('.modal #e_icon_key').val(res.data.icon_key || '');
                    $('.modal #e_donation_cause_group_id').val(res.data.donation_cause_group_id || '');
                    syncDestination('e_');
                    syncImagePreview('e_');
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

    function syncDestination(prefix) {
        var type = $('#' + prefix + 'destination_type').val() || 'restricted_fund';
        $('[data-destination-owner="' + prefix + '"]').each(function () {
            var active = $(this).data('destination-panel') === type;
            $(this).toggle(active);
            $(this).find('input,select,textarea').prop('disabled', !active).prop('required', active);
        });
        var purpose = $('#' + prefix + 'purpose_key').val() || '';
        var unrestricted = $('#' + prefix + 'destination_type option[value="unrestricted"]');
        unrestricted.prop('disabled', purpose === 'zakat');
        if (purpose === 'zakat' && type === 'unrestricted') {
            $('#' + prefix + 'destination_type').val('restricted_fund');
            syncDestination(prefix);
        }
    }

    function syncImagePreview(prefix) {
        var option = $('#' + prefix + 'image_media_uuid option:selected');
        var url = option.data('image-url') || '';
        $('#' + prefix + 'image_preview').attr('src', url).toggle(Boolean(url));
    }

    function synchronizeDonationTypeForms() {
        destinationPrefixes.forEach(function (prefix) {
            syncDestination(prefix);
            syncImagePreview(prefix);
        });
    }

    var destinationPrefixes = [];
    @if($canCreateDonationTypes)
    destinationPrefixes.push('new_');
    @endif
    @if($canEditDonationTypes)
    destinationPrefixes.push('e_');
    @endif
    destinationPrefixes.forEach(function (prefix) {
        $('#' + prefix + 'destination_type,#' + prefix + 'purpose_key').on('change', function () { syncDestination(prefix); });
        $('#' + prefix + 'image_media_uuid').on('change', function () { syncImagePreview(prefix); });
    });
    synchronizeDonationTypeForms();
</script>

@endsection
