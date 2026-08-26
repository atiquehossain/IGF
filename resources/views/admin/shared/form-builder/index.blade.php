<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page" aria-labelledby="afb-page-title">
    <header class="afb-page-header">
        <div>
            <p class="afb-eyebrow">{{ $sectionLabel }}</p>
            <h1 id="afb-page-title">Forms and templates</h1>
            <p>Build bilingual application questions, keep reusable templates, and publish immutable form versions.</p>
        </div>
        <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create']) }}">
            <i class="fa fa-plus" aria-hidden="true"></i> Create form
        </a>
    </header>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>The filters could not be applied.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card afb-filter-card" aria-label="Filter forms">
        <form method="get" action="{{ route($routeNames['index']) }}" class="afb-filter-grid">
            <label>
                <span>Search</span>
                <input class="form-control" type="search" name="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="Form name">
            </label>
            <label>
                <span>Kind</span>
                <select class="form-control" name="kind">
                    <option value="all" @selected($filters['kind'] === 'all')>Forms and templates</option>
                    <option value="forms" @selected($filters['kind'] === 'forms')>Forms only</option>
                    <option value="templates" @selected($filters['kind'] === 'templates')>Templates only</option>
                </select>
            </label>
            <label>
                <span>State</span>
                <select class="form-control" name="state">
                    <option value="all" @selected($filters['state'] === 'all')>Any state</option>
                    <option value="draft" @selected($filters['state'] === 'draft')>Has a draft</option>
                    <option value="published" @selected($filters['state'] === 'published')>Has a published version</option>
                </select>
            </label>
            <div class="afb-filter-actions">
                <button class="btn igf-btn igf-btn-secondary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> Apply</button>
                <a class="btn igf-btn igf-btn-tertiary" href="{{ route($routeNames['index']) }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="card" aria-labelledby="afb-list-title">
        <div class="card-header"><strong id="afb-list-title">{{ $forms->total() }} {{ Str::plural('form', $forms->total()) }}</strong></div>
        @if($forms->isEmpty())
            <div class="afb-empty">
                <i class="fa fa-list-alt" aria-hidden="true"></i>
                <h2>No forms found</h2>
                <p>Create a blank form or start from a reusable template.</p>
                <a class="btn igf-btn igf-btn-primary" href="{{ route($routeNames['create']) }}">Create the first form</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table afb-table">
                    <thead><tr><th>Name</th><th>Kind</th><th>Version state</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach($forms as $form)
                        @php
                            $draft = $form->versions->where('state', 'draft')->sortByDesc('version')->first();
                            $published = $form->versions->where('state', 'published')->sortByDesc('version')->first();
                        @endphp
                        <tr>
                            <td><strong>{{ $form->name }}</strong><small class="afb-row-meta">Editor revision {{ $form->editor_version }}</small></td>
                            <td><span class="badge {{ $form->is_template ? 'badge-info' : 'badge-light' }}">{{ $form->is_template ? 'Template' : 'Form' }}</span></td>
                            <td>
                                @if($draft)<span class="badge badge-warning">Draft v{{ $draft->version }}</span>@endif
                                @if($published)<span class="badge badge-success">Published v{{ $published->version }}</span>@endif
                                @if(!$draft && !$published)<span class="badge badge-light">Not available</span>@endif
                            </td>
                            <td><time datetime="{{ $form->updated_at?->toAtomString() }}">{{ $form->updated_at?->diffForHumans() }}</time></td>
                            <td>
                                <div class="afb-row-actions">
                                    <a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route($routeNames['edit'], $form) }}"><i class="fa fa-pencil" aria-hidden="true"></i> Edit</a>
                                    <a class="btn igf-btn igf-btn-tertiary igf-btn-compact" href="{{ route($routeNames['preview'], [$form, 'locale' => 'en']) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> Preview</a>
                                    <details class="afb-duplicate">
                                        <summary class="btn igf-btn igf-btn-tertiary igf-btn-compact"><i class="fa fa-copy" aria-hidden="true"></i> Duplicate</summary>
                                        <form method="post" action="{{ route($routeNames['duplicate'], $form) }}">
                                            @csrf
                                            <label><span>New name</span><input class="form-control" name="name" maxlength="150" required value="Copy of {{ $form->name }}"></label>
                                            <input type="hidden" name="is_template" value="0">
                                            <label class="afb-check"><input type="checkbox" name="is_template" value="1" @checked($form->is_template)> Save as reusable template</label>
                                            <button class="btn igf-btn igf-btn-primary" type="submit">Create copy</button>
                                        </form>
                                    </details>
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
