@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Contact enquiries</h1>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:12px">
                <div>
                    <strong class="card-title">Contact enquiries</strong>
                    <small class="d-block text-muted">Assign messages, record private notes and schedule follow-up.</small>
                </div>
                <div>
                <form action="{{ route('contact-message.search') }}" method="post" class="form-inline mb-2" role="search" aria-label="Search contact enquiries">@csrf
                    <label class="sr-only" for="contact-search">Search</label>
                    <input id="contact-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" required placeholder="Name, email, phone or message" class="form-control form-control-sm mr-2">
                    <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact mr-2"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                @if($search !== '')<form action="{{ route('contact-message.search.clear') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary igf-btn-compact mb-2"><i class="fa fa-undo" aria-hidden="true"></i> Clear private search</button></form>@endif
                <form action="{{ route('contact-message.index') }}" method="get" class="form-inline" aria-label="Filter contact enquiries by status">
                    <label class="sr-only" for="contact-status">Status</label>
                    <select id="contact-status" name="workflow_status" class="form-control form-control-sm mr-2">
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
            <table class="table table-hover mb-0" id="message_table">
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Message</th>
                        <th>Received</th>
                        <th style="min-width:320px">Team workflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contactMessages as $message)
                        <tr>
                            <td>
                                <strong>{{ trim($message->first_name . ' ' . $message->last_name) ?: 'Website visitor' }}</strong>
                                @if($message->email)<div><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></div>@endif
                                @if($message->phone)<div><a href="tel:{{ $message->phone }}">{{ $message->phone }}</a></div>@endif
                                @if($message->address)<small class="text-muted">{{ $message->address }}</small>@endif
                            </td>
                            <td style="max-width:520px;white-space:pre-wrap">{{ $message->message }}</td>
                            <td>{{ $message->created_at?->format('d M Y, H:i') }}</td>
                            <td>
                                @include('admin.enquiries._workflow', [
                                    'record' => $message,
                                    'routeName' => 'contact-message.workflow',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">No contact enquiries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $contactMessages->withQueryString()->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
