<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page afb-page--narrow" aria-labelledby="import-result-title">
    <nav class="afb-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">CSV imports</a><span aria-hidden="true">/</span><span>Result</span>
    </nav>
    <section class="card afb-create-card">
        <div class="card-header">
            <p class="afb-eyebrow">{{ $sectionLabel }} · {{ $listingLabel }}</p>
            <h1 id="import-result-title">CSV import {{ Str::headline($batch->state) }}</h1>
            <p>{{ $batch->source_name }}</p>
        </div>
        <div class="card-body">
            @if($batch->state === 'completed')
                <div class="alert alert-success" role="status"><strong>Import completed.</strong> The reviewed rows were committed atomically.</div>
            @else
                <div class="alert alert-warning" role="status"><strong>Import status: {{ Str::headline($batch->state) }}.</strong> No completed result is available.</div>
            @endif
            <dl class="row">
                <dt class="col-sm-7">Total CSV rows</dt><dd class="col-sm-5">{{ number_format($batch->total_rows) }}</dd>
                <dt class="col-sm-7">Imported creates or updates</dt><dd class="col-sm-5">{{ number_format($batch->imported_rows) }}</dd>
                <dt class="col-sm-7">Duplicate rows in preview</dt><dd class="col-sm-5">{{ number_format($batch->duplicate_rows) }}</dd>
                <dt class="col-sm-7">Invalid rows in preview</dt><dd class="col-sm-5">{{ number_format($batch->invalid_rows) }}</dd>
                <dt class="col-sm-7">Confirmed</dt><dd class="col-sm-5">{{ $batch->confirmed_at?->format('j M Y, H:i') ?: 'Not confirmed' }}</dd>
            </dl>
            @if($batch->invalid_rows > 0 || $batch->duplicate_rows > 0)
                <p><a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['errors_download'], ['batch' => $batch, 'listing' => $listing->uuid]) }}">Download safe error report</a></p>
            @endif
            <div class="afb-form-actions">
                <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">Back to CSV imports</a>
            </div>
        </div>
    </section>
</main>
