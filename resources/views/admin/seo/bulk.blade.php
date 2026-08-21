@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<style>
    .seo2-bulk-wrap{overflow:auto;border:1px solid var(--seo-line);border-radius:12px;background:#fff}.seo2-bulk{width:100%;min-width:1450px;border-collapse:separate;border-spacing:0}.seo2-bulk th{position:sticky;z-index:2;top:0;padding:11px;background:#f5f2ef;color:var(--seo-muted);font-size:10px;text-align:left;text-transform:uppercase}.seo2-bulk td{padding:10px;border-top:1px solid var(--seo-line);vertical-align:top}.seo2-bulk input,.seo2-bulk textarea,.seo2-bulk select{width:100%;border:1px solid #d7d0c9;border-radius:7px;padding:8px;background:#fff;font-size:12px}.seo2-bulk textarea{min-height:76px;resize:vertical}.seo2-bulk__content{position:sticky;z-index:1;left:0;width:220px;background:#fff}.seo2-bulk__content strong,.seo2-bulk__content small{display:block}.seo2-bulk__content small{margin-top:5px;color:var(--seo-muted)}.seo2-bulk__status{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.seo2-bulk__image{min-width:210px}.seo2-bulk__title{min-width:230px}.seo2-bulk__description{min-width:340px}.seo2-bulk__mode{min-width:130px}.seo2-bulk__mode.is-auto~.seo2-bulk__custom input,.seo2-bulk__mode.is-auto~.seo2-bulk__custom textarea{background:#f4f2f0;color:#827b75}.seo2-bulk-save{position:sticky;z-index:3;bottom:0;display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-top:1px solid var(--seo-line);background:rgba(255,255,255,.96)}
</style>
<main class="seo2">
    <header class="seo2-head">
        <div><h1>Bulk metadata editor</h1><p>Edit English and Bangla search titles, descriptions, social images, visibility and schema templates in one spreadsheet-style workspace.</p></div>
        <div class="seo2-actions"><a class="seo2-btn" href="{{ route('seo.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> SEO dashboard</a><a class="seo2-btn" href="{{ route('seo.bulk.export', array_filter($filters, fn($value) => $value !== 'all' && $value !== '')) }}"><i class="fa fa-download" aria-hidden="true"></i> Export CSV</a></div>
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
        <div class="seo2-bulk-wrap">
            <table class="seo2-bulk">
                <thead><tr><th>Content</th><th>Source</th><th>Search title</th><th>Search description</th><th>Social image URL</th><th>Visibility</th><th>Schema</th></tr></thead>
                <tbody>
                @forelse($targets as $index => $target)
                    @php($rowEditable = $canEditMetadata && ($target['is_editable'] ?? true))
                    <tr data-bulk-row>
                        <td class="seo2-bulk__content">
                            <strong>{{ $target['label'] }}</strong><small>{{ $target['type_label'] }} · {{ strtoupper($target['locale']) }}</small><small>{{ $target['path'] }}</small>
                            <div class="seo2-bulk__status"><span class="seo2-chip {{ $target['status']==='Needs attention' ? 'seo2-chip--danger' : '' }}">{{ $target['status'] }}</span><span class="seo2-chip seo2-chip--neutral">{{ $target['publication']['label'] }}</span></div>
                            @if($rowEditable)<input type="hidden" name="items[{{ $index }}][owner_type]" value="{{ $target['owner_type'] }}"><input type="hidden" name="items[{{ $index }}][owner_id]" value="{{ $target['owner_id'] }}"><input type="hidden" name="items[{{ $index }}][route_name]" value="{{ $target['route_name'] }}"><input type="hidden" name="items[{{ $index }}][locale]" value="{{ $target['locale'] }}">@if($target['expected_editor_version'] !== null)<input type="hidden" name="items[{{ $index }}][expected_editor_version]" value="{{ $target['expected_editor_version'] }}">@endif @if($target['expected_seo_version'] !== null)<input type="hidden" name="items[{{ $index }}][expected_seo_version]" value="{{ $target['expected_seo_version'] }}">@endif @elseif(!($target['is_editable'] ?? true)) @if($canViewTranslations)<a class="seo2-help" href="{{ $target['edit_url'] }}">Create this translation in Translation Center</a>@else<span class="seo2-help">Ask a Translation Center editor to create this translation.</span>@endif @endif
                        </td>
                        <td class="seo2-bulk__mode {{ $target['stored']['mode']==='auto' ? 'is-auto' : '' }}"><select name="items[{{ $index }}][mode]" data-bulk-mode @disabled(!$rowEditable)><option value="auto" @selected($target['stored']['mode']==='auto')>Use page content</option><option value="custom" @selected($target['stored']['mode']==='custom')>Custom wording</option></select><small class="seo2-help">Fallback: {{ Illuminate\Support\Str::limit($target['effective_title'], 55) }}</small></td>
                        <td class="seo2-bulk__title seo2-bulk__custom"><input name="items[{{ $index }}][title]" maxlength="255" value="{{ $target['stored']['title'] }}" placeholder="{{ $target['effective_title'] }}" @disabled(!$rowEditable)></td>
                        <td class="seo2-bulk__description seo2-bulk__custom"><textarea name="items[{{ $index }}][description]" maxlength="500" placeholder="{{ $target['effective_description'] }}" @disabled(!$rowEditable)>{{ $target['stored']['description'] }}</textarea></td>
                        <td class="seo2-bulk__image"><input type="url" name="items[{{ $index }}][image]" value="{{ $target['stored']['image'] }}" placeholder="https://…" @disabled(!$rowEditable)>@if($canViewMedia && $rowEditable)<a class="seo2-help" href="{{ route('media.index', ['type'=>'image']) }}" target="_blank" rel="noopener">Open Media Library</a>@endif</td>
                        <td><input type="hidden" name="items[{{ $index }}][indexable]" value="0" @disabled(!$rowEditable)><label class="seo2-auto" style="margin:0"><input type="checkbox" name="items[{{ $index }}][indexable]" value="1" @checked($target['stored']['indexable']) @disabled(!$rowEditable)><span><strong>Show in search</strong></span></label></td>
                        <td><select name="items[{{ $index }}][schema_template]" @disabled(!$rowEditable)>@foreach($schemaOptions as $key=>$label)<option value="{{ $key }}" @selected($target['stored']['schema_template']===$key)>{{ $label }}</option>@endforeach</select></td>
                    </tr>
                @empty<tr><td colspan="7"><div class="seo2-empty"><h3>No matching content</h3><p>Clear a filter to see more rows.</p></div></td></tr>@endforelse
                </tbody>
            </table>
            @if($editableTargetCount && $canEditMetadata)<div class="seo2-bulk-save"><span>Only the {{ $editableTargetCount }} editable rows on this page will be saved.</span><button class="seo2-btn seo2-btn--primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save this page</button></div>@endif
        </div>
    </form>
    <div style="margin-top:18px">{{ $targets->appends($filters)->links('vendor.pagination.bootstrap-4') }}</div>
</main>
@endsection

@section('custom-js')
<script>
document.querySelectorAll('[data-bulk-row]').forEach(row => {
    const select = row.querySelector('[data-bulk-mode]');
    const sync = () => row.querySelector('.seo2-bulk__mode')?.classList.toggle('is-auto', select?.value === 'auto');
    select?.addEventListener('change', sync); sync();
});
</script>
@endsection
