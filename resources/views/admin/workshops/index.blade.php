@extends('admin.layouts.master')

@php
    $admin = Auth::guard('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canCreate = $permissions->allows($admin, 'workshops.create');
    $canEdit = $permissions->allows($admin, 'workshops.edit');
    $canPublish = $permissions->allows($admin, 'workshops.status');
    $canDelete = $permissions->allows($admin, 'workshops.destroy');
    $canReview = $permissions->allows($admin, 'workshop.registrations.index');
@endphp

@section('content')
<main class="content pb-0" aria-labelledby="workshops-page-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div><h1 id="workshops-page-title" class="h3 mb-1">Workshops</h1><p class="text-muted mb-0">Manage free bilingual workshops, registration windows, capacity, and approvals.</p></div>
        @if($canCreate)<a class="btn igf-btn igf-btn-primary" href="{{ route('workshops.create') }}"><i class="fa fa-plus" aria-hidden="true"></i> Create workshop</a>@endif
    </div>

    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>The request could not be completed.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="card mb-3" aria-label="Filter workshops"><div class="card-body"><form method="get" action="{{ route('workshops.index') }}" class="form-inline">
        <label class="mr-2" for="workshop-status">Publication state</label><select id="workshop-status" class="form-control mr-2 mb-2 mb-sm-0" name="status"><option value="">All states</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($selectedStatus === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
        <button class="btn igf-btn igf-btn-secondary mr-2" type="submit">Apply</button><a class="btn igf-btn igf-btn-tertiary" href="{{ route('workshops.index') }}">Clear</a>
    </form></div></section>

    <section class="card" aria-labelledby="workshops-list-title">
        <div class="card-header"><strong id="workshops-list-title">{{ $workshops->total() }} {{ Str::plural('workshop', $workshops->total()) }}</strong></div>
        @if($workshops->isEmpty())
            <div class="card-body text-center py-5"><h2 class="h5">No workshops found</h2><p class="text-muted">Create a free workshop draft or clear the filter.</p></div>
        @else
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th scope="col">Workshop</th><th scope="col">Schedule</th><th scope="col">Mode</th><th scope="col">State</th><th scope="col">Registrations</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
            @foreach($workshops as $workshop)
                @php($english = $workshop->translations->firstWhere('locale', 'en') ?? $workshop->translations->first())
                <tr>
                    <td><strong>{{ $english?->title ?? 'Untitled workshop' }}</strong><small class="d-block text-success">Always free</small></td>
                    <td><small class="d-block"><time datetime="{{ $workshop->starts_at?->toAtomString() }}">{{ $workshop->starts_at?->format('d M Y, H:i') }}</time></small><small class="d-block text-muted">Registration closes {{ $workshop->registration_closes_at?->format('d M Y, H:i') }}</small></td>
                    <td>{{ Str::headline($workshop->attendance_mode) }} · {{ Str::headline($workshop->registration_mode) }}<small class="d-block text-muted">Capacity {{ $workshop->capacity ?? 'Unlimited' }}</small></td>
                    <td><span class="badge badge-{{ $workshop->publication_status === 'published' ? 'success' : ($workshop->publication_status === 'draft' ? 'warning' : 'secondary') }}">{{ Str::headline($workshop->publication_status) }}</span></td>
                    <td>@if($canReview)<a href="{{ route('workshop.registrations.index', ['listing' => $workshop->uuid]) }}">{{ $workshop->registrations_count }}</a>@else{{ $workshop->registrations_count }}@endif</td>
                    <td><div class="d-flex flex-wrap justify-content-end" style="gap:.35rem">
                        @if($canEdit)<a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route('workshops.edit', $workshop) }}">Edit</a>@endif
                        @if($canPublish && $workshop->publication_status !== 'withdrawn')
                            <form method="post" action="{{ route('workshops.status', $workshop) }}" onsubmit="return confirm('{{ $workshop->publication_status === 'draft' ? 'Publish this saved draft now? Confirm its deadline, workshop time, attendance, registration decision and capacity first.' : 'Close registration now? The public detail page will remain available, but no new registrations will be accepted.' }}')">@csrf @method('patch')<input type="hidden" name="editor_version" value="{{ $workshop->editor_version }}"><input type="hidden" name="action" value="{{ $workshop->publication_status === 'draft' ? 'publish' : 'close' }}"><button class="btn igf-btn igf-btn-tertiary igf-btn-compact" type="submit">{{ $workshop->publication_status === 'draft' ? 'Review & publish' : 'Close registration' }}</button></form>
                            <form method="post" action="{{ route('workshops.status', $workshop) }}" onsubmit="return confirm('Withdraw this workshop from all public pages? Its registrations will remain private and available to authorized staff.')">@csrf @method('patch')<input type="hidden" name="action" value="withdraw"><button class="btn btn-outline-secondary btn-sm" type="submit">Withdraw</button></form>
                        @endif
                        @if($canCreate)<form method="post" action="{{ route('workshops.duplicate', $workshop) }}">@csrf<button class="btn igf-btn igf-btn-tertiary igf-btn-compact" type="submit">Duplicate</button></form>@endif
                        @if($canDelete && $workshop->publication_status === 'draft' && $workshop->registrations_count === 0)<form method="post" action="{{ route('workshops.destroy', $workshop) }}" onsubmit="return confirm('Delete this unused draft?')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm" type="submit">Delete</button></form>@endif
                    </div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="card-footer">{{ $workshops->links('vendor.pagination.bootstrap-4') }}</div>
        @endif
    </section>
</main>
@endsection
