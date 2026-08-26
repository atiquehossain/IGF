<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

@php
    $editorSchemaJson = $schemaJson;
    $oldSchema = old('schema');
    if (is_string($oldSchema)) {
        try {
            $decodedOldSchema = json_decode($oldSchema, true, 64, JSON_THROW_ON_ERROR);
            if (is_array($decodedOldSchema) && array_is_list($decodedOldSchema)) {
                $editorSchemaJson = $oldSchema;
            }
        } catch (\JsonException) {
            // Keep the last authoritative server snapshot when old input is malformed.
        }
    }
@endphp

<main class="afb-builder-page" aria-labelledby="afb-builder-title">
    <header class="afb-builder-header">
        <div class="afb-builder-heading">
            <a class="afb-icon-link" href="{{ route($routeNames['index']) }}" aria-label="Back to forms"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div><p class="afb-eyebrow">{{ $sectionLabel }} form builder</p><h1 id="afb-builder-title">{{ $form->name }}</h1><p>Editing {{ $hasDraft ? 'draft' : 'published version' }} v{{ $version->version }} · editor revision <span data-editor-version-label>{{ $form->editor_version }}</span></p></div>
        </div>
        <div class="afb-builder-header-actions">
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => 'en']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> Preview EN</a>
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => 'bn']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> Preview BN</a>
        </div>
    </header>

    @if($errors->any())
        <div class="alert alert-danger afb-server-errors" role="alert">
            <strong>The draft was not saved.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @error('editor_version')<a class="btn igf-btn igf-btn-secondary mt-2" href="{{ route($routeNames['edit'], $form) }}">Reload current version</a>@enderror
        </div>
    @endif

    <noscript><div class="alert alert-danger" role="alert">JavaScript is required for the visual form builder. No changes have been made.</div></noscript>

    <section id="application-form-builder"
             class="afb-builder"
             data-update-url="{{ route($routeNames['update'], $form) }}"
             data-publish-url="{{ route($routeNames['publish'], $form) }}"
             data-edit-url="{{ route($routeNames['edit'], $form) }}"
             data-purpose="{{ $purpose }}"
             data-initial-dirty="{{ old('schema') ? '1' : '0' }}"
             data-has-draft="{{ $hasDraft ? '1' : '0' }}">
        <textarea id="afb-builder-config" hidden aria-hidden="true">{{ $configJson }}</textarea>
        <form id="afb-builder-form" method="post" action="{{ route($routeNames['update'], $form) }}">
            @csrf
            @method('PUT')
            <input id="afb-editor-version" type="hidden" name="editor_version" value="{{ old('editor_version', $form->editor_version) }}">
            <textarea id="afb-schema-input" name="schema" hidden aria-hidden="true">{{ $editorSchemaJson }}</textarea>

            <div id="afb-builder-alert" class="afb-builder-alert" role="status" aria-live="polite" tabindex="-1" hidden></div>
            <div class="afb-builder-toolbar" aria-label="Form builder actions">
                <button class="btn igf-btn igf-btn-primary" type="button" data-add-field><i class="fa fa-plus" aria-hidden="true"></i> Add question</button>
                <span class="afb-toolbar-spacer"></span>
                <span class="afb-save-state" data-save-state role="status" aria-live="polite">All changes saved</span>
                <button class="btn igf-btn igf-btn-secondary" type="submit" data-save-draft><i class="fa fa-save" aria-hidden="true"></i> Save draft</button>
                <button class="btn igf-btn igf-btn-primary" type="button" data-publish-form @disabled(!$hasDraft)><i class="fa fa-check-circle" aria-hidden="true"></i> Save and publish</button>
            </div>

            <div class="afb-builder-layout">
                <aside class="afb-builder-guide" aria-labelledby="afb-guide-title">
                    <h2 id="afb-guide-title">Build the form</h2>
                    <ol>
                        <li>Add or reorder questions.</li>
                        <li>Write English and Bangla copy.</li>
                        <li>Configure options and validation.</li>
                        <li>Add conditions using earlier questions.</li>
                        <li>Save, preview, then publish.</li>
                    </ol>
                    <div class="afb-guide-note"><strong>Protected fields</strong><p>Identity fields and the recruitment CV field cannot be removed or have their type or required setting changed. The server checks this again on every save.</p></div>
                </aside>
                <div class="afb-field-workspace">
                    <div class="afb-field-list-heading"><div><h2>Questions</h2><p><span data-field-count>0</span> of 100 fields</p></div><button class="btn igf-btn igf-btn-secondary" type="button" data-add-field><i class="fa fa-plus" aria-hidden="true"></i> Add question</button></div>
                    <div id="afb-field-list" class="afb-field-list" aria-live="polite"></div>
                </div>
            </div>
        </form>
    </section>
</main>

@section('custom-js')
<script type="module" src="{{ asset('admin-assets/application-form-builder/form-builder.js') }}"></script>
@endsection
