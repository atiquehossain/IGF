@extends('admin.layouts.master')

@php
    $canExportVolunteers = app(\App\Http\Middleware\Permission::class)
        ->allows(auth('admin')->user(), 'volunteer.export');
@endphp

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Volunteer applications</h1>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:12px">
                <div>
                    <strong class="card-title">Volunteer applications</strong>
                    <small class="d-block text-muted">Search, assign and record follow-up without using a separate spreadsheet.</small>
                </div>
                <div>
                <form action="{{ route('volunteer.search') }}" method="post" class="form-inline mb-2" role="search" aria-label="Search volunteer applications">@csrf
                    <label class="sr-only" for="volunteer-search">Search</label>
                    <input id="volunteer-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" required placeholder="Name, email, phone or institution" class="form-control form-control-sm mr-2">
                    <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact mr-2"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                @if($search !== '')<form action="{{ route('volunteer.search.clear') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary igf-btn-compact mb-2"><i class="fa fa-undo" aria-hidden="true"></i> Clear private search</button></form>@endif
                <form action="{{ route('volunteer.index') }}" method="get" class="form-inline" aria-label="Filter volunteer applications by status and date">
                    <label class="sr-only" for="volunteer-status">Status</label>
                    <select id="volunteer-status" name="workflow_status" class="form-control form-control-sm mr-2">
                        <option value="">All statuses</option>
                        @foreach($workflowStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="volunteer-from">From date</label>
                    <input id="volunteer-from" type="date" name="from_date" value="{{ $from_date }}" class="form-control form-control-sm mr-2">
                    <label class="sr-only" for="volunteer-to">To date</label>
                    <input id="volunteer-to" type="date" name="to_date" value="{{ $to_date }}" class="form-control form-control-sm mr-2">
                    <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact mr-2"><i class="fa fa-filter" aria-hidden="true"></i> Apply filters</button>
                    @if($canExportVolunteers)
                        <a class="btn igf-btn igf-btn-secondary igf-btn-compact" target="_blank" rel="noopener" href="{{ route('volunteer.export.excel', request()->only(['workflow_status', 'from_date', 'to_date'])) }}">
                            <i class="fa fa-download" aria-hidden="true"></i> Export filtered list
                        </a>
                    @endif
                </form>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="volunteer_table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Interest</th>
                        <th>Application</th>
                        <th style="min-width:320px">Team workflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($volunteers as $volunteer)
                        <tr>
                            <td>
                                <strong>{{ $volunteer->name }}</strong>
                                @if($volunteer->institution)<div>{{ $volunteer->institution }}</div>@endif
                                <div><a href="mailto:{{ $volunteer->email }}">{{ $volunteer->email }}</a></div>
                                @if($volunteer->phone)<div><a href="tel:{{ $volunteer->phone }}">{{ $volunteer->phone }}</a></div>@endif
                            </td>
                            <td>
                                <strong>{{ $volunteer->cause?->name ?: 'Not specified' }}</strong>
                                @if($volunteer->address)<div class="text-muted">{{ $volunteer->address }}</div>@endif
                            </td>
                            <td>{{ $volunteer->created_at?->format('d M Y, H:i') }}</td>
                            <td>
                                @include('admin.enquiries._workflow', [
                                    'record' => $volunteer,
                                    'routeName' => 'volunteer.workflow',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">No volunteer applications match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $volunteers->withQueryString()->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
