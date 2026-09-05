<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace);
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
            <a class="afb-icon-link" href="{{ route($routeNames['index']) }}" aria-label="{{ $ui('edit.back') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div><p class="afb-eyebrow">{{ $ui('edit.eyebrow', ['area' => $sectionLabel]) }}</p><h1 id="afb-builder-title">{{ $form->name }}</h1><p>{{ $ui($hasDraft ? 'edit.editing_draft' : 'edit.editing_published', ['version' => $version->version, 'revision' => $form->editor_version]) }}</p></div>
        </div>
        <div class="afb-builder-header-actions">
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => 'en']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> {{ $ui('edit.preview_en') }}</a>
            <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['preview'], [$form, 'locale' => 'bn']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> {{ $ui('edit.preview_bn') }}</a>
        </div>
    </header>

    @if($errors->any())
        <div class="alert alert-danger afb-server-errors" role="alert">
            <strong>{{ $ui('edit.save_failed') }}</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @error('editor_version')<a class="btn igf-btn igf-btn-secondary mt-2" href="{{ route($routeNames['edit'], $form) }}">{{ $ui('edit.reload') }}</a>@enderror
        </div>
    @endif

    <details class="card afb-metadata-card" @if($errors->has('name') || $errors->has('is_template')) open @endif>
        <summary><span><i class="fa fa-cog" aria-hidden="true"></i> {{ $ui('edit.details') }}</span><small>{{ $ui('edit.details_help') }}</small></summary>
        <form method="post" action="{{ route($routeNames['metadata'], $form) }}" class="afb-metadata-form">
            @csrf
            @method('PATCH')
            <input type="hidden" name="editor_version" value="{{ old('editor_version', $form->editor_version) }}">
            <label><span>{{ $ui('edit.name') }}</span><input class="form-control" type="text" name="name" maxlength="150" required value="{{ old('name', $form->name) }}"></label>
            <input type="hidden" name="is_template" value="0">
            <label class="afb-check afb-check--card"><input type="checkbox" name="is_template" value="1" @checked((bool) old('is_template', $form->is_template))><span><strong>{{ $ui('edit.template_label') }}</strong><small>{{ $ui('edit.template_help') }}</small></span></label>
            <div class="afb-form-actions"><button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> {{ $ui('edit.save_details') }}</button></div>
        </form>
    </details>

    <noscript><div class="alert alert-danger" role="alert">{{ $ui('edit.javascript_required') }}</div></noscript>

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
            <div class="afb-builder-toolbar" aria-label="{{ $ui('edit.actions_label') }}">
                <button class="btn igf-btn igf-btn-primary" type="button" data-add-field><i class="fa fa-plus" aria-hidden="true"></i> {{ $ui('edit.add_question') }}</button>
                <span class="afb-toolbar-spacer"></span>
                <span class="afb-save-state" data-save-state role="status" aria-live="polite">{{ $ui('edit.all_saved') }}</span>
                <button class="btn igf-btn igf-btn-secondary" type="submit" data-save-draft><i class="fa fa-save" aria-hidden="true"></i> {{ $ui('edit.save_draft') }}</button>
                <button class="btn igf-btn igf-btn-primary" type="button" data-publish-form @disabled(!$hasDraft)><i class="fa fa-check-circle" aria-hidden="true"></i> {{ $ui('edit.save_publish') }}</button>
            </div>

            <div class="afb-builder-layout">
                <aside class="afb-builder-guide" aria-labelledby="afb-guide-title">
                    <h2 id="afb-guide-title">{{ $ui('edit.guide_title') }}</h2>
                    <ol>
                        <li>{{ $ui('edit.guide_1') }}</li>
                        <li>{{ $ui('edit.guide_2') }}</li>
                        <li>{{ $ui('edit.guide_3') }}</li>
                        <li>{{ $ui('edit.guide_4') }}</li>
                        <li>{{ $ui('edit.guide_5') }}</li>
                    </ol>
                    <div class="afb-guide-note"><strong>{{ $ui('edit.protected_title') }}</strong><p>{{ $ui('edit.protected_help') }}</p></div>
                </aside>
                <div class="afb-field-workspace">
                    <div class="afb-field-list-heading"><div><h2>{{ $ui('edit.questions') }}</h2><p><span data-field-count>0</span> {{ $ui('edit.field_count_suffix', ['max' => \App\Services\ApplicationFormSchemaService::MAX_FIELDS]) }}</p></div><button class="btn igf-btn igf-btn-secondary" type="button" data-add-field><i class="fa fa-plus" aria-hidden="true"></i> {{ $ui('edit.add_question') }}</button></div>
                    <div id="afb-field-list" class="afb-field-list" aria-live="polite"></div>
                </div>
            </div>
        </form>
    </section>
</main>

@section('custom-js')
<script type="module" src="{{ asset('admin-assets/application-form-builder/form-builder.js') }}"></script>
@endsection
