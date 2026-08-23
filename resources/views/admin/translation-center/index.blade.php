@extends('admin.layouts.master')

@section('content')
@php
    $permission = app(\App\Http\Middleware\Permission::class);
    $admin = auth('admin')->user();
    $canEditTranslations = $permission->allows($admin, 'translations.edit');
    $canManageTranslationStatus = $permission->allows($admin, 'translations.status');
@endphp
<style>
    .translation-center{--tc-orange:#ff7500;--tc-brown:#9c4500;--tc-ink:#191c1d;--tc-muted:#69676b;--tc-line:#e7e1dc;max-width:1500px;margin:0 auto;padding:30px 30px 90px;color:var(--tc-ink);font-family:'Hanken Grotesk',sans-serif}.tc-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}.tc-head h1{margin:0 0 6px;font:700 clamp(36px,4vw,56px)/1.05 'Literata',serif;letter-spacing:-.035em}.tc-head p{max-width:760px;margin:0;color:var(--tc-muted);font-size:15px;line-height:1.55}.tc-state{display:flex;align-items:center;gap:9px;padding:10px 13px;border:1px solid var(--tc-line);border-radius:9px;background:#fff;font-size:12px;font-weight:800;white-space:nowrap}.tc-state i{width:9px;height:9px;border-radius:50%;background:#aaa}.tc-state.is-live i{background:#20a05a;box-shadow:0 0 0 4px #ddf4e7}.tc-overview{display:grid;grid-template-columns:minmax(260px,1fr) repeat(3,minmax(130px,190px));gap:12px;margin-bottom:18px}.tc-progress-card,.tc-stat{border:1px solid var(--tc-line);border-radius:11px;background:#fff;box-shadow:0 6px 20px rgba(25,28,29,.025)}.tc-progress-card{padding:17px 19px}.tc-progress-meta{display:flex;justify-content:space-between;gap:12px;margin-bottom:9px}.tc-progress-meta strong{font:600 17px 'Literata',serif}.tc-progress-meta span{color:var(--tc-brown);font-weight:800}.tc-track{height:9px;overflow:hidden;border-radius:999px;background:#eeeae7}.tc-track span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--tc-orange),#e95f00)}.tc-stat{display:grid;align-content:center;padding:13px 17px}.tc-stat strong{font:700 25px 'Literata',serif}.tc-stat span{color:var(--tc-muted);font-size:11px;font-weight:800;text-transform:uppercase}.tc-workspace{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px}.tc-sidebar{display:grid;align-content:start;gap:13px}.tc-panel{padding:16px;border:1px solid var(--tc-line);border-radius:10px;background:#fff}.tc-panel h2{margin:0 0 12px;font:600 17px 'Literata',serif}.tc-language-flow{display:grid;grid-template-columns:1fr 24px 1fr;align-items:center;gap:5px}.tc-language{padding:10px 8px;border-radius:7px;background:#f5f3f1;text-align:center}.tc-language strong,.tc-language small{display:block}.tc-language small{margin-top:2px;color:var(--tc-muted);font-size:10px}.tc-language-flow>span{text-align:center;color:#8b837e}.tc-filter-links{display:grid;gap:4px}.tc-filter-links a{display:flex;justify-content:space-between;padding:8px 9px;border-radius:7px;color:#555158;font-size:12px;font-weight:700;text-decoration:none}.tc-filter-links a:hover,.tc-filter-links a.is-active{background:#fff1e8;color:var(--tc-brown)}.tc-publish-copy{margin:0 0 12px;color:var(--tc-muted);font-size:12px;line-height:1.45}.tc-publish-button{width:100%;min-height:40px;border:0;border-radius:7px;background:var(--tc-orange);color:#fff;font-size:12px;font-weight:800;cursor:pointer}.tc-publish-button[disabled]{background:#d7d2ce;color:#716d69;cursor:not-allowed}.tc-hide-button{background:#fff3eb;color:var(--tc-brown);border:1px solid #f1c8aa}.tc-main{min-width:0}.tc-tools{display:flex;align-items:end;gap:9px;margin-bottom:11px;padding:13px;border:1px solid var(--tc-line);border-radius:10px;background:#fff}.tc-search{flex:1}.tc-tools label{display:block;margin-bottom:5px;color:#77716d;font-size:10px;font-weight:800;text-transform:uppercase}.tc-tools input,.tc-tools select{width:100%;height:39px;border:1px solid #ddd7d2;border-radius:7px;padding:7px 10px;background:#fff}.tc-search input{min-width:200px}.tc-filter-button{height:39px;padding:0 15px;border:0;border-radius:7px;background:#3d3c3f;color:#fff;font-weight:800}.tc-reset{height:39px;padding:10px 4px;color:var(--tc-brown);font-size:12px;font-weight:800}.tc-sheet-wrap{overflow:auto;border:1px solid var(--tc-line);border-radius:11px;background:#fff;box-shadow:0 8px 25px rgba(25,28,29,.03)}.tc-sheet{width:100%;min-width:940px;border-collapse:separate;border-spacing:0}.tc-sheet th{position:sticky;z-index:3;top:0;padding:11px 12px;border-right:1px solid var(--tc-line);border-bottom:1px solid #d7d0cb;background:#eeeae7;color:#555158;font-size:10px;font-weight:900;letter-spacing:.045em;text-align:left;text-transform:uppercase}.tc-sheet th:first-child{left:0;z-index:4;width:46px;text-align:center}.tc-sheet th:nth-child(2){left:46px;z-index:4;width:230px}.tc-sheet td{vertical-align:top;border-right:1px solid #eee9e5;border-bottom:1px solid #eee9e5;background:#fff}.tc-row-number{position:sticky;left:0;z-index:2;width:46px;padding:15px 8px!important;background:#f7f5f3!important;color:#8b837e;text-align:center;font-size:11px}.tc-context{position:sticky;left:46px;z-index:2;width:230px;padding:13px;background:#fff!important}.tc-context strong{display:block;font-size:12px;line-height:1.35}.tc-context span{display:block;margin-top:5px;color:var(--tc-muted);font-size:10px}.tc-context .tc-requirement{width:max-content;padding:3px 6px;border-radius:999px;background:#edf7f0;color:#287344;font-weight:900;text-transform:uppercase}.tc-context .tc-requirement--optional{background:#f1efed;color:#6d6762}.tc-source,.tc-target{width:34%;min-width:260px}.tc-source-text{max-height:128px;overflow:auto;padding:13px;color:#4a484b;font-size:13px;line-height:1.5;white-space:pre-wrap}.tc-target-cell{position:relative;padding:6px}.tc-target textarea{display:block;width:100%;min-height:92px;resize:vertical;border:1px solid transparent;border-radius:5px;padding:8px 34px 8px 9px;background:#fff;color:var(--tc-ink);font:500 13px/1.5 'Hanken Grotesk',sans-serif}.tc-target textarea:hover{border-color:#ddd5cf}.tc-target textarea:focus{border-color:var(--tc-orange);outline:3px solid rgba(255,117,0,.12)}.tc-copy{position:absolute;top:10px;right:10px;display:grid;width:25px;height:25px;place-content:center;border:1px solid #e3dcd6;border-radius:5px;background:#fff;color:#6d6661;cursor:pointer}.tc-copy:hover{border-color:var(--tc-orange);color:var(--tc-brown)}.tc-status{width:100px;padding:13px 10px}.tc-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 7px;border-radius:999px;font-size:9px;font-weight:900;text-transform:uppercase}.tc-badge:before{content:'';width:6px;height:6px;border-radius:50%}.tc-badge--translated{background:#e5f5eb;color:#247542}.tc-badge--translated:before{background:#26a45d}.tc-badge--missing{background:#fff0e4;color:#9c4500}.tc-badge--missing:before{background:#ff7500}.tc-empty{padding:70px 25px!important;color:var(--tc-muted);text-align:center}.tc-pagination{display:flex;justify-content:flex-end;margin-top:15px}.tc-pagination nav{max-width:100%;overflow-x:auto;padding:3px}.tc-pagination .pagination{display:flex;align-items:center;gap:6px;margin:0;padding:0;list-style:none}.tc-pagination .page-item{margin:0}.tc-pagination .page-link{display:inline-flex;width:38px;height:38px;align-items:center;justify-content:center;padding:0;border:1px solid #ded8d3;border-radius:7px;background:#fff;color:#4d4b49;font-size:13px;font-weight:800;line-height:1;text-decoration:none;box-shadow:none}.tc-pagination .page-link:hover{border-color:var(--tc-orange);background:#fff8f2;color:var(--tc-brown)}.tc-pagination .page-item.active .page-link{border-color:var(--tc-orange);background:var(--tc-orange);color:#fff}.tc-pagination .page-item.disabled .page-link{background:#f3f1ef;color:#aaa5a0;cursor:not-allowed}.tc-savebar{position:fixed;z-index:1025;right:28px;bottom:24px;display:flex;align-items:center;gap:14px;padding:10px 12px 10px 17px;border:1px solid #dcd5cf;border-radius:11px;background:rgba(255,255,255,.96);box-shadow:0 12px 35px rgba(25,28,29,.15);backdrop-filter:blur(8px)}.tc-savebar span{color:var(--tc-muted);font-size:12px;font-weight:700}.tc-save{min-height:40px;padding:0 18px;border:0;border-radius:8px;background:var(--tc-orange);color:#fff;font-size:12px;font-weight:900;cursor:pointer}.tc-save:disabled{background:#bdb8b4}.tc-help{margin-top:12px;padding:11px 13px;border-radius:8px;background:#f7f4f1;color:#67615d;font-size:11px;line-height:1.45}.tc-alert{margin-bottom:13px;padding:12px 14px;border:1px solid #efbcae;border-radius:8px;background:#fff0ed;color:#942b1f;font-size:13px}.tc-dirty{background:#fffaf5!important}@media(max-width:1100px){.tc-workspace{grid-template-columns:1fr}.tc-sidebar{grid-template-columns:repeat(2,minmax(0,1fr))}.tc-overview{grid-template-columns:1fr repeat(3,130px)}}@media(max-width:760px){.translation-center{padding:23px 12px 95px}.tc-head{flex-direction:column}.tc-overview{grid-template-columns:1fr 1fr}.tc-progress-card{grid-column:1/-1}.tc-sidebar{grid-template-columns:1fr}.tc-tools{align-items:stretch;flex-direction:column}.tc-savebar{right:12px;bottom:12px;left:12px;justify-content:space-between}.tc-state{white-space:normal}}
    .tc-read-only{margin-bottom:18px;padding:13px 15px;border:1px solid #e3d8cf;border-left:4px solid var(--tc-orange);border-radius:8px;background:#fff8f2;color:#5e554f;font-size:13px;line-height:1.5}.tc-read-only strong{color:var(--tc-ink)}.tc-target textarea[readonly]{border-color:#ebe5e0;background:#f7f5f3;color:#67615d;cursor:default}.tc-target textarea[readonly]:hover{border-color:#ebe5e0}.tc-target textarea[readonly]:focus{border-color:#ebe5e0;outline:none}
    .tc-publish-button,.tc-save,.tc-pagination .page-item.active .page-link{border-color:#9c4500;background:#9c4500;color:#fff}
    .tc-publish-button:hover,.tc-publish-button:focus-visible,.tc-save:hover,.tc-save:focus-visible{background:#783300}
    .tc-publish-button:focus-visible,.tc-save:focus-visible{outline:3px solid rgba(156,69,0,.28);outline-offset:2px}
    .tc-publish-button[disabled],.tc-save:disabled{background:#d7d2ce;color:#514e4b}
    .translation-center,.tc-workspace,.tc-sidebar,.tc-main,.tc-panel,.tc-tools,.tc-sheet-wrap{min-width:0;max-width:100%}.tc-filter-links a,.tc-publish-button,.tc-tools input,.tc-tools select,.tc-filter-button,.tc-reset,.tc-save{min-height:44px}.tc-filter-links a{align-items:center}.tc-copy{width:44px;min-width:44px;height:44px;min-height:44px}.tc-target textarea{padding-right:52px}.tc-pagination .page-link{width:44px;min-width:44px;height:44px;min-height:44px}.tc-pagination nav{overscroll-behavior-inline:contain;-webkit-overflow-scrolling:touch}
    @media(max-width:760px){.tc-tools input,.tc-tools select,.tc-filter-button,.tc-reset{width:100%}.tc-search input{min-width:0}.tc-savebar{max-width:calc(100vw - 24px);gap:8px}.tc-savebar span{min-width:0}.tc-save{flex:0 0 auto}.tc-sheet-wrap{overscroll-behavior-inline:contain;-webkit-overflow-scrolling:touch}}
</style>

<main class="translation-center">
    <header class="tc-head">
        <div>
            <h1>Translation Center</h1>
            <p>Translate the whole website from one spreadsheet. English stays on the left; enter the approved Bangla wording on the right. Only text that is currently public in English must be complete before Bangla can go live.</p>
        </div>
        <div class="tc-state {{ $targetLanguage?->is_enabled ? 'is-live' : '' }}"><i aria-hidden="true"></i>{{ $targetLanguage?->is_enabled ? 'Bangla is live' : 'Bangla is hidden' }}</div>
    </header>

    @if($errors->any())<div class="tc-alert" role="alert">{{ $errors->first() }}</div>@endif

    @if(!$canEditTranslations || !$canManageTranslationStatus)
        <div class="tc-read-only" role="status">
            @if(!$canEditTranslations && !$canManageTranslationStatus)
                <strong>Read-only access.</strong> You can review, search, and filter translations, but your role cannot change wording or show and hide Bangla on the website.
            @elseif(!$canEditTranslations)
                <strong>Translation wording is read only.</strong> You can manage whether Bangla is visible, but an editor must change translated wording.
            @else
                <strong>Language visibility is read only.</strong> You can edit translated wording, but your role cannot show or hide Bangla on the website.
            @endif
        </div>
    @endif

    <section class="tc-overview" aria-label="Translation progress">
        <div class="tc-progress-card">
            <div class="tc-progress-meta"><strong>Bangla completion</strong><span>{{ $summary['percent'] }}%</span></div>
            <div class="tc-track" role="progressbar" aria-label="Bangla translation completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $summary['percent'] }}"><span style="width:{{ $summary['percent'] }}%"></span></div>
        </div>
        <div class="tc-stat"><strong>{{ number_format($summary['total']) }}</strong><span>Required public rows</span></div>
        <div class="tc-stat"><strong>{{ number_format($summary['translated']) }}</strong><span>Required translated</span></div>
        <div class="tc-stat"><strong>{{ number_format($summary['missing']) }}</strong><span>Required missing</span></div>
    </section>

    <div class="tc-workspace">
        <aside class="tc-sidebar" aria-label="Translation controls">
            <section class="tc-panel">
                <h2>Languages</h2>
                <div class="tc-language-flow">
                    <div class="tc-language"><strong>EN</strong><small>English source</small></div><span aria-hidden="true">→</span>
                    <div class="tc-language"><strong>বাং</strong><small>Bangla translation</small></div>
                </div>
                <div class="tc-help">English is the master copy. Draft, private, scheduled-later and inactive text stays available for advance translation, but does not block publishing. {{ number_format($summary['optional']) }} optional {{ $summary['optional'] === 1 ? 'row is' : 'rows are' }} currently excluded from completion.</div>
            </section>

            <section class="tc-panel">
                <h2>Show text from</h2>
                <nav class="tc-filter-links" aria-label="Translation areas">
                    <a class="{{ $group === '' ? 'is-active' : '' }}" href="{{ route('translations.index', array_filter(['status' => $status, 'search' => $search])) }}"><span>Everything</span><span>→</span></a>
                    @foreach($groups as $key => $label)
                        <a class="{{ $group === $key ? 'is-active' : '' }}" href="{{ route('translations.index', array_filter(['group' => $key, 'status' => $status, 'search' => $search])) }}"><span>{{ $label }}</span><span>→</span></a>
                    @endforeach
                </nav>
            </section>

            <section class="tc-panel">
                <h2>Website language</h2>
                @if($canManageTranslationStatus)
                    @if($targetLanguage?->is_enabled)
                        <p class="tc-publish-copy">Visitors can currently choose Bangla. Hiding it will not delete any translated text.</p>
                        <form method="POST" action="{{ route('translations.toggle') }}">@csrf @method('PUT')<input type="hidden" name="source_locale" value="{{ $sourceLocale }}"><input type="hidden" name="target_locale" value="{{ $targetLocale }}"><input type="hidden" name="enabled" value="0"><button class="tc-publish-button tc-hide-button" type="submit">Hide Bangla</button></form>
                    @else
                        <p class="tc-publish-copy">Complete every required row from currently public English content before making Bangla visible to visitors.</p>
                        <form method="POST" action="{{ route('translations.toggle') }}">@csrf @method('PUT')<input type="hidden" name="source_locale" value="{{ $sourceLocale }}"><input type="hidden" name="target_locale" value="{{ $targetLocale }}"><input type="hidden" name="enabled" value="1"><button class="tc-publish-button" type="submit" @disabled($summary['missing'] > 0)>Enable Bangla</button></form>
                    @endif
                @else
                    <p class="tc-publish-copy">Bangla is currently <strong>{{ $targetLanguage?->is_enabled ? 'visible to visitors' : 'hidden from visitors' }}</strong>.</p>
                    <div class="tc-help"><strong>Language visibility is read only.</strong> Ask an administrator with publishing access if this setting needs to change.</div>
                @endif
            </section>
        </aside>

        <section class="tc-main" aria-label="Translation spreadsheet">
            <form class="tc-tools" method="GET" action="{{ route('translations.index') }}">
                <input type="hidden" name="group" value="{{ $group }}">
                <div class="tc-search"><label for="translation-search">Find any text</label><input id="translation-search" name="search" value="{{ $search }}" placeholder="Search pages, buttons, menus, or wording…"></div>
                <div><label for="translation-status">Status</label><select id="translation-status" name="status"><option value="">All rows</option><option value="missing" @selected($status === 'missing')>Missing required</option><option value="translated" @selected($status === 'translated')>Translated only</option><option value="optional" @selected($status === 'optional')>Optional / unpublished</option></select></div>
                <button class="tc-filter-button" type="submit">Apply</button>
                <a class="tc-reset btn igf-btn igf-btn-tertiary" href="{{ route('translations.index') }}"><i class="fa fa-undo" aria-hidden="true"></i> Reset</a>
            </form>

            @if($canEditTranslations)
                <form id="translation-form" method="POST" action="{{ route('translations.update', request()->query()) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="source_locale" value="{{ $sourceLocale }}"><input type="hidden" name="target_locale" value="{{ $targetLocale }}">
            @else
                <div id="translation-form" data-read-only="true">
            @endif
                <div class="tc-sheet-wrap table-responsive" role="region" aria-label="Scrollable translation spreadsheet" tabindex="0">
                    <table class="tc-sheet">
                        <thead><tr><th scope="col">#</th><th scope="col">Page / location</th><th scope="col">English</th><th scope="col">বাংলা</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @forelse($rows as $row)
                            @php($index = $loop->index)
                            <tr data-translation-row>
                                <td class="tc-row-number">{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="tc-context"><strong>{{ $row['context'] }}</strong><span>{{ $row['field'] }}</span><span class="tc-requirement {{ $row['required'] ? '' : 'tc-requirement--optional' }}">{{ $row['required'] ? 'Required now' : 'Optional until public' }}</span></td>
                                <td class="tc-source"><div class="tc-source-text" data-source-text>{{ strip_tags($row['source']) }}</div></td>
                                <td class="tc-target"><div class="tc-target-cell">
                                    @if($canEditTranslations)
                                        <input type="hidden" name="translations[{{ $index }}][key]" value="{{ $row['key'] }}">
                                        <input type="hidden" name="translations[{{ $index }}][precondition]" value="{{ $row['precondition'] }}">
                                    @endif
                                    <textarea @if($canEditTranslations) name="translations[{{ $index }}][value]" @endif aria-label="Bangla translation for {{ $row['context'] }}, {{ $row['field'] }}" data-original="{{ $row['target'] }}" @readonly(!$canEditTranslations)>{{ $row['target'] }}</textarea>
                                    @if($canEditTranslations)<button class="tc-copy" type="button" title="Copy English into this cell" aria-label="Copy English into the Bangla cell">⧉</button>@endif
                                </div></td>
                                <td class="tc-status"><span class="tc-badge tc-badge--{{ $row['status'] }}" data-row-status>{{ $row['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="tc-empty" colspan="5"><strong>No translation rows match these filters.</strong><br>Try clearing the search or choosing another area.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($canEditTranslations && $rows->count())<div class="tc-savebar"><span data-save-message>Everything on this page is saved</span><button class="tc-save" type="submit" data-save-button disabled>Save translations</button></div>@endif
            @if($canEditTranslations)</form>@else</div>@endif
            <div class="tc-pagination">{{ $rows->onEachSide(1)->links('vendor.pagination.bootstrap-4') }}</div>
        </section>
    </div>
</main>
@endsection

@section('custom-js')
<script>
(() => {
    const form = document.getElementById('translation-form');
    if (!form || form.dataset.readOnly === 'true') return;
    const fields = [...form.querySelectorAll('textarea')];
    const message = form.querySelector('[data-save-message]');
    const saveButton = form.querySelector('[data-save-button]');
    const update = (field) => {
        const row = field.closest('[data-translation-row]');
        const changed = field.value !== field.dataset.original;
        row?.classList.toggle('tc-dirty', changed);
        const badge = row?.querySelector('[data-row-status]');
        if (badge) {
            const complete = field.value.trim().length > 0;
            badge.textContent = complete ? 'translated' : 'missing';
            badge.className = `tc-badge tc-badge--${complete ? 'translated' : 'missing'}`;
        }
        const dirty = fields.filter(item => item.value !== item.dataset.original).length;
        if (message) message.textContent = dirty ? `${dirty} unsaved ${dirty === 1 ? 'change' : 'changes'}` : 'Everything on this page is saved';
        if (saveButton) saveButton.disabled = dirty === 0;
    };
    fields.forEach(field => field.addEventListener('input', () => update(field)));
    form.querySelectorAll('.tc-copy').forEach(button => button.addEventListener('click', () => {
        const row = button.closest('[data-translation-row]');
        const field = row.querySelector('textarea');
        field.value = row.querySelector('[data-source-text]').textContent.trim();
        field.focus();
        update(field);
    }));
    window.addEventListener('beforeunload', event => {
        if (fields.some(field => field.value !== field.dataset.original)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    form.addEventListener('submit', event => {
        const dirtyFields = fields.filter(field => field.value !== field.dataset.original);
        if (dirtyFields.length === 0) {
            event.preventDefault();
            return;
        }
        fields.forEach(field => {
            const changed = field.value !== field.dataset.original;
            field.disabled = !changed;
            field.closest('[data-translation-row]')?.querySelectorAll('input[type="hidden"]')
                .forEach(input => input.disabled = !changed);
            if (changed) field.dataset.original = field.value;
        });
    });
})();
</script>
@endsection
