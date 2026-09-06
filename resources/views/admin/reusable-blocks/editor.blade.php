@extends('admin.layouts.master')

@php
    $connectedCount = $connectedPages->count();
    $previewLocale = $block->locale === '*' ? request('locale', config('app.fallback_locale', 'en')) : $block->locale;
    $previewUrl = route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => $previewLocale]);
    $connectedPageIds = $connectedPages->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    $mediaChoices = $mediaAssets->map(fn ($asset) => [
        'url' => $asset->url,
        'name' => $asset->original_name,
        'mime' => $asset->mime_type,
        'alt' => $asset->alt_text,
    ])->values()->all();
@endphp

@section('content')
<style>
    .reuse-workspace{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#68635f;--line:#e2dcd6;max-width:1480px;margin:24px auto;padding:0 22px;color:var(--ink)}
    .reuse-workspace *{box-sizing:border-box}.reuse-workspace__head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px}.reuse-workspace__title{display:flex;min-width:0;align-items:center;gap:14px}.reuse-workspace__back,.reuse-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:7px;padding:9px 14px;border:1px solid var(--line);border-radius:9px;background:#fff;color:#393532;font-weight:800;text-decoration:none!important;cursor:pointer}.reuse-workspace__back{width:44px;flex:0 0 44px;padding:0}.reuse-workspace h1{overflow:hidden;margin:0;font:700 clamp(27px,3vw,42px)/1.08 Georgia,serif;text-overflow:ellipsis;white-space:nowrap}.reuse-workspace__title p{margin:5px 0 0;color:var(--muted);font-size:13px}.reuse-head-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.reuse-btn--primary{border-color:var(--brown);background:var(--brown);color:#fff}.reuse-btn--primary:hover{background:#783300;color:#fff}.reuse-btn:disabled{cursor:not-allowed;opacity:.48}.reuse-grid{display:grid;grid-template-columns:minmax(360px,520px) minmax(480px,1fr);align-items:start;gap:20px}.reuse-card{overflow:hidden;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(35,28,23,.05)}.reuse-card__head{padding:18px 20px;border-bottom:1px solid var(--line);background:#fcfbfa}.reuse-card__head h2{margin:0;font:700 21px Georgia,serif}.reuse-card__head p{margin:5px 0 0;color:var(--muted);font-size:12px;line-height:1.5}.reuse-card__body{padding:20px}.reuse-field{display:grid;gap:6px;margin:0 0 16px}.reuse-field>label,.reuse-field>span,.reuse-field legend{color:#4d4844;font-size:11px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.reuse-field input,.reuse-field textarea,.reuse-field select,.reuse-rich{width:100%;min-height:44px;padding:10px 11px;border:1px solid #d8d0c9;border-radius:8px;background:#fff;color:var(--ink);font:14px/1.45 Arial,sans-serif}.reuse-field textarea{min-height:96px;resize:vertical}.reuse-field input:focus,.reuse-field textarea:focus,.reuse-field select:focus,.reuse-rich:focus{border-color:var(--orange);outline:3px solid rgba(255,117,0,.14)}.reuse-field small{color:var(--muted);font-size:11px;line-height:1.45}.reuse-check{display:flex;min-height:44px;align-items:flex-start;gap:9px;margin:0 0 14px;color:#3e3a37;font-size:13px;line-height:1.45}.reuse-check input{width:19px;height:19px;flex:0 0 auto;margin-top:1px;accent-color:var(--orange)}.reuse-rich-toolbar{display:flex;gap:5px;padding:6px;border:1px solid #d8d0c9;border-bottom:0;border-radius:8px 8px 0 0;background:#f7f4f1}.reuse-rich-toolbar button{min-width:44px;min-height:40px;border:1px solid #ded7d1;border-radius:6px;background:#fff;font-weight:900}.reuse-rich{min-height:130px;border-radius:0 0 8px 8px;overflow:auto}.reuse-repeat{display:grid;gap:10px}.reuse-repeat-item,.reuse-object{padding:13px;border:1px solid var(--line);border-radius:10px;background:#faf9f7}.reuse-repeat-item>summary,.reuse-object>summary{display:flex;min-height:32px;align-items:center;justify-content:space-between;gap:10px;font-weight:850;cursor:pointer}.reuse-repeat-actions{display:flex;flex-wrap:wrap;gap:5px;margin:10px 0}.reuse-repeat-actions button{min-height:40px;padding:7px 10px;border:1px solid var(--line);border-radius:7px;background:#fff;font-size:12px;font-weight:800}.reuse-repeat-actions .is-danger{color:#a32b23}.reuse-section-group{margin:0 0 18px;padding:0;border:0}.reuse-section-group>legend{width:100%;margin:0 0 12px;padding:0;color:#2b2826;font:700 17px Georgia,serif;text-transform:none;letter-spacing:0}.reuse-media-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px}.reuse-media-preview{display:block;width:100%;max-height:180px;margin-top:8px;border-radius:9px;object-fit:contain;background:#f1efed}.reuse-notice{margin:0 0 18px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e;line-height:1.55}.reuse-notice--impact{border-color:#efc18f;background:#fff5e9;color:#66380d}.reuse-notice--safe{border-color:#b9dfc7;background:#f0faf4;color:#235b38}.reuse-notice strong{display:block;margin-bottom:3px}.reuse-impact-list{display:grid;gap:8px;margin:12px 0 0;padding:0;list-style:none}.reuse-impact-item{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;padding:10px;border-radius:8px;background:rgba(255,255,255,.72)}.reuse-impact-item strong,.reuse-impact-item small{display:block}.reuse-impact-item small{margin-top:2px;color:#6a5a4c}.reuse-impact-actions{display:flex;gap:5px}.reuse-impact-actions a{display:inline-flex;min-height:40px;align-items:center;padding:6px 9px;border:1px solid #dfc6ae;border-radius:7px;background:#fff;color:#6f3608;font-size:11px;font-weight:850;text-decoration:none}.reuse-save{position:sticky;z-index:8;bottom:0;margin:20px -20px -20px;padding:15px 20px;border-top:1px solid var(--line);background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}.reuse-save__row{display:flex;align-items:center;justify-content:space-between;gap:12px}.reuse-save-state{color:var(--muted);font-size:12px;font-weight:750}.reuse-save-state.is-error{color:#a32920}.reuse-save-state.is-success{color:#24713f}.reuse-preview-card{position:sticky;top:18px}.reuse-preview-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid var(--line);background:#fcfbfa}.reuse-viewport{display:flex;gap:4px;padding:3px;border-radius:8px;background:#eeece9}.reuse-viewport button{display:grid;width:44px;height:44px;place-items:center;border:0;border-radius:6px;background:transparent;color:#6d6762}.reuse-viewport button.is-active{background:#fff;color:var(--brown);box-shadow:0 1px 5px rgba(0,0,0,.12)}.reuse-preview-shell{display:flex;min-height:690px;padding:14px;justify-content:center;overflow:auto;background:#efeeec}.reuse-preview-frame{width:100%;height:660px;border:1px solid #d7d0ca;border-radius:9px;background:#fff;transition:width .18s}.reuse-preview-frame.is-tablet{width:min(100%,768px)}.reuse-preview-frame.is-mobile{width:min(100%,390px)}.reuse-preview-help{padding:10px 14px;border-top:1px solid var(--line);color:var(--muted);font-size:11px}.reuse-errors{margin:0 0 18px;padding:14px 18px;border:1px solid #e8b8b4;border-radius:10px;background:#fff1f0;color:#8f2b25}.reuse-errors ul{margin:6px 0 0;padding-left:20px}.reuse-read-only{margin-bottom:18px}.reuse-advanced{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.reuse-advanced>summary{min-height:44px;color:var(--brown);font-weight:850;cursor:pointer}.reuse-empty{padding:20px;border:1px dashed #d8d0c9;border-radius:9px;color:var(--muted);text-align:center}.reuse-editor-help{margin:-7px 0 17px;color:var(--muted);font-size:11px;line-height:1.5}
    .reuse-managed-options{display:grid;gap:8px;padding:11px;border:1px solid var(--line);border-radius:9px;background:#faf9f7}.reuse-managed-option{display:flex;min-height:44px;align-items:flex-start;gap:9px;padding:8px;border-radius:7px;background:#fff}.reuse-managed-option input{width:18px;height:18px;flex:0 0 auto;margin-top:2px;accent-color:var(--orange)}.reuse-managed-option strong,.reuse-managed-option small{display:block}.reuse-managed-option small{margin-top:2px}
    @media(max-width:1120px){.reuse-grid{grid-template-columns:1fr}.reuse-preview-card{position:static}.reuse-preview-shell{min-height:570px}.reuse-preview-frame{height:540px}}
    @media(max-width:700px){.reuse-workspace{padding:0 12px}.reuse-workspace__head{align-items:flex-start;flex-direction:column}.reuse-head-actions,.reuse-head-actions .reuse-btn{width:100%}.reuse-workspace h1{white-space:normal}.reuse-card__body{padding:15px}.reuse-save{margin-right:-15px;margin-left:-15px;padding:14px 15px}.reuse-save__row{align-items:stretch;flex-direction:column}.reuse-impact-item{grid-template-columns:1fr}.reuse-preview-shell{padding:8px}.reuse-media-row{grid-template-columns:1fr}}
</style>

<main class="reuse-workspace" id="reusable-section-workspace">
    <header class="reuse-workspace__head">
        <div class="reuse-workspace__title">
            <a class="reuse-workspace__back" href="{{ route('reusable-blocks.index') }}" aria-label="Back to reusable sections"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div>
                <h1>{{ $block->name }}</h1>
                <p>{{ $blockLabel }} &middot; {{ $block->locale === '*' ? 'All languages' : strtoupper($block->locale) }} &middot; Shared section library</p>
            </div>
        </div>
        <div class="reuse-head-actions">
            <a class="reuse-btn" href="{{ $previewUrl }}" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Open full preview</a>
            @if($canEdit && !$editing)<a class="reuse-btn reuse-btn--primary" href="{{ route('reusable-blocks.edit', $block) }}"><i class="fa fa-pencil" aria-hidden="true"></i> Edit section</a>@endif
        </div>
    </header>

    @unless($canEdit && $editing)
        <div class="reuse-notice reuse-read-only" role="status"><strong>Read-only view</strong>You can inspect the content, preview every screen size, and see where this section is used. Your role cannot save library changes from this screen.</div>
    @endunless

    @if($errors->any())
        <div class="reuse-errors" role="alert"><strong>Please review these items:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="reuse-grid">
        <form class="reuse-card" id="reusable-section-form" method="POST" action="{{ route('reusable-blocks.update', $block) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" id="reusable-editor-version" value="{{ (int) $block->editor_version }}">
            <input type="hidden" name="library_editor" value="1">
            <input type="hidden" name="content_json" id="reusable-content-json" value="{{ json_encode((array) $block->content) }}">
            <input type="hidden" name="settings_json" id="reusable-settings-json" value="{{ json_encode((array) $block->settings) }}">
            <input type="hidden" name="expected_page_uuids_json" value="{{ json_encode($mutationPageUuids) }}">
            <input type="hidden" name="expected_connected_page_ids_json" value="{{ json_encode($connectedPageIds) }}">

            <div class="reuse-card__head"><h2>Section content</h2><p>No code or JSON is required. Use ordinary fields; saved changes are shared everywhere listed below.</p></div>
            <div class="reuse-card__body">
                <fieldset class="reuse-section-group">
                    <legend>Library details</legend>
                    <div class="reuse-field"><label for="reusable-name">Library name</label><input id="reusable-name" name="name" maxlength="255" required value="{{ old('name', $block->name) }}" @disabled(!$canEdit || !$editing)><small>Use a name another editor will recognize; this name is not shown to visitors.</small></div>
                    <div class="reuse-field"><label for="reusable-locale">Language</label><select id="reusable-locale" name="locale" required @disabled(!$canEdit || !$editing)><option value="*" @selected(old('locale', $block->locale) === '*')>All languages</option>@foreach($locales as $code => $language)<option value="{{ $code }}" @selected(old('locale', $block->locale) === $code)>{{ $language['name'] ?? strtoupper($code) }} ({{ strtoupper($code) }})</option>@endforeach</select><small>Choose “All languages” only when the exact wording is suitable in every language.</small></div>
                    <input type="hidden" name="is_enabled" value="0">
                    <label class="reuse-check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $block->is_enabled)) @disabled(!$canEdit || !$editing)> <span><strong>Available on connected pages</strong><br>Turn this off to hide the shared content without deleting it.</span></label>
                </fieldset>

                <fieldset class="reuse-section-group">
                    <legend>Visitor-facing content</legend>
                    <p class="reuse-editor-help">Fields and repeatable items below match this {{ strtolower($blockLabel) }}. Use the arrows to change item order.</p>
                    <div id="reusable-content-fields"></div>
                </fieldset>

                @if($canOpenMedia)
                    <p class="reuse-editor-help"><a class="reuse-btn" href="{{ route('media.index') }}" target="_blank" rel="noopener"><i class="fa fa-picture-o" aria-hidden="true"></i> Open Media Library</a> Upload or improve media there, then return here to choose it.</p>
                @endif

                <details class="reuse-advanced" id="reusable-settings-panel" @if(!empty($block->settings)) open @endif>
                    <summary>Advanced display settings</summary>
                    <p class="reuse-editor-help">Most sections do not need these optional display settings.</p>
                    <div id="reusable-settings-fields"></div>
                </details>

                @if($connectedCount > 0)
                    <section class="reuse-notice reuse-notice--impact" aria-labelledby="reusable-impact-title">
                        <strong id="reusable-impact-title">Saving updates {{ $connectedCount }} {{ Str::plural('page', $connectedCount) }}</strong>
                        <span>Published pages can change for visitors immediately. Draft or hidden pages keep the same shared update for their next publication.</span>
                        <ul class="reuse-impact-list">
                            @foreach($connectedPages as $page)
                                <li class="reuse-impact-item">
                                    <span><strong>{{ $page->name }}</strong><small>{{ strtoupper($page->language) }} &middot; {{ str_replace('_', ' ', ucfirst($page->publication_status ?: ($page->status ? 'published' : 'draft'))) }}@if($page->visibility !== 'public') &middot; {{ ucfirst($page->visibility) }}@endif</small></span>
                                    @if($canEditPages || $canPreviewPages)<span class="reuse-impact-actions">@if($canEditPages)<a href="{{ route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => $page->language]) }}">Edit page</a>@endif @if($canPreviewPages)<a href="{{ route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]) }}" target="_blank" rel="noopener">Preview</a>@endif</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                    @if($canEdit && $editing)<label class="reuse-check"><input id="reusable-impact-acknowledged" name="impact_acknowledged" type="checkbox" value="1" required> <span>I reviewed the affected pages and understand this one save updates all {{ $connectedCount }}.</span></label>@endif
                @else
                    <div class="reuse-notice reuse-notice--safe" role="status"><strong>Not used on a page yet</strong>You can edit and preview this library section safely. No visitor page changes until an editor attaches it to a page.</div>
                @endif

                @if($canEdit && $editing)
                    <div class="reuse-save"><div class="reuse-save__row"><span class="reuse-save-state" id="reusable-save-state" role="status" aria-live="polite">No unsaved changes</span><button class="reuse-btn reuse-btn--primary" id="reusable-save-button" type="submit" @if($connectedCount > 0) disabled @endif><i class="fa fa-save" aria-hidden="true"></i> {{ $connectedCount > 0 ? 'Update '.$connectedCount.' '.Str::plural('page', $connectedCount) : 'Save library section' }}</button></div></div>
                @endif
            </div>
        </form>

        <section class="reuse-card reuse-preview-card" aria-labelledby="reusable-preview-heading">
            <div class="reuse-card__head"><h2 id="reusable-preview-heading">Visitor preview</h2><p>This uses the same section renderer as the public website. Save first to refresh content changes here.</p></div>
            <div class="reuse-preview-toolbar">
                <div class="reuse-viewport" role="group" aria-label="Preview size">
                    <button type="button" class="is-active" data-preview-size="desktop" aria-label="Desktop preview" aria-pressed="true"><i class="fa fa-desktop" aria-hidden="true"></i></button>
                    <button type="button" data-preview-size="tablet" aria-label="Tablet preview" aria-pressed="false"><i class="fa fa-tablet" aria-hidden="true"></i></button>
                    <button type="button" data-preview-size="mobile" aria-label="Mobile preview" aria-pressed="false"><i class="fa fa-mobile" aria-hidden="true"></i></button>
                </div>
                <button class="reuse-btn" id="reusable-refresh-preview" type="button"><i class="fa fa-refresh" aria-hidden="true"></i> Refresh preview</button>
            </div>
            <div class="reuse-preview-shell"><iframe class="reuse-preview-frame" id="reusable-preview-frame" src="{{ $previewUrl }}" title="Preview of {{ $block->name }}" sandbox="allow-same-origin allow-scripts"></iframe></div>
            <p class="reuse-preview-help">Links and submissions are disabled inside this safe preview. Use an affected page’s Preview button to review the full page context.</p>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const canEdit = @json($canEdit && $editing);
    const connectedCount = @json($connectedCount);
    const content = @json((array) $block->content);
    const settings = @json((array) $block->settings);
    const defaults = @json((array) $defaultContent);
    const blockType = @json((string) $block->type);
    const configuredChoices = @json($fieldChoices);
    const managedContent = @json($managedContent);
    const mediaAssets = @json($mediaChoices);
    const form = document.getElementById('reusable-section-form');
    const contentRoot = document.getElementById('reusable-content-fields');
    const settingsRoot = document.getElementById('reusable-settings-fields');
    const contentInput = document.getElementById('reusable-content-json');
    const settingsInput = document.getElementById('reusable-settings-json');
    const saveButton = document.getElementById('reusable-save-button');
    const saveState = document.getElementById('reusable-save-state');
    const impactAcknowledged = document.getElementById('reusable-impact-acknowledged');
    const previewFrame = document.getElementById('reusable-preview-frame');
    let dirty = false;
    let busy = false;

    const labels = {
        eyebrow:'Small heading',heading:'Heading',body:'Body text',description:'Description',image:'Image',image_alt:'Image description',poster:'Poster image',caption:'Caption',video_url:'Video link',youtube_url:'YouTube link',image_position:'Media position',primary_label:'Main button text',primary_url:'Main button destination',secondary_label:'Second button text',secondary_url:'Second button destination',report_label:'Report link text',report_url:'Report link destination',link_label:'Link text',link_url:'Link destination',view_all_label:'View-all link text',view_all_url:'View-all destination',item_link_label:'Item link text',empty_state:'Empty-state message',email_label:'Email field label',email_placeholder:'Email placeholder',button_label:'Button text',consent_text:'Consent wording',privacy_label:'Privacy link text',privacy_url:'Privacy page',section_presentation:'Section background style',section_spacing:'Section spacing',content_alignment:'Content alignment',column_count:'Columns',content_source:'Content source',selection_mode:'Item selection',selected_items:'Selected managed content',sort:'Item order',limit:'Maximum items',presentation:'Program card style',layout:'Layout',project_uuid:'Connected project',autoplay:'Rotate automatically',pause_on_hover:'Pause when pointed at',interval:'Time per slide',overlay_opacity:'Image darkness',animation_enabled:'Animate numbers',animation_type:'Animation style',animation_duration:'Animation speed',animation_delay:'Delay between numbers',items:'Items',slides:'Slides',features:'Features',icon:'Icon',html:'Custom HTML',variant:'Special layout',locale:'Language'
    };
    const choiceMap = items => Object.fromEntries((Array.isArray(items) ? items : []).map(item => [String(item.value), String(item.label || item.name || 'Managed item')]));
    const enumChoices = {
        ...configuredChoices,
        category_slug:choiceMap(managedContent.categories),
        tag_slug:choiceMap(managedContent.tags),
        project_uuid:{'':'No connected project',...choiceMap(managedContent.ways_to_give?.projects)},
        media_type:{image:'Image',video:'Uploaded video',youtube:'YouTube video'},
        image_position:{left:'Left',right:'Right'},
        selection_mode:{automatic:'Automatic',manual:'Choose items manually'},
        layout:{single_cta:'Single call to action',card_grid:'Card grid',banner:'Wide banner'},
        animation_type:{count_up:'Count up',fade_up:'Fade upward',pop:'Gentle pop'},
        interval:{4000:'4 seconds',6000:'6 seconds',8000:'8 seconds',12000:'12 seconds'},
        icon:{'':'No icon',people:'People',map:'Location',heart:'Care and support',school:'Education',health:'Health',water:'Water',leaf:'Environment',relief:'Emergency relief',child:'Children',report:'Report',financials:'Finance',security:'Safeguarding',policy:'Policy'}
    };
    const richKeys = new Set(['body','html']);
    const longTextKeys = new Set(['description','caption','consent_text','empty_state']);
    const imageKeys = new Set(['image','poster','photo','logo','thumbnail']);
    const urlKeys = new Set(['url','link_url','primary_url','secondary_url','report_url','view_all_url','privacy_url','video_url','youtube_url']);
    const orderedKeys = ['section_presentation','section_spacing','content_alignment','column_count','eyebrow','heading','body','description','media_type','image','image_alt','video_url','youtube_url','poster','caption','image_position','primary_label','primary_url','secondary_label','secondary_url','report_label','report_url','content_source','selection_mode','selected_items','sort','limit','presentation','layout','project_uuid','item_link_label','view_all_label','view_all_url','empty_state','autoplay','interval','pause_on_hover','animation_enabled','animation_type','animation_duration','animation_delay','items','slides'];

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
    }
    function safeRichHtml(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        const allowed = new Set(['A','B','BLOCKQUOTE','BR','EM','H3','H4','LI','OL','P','STRONG','U','UL']);
        [...template.content.querySelectorAll('*')].forEach(element => {
            if (!allowed.has(element.tagName)) { element.replaceWith(...element.childNodes); return; }
            const href = element.tagName === 'A' ? element.getAttribute('href') || '' : '';
            [...element.attributes].forEach(attribute => element.removeAttribute(attribute.name));
            if (element.tagName === 'A' && /^(?:https?:\/\/|\/(?!\/)|mailto:|tel:)/i.test(href)) element.setAttribute('href', href);
        });
        return template.innerHTML;
    }
    function friendlyLabel(key) {
        if (labels[key]) return labels[key];
        return String(key).replaceAll('_',' ').replace(/\b\w/g, character => character.toUpperCase());
    }
    function pathToken(path) { return escapeHtml(JSON.stringify(path)); }
    function valueAt(root, path) { return path.reduce((value, segment) => value?.[segment], root); }
    function setValue(root, path, value) {
        const parent = path.slice(0,-1).reduce((target, segment) => target[segment], root);
        parent[path[path.length - 1]] = value;
    }
    function orderedObjectKeys(value, template = {}) {
        const keys = [...new Set([...orderedKeys, ...Object.keys(template || {}), ...Object.keys(value || {})])];
        return keys.filter(key => Object.prototype.hasOwnProperty.call(value || {}, key));
    }
    function blankFrom(value, key = '') {
        if (Array.isArray(value)) return [];
        if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([childKey, child]) => [childKey, blankFrom(child, childKey)]));
        if (typeof value === 'boolean') return false;
        if (typeof value === 'number') return key === 'overlay_opacity' ? 64 : 0;
        return '';
    }
    function arrayTemplate(path, value) {
        if (value.length) return value[0];
        const candidate = valueAt(defaults, path);
        if (Array.isArray(candidate) && candidate.length) return candidate[0];
        const key = path[path.length - 1];
        if (key === 'slides') return {eyebrow:'',heading:'New slide',body:'',primary_label:'',primary_url:'',secondary_label:'',secondary_url:'',report_label:'',report_url:'',image:'',overlay_opacity:64};
        if (key === 'items') return {heading:'New item',body:'',image:'',image_alt:'',url:'',link_label:''};
        return '';
    }
    function managedSelectionChoices(value) {
        const selected = new Set((Array.isArray(value) ? value : []).map(String));
        let choices = [];
        if (blockType === 'ways_to_give') {
            choices = [
                ...(managedContent.ways_to_give?.items || []),
                ...(managedContent.ways_to_give?.known_items || []),
            ];
        } else {
            const source = String(content.content_source || '');
            choices = managedContent.items?.[source] || [];
        }
        const unique = new Map(choices.map(item => [String(item.value), item]));
        selected.forEach(token => {
            if (!unique.has(token)) unique.set(token, {value:token,label:'Previously selected item (no longer available)',active:false});
        });
        return [...unique.values()];
    }
    function managedSelectionHtml(rootName, key, value, path) {
        const selected = new Set((Array.isArray(value) ? value : []).map(String));
        const choices = managedSelectionChoices(value);
        const disabled = canEdit ? '' : ' disabled';
        const options = choices.map(item => {
            const token = String(item.value);
            const unavailable = item.active === false;
            const detail = item.destination || item.body || (unavailable ? 'This saved choice is unavailable. Uncheck it before publishing.' : 'Managed content');
            return `<label class="reuse-managed-option"><input type="checkbox" data-managed-root="${rootName}" data-managed-path="${pathToken(path)}" value="${escapeHtml(token)}" ${selected.has(token)?'checked':''}${disabled}><span><strong>${escapeHtml(item.label || 'Managed item')}${unavailable?' — unavailable':''}</strong><small>${escapeHtml(detail)}</small></span></label>`;
        }).join('');
        return `<fieldset class="reuse-field"><legend>${escapeHtml(friendlyLabel(key))}</legend><div class="reuse-managed-options" data-e2e="reusable-managed-items">${options || '<div class="reuse-empty">No published managed items are available. Add or publish content in its main admin area first.</div>'}</div><small>Choose by name. Internal record IDs are never required.</small></fieldset>`;
    }
    function fieldHtml(rootName, root, key, value, path) {
        const label = friendlyLabel(key);
        const token = pathToken(path);
        const disabled = canEdit ? '' : ' disabled';
        if (key === 'selected_items' && Array.isArray(value)) return managedSelectionHtml(rootName, key, value, path);
        if (Array.isArray(value)) return arrayHtml(rootName, root, key, value, path);
        if (value && typeof value === 'object') {
            return `<details class="reuse-object" open><summary>${escapeHtml(label)}</summary><div style="padding-top:12px">${orderedObjectKeys(value).map(child => fieldHtml(rootName, root, child, value[child], [...path,child])).join('')}</div></details>`;
        }
        if (typeof value === 'boolean') return `<label class="reuse-check"><input type="checkbox" data-root="${rootName}" data-path="${token}" ${value?'checked':''}${disabled}> <span>${escapeHtml(label)}</span></label>`;
        const choices = enumChoices[key] || {};
        if (Object.keys(choices).length) {
            const currentChoices = Object.prototype.hasOwnProperty.call(choices, String(value)) ? choices : {...choices,[String(value)]:`Current: ${String(value)}`};
            return `<div class="reuse-field"><label>${escapeHtml(label)}</label><select data-root="${rootName}" data-path="${token}"${disabled}>${Object.entries(currentChoices).map(([choice,choiceLabel]) => `<option value="${escapeHtml(choice)}" ${String(value)===String(choice)?'selected':''}>${escapeHtml(choiceLabel)}</option>`).join('')}</select></div>`;
        }
        if (richKeys.has(key)) {
            return `<div class="reuse-field"><span>${escapeHtml(label)}</span><div class="reuse-rich-toolbar" role="toolbar" aria-label="Format ${escapeHtml(label)}"><button type="button" data-format="bold"${disabled}>B</button><button type="button" data-format="italic"${disabled}><em>I</em></button><button type="button" data-format="insertUnorderedList"${disabled}>• List</button><button type="button" data-format="createLink"${disabled}>Link</button></div><div class="reuse-rich" role="textbox" aria-multiline="true" data-rich-root="${rootName}" data-path="${token}" ${canEdit?'contenteditable="true"':'aria-readonly="true"'}>${safeRichHtml(value)}</div></div>`;
        }
        if (imageKeys.has(key) || key.endsWith('_image')) {
            const imageOptions = mediaAssets.filter(asset => String(asset.mime).startsWith('image/'));
            const preview = value ? `<img class="reuse-media-preview" src="${escapeHtml(value)}" alt="" onerror="this.hidden=true">` : '';
            return `<div class="reuse-field"><label>${escapeHtml(label)}</label><div class="reuse-media-row"><input data-root="${rootName}" data-path="${token}" value="${escapeHtml(value)}" maxlength="2048" placeholder="Choose media or enter a web address"${disabled}><select data-media-root="${rootName}" data-path="${token}" aria-label="Choose ${escapeHtml(label)} from Media Library"${disabled}><option value="">Choose from Media Library…</option>${imageOptions.map(asset=>`<option value="${escapeHtml(asset.url)}">${escapeHtml(asset.name)}</option>`).join('')}</select></div>${preview}</div>`;
        }
        const isLong = longTextKeys.has(key) || String(value || '').length > 180;
        const type = typeof value === 'number' ? 'number' : 'text';
        const input = isLong
            ? `<textarea data-root="${rootName}" data-path="${token}" maxlength="5000"${disabled}>${escapeHtml(value)}</textarea>`
            : `<input type="${type}" data-root="${rootName}" data-path="${token}" value="${escapeHtml(value)}" ${urlKeys.has(key)?'maxlength="2048"':'maxlength="5000"'}${key==='overlay_opacity'?' min="0" max="100"':''}${disabled}>`;
        return `<div class="reuse-field"><label>${escapeHtml(label)}</label>${input}${urlKeys.has(key)?'<small>Choose an internal path such as /donate or a complete https:// address.</small>':''}</div>`;
    }
    function arrayHtml(rootName, root, key, value, path) {
        const label = friendlyLabel(key);
        const items = value.map((item,index) => {
            const itemPath = [...path,index];
            const heading = item && typeof item === 'object' ? (item.heading || item.label || item.name || `${label.slice(0,-1) || 'Item'} ${index+1}`) : `${label.slice(0,-1) || 'Item'} ${index+1}`;
            const fields = item && typeof item === 'object'
                ? orderedObjectKeys(item).map(child => fieldHtml(rootName, root, child, item[child], [...itemPath,child])).join('')
                : fieldHtml(rootName, root, String(index+1), item, itemPath);
            return `<details class="reuse-repeat-item" ${index===0?'open':''}><summary><span>${escapeHtml(heading)}</span></summary>${canEdit?`<div class="reuse-repeat-actions"><button type="button" data-array-action="up" data-root="${rootName}" data-path="${pathToken(path)}" data-index="${index}" ${index===0?'disabled':''}>↑ Earlier</button><button type="button" data-array-action="down" data-root="${rootName}" data-path="${pathToken(path)}" data-index="${index}" ${index===value.length-1?'disabled':''}>↓ Later</button><button class="is-danger" type="button" data-array-action="remove" data-root="${rootName}" data-path="${pathToken(path)}" data-index="${index}">Remove</button></div>`:''}<div>${fields}</div></details>`;
        }).join('');
        return `<div class="reuse-field"><span>${escapeHtml(label)}</span><div class="reuse-repeat">${items || '<div class="reuse-empty">No items yet.</div>'}</div>${canEdit?`<button class="reuse-btn" type="button" data-array-action="add" data-root="${rootName}" data-path="${pathToken(path)}"><i class="fa fa-plus" aria-hidden="true"></i> Add ${escapeHtml(key==='slides'?'slide':'item')}</button>`:''}</div>`;
    }
    function renderRoot(element, rootName, root, template = {}) {
        const keys = orderedObjectKeys(root, template);
        element.innerHTML = keys.length ? keys.map(key => fieldHtml(rootName, root, key, root[key], [key])).join('') : '<div class="reuse-empty">This section has no optional display settings.</div>';
        syncHidden();
    }
    function renderAll() {
        renderRoot(contentRoot, 'content', content, defaults);
        renderRoot(settingsRoot, 'settings', settings, {});
        wireRichToolbars();
    }
    function rootFor(name) { return name === 'settings' ? settings : content; }
    function syncHidden() {
        contentInput.value = JSON.stringify(content);
        settingsInput.value = JSON.stringify(settings);
    }
    function markDirty() {
        if (!canEdit) return;
        dirty = true;
        syncHidden();
        saveState.textContent = 'Unsaved changes';
        saveState.className = 'reuse-save-state';
        updateSaveButton();
    }
    function updateSaveButton() {
        if (!saveButton) return;
        saveButton.disabled = busy || (connectedCount > 0 && !impactAcknowledged?.checked);
    }
    function wireRichToolbars() {
        document.querySelectorAll('[data-format]').forEach(button => button.addEventListener('click', () => {
            const editor = button.closest('.reuse-field')?.querySelector('[contenteditable="true"]');
            if (!editor) return;
            editor.focus();
            if (button.dataset.format === 'createLink') {
                const destination = window.prompt('Enter the link address:', 'https://');
                if (destination) document.execCommand('createLink', false, destination);
            } else document.execCommand(button.dataset.format, false, null);
            editor.dispatchEvent(new Event('input', {bubbles:true}));
        }));
    }

    form.addEventListener('input', event => {
        const control = event.target;
        let refreshManagedChoices = false;
        if (control.matches('[data-root][data-path]')) {
            const root = rootFor(control.dataset.root);
            const path = JSON.parse(control.dataset.path);
            const currentValue = valueAt(root, path);
            const value = control.type === 'checkbox' ? control.checked : (typeof currentValue === 'number' ? Number(control.value) : control.value);
            setValue(root, path, value);
            refreshManagedChoices = path.at(-1) === 'content_source';
        } else if (control.matches('[data-rich-root][data-path]')) {
            setValue(rootFor(control.dataset.richRoot), JSON.parse(control.dataset.path), control.innerHTML);
        }
        markDirty();
        if (refreshManagedChoices) renderAll();
    });
    form.addEventListener('change', event => {
        const control = event.target;
        if (control.matches('[data-managed-root][data-managed-path]')) {
            const selected = valueAt(rootFor(control.dataset.managedRoot), JSON.parse(control.dataset.managedPath));
            const token = String(control.value);
            if (control.checked && !selected.includes(token)) selected.push(token);
            if (!control.checked) {
                const index = selected.indexOf(token);
                if (index !== -1) selected.splice(index, 1);
            }
            markDirty();
        } else if (control.matches('[data-media-root][data-path]') && control.value) {
            setValue(rootFor(control.dataset.mediaRoot), JSON.parse(control.dataset.path), control.value);
            markDirty();
            renderAll();
        }
    });
    form.addEventListener('click', event => {
        const action = event.target.closest('[data-array-action]');
        if (!action) return;
        const rootName = action.dataset.root;
        const path = JSON.parse(action.dataset.path);
        const array = valueAt(rootFor(rootName), path);
        if (!Array.isArray(array)) return;
        const index = Number(action.dataset.index);
        if (action.dataset.arrayAction === 'add') array.push(blankFrom(arrayTemplate(path, array), path[path.length-1]));
        if (action.dataset.arrayAction === 'remove') array.splice(index, 1);
        if (action.dataset.arrayAction === 'up' && index > 0) [array[index-1],array[index]] = [array[index],array[index-1]];
        if (action.dataset.arrayAction === 'down' && index < array.length-1) [array[index+1],array[index]] = [array[index],array[index+1]];
        markDirty();
        renderAll();
    });
    impactAcknowledged?.addEventListener('change', updateSaveButton);
    form.querySelectorAll('input[name="name"],select[name="locale"],input[name="is_enabled"]').forEach(control => control.addEventListener('change', markDirty));

    document.querySelectorAll('[data-preview-size]').forEach(button => button.addEventListener('click', () => {
        const size = button.dataset.previewSize;
        previewFrame.classList.toggle('is-tablet', size === 'tablet');
        previewFrame.classList.toggle('is-mobile', size === 'mobile');
        document.querySelectorAll('[data-preview-size]').forEach(candidate => {
            const active = candidate === button;
            candidate.classList.toggle('is-active', active);
            candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }));
    function refreshPreview() {
        const url = new URL(previewFrame.src, window.location.href);
        url.searchParams.set('_preview', Date.now());
        previewFrame.src = url.toString();
    }
    document.getElementById('reusable-refresh-preview').addEventListener('click', refreshPreview);

    if (canEdit) form.addEventListener('submit', async event => {
        event.preventDefault();
        syncHidden();
        if (!form.reportValidity()) return;
        busy = true;
        updateSaveButton();
        saveState.textContent = 'Saving…';
        saveState.className = 'reuse-save-state';
        try {
            const response = await fetch(form.action, {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:new FormData(form)});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'The section could not be saved.'));
            document.getElementById('reusable-editor-version').value = Number(payload.block?.editor_version || document.getElementById('reusable-editor-version').value);
            if (payload.block?.content) Object.assign(content, payload.block.content);
            if (payload.block?.settings) Object.assign(settings, payload.block.settings);
            dirty = false;
            if (impactAcknowledged) impactAcknowledged.checked = false;
            saveState.textContent = connectedCount ? `Saved. ${connectedCount} ${connectedCount===1?'page':'pages'} updated.` : 'Library section saved.';
            saveState.className = 'reuse-save-state is-success';
            renderAll();
            refreshPreview();
        } catch (error) {
            saveState.textContent = error.message;
            saveState.className = 'reuse-save-state is-error';
        } finally {
            busy = false;
            updateSaveButton();
        }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    renderAll();
    updateSaveButton();
});
</script>
@endsection
