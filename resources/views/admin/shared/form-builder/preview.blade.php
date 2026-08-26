<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-preview-page" aria-labelledby="afb-preview-title">
    <header class="afb-preview-toolbar">
        <div>
            <p class="afb-eyebrow">{{ $sectionLabel }} form preview</p>
            <h1 id="afb-preview-title">{{ $form->name }}</h1>
            <p>{{ ucfirst($version->state) }} version {{ $version->version }} · {{ $locale === 'bn' ? 'Bangla' : 'English' }}</p>
        </div>
        <div class="afb-preview-actions">
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => $locale === 'en' ? 'bn' : 'en', 'state' => $version->state]) }}">View {{ $locale === 'en' ? 'Bangla' : 'English' }}</a>
            <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['edit'], $form) }}"><i class="fa fa-pencil" aria-hidden="true"></i> Back to builder</a>
        </div>
    </header>

    <div class="alert alert-info afb-preview-notice" role="status"><strong>Preview only.</strong> Nothing entered on this page is saved or submitted.</div>
    <textarea id="afb-preview-schema" hidden aria-hidden="true">{{ $previewSchemaJson }}</textarea>

    <section class="afb-public-form" data-form-preview aria-label="{{ $form->name }} preview">
        @foreach($previewSchema['fields'] as $field)
            @include('admin.shared.form-builder.preview-field', ['field' => $field, 'fieldIndex' => $loop->index, 'locale' => $locale])
        @endforeach
        <div class="afb-preview-submit"><button class="btn igf-btn igf-btn-primary" type="button" disabled aria-disabled="true">Submit (disabled in preview)</button></div>
    </section>
</main>

@section('custom-js')
<script type="module" src="{{ asset('admin-assets/application-form-builder/form-builder.js') }}"></script>
@endsection
