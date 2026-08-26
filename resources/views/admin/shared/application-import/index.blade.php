<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page" aria-labelledby="import-page-title">
    <header class="afb-page-header">
        <div>
            <p class="afb-eyebrow">{{ $sectionLabel }}</p>
            <h1 id="import-page-title">CSV imports</h1>
            <p>Import reviewed Google Forms exports into one listing. Every batch stays private and auditable.</p>
        </div>
        @if($listing)
            <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create'], ['listing' => $listing->uuid]) }}">
                <i class="fa fa-upload" aria-hidden="true"></i> Upload CSV
            </a>
        @endif
    </header>

    @if($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1">
            <strong>The import list could not be opened.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card afb-filter-card" aria-labelledby="import-listing-title">
        <div class="card-body">
            <form method="get" action="{{ route($routeNames['index']) }}" class="afb-filter-grid">
                <label>
                    <span id="import-listing-title">{{ ucfirst($recordLabel) }} listing</span>
                    <select class="form-control" name="listing" required>
                        <option value="" @selected(!$listing)>Choose a listing</option>
                        @foreach($listings as $option)
                            <option value="{{ $option->uuid }}" @selected($listing && $listing->is($option))>
                                {{ $listingTitle($option) ?? '' }} ({{ $option->import_batches_count }} {{ Str::plural('batch', $option->import_batches_count) }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="afb-filter-actions">
                    <button class="btn igf-btn igf-btn-secondary" type="submit">Open imports</button>
                </div>
            </form>
        </div>
    </section>

    @if(!$listing)
        <section class="card" aria-label="No listings">
            <div class="afb-empty">
                <i class="fa fa-list" aria-hidden="true"></i>
                <h2>No {{ Str::plural($recordLabel) }} listings are available</h2>
                <p>Create and publish a listing with a form before importing historical records.</p>
            </div>
        </section>
    @else
        <section class="card" aria-labelledby="import-batches-title">
            <div class="card-header">
                <strong id="import-batches-title">{{ $batches->total() }} import {{ Str::plural('batch', $batches->total()) }} for {{ $listingLabel }}</strong>
            </div>
            @if($batches->isEmpty())
                <div class="afb-empty">
                    <i class="fa fa-upload" aria-hidden="true"></i>
                    <h2>No CSV imports yet</h2>
                    <p>Upload a UTF-8 CSV, map the columns, then confirm only after reviewing the preview.</p>
                    <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create'], ['listing' => $listing->uuid]) }}">Upload the first CSV</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table afb-table">
                        <caption class="sr-only">Private CSV import batches for {{ $listingLabel }}</caption>
                        <thead>
                        <tr><th>Uploaded</th><th>File</th><th>Status</th><th>Rows</th><th>Uploaded by</th><th><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody>
                        @foreach($batches as $batch)
                            <tr>
                                <td><time datetime="{{ $batch->created_at?->toAtomString() }}">{{ $batch->created_at?->format('j M Y, H:i') }}</time></td>
                                <td>{{ $batch->source_name }}</td>
                                <td><span class="badge badge-light">{{ Str::headline($batch->state) }}</span></td>
                                <td>{{ number_format($batch->total_rows) }}</td>
                                <td>{{ $batch->uploadedByAdmin?->name ?: 'Former administrator' }}</td>
                                <td>
                                    @php($batchRoute = in_array($batch->state, ['uploaded', 'previewed'], true) ? $routeNames['preview'] : $routeNames['result'])
                                    <a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route($batchRoute, ['batch' => $batch, 'listing' => $listing->uuid]) }}">
                                        {{ in_array($batch->state, ['uploaded', 'previewed'], true) ? 'Continue review' : 'View result' }}
                                    </a>
                                    @if($batch->invalid_rows > 0 || $batch->duplicate_rows > 0)
                                        <a class="btn igf-btn igf-btn-tertiary igf-btn-compact" href="{{ route($routeNames['errors_download'], ['batch' => $batch, 'listing' => $listing->uuid]) }}">Error report</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="afb-pagination">{{ $batches->links('vendor.pagination.bootstrap-4') }}</div>
            @endif
        </section>
    @endif
</main>
