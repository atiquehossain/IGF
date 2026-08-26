<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page afb-page--narrow" aria-labelledby="import-upload-title">
    <nav class="afb-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">CSV imports</a><span aria-hidden="true">/</span><span>Upload</span>
    </nav>
    <section class="card afb-create-card">
        <div class="card-header">
            <p class="afb-eyebrow">{{ $sectionLabel }} · {{ $listingLabel }}</p>
            <h1 id="import-upload-title">Upload a CSV</h1>
            <p>The file is stored privately. You will map columns and review row decisions before any {{ $recordLabel }} is written.</p>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert" tabindex="-1">
                    <strong>The CSV could not be uploaded.</strong>
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="post" action="{{ route($routeNames['store'], ['listing' => $listing->uuid]) }}" enctype="multipart/form-data" class="afb-create-form">
                @csrf
                <input type="hidden" name="listing" value="{{ $listing->uuid }}">
                <label for="import-file">
                    <span>UTF-8 CSV file <b aria-hidden="true">*</b></span>
                    <input id="import-file" class="form-control" type="file" name="file" accept=".csv,text/csv" required aria-describedby="import-file-help">
                    <small id="import-file-help">Maximum {{ number_format($maxBytes / 1048576) }} MiB, 20,000 data rows, and 100 columns. Export Google Forms responses as CSV; external CV links are never imported as files.</small>
                </label>
                <div class="alert alert-info" role="note">
                    Spreadsheet formulas, HTML, file paths, and external attachment links remain inert text. Protected upload fields cannot be mapped.
                </div>
                <div class="afb-form-actions">
                    <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}">Cancel</a>
                    <button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-arrow-right" aria-hidden="true"></i> Upload and map columns</button>
                </div>
            </form>
        </div>
    </section>
</main>
