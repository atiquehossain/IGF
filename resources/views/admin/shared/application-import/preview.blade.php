<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page" aria-labelledby="import-preview-title">
    <nav class="afb-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">CSV imports</a><span aria-hidden="true">/</span><span>Review</span>
    </nav>
    <header class="afb-page-header">
        <div>
            <p class="afb-eyebrow">{{ $sectionLabel }} · {{ $listingLabel }}</p>
            <h1 id="import-preview-title">{{ $screen === 'mapping' ? 'Map CSV columns' : 'Review import preview' }}</h1>
            <p>{{ $batch->source_name }} · {{ number_format($batch->total_rows) }} data {{ Str::plural('row', $batch->total_rows) }}</p>
        </div>
        @if($screen === 'review')
            <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['preview'], ['batch' => $batch, 'listing' => $listing->uuid, 'remap' => 1]) }}">Change mapping</a>
        @endif
    </header>

    @if($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1">
            <strong>{{ $screen === 'mapping' ? 'The preview could not be generated.' : 'The import could not be confirmed.' }}</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($screen === 'mapping')
        <section class="card" aria-labelledby="mapping-title">
            <div class="card-header"><strong id="mapping-title">Column mapping</strong></div>
            <div class="card-body">
                <p>Map exactly one column to applicant name and one to email. Ignore export timestamps, CV links, and columns you do not need. Protected upload fields cannot be mapped.</p>
                <form method="post" action="{{ route($routeNames['preview'], ['batch' => $batch, 'listing' => $listing->uuid]) }}">
                    @csrf
                    <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                    <div class="table-responsive">
                        <table class="table afb-table">
                            <caption class="sr-only">Map CSV headers to protected identity fields or published form questions</caption>
                            <thead><tr><th scope="col">CSV column</th><th scope="col">Import destination</th></tr></thead>
                            <tbody>
                            @foreach($headers as $index => $header)
                                @php($selected = old("columns.{$index}.destination", $suggestedMapping[$header] ?? 'ignore'))
                                <tr>
                                    <th scope="row">{{ $header }}</th>
                                    <td>
                                        <input type="hidden" name="columns[{{ $index }}][header]" value="{{ $header }}">
                                        <label class="sr-only" for="mapping-{{ $index }}">Destination for {{ $header }}</label>
                                        <select id="mapping-{{ $index }}" class="form-control" name="columns[{{ $index }}][destination]" required>
                                            <option value="ignore" @selected($selected === 'ignore')>Ignore this column</option>
                                            @foreach($destinations as $key => $destination)
                                                <option value="{{ $key }}" @selected($selected === $key)>
                                                    {{ $destination['label'] }} · {{ $destination['type'] }}{{ $destination['required'] ? ' · required' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <fieldset class="mt-4">
                        <legend class="h5">Duplicate email policy</legend>
                        <p>One email can have only one current record in this listing.</p>
                        @foreach([
                            'update' => 'Update the existing record with the last CSV occurrence while preserving its workflow and assignment.',
                            'skip' => 'Skip every row whose email already exists or repeats in this CSV.',
                            'reject' => 'Mark duplicate emails invalid so the import cannot be confirmed.',
                        ] as $value => $description)
                            <label class="afb-check afb-check--card d-flex mb-2">
                                <input type="radio" name="duplicate_policy" value="{{ $value }}" required @checked($duplicatePolicy === $value)>
                                <span><strong>{{ Str::headline($value) }}</strong><small>{{ $description }}</small></span>
                            </label>
                        @endforeach
                    </fieldset>
                    <div class="afb-form-actions mt-4">
                        <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">Cancel</a>
                        <button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> Generate reviewed preview</button>
                    </div>
                </form>
            </div>
        </section>
    @else
        <section class="row" aria-label="Preview totals">
            @foreach([
                ['label' => 'Total rows', 'value' => $batch->total_rows],
                ['label' => 'Valid rows', 'value' => $batch->valid_rows],
                ['label' => 'Invalid rows', 'value' => $batch->invalid_rows],
                ['label' => 'Duplicate rows', 'value' => $batch->duplicate_rows],
            ] as $total)
                <div class="col-sm-6 col-xl-3 mb-3"><div class="card"><div class="card-body"><small>{{ $total['label'] }}</small><div class="h3 mb-0">{{ number_format($total['value']) }}</div></div></div></div>
            @endforeach
        </section>

        @if($batch->invalid_rows > 0)
            <div class="alert alert-danger" role="alert">
                <strong>This import cannot be confirmed.</strong> Change the mapping or upload a corrected CSV until every row is valid.
                <a class="alert-link" href="{{ route($routeNames['errors_download'], ['batch' => $batch, 'listing' => $listing->uuid]) }}">Download the safe error report</a>.
            </div>
        @else
            <div class="alert alert-info" role="status">No application or registration has been written yet. Confirmation revalidates the source, schema, and duplicate decisions.</div>
        @endif

        <section class="card" aria-labelledby="preview-rows-title">
            <div class="card-header"><strong id="preview-rows-title">Reviewed row decisions</strong></div>
            <div class="table-responsive">
                <table class="table afb-table">
                    <caption class="sr-only">Import decisions and validation errors. Applicant values are intentionally not displayed.</caption>
                    <thead><tr><th scope="col">CSV row</th><th scope="col">State</th><th scope="col">Action</th><th scope="col">Validation errors</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td><span class="badge badge-light">{{ Str::headline($row->state) }}</span></td>
                            <td>{{ $row->action ? Str::headline($row->action) : 'Blocked' }}</td>
                            <td>
                                @php($rowErrors = collect((array) $row->validation_errors)->flatten()->filter()->values())
                                @if($rowErrors->isEmpty())
                                    <span class="text-muted">None</span>
                                @else
                                    <ul class="mb-0">@foreach($rowErrors as $rowError)<li>{{ $rowError }}</li>@endforeach</ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="afb-pagination">{{ $rows->links('vendor.pagination.bootstrap-4') }}</div>
        </section>

        @if($batch->invalid_rows === 0)
            <section class="card afb-create-card mt-4" aria-labelledby="confirm-import-title">
                <div class="card-body">
                    <h2 id="confirm-import-title" class="h4">Confirm import</h2>
                    <p>This is the write step. It will create, update, or skip rows exactly as shown, after a fresh server-side verification.</p>
                    <form method="post" action="{{ route($routeNames['confirm'], ['batch' => $batch, 'listing' => $listing->uuid]) }}">
                        @csrf
                        <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                        <label class="afb-check afb-check--card">
                            <input type="checkbox" name="confirm_import" value="1" required>
                            <span><strong>I reviewed the counts, row actions, and duplicate policy.</strong><small>Import {{ number_format($batch->valid_rows) }} valid rows using the {{ Str::headline($duplicatePolicy) }} policy.</small></span>
                        </label>
                        <div class="afb-form-actions mt-3">
                            <button class="btn igf-btn igf-btn-primary" type="submit">Confirm and import</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif
    @endif
</main>
