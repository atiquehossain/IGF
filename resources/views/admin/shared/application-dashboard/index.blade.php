<link rel="stylesheet" href="{{ asset('admin-assets/application-dashboard/dashboard.css') }}">

@php
    $selectionName = $isJob ? 'application_ids' : 'registration_ids';
    $countAttribute = $isJob ? 'applications_count' : 'registrations_count';
    $pretty = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
    $safeFilters = collect($filters)->only(['status', 'assigned_to', 'from', 'to', 'sort', 'direction', 'per_page']);
@endphp

<main class="ad-page" aria-labelledby="ad-page-title">
    <header class="ad-page-header">
        <div>
            <p class="ad-eyebrow">{{ $sectionLabel }} review workspace</p>
            <h1 id="ad-page-title">{{ ucfirst($recordsLabel) }}</h1>
            <p>Review submissions, coordinate staff decisions, and export the selected result set.</p>
        </div>
        @if($listing && $canExport)
            <details class="ad-export-menu">
                <summary class="btn igf-btn igf-btn-secondary"><i class="fa fa-download" aria-hidden="true"></i> Export CSV</summary>
                <form method="get" action="{{ route($routeNames['export']) }}" class="ad-popover-card" data-no-busy>
                    <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                    @foreach($safeFilters as $filter => $value)
                        <input type="hidden" name="{{ $filter }}" value="{{ $value }}">
                    @endforeach
                    <fieldset>
                        <legend>CSV columns</legend>
                        <div class="ad-check-grid">
                            @foreach($fixedExportColumns as $column)
                                <label><input type="checkbox" name="columns[]" value="{{ $column }}" checked> {{ $pretty($column) }}</label>
                            @endforeach
                            @foreach($availableColumns as $column)
                                <label><input type="checkbox" name="columns[]" value="{{ $column['key'] }}" @checked(in_array($column['key'], $visibleColumns, true))> {{ $column['label'] }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <button class="btn igf-btn igf-btn-primary" type="submit">Download secure CSV</button>
                    <p class="ad-help">The export uses the current filters and private session search. Search text never appears in the URL or audit log.</p>
                </form>
            </details>
        @endif
    </header>

    @if(session('message'))
        <div class="alert alert-{{ session('alert-type', 'success') }}" role="status">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>The request could not be completed.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="ad-card" aria-labelledby="ad-listing-title">
        <div class="ad-card-heading">
            <div><h2 id="ad-listing-title">Choose a listing</h2><p>Applicant data is always isolated to one listing.</p></div>
        </div>
        <form action="{{ route($routeNames['index']) }}" method="get" class="ad-inline-form">
            <div class="ad-field ad-field-grow">
                <label for="ad-listing">{{ $isJob ? 'Job listing' : 'Workshop' }}</label>
                <select id="ad-listing" name="listing" required>
                    @forelse($listings as $option)
                        <option value="{{ $option->uuid }}" @selected($listing?->is($option))>
                            {{ $listingTitle($option) }} · {{ $option->{$countAttribute} }} {{ $recordsLabel }}
                        </option>
                    @empty
                        <option value="">No listings available</option>
                    @endforelse
                </select>
            </div>
            <button class="btn igf-btn igf-btn-primary" type="submit" @disabled($listings->isEmpty())>Open listing</button>
        </form>
    </section>

    @if(!$listing)
        <section class="ad-empty" role="status">
            <i class="fa fa-inbox" aria-hidden="true"></i>
            <h2>No {{ $isJob ? 'job listings' : 'workshops' }} yet</h2>
            <p>Create and publish a listing before reviewing {{ $recordsLabel }}.</p>
        </section>
    @else
        <section class="ad-card" aria-labelledby="ad-filter-title">
            <div class="ad-card-heading">
                <div><p class="ad-eyebrow">Selected listing</p><h2 id="ad-filter-title">{{ $listingLabel }}</h2></div>
                <span class="ad-count">{{ number_format($records->total()) }} matched</span>
            </div>

            <div class="ad-search-row">
                <form method="post" action="{{ route($routeNames['search']) }}" class="ad-private-search" autocomplete="off">
                    @csrf
                    <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                    <div class="ad-field ad-field-grow">
                        <label for="ad-private-search">Private applicant search</label>
                        <input id="ad-private-search" name="search" type="search" maxlength="100" required
                               placeholder="Name, email, phone, or reference" aria-describedby="ad-search-help">
                        <small id="ad-search-help">Stored only in your server-side session for 10 minutes; never placed in the URL.</small>
                    </div>
                    <button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                @if($privateSearch !== '')
                    <div class="ad-active-search" role="status">
                        <span>Private search active: <strong>{{ $privateSearch }}</strong></span>
                        <form method="post" action="{{ route($routeNames['search_clear']) }}">
                            @csrf
                            <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                            <button class="btn igf-btn igf-btn-tertiary" type="submit">Clear search</button>
                        </form>
                    </div>
                @endif
            </div>

            <form method="get" action="{{ route($routeNames['index']) }}" class="ad-filter-grid">
                <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                <div class="ad-field"><label for="ad-status">Status</label><select id="ad-status" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $pretty($status) }}</option>@endforeach</select></div>
                <div class="ad-field"><label for="ad-assignee">Assigned to</label><select id="ad-assignee" name="assigned_to"><option value="">Anyone</option><option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $assignee->id)>{{ $assignee->name ?: $assignee->username }}</option>@endforeach</select></div>
                <div class="ad-field"><label for="ad-from">Submitted from</label><input id="ad-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="ad-field"><label for="ad-to">Submitted to</label><input id="ad-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="ad-field"><label for="ad-sort">Sort by</label><select id="ad-sort" name="sort">@foreach($sorts as $sort)<option value="{{ $sort }}" @selected(($filters['sort'] ?? '') === $sort)>{{ $pretty($sort) }}</option>@endforeach</select></div>
                <div class="ad-field"><label for="ad-direction">Direction</label><select id="ad-direction" name="direction"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descending</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Ascending</option></select></div>
                <div class="ad-field"><label for="ad-per-page">Rows</label><select id="ad-per-page" name="per_page">@foreach([25, 50, 100] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></div>
                <div class="ad-filter-actions"><button class="btn igf-btn igf-btn-primary" type="submit">Apply filters</button><a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">Reset filters</a></div>
            </form>
        </section>

        <section class="ad-card" aria-labelledby="ad-results-title">
            <div class="ad-card-heading">
                <div><h2 id="ad-results-title">{{ ucfirst($recordsLabel) }}</h2><p>Showing the most recent saved submission for each email address.</p></div>
                @if($canEdit)
                    <details class="ad-column-menu">
                        <summary class="btn igf-btn igf-btn-secondary"><i class="fa fa-columns" aria-hidden="true"></i> Table columns</summary>
                        <form method="post" action="{{ route($routeNames['bulk']) }}" class="ad-popover-card">
                            @csrf
                            <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                            <input type="hidden" name="operation" value="preferences">
                            <fieldset>
                                <legend>Custom answer columns</legend>
                                @forelse($availableColumns as $column)
                                    <label><input type="checkbox" name="visible_columns[]" value="{{ $column['key'] }}" @checked(in_array($column['key'], $visibleColumns, true))> {{ $column['label'] }}</label>
                                @empty
                                    <p class="ad-help">This listing has no custom answer fields.</p>
                                @endforelse
                            </fieldset>
                            <div class="ad-two-column">
                                <div class="ad-field"><label for="ad-pref-sort">Default sort</label><select id="ad-pref-sort" name="sort">@foreach($sorts as $sort)<option value="{{ $sort }}" @selected(($filters['sort'] ?? '') === $sort)>{{ $pretty($sort) }}</option>@endforeach</select></div>
                                <div class="ad-field"><label for="ad-pref-direction">Default direction</label><select id="ad-pref-direction" name="direction"><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Descending</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Ascending</option></select></div>
                            </div>
                            <button class="btn igf-btn igf-btn-primary" type="submit">Save my table</button>
                        </form>
                    </details>
                @endif
            </div>

            <form method="post" action="{{ route($routeNames['bulk']) }}" data-bulk-form>
                @csrf
                <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                @if($canEdit)
                    <div class="ad-bulk-toolbar">
                        <div class="ad-field"><label for="ad-bulk-operation">Bulk action</label><select id="ad-bulk-operation" name="operation" data-bulk-operation required><option value="">Choose action</option><option value="status">Change status</option><option value="assignment">Assign reviewer</option></select></div>
                        <div class="ad-field" data-bulk-status hidden><label for="ad-bulk-status-value">New status</label><select id="ad-bulk-status-value" name="workflow_status">@foreach($statuses as $status)<option value="{{ $status }}">{{ $pretty($status) }}</option>@endforeach</select></div>
                        <div class="ad-field" data-bulk-assignment hidden><label for="ad-bulk-assignee-value">Reviewer</label><select id="ad-bulk-assignee-value" name="assigned_to_admin_id"><option value="">Unassigned</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}">{{ $assignee->name ?: $assignee->username }}</option>@endforeach</select></div>
                        <button class="btn igf-btn igf-btn-primary" type="submit">Apply to selected</button>
                        <span class="ad-help" data-selected-count role="status" aria-live="polite">0 selected · maximum 100</span>
                    </div>
                @endif

                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <caption class="sr-only">{{ ucfirst($recordsLabel) }} for {{ $listingLabel }}</caption>
                        <thead><tr>
                            @if($canEdit)<th class="ad-select-column"><label><span class="sr-only">Select every visible {{ $recordLabel }}</span><input type="checkbox" data-select-all></label></th>@endif
                            <th>Applicant</th><th>Reference</th><th>Status</th><th>Assigned</th><th>Submitted</th>
                            @if($isJob)<th>Average score</th>@endif
                            @foreach($availableColumns->whereIn('key', $visibleColumns) as $column)<th>{{ $column['label'] }}</th>@endforeach
                            <th><span class="sr-only">Actions</span></th>
                        </tr></thead>
                        <tbody>
                        @forelse($records as $record)
                            <tr>
                                @if($canEdit)<td data-label="Select"><label><span class="sr-only">Select {{ $record->name }}</span><input type="checkbox" name="{{ $selectionName }}[]" value="{{ $record->id }}" data-row-select></label></td>@endif
                                <td data-label="Applicant"><strong>{{ $record->name }}</strong><span class="ad-subline"><span>{{ $record->email }}</span><button class="ad-copy-button" type="button" data-copy-value="{{ $record->email }}" aria-label="Copy email for {{ $record->name }}"><i class="fa fa-copy" aria-hidden="true"></i> Copy</button></span>@if($record->phone)<span class="ad-subline">{{ $record->phone }}</span>@endif</td>
                                <td data-label="Reference"><code>{{ $record->reference_number }}</code>@if($record->submission_count > 1)<span class="ad-pill">Updated {{ $record->submission_count }}×</span>@endif</td>
                                <td data-label="Status"><span class="ad-status ad-status-{{ $record->workflow_status }}">{{ $pretty($record->workflow_status) }}</span></td>
                                <td data-label="Assigned">{{ $record->assignedAdmin?->name ?: ($record->assignedAdmin?->username ?: 'Unassigned') }}</td>
                                <td data-label="Submitted"><time datetime="{{ $record->last_submitted_at?->toAtomString() }}">{{ $record->last_submitted_at?->format('d M Y, g:i A') }}</time></td>
                                @if($isJob)<td data-label="Average score">{{ $record->scores_avg_score !== null ? number_format((float) $record->scores_avg_score, 2) : '—' }}</td>@endif
                                @foreach($availableColumns->whereIn('key', $visibleColumns) as $column)<td data-label="{{ $column['label'] }}">{{ $answerValues[$record->id][$column['key']] ?? '—' }}</td>@endforeach
                                <td data-label="Actions"><a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['show'], $record) }}">Review <span class="sr-only">{{ $record->name }}</span></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 7 + count($visibleColumns) + ($canEdit ? 1 : 0) + ($isJob ? 1 : 0) }}" class="ad-empty-cell">No {{ $recordsLabel }} match the current filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            @if(method_exists($records, 'links'))<div class="ad-pagination">{{ $records->links() }}</div>@endif
        </section>
    @endif

    <div class="ad-copy-status" data-copy-status role="status" aria-live="polite"></div>
</main>

@section('custom-js')
<script src="{{ asset('admin-assets/application-dashboard/dashboard.js') }}" defer></script>
@endsection
