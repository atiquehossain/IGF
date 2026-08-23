@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Sponsorship enquiries</h1>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:12px">
                <div>
                    <strong class="card-title">Sponsorship enquiries</strong>
                    <small class="d-block text-muted">Track every request from first contact through completion.</small>
                </div>
                <div>
                <form action="{{ route('sponsorships.search') }}" method="post" class="form-inline mb-2" role="search" aria-label="Search sponsorship enquiries">@csrf
                    <label class="sr-only" for="sponsor-search">Search</label>
                    <input id="sponsor-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" required placeholder="Name, email, phone or reference" class="form-control form-control-sm mr-2">
                    <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact mr-2"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                @if($search !== '')<form action="{{ route('sponsorships.search.clear') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary igf-btn-compact mb-2"><i class="fa fa-undo" aria-hidden="true"></i> Clear private search</button></form>@endif
                <form action="{{ route('sponsorships.index') }}" method="get" class="form-inline" aria-label="Filter sponsorship enquiries by status">
                    <label class="sr-only" for="sponsor-status">Status</label>
                    <select id="sponsor-status" name="workflow_status" class="form-control form-control-sm mr-2">
                        <option value="">All statuses</option>
                        @foreach($workflowStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-filter" aria-hidden="true"></i> Apply status filter</button>
                </form>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="sponsorship_table">
                <thead>
                    <tr>
                        <th>Supporter</th>
                        <th>Request</th>
                        <th>Payment</th>
                        <th style="min-width:320px">Team workflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sponsorships as $sponsor)
                        <tr>
                            <td>
                                <strong>{{ $sponsor->name }}</strong>
                                <div><a href="mailto:{{ $sponsor->email }}">{{ $sponsor->email }}</a></div>
                                @if($sponsor->phone)<div><a href="tel:{{ $sponsor->phone }}">{{ $sponsor->phone }}</a></div>@endif
                                @if($sponsor->address)<small class="text-muted">{{ $sponsor->address }}</small>@endif
                            </td>
                            <td>
                                <strong>{{ $sponsor->number_of_children }} {{ (int) $sponsor->number_of_children === 1 ? 'child' : 'children' }}</strong>
                                <div>{{ ucfirst(str_replace('_', ' ', $sponsor->contribution_interval)) }}</div>
                                <small class="text-muted">Received {{ $sponsor->created_at?->format('d M Y, H:i') }}</small>
                            </td>
                            <td>
                                <strong>৳{{ number_format((float) $sponsor->sponsorship_amount, 2) }}</strong>
                                <div>{{ $sponsor->payment_status }}</div>
                                <small class="text-muted">{{ $sponsor->transaction_id }}</small>
                            </td>
                            <td>
                                @include('admin.enquiries._workflow', [
                                    'record' => $sponsor,
                                    'routeName' => 'sponsorships.workflow',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">No sponsorship enquiries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $sponsorships->withQueryString()->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
