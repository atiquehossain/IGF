<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace);
@endphp

<main class="afb-page" aria-labelledby="afb-page-title">
    <header class="afb-page-header">
        <div>
            <p class="afb-eyebrow">{{ $sectionLabel }}</p>
            <h1 id="afb-page-title">{{ $ui($isTrash ? 'list.trash_title' : 'list.active_title') }}</h1>
            <p>{{ $ui($isTrash ? 'list.trash_intro' : 'list.active_intro') }}</p>
        </div>
        <div class="afb-page-header-actions">
            @if($isTrash)
                <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['index']) }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $ui('list.active_forms') }}</a>
            @else
                <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['trash']) }}"><i class="fa fa-trash" aria-hidden="true"></i> {{ $ui('list.view_trash') }}</a>
                @if($canManage)
                    <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create']) }}"><i class="fa fa-plus" aria-hidden="true"></i> {{ $ui('list.create_form') }}</a>
                @endif
            @endif
        </div>
    </header>

    @if(!$canManage)
        <div class="alert alert-info afb-readonly-notice" role="status">
            <strong>{{ $ui('list.readonly_title') }}</strong> {{ $ui('list.readonly_help') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>{{ $ui('list.request_failed') }}</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card afb-filter-card" aria-label="{{ $ui('list.filter_label') }}">
        <form method="get" action="{{ route($isTrash ? $routeNames['trash'] : $routeNames['index']) }}" class="afb-filter-grid">
            <label><span>{{ $ui('list.search') }}</span><input class="form-control" type="search" name="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="{{ $ui('list.search_placeholder') }}"></label>
            <label>
                <span>{{ $ui('list.kind') }}</span>
                <select class="form-control" name="kind">
                    <option value="all" @selected($filters['kind'] === 'all')>{{ $ui('list.all_kinds') }}</option>
                    <option value="forms" @selected($filters['kind'] === 'forms')>{{ $ui('list.forms_only') }}</option>
                    <option value="templates" @selected($filters['kind'] === 'templates')>{{ $ui('list.templates_only') }}</option>
                </select>
            </label>
            <label>
                <span>{{ $ui('list.state') }}</span>
                <select class="form-control" name="state">
                    <option value="all" @selected($filters['state'] === 'all')>{{ $ui('list.any_state') }}</option>
                    <option value="draft" @selected($filters['state'] === 'draft')>{{ $ui('list.has_draft') }}</option>
                    <option value="published" @selected($filters['state'] === 'published')>{{ $ui('list.has_published') }}</option>
                </select>
            </label>
            <div class="afb-filter-actions">
                <button class="btn igf-btn igf-btn-secondary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> {{ $ui('list.apply') }}</button>
                <a class="btn igf-btn igf-btn-tertiary" href="{{ route($isTrash ? $routeNames['trash'] : $routeNames['index']) }}">{{ $ui('list.clear') }}</a>
            </div>
        </form>
    </section>

    <section class="card" aria-labelledby="afb-list-title">
        <div class="card-header"><strong id="afb-list-title">{{ $ui($isTrash ? ($forms->total() === 1 ? 'list.archived_count_one' : 'list.archived_count_many') : ($forms->total() === 1 ? 'list.count_one' : 'list.count_many'), ['count' => $forms->total()]) }}</strong></div>
        @if($forms->isEmpty())
            <div class="afb-empty">
                <i class="fa {{ $isTrash ? 'fa-trash-o' : 'fa-list-alt' }}" aria-hidden="true"></i>
                <h2>{{ $ui($isTrash ? 'list.trash_empty' : 'list.no_forms') }}</h2>
                <p>{{ $ui($isTrash ? 'list.trash_empty_help' : 'list.no_forms_help') }}</p>
                @if(!$isTrash && $canManage)<a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create']) }}">{{ $ui('list.create_first') }}</a>@endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table afb-table">
                    <thead><tr><th>{{ $ui('list.name') }}</th><th>{{ $ui('list.kind') }}</th><th>{{ $ui('list.version_state') }}</th><th>{{ $ui($isTrash ? 'list.archived' : 'list.updated') }}</th><th><span class="sr-only">{{ $ui('list.actions') }}</span></th></tr></thead>
                    <tbody>
                    @foreach($forms as $form)
                        @php
                            $draft = $form->versions->where('state', 'draft')->sortByDesc('version')->first();
                            $published = $form->versions->where('state', 'published')->sortByDesc('version')->first();
                            $hasPermanentVersion = $form->versions->contains(fn ($version) => $version->state !== 'draft');
                            $activeReferences = (int) $form->active_job_postings_count + (int) $form->active_workshops_count;
                            $totalReferences = (int) $form->total_job_postings_count + (int) $form->total_workshops_count;
                            $canPermanentlyDelete = $totalReferences === 0 && !$hasPermanentVersion;
                        @endphp
                        <tr>
                            <td><strong>{{ $form->name }}</strong><small class="afb-row-meta">{{ $ui('list.editor_revision', ['version' => $form->editor_version]) }}</small></td>
                            <td><span class="badge {{ $form->is_template ? 'badge-info' : 'badge-light' }}">{{ $ui($form->is_template ? 'list.template' : 'list.form') }}</span></td>
                            <td>
                                @if($draft)<span class="badge badge-warning">{{ $ui('list.draft_version', ['version' => $draft->version]) }}</span>@endif
                                @if($published)<span class="badge badge-success">{{ $ui('list.published_version', ['version' => $published->version]) }}</span>@endif
                                @if(!$draft && !$published)<span class="badge badge-light">{{ $ui('list.permanent_history') }}</span>@endif
                            </td>
                            <td><time datetime="{{ ($isTrash ? $form->deleted_at : $form->updated_at)?->toAtomString() }}">{{ ($isTrash ? $form->deleted_at : $form->updated_at)?->diffForHumans() }}</time></td>
                            <td>
                                <div class="afb-row-actions">
                                    @if(!$isTrash)
                                        <a class="btn igf-btn igf-btn-tertiary igf-btn-compact" href="{{ route($routeNames['preview'], [$form, 'locale' => 'en']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> {{ $ui('list.preview') }}</a>
                                        @if($canManage)
                                            <a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route($routeNames['edit'], $form) }}"><i class="fa fa-pencil" aria-hidden="true"></i> {{ $ui('list.edit') }}</a>
                                            <details class="afb-duplicate">
                                                <summary class="btn igf-btn igf-btn-tertiary igf-btn-compact"><i class="fa fa-copy" aria-hidden="true"></i> {{ $ui('list.duplicate') }}</summary>
                                                <form method="post" action="{{ route($routeNames['duplicate'], $form) }}">
                                                    @csrf
                                                    <label><span>{{ $ui('list.new_name') }}</span><input class="form-control" name="name" maxlength="150" required value="{{ $ui('list.copy_name', ['name' => $form->name]) }}"></label>
                                                    <input type="hidden" name="is_template" value="0">
                                                    <label class="afb-check"><input type="checkbox" name="is_template" value="1" @checked($form->is_template)> {{ $ui('list.save_as_template') }}</label>
                                                    <button class="btn igf-btn igf-btn-primary" type="submit">{{ $ui('list.create_copy') }}</button>
                                                </form>
                                            </details>
                                            <form class="afb-lifecycle-form" method="post" action="{{ route($routeNames['destroy'], $form) }}" onsubmit="return confirm(@js($ui('list.archive_confirm')));">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="editor_version" value="{{ $form->editor_version }}">
                                                <button class="btn igf-btn igf-btn-danger igf-btn-compact" type="submit" @disabled($activeReferences > 0) title="{{ $ui($activeReferences > 0 ? 'list.reassign_before_archive' : 'list.move_to_trash') }}"><i class="fa fa-trash" aria-hidden="true"></i> {{ $ui('list.archive') }}</button>
                                            </form>
                                            @if($activeReferences > 0)<small class="afb-action-help">{{ $ui($activeReferences === 1 ? 'list.in_use_one' : 'list.in_use_many', ['count' => $activeReferences]) }}</small>@endif
                                        @endif
                                    @elseif($canManage)
                                        <form class="afb-lifecycle-form" method="post" action="{{ route($routeNames['restore'], $form->uuid) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="editor_version" value="{{ $form->editor_version }}">
                                            <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="submit"><i class="fa fa-undo" aria-hidden="true"></i> {{ $ui('list.restore') }}</button>
                                        </form>
                                        <form class="afb-lifecycle-form" method="post" action="{{ route($routeNames['force-destroy'], $form->uuid) }}" onsubmit="return confirm(@js($ui('list.delete_forever_confirm')));">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="editor_version" value="{{ $form->editor_version }}">
                                            <button class="btn igf-btn igf-btn-danger igf-btn-compact" type="submit" @disabled(!$canPermanentlyDelete) title="{{ $ui($canPermanentlyDelete ? 'list.delete_unused_title' : 'list.delete_protected_title') }}"><i class="fa fa-times" aria-hidden="true"></i> {{ $ui('list.delete_forever') }}</button>
                                        </form>
                                        @if(!$canPermanentlyDelete)<small class="afb-action-help">{{ $ui('list.protected_history') }}</small>@endif
                                    @else
                                        <span class="afb-row-meta">{{ $ui('list.archived') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="afb-pagination">{{ $forms->links('vendor.pagination.bootstrap-4') }}</div>
        @endif
    </section>
</main>
