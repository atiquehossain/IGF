<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace);
    $targetLocale = $locale === 'en' ? 'bn' : 'en';
@endphp

<main class="afb-preview-page" aria-labelledby="afb-preview-title">
    <header class="afb-preview-toolbar">
        <div>
            <p class="afb-eyebrow">{{ $ui('preview.eyebrow', ['area' => $sectionLabel]) }}</p>
            <h1 id="afb-preview-title">{{ $form->name }}</h1>
            <p>{{ $ui('preview.version', ['state' => $ui("states.{$version->state}"), 'version' => $version->version, 'language' => $ui("languages.{$locale}")]) }}</p>
        </div>
        <div class="afb-preview-actions">
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => $targetLocale, 'state' => $version->state]) }}">{{ $ui('preview.view_language', ['language' => $ui("languages.{$targetLocale}")]) }}</a>
            <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['edit'], $form) }}"><i class="fa fa-pencil" aria-hidden="true"></i> {{ $ui('preview.back') }}</a>
        </div>
    </header>

    <div class="alert alert-info afb-preview-notice" role="status"><strong>{{ $ui('preview.notice_title') }}</strong> {{ $ui('preview.notice_help') }}</div>
    <textarea id="afb-preview-schema" hidden aria-hidden="true">{{ $previewSchemaJson }}</textarea>

    <section class="afb-public-form" data-form-preview aria-label="{{ $ui('preview.label', ['name' => $form->name]) }}">
        @foreach($previewSchema['fields'] as $field)
            @include('admin.shared.form-builder.preview-field', ['field' => $field, 'fieldIndex' => $loop->index, 'locale' => $locale])
        @endforeach
        <div class="afb-preview-submit"><button class="btn igf-btn igf-btn-primary" type="button" disabled aria-disabled="true">{{ $ui('preview.submit_disabled') }}</button></div>
    </section>
</main>

@section('custom-js')
<script type="module" src="{{ asset('admin-assets/application-form-builder/form-builder.js') }}"></script>
@endsection
