@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<style>
    .seo2-bulk-wrap{overflow:auto;border:1px solid var(--seo-line);border-radius:12px;background:#fff}.seo2-bulk{width:100%;min-width:1580px;border-collapse:separate;border-spacing:0}.seo2-bulk th{position:sticky;z-index:2;top:0;padding:11px;background:#f5f2ef;color:var(--seo-muted);font-size:10px;text-align:left;text-transform:uppercase}.seo2-bulk td{padding:10px;border-top:1px solid var(--seo-line);vertical-align:top}.seo2-bulk input,.seo2-bulk textarea,.seo2-bulk select{width:100%;border:1px solid #d7d0c9;border-radius:7px;padding:8px;background:#fff;font-size:12px}.seo2-bulk input[type=checkbox]{width:auto}.seo2-bulk textarea{min-height:76px;resize:vertical}.seo2-bulk__content{position:sticky;z-index:1;left:0;width:250px;background:#fff}.seo2-bulk__content strong,.seo2-bulk__content small{display:block}.seo2-bulk__content small{margin-top:5px;color:var(--seo-muted)}.seo2-bulk__select{display:flex;align-items:flex-start;gap:8px;margin:0 0 8px;color:var(--seo-ink);font-size:12px;font-weight:800;text-transform:none}.seo2-bulk__select input{margin-top:2px}.seo2-bulk__status{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.seo2-bulk__guidance{margin:8px 0 0;padding-left:17px;color:#6f3c18;font-size:11px;line-height:1.35}.seo2-bulk__image{min-width:260px}.seo2-bulk__image input+input{margin-top:7px}.seo2-bulk__title{min-width:230px}.seo2-bulk__description{min-width:340px}.seo2-bulk__mode{min-width:130px}.seo2-bulk__mode.is-auto~.seo2-bulk__custom input,.seo2-bulk__mode.is-auto~.seo2-bulk__custom textarea{background:#f4f2f0;color:#827b75}.seo2-bulk__quality{display:block;margin-top:5px;color:var(--seo-muted);font-size:11px}.seo2-bulk__quality.is-good{color:#276442}.seo2-bulk__quality.is-warning{color:#8a4516}.seo2-bulk tr.is-unselected>td:not(.seo2-bulk__content){background:#fbfaf9}.seo2-bulk-save{position:sticky;z-index:3;bottom:0;display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-top:1px solid var(--seo-line);background:rgba(255,255,255,.96)}
</style>
<main class="seo2">
    <header class="seo2-head">
        <div><h1>Bulk metadata editor</h1><p>Edit English and Bangla search titles, descriptions, social images, visibility and schema templates in one spreadsheet-style workspace.</p></div>
        <div class="seo2-actions"><a class="seo2-btn" href="{{ route('seo.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Search &amp; Sharing</a><a class="seo2-btn" href="{{ route('seo.bulk.export', array_filter($filters, fn($value) => $value !== 'all' && $value !== '')) }}"><i class="fa fa-download" aria-hidden="true"></i> Export CSV</a></div>
    </header>
    @if(session('message'))<div class="seo2-alert" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>No rows were saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($canEditMetadata)<div class="seo2-alert seo2-alert--warning" role="status"><strong>Read-only SEO access.</strong> You can search and export this sheet, but editing requires the “Edit SEO metadata” permission.</div>@endunless

    <section class="seo2-card" style="margin-bottom:18px">
        <div class="seo2-card__body"><form class="seo2-filter" method="GET" action="{{ route('seo.bulk.index') }}">
            <label class="seo2-field"><span>Find content</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Title or public address"></label>
            <label class="seo2-field"><span>Language</span><select name="locale"><option value="all">English + Bangla</option>@foreach($locales as $option)<option value="{{ $option->id }}" @selected($filters['locale'] === (string)$option->id)>{{ $option->name }}</option>@endforeach</select></label>
            <label class="seo2-field"><span>Content type</span><select name="type">@foreach(['all'=>'All content','page'=>'Pages','category'=>'Categories','event'=>'Events & publications','annual_report'=>'Annual reports','project'=>'Projects','route'=>'Website features'] as $key=>$label)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <button class="seo2-btn" type="submit">Apply filters</button>
        </form></div>
    </section>

    @php($editableTargetCount = collect($targets->items())->filter(fn ($target) => $target['is_editable'] ?? true)->count())
    <form method="POST" action="{{ route('seo.bulk.update') }}" data-seo-bulk-form>
        @csrf @method('PUT')
        <input type="hidden" name="selection_mode" value="explicit">
        <div class="seo2-bulk-wrap">
            <table class="seo2-bulk">
                <thead><tr><th><label class="seo2-bulk__select"><input type="checkbox" data-bulk-select-all @disabled(!$editableTargetCount || !$canEditMetadata)> Select all editable rows</label>Content</th><th>Source</th><th>Search title</th><th>Search description</th><th>Social image</th><th>Visibility</th><th>Schema</th></tr></thead>
                <tbody>
                @forelse($targets as $index => $target)
                    @php($rowEditable = $canEditMetadata && ($target['is_editable'] ?? true))
                    @php($controlLabel = $target['label'].' ('.strtoupper($target['locale']).')')
                    @php($rowIssues = collect($target['issues'])->whereIn('level', ['required', 'recommended'])->take(2))
                    <tr data-bulk-row class="{{ $rowEditable ? 'is-unselected' : '' }}">
                        <td class="seo2-bulk__content">
                            @if($rowEditable)<input type="hidden" name="items[{{ $index }}][selected]" value="0"><label class="seo2-bulk__select"><input type="checkbox" name="items[{{ $index }}][selected]" value="1" data-bulk-select @checked((bool) old("items.$index.selected", false))> Save this row</label>@endif
                            <strong>{{ $target['label'] }}</strong><small>{{ $target['type_label'] }} · {{ strtoupper($target['locale']) }}</small><small>{{ $target['path'] }}</small>
                            <div class="seo2-bulk__status"><span class="seo2-chip {{ $target['status']==='Needs attention' ? 'seo2-chip--danger' : '' }}">{{ $target['status'] }}</span><span class="seo2-chip seo2-chip--neutral">{{ $target['publication']['label'] }}</span></div>
                            @if($rowIssues->isNotEmpty())<ul class="seo2-bulk__guidance">@foreach($rowIssues as $issue)<li>{{ $issue['label'] }}</li>@endforeach</ul>@endif
                            @if($rowEditable)<input type="hidden" name="items[{{ $index }}][owner_type]" value="{{ $target['owner_type'] }}"><input type="hidden" name="items[{{ $index }}][owner_id]" value="{{ $target['owner_id'] }}"><input type="hidden" name="items[{{ $index }}][route_name]" value="{{ $target['route_name'] }}"><input type="hidden" name="items[{{ $index }}][locale]" value="{{ $target['locale'] }}">@if($target['expected_editor_version'] !== null)<input type="hidden" name="items[{{ $index }}][expected_editor_version]" value="{{ $target['expected_editor_version'] }}">@endif @if($target['expected_seo_version'] !== null)<input type="hidden" name="items[{{ $index }}][expected_seo_version]" value="{{ $target['expected_seo_version'] }}">@endif @elseif(!($target['is_editable'] ?? true)) @if($canViewTranslations)<a class="seo2-help" href="{{ $target['edit_url'] }}">Create this translation in Translation Center</a>@else<span class="seo2-help">Ask a Translation Center editor to create this translation.</span>@endif @endif
                        </td>
                        <td class="seo2-bulk__mode {{ $target['stored']['mode']==='auto' ? 'is-auto' : '' }}"><select name="items[{{ $index }}][mode]" data-bulk-mode aria-label="Metadata source for {{ $controlLabel }}" @disabled(!$rowEditable)><option value="auto" @selected($target['stored']['mode']==='auto')>Use page content</option><option value="custom" @selected($target['stored']['mode']==='custom')>Custom wording</option></select><small class="seo2-help">Fallback: {{ Illuminate\Support\Str::limit($target['effective_title'], 55) }}</small></td>
                        <td class="seo2-bulk__title seo2-bulk__custom"><input name="items[{{ $index }}][title]" maxlength="255" value="{{ $target['stored']['title'] }}" placeholder="{{ $target['effective_title'] }}" data-bulk-title data-fallback="{{ $target['effective_title'] }}" aria-label="Search title for {{ $controlLabel }}" @disabled(!$rowEditable)><span class="seo2-bulk__quality" data-bulk-title-quality aria-live="polite"></span></td>
                        <td class="seo2-bulk__description seo2-bulk__custom"><textarea name="items[{{ $index }}][description]" maxlength="500" placeholder="{{ $target['effective_description'] }}" data-bulk-description data-fallback="{{ $target['effective_description'] }}" aria-label="Search description for {{ $controlLabel }}" @disabled(!$rowEditable)>{{ $target['stored']['description'] }}</textarea><span class="seo2-bulk__quality" data-bulk-description-quality aria-live="polite"></span></td>
                        <td class="seo2-bulk__image"><input type="url" name="items[{{ $index }}][image]" value="{{ $target['stored']['image'] }}" placeholder="https://…" aria-label="Social image URL for {{ $controlLabel }}" @disabled(!$rowEditable)><input name="items[{{ $index }}][image_alt]" maxlength="420" value="{{ $target['stored']['image_alt'] }}" placeholder="Describe the image" aria-label="Social image description for {{ $controlLabel }}" @disabled(!$rowEditable)>@if($canViewMedia && $rowEditable)<a class="seo2-help" href="{{ route('media.index', ['type'=>'image']) }}" target="_blank" rel="noopener">Open Media Library</a>@endif</td>
                        <td><input type="hidden" name="items[{{ $index }}][indexable]" value="0" @disabled(!$rowEditable)><label class="seo2-auto" style="margin:0"><input type="checkbox" name="items[{{ $index }}][indexable]" value="1" aria-label="Show {{ $controlLabel }} in search" @checked($target['stored']['indexable']) @disabled(!$rowEditable)><span><strong>Show in search</strong></span></label></td>
                        <td><select name="items[{{ $index }}][schema_template]" aria-label="Schema template for {{ $controlLabel }}" @disabled(!$rowEditable)>@foreach($schemaOptions as $key=>$label)<option value="{{ $key }}" @selected($target['stored']['schema_template']===$key)>{{ $label }}</option>@endforeach</select></td>
                    </tr>
                @empty<tr><td colspan="7"><div class="seo2-empty"><h3>No matching content</h3><p>Clear a filter to see more rows.</p></div></td></tr>@endforelse
                </tbody>
            </table>
            @if($editableTargetCount && $canEditMetadata)<div class="seo2-bulk-save"><span data-bulk-selection-status>Select one or more rows. Unselected rows will not be saved.</span><button class="seo2-btn seo2-btn--primary" type="submit" data-bulk-save disabled><i class="fa fa-save" aria-hidden="true"></i> Save selected rows</button></div>@endif
        </div>
    </form>
    <div style="margin-top:18px">{{ $targets->appends($filters)->links('vendor.pagination.bootstrap-4') }}</div>
</main>
@endsection

@section('custom-js')
<script>
(() => {
    const form = document.querySelector('[data-seo-bulk-form]');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-bulk-row]')];
    const selectAll = form.querySelector('[data-bulk-select-all]');
    const save = form.querySelector('[data-bulk-save]');
    const status = form.querySelector('[data-bulk-selection-status]');
    const selectedInputs = () => rows.map(row => row.querySelector('[data-bulk-select]')).filter(Boolean);
    const quality = (node, value, goodMin, goodMax, kind) => {
        if (!node) return;
        const length = [...String(value || '')].length;
        let message = `${length} characters`;
        let state = '';
        if (length === 0) { message += ` · add a ${kind}`; state = 'is-warning'; }
        else if (length < goodMin) { message += ' · could explain more'; state = 'is-warning'; }
        else if (length <= goodMax) { message += ' · good length'; state = 'is-good'; }
        else { message += ' · may be cut off'; state = 'is-warning'; }
        node.textContent = message;
        node.classList.toggle('is-good', state === 'is-good');
        node.classList.toggle('is-warning', state === 'is-warning');
    };
    const syncRow = row => {
        const mode = row.querySelector('[data-bulk-mode]');
        const title = row.querySelector('[data-bulk-title]');
        const description = row.querySelector('[data-bulk-description]');
        const automatic = mode?.value === 'auto';
        row.querySelector('.seo2-bulk__mode')?.classList.toggle('is-auto', automatic);
        quality(row.querySelector('[data-bulk-title-quality]'), automatic ? title?.dataset.fallback : title?.value, 35, 60, 'search title');
        quality(row.querySelector('[data-bulk-description-quality]'), automatic ? description?.dataset.fallback : description?.value, 120, 160, 'search description');
    };
    const syncSelection = () => {
        const inputs = selectedInputs();
        const count = inputs.filter(input => input.checked).length;
        rows.forEach(row => row.classList.toggle('is-unselected', !(row.querySelector('[data-bulk-select]')?.checked)));
        if (selectAll) {
            selectAll.checked = inputs.length > 0 && count === inputs.length;
            selectAll.indeterminate = count > 0 && count < inputs.length;
        }
        if (save) save.disabled = count === 0;
        if (status) status.textContent = count === 0
            ? 'Select one or more rows. Unselected rows will not be saved.'
            : `${count} selected row${count === 1 ? '' : 's'} will be saved.`;
    };
    rows.forEach(row => {
        row.querySelector('[data-bulk-mode]')?.addEventListener('change', () => syncRow(row));
        row.querySelector('[data-bulk-title]')?.addEventListener('input', () => syncRow(row));
        row.querySelector('[data-bulk-description]')?.addEventListener('input', () => syncRow(row));
        row.querySelector('[data-bulk-select]')?.addEventListener('change', syncSelection);
        syncRow(row);
    });
    selectAll?.addEventListener('change', () => {
        selectedInputs().forEach(input => { input.checked = selectAll.checked; });
        syncSelection();
    });
    form.addEventListener('submit', event => {
        if (selectedInputs().some(input => input.checked)) return;
        event.preventDefault();
        window.alert('Select at least one editable row to save.');
    });
    syncSelection();
})();
</script>
@endsection
