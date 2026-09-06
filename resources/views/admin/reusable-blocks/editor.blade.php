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
    .reuse-layout-guide{margin:0 0 16px;padding:13px 14px;border-left:4px solid var(--orange);border-radius:8px;background:#fff6ed;color:#5d493b;font-size:12px;line-height:1.55}.reuse-layout-guide strong{display:block;margin-bottom:3px;color:var(--brown);font-size:13px}.reuse-layout-row{margin:0 0 14px;border:1px solid #dcd3cb;border-radius:11px;background:#f8f6f4}.reuse-layout-row__head,.reuse-layout-element__head{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:10px}.reuse-layout-row__title strong,.reuse-layout-row__title small{display:block}.reuse-layout-row__title strong{font-size:13px}.reuse-layout-row__title small{margin-top:3px;color:var(--muted);font-size:10px}.reuse-layout-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:4px}.reuse-layout-actions button{display:grid;min-width:44px;min-height:44px;padding:6px;place-items:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);cursor:pointer}.reuse-layout-actions button:disabled{cursor:not-allowed;opacity:.35}.reuse-layout-actions button[data-layout-row-action=remove],.reuse-layout-actions button[data-layout-element-action=remove]{color:#a32b23}.reuse-layout-row__body{padding:13px;border-top:1px solid var(--line)}.reuse-layout-row__settings{display:grid;grid-template-columns:1fr 1fr;gap:0 9px}.reuse-layout-row__settings .reuse-field:first-child{grid-column:1/-1}.reuse-layout-columns{display:grid;gap:11px}.reuse-layout-column{padding:11px;border:1px dashed #d1c5ba;border-radius:9px;background:#fff}.reuse-layout-column__head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}.reuse-layout-column__head strong{font-size:12px}.reuse-layout-column__head small{color:var(--muted);font-size:10px}.reuse-layout-element{margin-bottom:9px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.reuse-layout-element__head{align-items:flex-start;padding:7px 8px}.reuse-layout-element__title{display:flex;min-width:0;align-items:center;gap:7px;padding-top:8px;font-size:11px;font-weight:850}.reuse-layout-element__title>span{overflow:hidden;text-overflow:ellipsis}.reuse-layout-element__title i{display:grid;width:29px;height:29px;flex:0 0 auto;place-items:center;border-radius:6px;background:#fff0e4;color:var(--brown)}.reuse-layout-element details>summary{display:flex;min-height:44px;align-items:center;padding:8px 10px;border-top:1px solid var(--line);color:var(--brown);font-size:11px;font-weight:850;cursor:pointer;list-style:none}.reuse-layout-element details>summary::-webkit-details-marker{display:none}.reuse-layout-element details>summary::after{margin-left:auto;content:'+';font-size:17px}.reuse-layout-element details[open]>summary::after{content:'\2212'}.reuse-layout-element__body{padding:11px;border-top:1px solid var(--line)}.reuse-layout-element__note{margin:0;color:var(--muted);font-size:11px;line-height:1.5}.reuse-layout-add{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;margin-top:9px}.reuse-layout-add select{min-width:0;min-height:44px;padding:8px;border:1px solid #d8d0c9;border-radius:7px;background:#fff}.reuse-layout-add-row{width:100%}.reuse-layout-empty{margin:0 0 9px;padding:14px 9px;border:1px dashed #d8d0c9;border-radius:8px;color:var(--muted);font-size:11px;text-align:center}.reuse-layout-limit{margin:9px 0 0;color:var(--muted);font-size:11px;text-align:center}.reuse-layout-status{min-height:20px;margin:0 0 8px;color:#8b3d00;font-size:11px;font-weight:750}.reuse-layout-sr{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(1px,1px,1px,1px)!important;white-space:nowrap!important}.reuse-layout-media-select{margin-top:7px}.reuse-layout-thumbnail{display:block;width:100%;max-height:150px;margin-top:8px;border-radius:8px;object-fit:contain;background:#f0eeeb}
    @media(max-width:1120px){.reuse-grid{grid-template-columns:1fr}.reuse-preview-card{position:static}.reuse-preview-shell{min-height:570px}.reuse-preview-frame{height:540px}}
    @media(max-width:700px){.reuse-workspace{padding:0 12px}.reuse-workspace__head{align-items:flex-start;flex-direction:column}.reuse-head-actions,.reuse-head-actions .reuse-btn{width:100%}.reuse-workspace h1{white-space:normal}.reuse-card__body{padding:15px}.reuse-save{margin-right:-15px;margin-left:-15px;padding:14px 15px}.reuse-save__row{align-items:stretch;flex-direction:column}.reuse-impact-item{grid-template-columns:1fr}.reuse-preview-shell{padding:8px}.reuse-media-row,.reuse-layout-row__settings,.reuse-layout-add{grid-template-columns:1fr}.reuse-layout-row__settings .reuse-field:first-child{grid-column:auto}.reuse-layout-row__head,.reuse-layout-element__head{align-items:stretch;flex-direction:column}.reuse-layout-actions{justify-content:flex-start}.reuse-layout-add .reuse-btn{width:100%}}
    .reuse-layout-clear-image{width:max-content;margin-top:7px;color:#a32b23}
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
    const designDefaults = @json($designDefaults);
    const columnCountBlockTypes = new Set((@json($columnCountBlockTypes) || []).map(String));
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
    let layoutOpenPath = null;

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
    const layoutGlobalDesignChoices = Object.freeze({
        section_presentation: enumChoices.section_presentation,
        section_spacing: enumChoices.section_spacing,
        content_alignment: enumChoices.content_alignment,
        column_count: enumChoices.column_count,
    });
    const richKeys = new Set(['body','html']);
    const longTextKeys = new Set(['description','caption','consent_text','empty_state']);
    const imageKeys = new Set(['image','poster','photo','logo','thumbnail']);
    const urlKeys = new Set(['url','link_url','primary_url','secondary_url','report_url','view_all_url','privacy_url','video_url','youtube_url']);
    const orderedKeys = ['section_presentation','section_spacing','content_alignment','column_count','eyebrow','heading','body','description','media_type','image','image_alt','video_url','youtube_url','poster','caption','image_position','primary_label','primary_url','secondary_label','secondary_url','report_label','report_url','content_source','selection_mode','selected_items','sort','limit','presentation','layout','project_uuid','item_link_label','view_all_label','view_all_url','empty_state','autoplay','interval','pause_on_hover','animation_enabled','animation_type','animation_duration','animation_delay','items','slides'];
    const layoutConfig = {...@json(config('page-builder.layout', [])), ...(managedContent.layout || {})};
    const fallbackLayoutPresets = Object.freeze({
        full:{label:'One column',columns:1},
        halves:{label:'Two equal columns',columns:2},
        thirds:{label:'Three equal columns',columns:3},
        quarter:{label:'Four equal columns',columns:4},
        third_two_thirds:{label:'One third / two thirds',columns:2},
        two_thirds_third:{label:'Two thirds / one third',columns:2},
    });
    const configuredLayoutLabel = (configured, fallback) => {
        const label = typeof configured === 'string' ? configured : configured?.label;
        return typeof label === 'string' && label.trim() ? label : fallback;
    };
    const layoutChoiceMap = (configured, fallback) => Object.fromEntries(Object.entries(fallback).map(([token,label]) => [token, configuredLayoutLabel(configured?.[token], label)]));
    const layoutPresets = Object.fromEntries(Object.entries(fallbackLayoutPresets).map(([token,preset]) => {
        const configured = layoutConfig.presets?.[token];
        const columns = Number(typeof configured === 'object' ? configured.columns : 0);
        return [token,{label:configuredLayoutLabel(configured,preset.label),columns:Number.isInteger(columns)&&columns>=1&&columns<=4?columns:preset.columns}];
    }));
    const layoutPresetChoices = Object.fromEntries(Object.entries(layoutPresets).map(([token,preset]) => [token,preset.label]));
    const layoutWidthChoices = layoutChoiceMap(layoutConfig.widths,{standard:'Standard page width',wide:'Wide',full:'Full width'});
    const layoutBackgroundChoices = layoutChoiceMap(layoutConfig.backgrounds,{default:'Default',soft:'Soft neutral',accent:'Accent',dark:'Dark'});
    const layoutSpacingChoices = layoutChoiceMap(layoutConfig.spacings,{compact:'Compact',standard:'Standard',generous:'Generous'});
    const layoutHeadingChoices = layoutChoiceMap(layoutConfig.heading_levels,{h2:'Large heading',h3:'Medium heading',h4:'Small heading'});
    const layoutVideoSourceChoices = layoutChoiceMap(layoutConfig.video_source_types,{upload:'Uploaded video',youtube:'YouTube video'});
    const layoutButtonStyleChoices = layoutChoiceMap(layoutConfig.button_styles,{primary:'Primary',secondary:'Secondary',text:'Text link'});
    const layoutSpacerSizeChoices = layoutChoiceMap(layoutConfig.spacer_sizes,{small:'Small',medium:'Medium',large:'Large'});
    const layoutCatalogGroups = Object.entries(layoutConfig.element_catalog || {}).map(([token,group]) => ({
        token,
        label:String(group?.label || token.replaceAll('_',' ')),
        description:String(group?.description || ''),
        icon:String(group?.icon || 'fa-square-o'),
        elements:(Array.isArray(group?.elements) ? group.elements : []).filter(element => element && element.mode === 'static'),
    })).filter(group => group.elements.length);
    const layoutElementCatalog = Object.fromEntries(layoutCatalogGroups.flatMap(group => group.elements.map(element => [String(element.type), element])));
    const layoutElementChoices = Object.fromEntries(Object.entries(layoutElementCatalog).map(([type,element]) => [type,String(element.label || type)]));
    const layoutElementIcons = Object.freeze(Object.fromEntries(Object.entries(layoutElementCatalog).map(([type,element]) => [type,String(element.icon || 'fa-square-o')])));
    const imageMimeTypes = new Set(['image/avif','image/gif','image/jpeg','image/png','image/webp']);
    const documentMimeTypes = new Set([
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
    const mediaLibraryUrl = @json($canOpenMedia ? route('media.index') : null);

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
    function safeLayoutRichHtml(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        const allowed = new Set(['A','B','BLOCKQUOTE','BR','EM','H3','H4','LI','OL','P','STRONG','U','UL']);
        [...template.content.querySelectorAll('*')].forEach(element => {
            if (!allowed.has(element.tagName)) { element.replaceWith(...element.childNodes); return; }
            const href = element.tagName === 'A' ? String(element.getAttribute('href') || '').trim() : '';
            [...element.attributes].forEach(attribute => element.removeAttribute(attribute.name));
            if (element.tagName !== 'A' || !href || /[\\\u0000-\u0020\u007f]/.test(href)) return;
            const internal = href.startsWith('#') || (href.startsWith('/') && !href.startsWith('//'));
            let secureExternal = false;
            if (!internal) {
                try {
                    const parsed = new URL(href);
                    secureExternal = parsed.protocol === 'https:' && !!parsed.hostname && !parsed.username && !parsed.password && (!parsed.port || parsed.port === '443');
                } catch (error) {
                    secureExternal = false;
                }
            }
            if (!internal && !secureExternal) return;
            element.setAttribute('href', href);
            if (secureExternal) {
                element.setAttribute('target', '_blank');
                element.setAttribute('rel', 'noopener noreferrer');
            }
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
    const layoutChoice = (value, choices, fallback) => Object.prototype.hasOwnProperty.call(choices, String(value || '')) ? String(value) : fallback;
    const layoutColumnCount = preset => layoutPresets[layoutChoice(preset,layoutPresetChoices,'full')].columns;
    const positiveLayoutLimit = (value,fallback) => {
        const parsed = Math.floor(Number(value));
        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    };
    const layoutStorageLimits = Object.freeze({
        payloadBytes:positiveLayoutLimit(layoutConfig.limits?.payload_bytes,524288),
        rows:positiveLayoutLimit(layoutConfig.limits?.rows,12),
        columns:positiveLayoutLimit(layoutConfig.limits?.columns,4),
        elementsPerColumn:positiveLayoutLimit(layoutConfig.limits?.elements_per_column,12),
    });
    const layoutColumnElementLimit = layoutStorageLimits.elementsPerColumn;
    function layoutElementInstanceLimit(type) {
        return Math.max(1,Math.min(layoutColumnElementLimit,Number(layoutElementCatalog[type]?.safe_bounds?.max_instances_per_column)||layoutColumnElementLimit));
    }
    const layoutColumnElements = column => Array.isArray(column?.elements) ? column.elements : [];
    const layoutColumnTypeCount = (column,type) => layoutColumnElements(column).filter(element=>String(element?.type||'')===String(type||'')).length;
    function layoutColumnCapacityIssue(column,type,additional=1) {
        const amount = Math.max(0,Math.floor(Number(additional)||0));
        if (layoutColumnElements(column).length+amount>layoutColumnElementLimit) return 'total';
        if (layoutColumnTypeCount(column,type)+amount>layoutElementInstanceLimit(type)) return 'type';
        return null;
    }
    const layoutColumnCanAccept = (column,type,additional=1) => layoutColumnCapacityIssue(column,type,additional)===null;
    function layoutColumnIsWithinCapacity(column) {
        const elements = layoutColumnElements(column);
        if (elements.length>layoutColumnElementLimit) return false;
        const counts = new Map();
        elements.forEach(element=>{const type=String(element?.type||'');counts.set(type,(counts.get(type)||0)+1)});
        return [...counts].every(([type,count])=>count<=layoutElementInstanceLimit(type));
    }
    function layoutCapacityForType(column,type) {
        return Math.max(0,Math.min(
            layoutColumnElementLimit-layoutColumnElements(column).length,
            layoutElementInstanceLimit(type)-layoutColumnTypeCount(column,type)
        ));
    }
    function layoutCollapseAssignments(columns,displaced) {
        if (!displaced.length) return [];
        const types=[...new Set(displaced.map(element=>String(element?.type||'')))].sort();
        const sourceNode=0,typeOffset=1,columnOffset=typeOffset+types.length,sinkNode=columnOffset+columns.length,nodeCount=sinkNode+1;
        const residual=Array.from({length:nodeCount},()=>Array(nodeCount).fill(0));
        const addCapacity=(from,to,capacity)=>{residual[from][to]+=Math.max(0,Math.floor(Number(capacity)||0))};
        types.forEach((type,typeIndex)=>{
            addCapacity(sourceNode,typeOffset+typeIndex,displaced.filter(element=>String(element?.type||'')===type).length);
            columns.forEach((column,columnIndex)=>addCapacity(typeOffset+typeIndex,columnOffset+columnIndex,layoutCapacityForType(column,type)));
        });
        columns.forEach((column,columnIndex)=>addCapacity(columnOffset+columnIndex,sinkNode,layoutColumnElementLimit-layoutColumnElements(column).length));
        let flow=0;
        while (flow<displaced.length) {
            const parent=Array(nodeCount).fill(-1),queue=[sourceNode];
            parent[sourceNode]=sourceNode;
            for(let cursor=0;cursor<queue.length&&parent[sinkNode]===-1;cursor+=1){
                const node=queue[cursor];
                for(let next=0;next<nodeCount;next+=1){if(parent[next]===-1&&residual[node][next]>0){parent[next]=node;queue.push(next);if(next===sinkNode)break}}
            }
            if(parent[sinkNode]===-1)break;
            let amount=Number.POSITIVE_INFINITY;
            for(let node=sinkNode;node!==sourceNode;node=parent[node])amount=Math.min(amount,residual[parent[node]][node]);
            for(let node=sinkNode;node!==sourceNode;node=parent[node]){residual[parent[node]][node]-=amount;residual[node][parent[node]]+=amount}
            flow+=amount;
        }
        if(flow!==displaced.length)return null;
        const quotas=new Map(types.map((type,typeIndex)=>[type,columns.map((column,columnIndex)=>layoutCapacityForType(column,type)-residual[typeOffset+typeIndex][columnOffset+columnIndex])]));
        const assignments=displaced.map(element=>{
            const available=quotas.get(String(element?.type||''))||[],columnIndex=available.findIndex(count=>count>0);
            if(columnIndex>=0)available[columnIndex]-=1;
            return columnIndex;
        });
        return assignments.some(columnIndex=>columnIndex<0)?null:assignments;
    }
    function planLayoutColumnCollapse(currentColumns,desiredCount) {
        const source = Array.isArray(currentColumns) ? currentColumns : [];
        const base = source.slice(0,desiredCount).map(column=>({...column,elements:[...layoutColumnElements(column)]}));
        if (!base.length || base.some(column=>!layoutColumnIsWithinCapacity(column))) return null;
        const displaced = source.slice(desiredCount).flatMap(column=>layoutColumnElements(column));
        const assignments=layoutCollapseAssignments(base,displaced);
        if(!assignments)return null;
        displaced.forEach((element,index)=>base[assignments[index]].elements.push(element));
        return base;
    }
    const validLayoutId = value => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
    const isLayoutObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);
    let reusableLayoutLoadGuard = null;
    function newLayoutId() {
        if (typeof window.crypto?.randomUUID === 'function') return window.crypto.randomUUID();
        const bytes = new Uint8Array(16);
        if (typeof window.crypto?.getRandomValues === 'function') window.crypto.getRandomValues(bytes);
        else for (let index = 0; index < bytes.length; index += 1) bytes[index] = Math.floor(Math.random() * 256);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map(byte => byte.toString(16).padStart(2,'0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    const cloneLayoutValue = value => typeof window.structuredClone === 'function' ? window.structuredClone(value) : JSON.parse(JSON.stringify(value));
    function uniqueLayoutId(candidate, used) {
        let id = validLayoutId(candidate) && !used.has(String(candidate)) ? String(candidate) : newLayoutId();
        while (used.has(id)) id = newLayoutId();
        used.add(id);
        return id;
    }
    function ensureRepeaterIds(element, definition, usedIds) {
        Object.entries(definition?.fields || {}).forEach(([field,fieldDefinition]) => {
            if (fieldDefinition?.kind !== 'repeater' || !Array.isArray(element[field])) return;
            const identity = String(fieldDefinition.item_identity || 'id');
            element[field].forEach(item => {
                if (!item || typeof item !== 'object' || Array.isArray(item)) return;
                item[identity] = uniqueLayoutId(item[identity],usedIds);
            });
        });
    }
    function layoutStoredFieldShapeIssue(value, definition, location) {
        if ((value === null || value === undefined) && !definition?.required) return null;
        const kind = String(definition?.kind || 'plain_text');
        if (kind === 'repeater') return Array.isArray(value) ? null : `${location} has a damaged saved list.`;
        if (kind === 'boolean') return typeof value === 'boolean' ? null : `${location} has a damaged saved value.`;
        if (kind === 'integer') return Number.isInteger(value) ? null : `${location} has a damaged saved number.`;
        if (kind === 'uuid') return validLayoutId(value) ? null : `${location} has a damaged stable identity.`;
        if (typeof value !== 'string') return `${location} has a damaged saved value.`;
        if (['choice','approved_icon'].includes(kind) && !Object.prototype.hasOwnProperty.call(definition?.options || {},value)) return `${location} uses a choice this editor does not support.`;
        return null;
    }
    function layoutSerializedByteLength(value) {
        let serialized;
        try {
            serialized = JSON.stringify(value);
        } catch (error) {
            return Number.POSITIVE_INFINITY;
        }
        if (typeof serialized !== 'string') return Number.POSITIVE_INFINITY;
        // Match PHP json_encode: these line separators stay escaped even with JSON_UNESCAPED_UNICODE.
        serialized = serialized.replace(/\u2028/g,'\\u2028').replace(/\u2029/g,'\\u2029');
        if (typeof TextEncoder === 'function') return new TextEncoder().encode(serialized).length;

        let bytes = 0;
        for (const character of serialized) {
            const codePoint = character.codePointAt(0);
            bytes += codePoint <= 0x7f ? 1 : codePoint <= 0x7ff ? 2 : codePoint <= 0xffff ? 3 : 4;
        }
        return bytes;
    }
    function layoutStoredBoundsIssue(value) {
        if (!isLayoutObject(value) || !Array.isArray(value.rows)) return null;
        if (value.rows.length > layoutStorageLimits.rows) {
            return `The saved visual layout contains more than ${layoutStorageLimits.rows} rows.`;
        }
        for (let rowIndex=0; rowIndex<value.rows.length; rowIndex+=1) {
            const row = value.rows[rowIndex];
            if (!isLayoutObject(row) || !Array.isArray(row.columns)) continue;
            if (row.columns.length > layoutStorageLimits.columns) {
                return `Row ${rowIndex+1} contains more columns than this editor can safely handle.`;
            }
            for (let columnIndex=0; columnIndex<row.columns.length; columnIndex+=1) {
                const column = row.columns[columnIndex];
                if (!isLayoutObject(column) || !Array.isArray(column.elements)) continue;
                const columnLocation = `Row ${rowIndex+1}, column ${columnIndex+1}`;
                if (column.elements.length > layoutStorageLimits.elementsPerColumn) {
                    return `${columnLocation} contains more content items than this editor can safely handle.`;
                }
                const typeCounts = new Map();
                for (let elementIndex=0; elementIndex<column.elements.length; elementIndex+=1) {
                    const element = column.elements[elementIndex];
                    if (!isLayoutObject(element)) continue;
                    const type = String(element.type || '');
                    const definition = layoutElementCatalog[type];
                    if (!definition) continue;
                    const count = (typeCounts.get(type) || 0) + 1;
                    typeCounts.set(type,count);
                    const instanceLimit = Math.max(1,Math.min(layoutStorageLimits.elementsPerColumn,Math.floor(Number(definition.safe_bounds?.max_instances_per_column)||layoutStorageLimits.elementsPerColumn)));
                    if (count > instanceLimit) {
                        return `${columnLocation} contains more than ${instanceLimit} ${String(definition.label || type)} items.`;
                    }
                    const byteLimit = Math.max(1,Math.floor(Number(definition.safe_bounds?.max_serialized_bytes)||65536));
                    if (layoutSerializedByteLength(element) > byteLimit) {
                        return `${columnLocation}, content item ${elementIndex+1} is too large for this editor to save safely.`;
                    }
                }
            }
        }
        if (layoutSerializedByteLength(value) > layoutStorageLimits.payloadBytes) {
            return 'The saved visual layout is too large for this editor to save safely.';
        }
        return null;
    }
    function layoutStoredGlobalDesignIssue(value) {
        for (const [key, choices] of Object.entries(layoutGlobalDesignChoices)) {
            if (!Object.prototype.hasOwnProperty.call(value,key)) continue;
            if (typeof value[key] !== 'string' || !Object.prototype.hasOwnProperty.call(choices || {},value[key])) {
                return 'The visual layout contains a design choice this editor does not support.';
            }
        }
        return null;
    }
    function inspectStoredLayout(value) {
        const blocked = detail => ({blocked:true,detail,missingIdentities:[],usedIds:{},legacy:false});
        if (!isLayoutObject(value)) return blocked('The saved visual layout is not in a usable format.');
        const hasVersion = Object.prototype.hasOwnProperty.call(value,'schema_version');
        const version = hasVersion ? value.schema_version : null;
        if (hasVersion && version !== 1 && version !== 2) return blocked('This visual layout was created by an unsupported editor version.');
        const legacy = !hasVersion || version === 1;
        if (!Array.isArray(value.rows)) return blocked('The saved rows list is damaged or missing.');
        const allowedContentKeys = new Set(['schema_version','rows','section_presentation','section_spacing','content_alignment','column_count']);
        if (Object.keys(value).some(key=>!allowedContentKeys.has(key))) return blocked('The visual layout contains a setting this editor does not support.');
        const designIssue = layoutStoredGlobalDesignIssue(value);
        if (designIssue) return blocked(designIssue);
        const boundsIssue = layoutStoredBoundsIssue(value);
        if (boundsIssue) return blocked(boundsIssue);
        const usedIds = {row:new Set(),column:new Set(),element:new Set(),repeater:new Set()};
        const missingIdentities = [];
        const identityIssue = (owner,key,location,kind) => {
            const identity = owner[key];
            if (identity === undefined || identity === null || identity === '') {
                if (!legacy) return `${location} lost its stable identity.`;
                missingIdentities.push({owner,key,kind});
                return null;
            }
            if (!validLayoutId(identity)) return `${location} has a damaged stable identity.`;
            const normalized = String(identity).toLowerCase();
            if (usedIds[kind].has(normalized)) return `${location} repeats an identity already used by another ${kind==='repeater'?'list item':kind}.`;
            usedIds[kind].add(normalized);
            return null;
        };
        for (let rowIndex=0; rowIndex<value.rows.length; rowIndex+=1) {
            const row = value.rows[rowIndex];
            const rowLocation = `Row ${rowIndex+1}`;
            if (!isLayoutObject(row)) return blocked(`${rowLocation} is not stored in a usable format.`);
            if (Object.keys(row).some(key=>!['id','layout','width','background','spacing','columns'].includes(key))) return blocked(`${rowLocation} contains a setting this editor does not support.`);
            const rowIdIssue = identityIssue(row,'id',rowLocation,'row');
            if (rowIdIssue) return blocked(rowIdIssue);
            if (!Object.prototype.hasOwnProperty.call(layoutPresetChoices,String(row.layout||''))) return blocked(`${rowLocation} uses a column design this editor does not support.`);
            if (!Object.prototype.hasOwnProperty.call(layoutWidthChoices,String(row.width||''))) return blocked(`${rowLocation} uses a content width this editor does not support.`);
            if (!Object.prototype.hasOwnProperty.call(layoutBackgroundChoices,String(row.background||''))) return blocked(`${rowLocation} uses a background this editor does not support.`);
            if (!Object.prototype.hasOwnProperty.call(layoutSpacingChoices,String(row.spacing||''))) return blocked(`${rowLocation} uses spacing this editor does not support.`);
            if (!Array.isArray(row.columns)) return blocked(`${rowLocation} has a damaged or missing columns list.`);
            if (row.columns.length !== layoutColumnCount(row.layout)) return blocked(`${rowLocation} does not have the number of columns required by its saved design.`);
            for (let columnIndex=0; columnIndex<row.columns.length; columnIndex+=1) {
                const column = row.columns[columnIndex];
                const columnLocation = `${rowLocation}, column ${columnIndex+1}`;
                if (!isLayoutObject(column)) return blocked(`${columnLocation} is not stored in a usable format.`);
                if (Object.keys(column).some(key=>!['id','elements'].includes(key))) return blocked(`${columnLocation} contains a setting this editor does not support.`);
                const columnIdIssue = identityIssue(column,'id',columnLocation,'column');
                if (columnIdIssue) return blocked(columnIdIssue);
                if (!Array.isArray(column.elements)) return blocked(`${columnLocation} has a damaged or missing content list.`);
                for (let elementIndex=0; elementIndex<column.elements.length; elementIndex+=1) {
                    const element = column.elements[elementIndex];
                    const elementLocation = `${columnLocation}, content item ${elementIndex+1}`;
                    if (!isLayoutObject(element)) return blocked(`${elementLocation} is not stored in a usable format.`);
                    if (!Object.prototype.hasOwnProperty.call(layoutElementCatalog,String(element.type||''))) return blocked(`${elementLocation} uses a content type this editor does not support.`);
                    const definition = layoutElementCatalog[element.type];
                    const allowedFields = new Set(Array.isArray(definition?.allowed_fields) ? definition.allowed_fields : ['id','type',...Object.keys(definition?.fields||{})]);
                    if (Object.keys(element).some(key=>!allowedFields.has(key))) return blocked(`${elementLocation} contains a setting this editor does not support.`);
                    const elementIdIssue = identityIssue(element,'id',elementLocation,'element');
                    if (elementIdIssue) return blocked(elementIdIssue);
                    for (const [field,fieldDefinition] of Object.entries(definition?.fields || {})) {
                        const fieldLocation = `${elementLocation}, ${String(fieldDefinition?.label||field).toLowerCase()}`;
                        if (!Object.prototype.hasOwnProperty.call(element,field)) {
                            if (fieldDefinition?.required) return blocked(`${fieldLocation} is missing from the saved content.`);
                            continue;
                        }
                        const shapeIssue = layoutStoredFieldShapeIssue(element[field],fieldDefinition,fieldLocation);
                        if (shapeIssue) return blocked(shapeIssue);
                        if (fieldDefinition?.kind !== 'repeater') continue;
                        const items = element[field];
                        const maxItems = Number(fieldDefinition?.bounds?.max_items ?? 12);
                        if (Number.isFinite(maxItems) && items.length>maxItems) return blocked(`${fieldLocation} contains more items than this editor can safely handle.`);
                        const itemFields = fieldDefinition?.item_fields || {};
                        const allowedItemFields = new Set(Object.keys(itemFields));
                        const itemIdentity = String(fieldDefinition?.item_identity || 'id');
                        for (let itemIndex=0; itemIndex<items.length; itemIndex+=1) {
                            const item = items[itemIndex];
                            const itemLocation = `${fieldLocation}, item ${itemIndex+1}`;
                            if (!isLayoutObject(item)) return blocked(`${itemLocation} is not stored in a usable format.`);
                            if (Object.keys(item).some(key=>!allowedItemFields.has(key))) return blocked(`${itemLocation} contains a setting this editor does not support.`);
                            const itemIdIssue = identityIssue(item,itemIdentity,itemLocation,'repeater');
                            if (itemIdIssue) return blocked(itemIdIssue);
                            for (const [itemField,itemDefinition] of Object.entries(itemFields)) {
                                if (itemField===itemIdentity && legacy && !Object.prototype.hasOwnProperty.call(item,itemField)) continue;
                                if (!Object.prototype.hasOwnProperty.call(item,itemField)) {
                                    if (itemDefinition?.required) return blocked(`${itemLocation} is missing ${String(itemDefinition?.label||itemField).toLowerCase()}.`);
                                    continue;
                                }
                                const itemShapeIssue = layoutStoredFieldShapeIssue(item[itemField],itemDefinition,`${itemLocation}, ${String(itemDefinition?.label||itemField).toLowerCase()}`);
                                if (itemShapeIssue) return blocked(itemShapeIssue);
                            }
                        }
                    }
                }
            }
        }
        return {blocked:false,detail:'',missingIdentities,usedIds,legacy};
    }
    function layoutLoadGuard() {
        if (reusableLayoutLoadGuard) return reusableLayoutLoadGuard;
        const guard = inspectStoredLayout(content);
        if (!guard.blocked && guard.legacy) {
            guard.missingIdentities.forEach(({owner,key,kind})=>{owner[key]=uniqueLayoutId(null,guard.usedIds[kind])});
            content.schema_version=2;
            guard.upgraded=true;
        }
        reusableLayoutLoadGuard=guard;
        return guard;
    }
    function layoutRows() {
        const guard=layoutLoadGuard();
        return guard.blocked ? [] : content.rows;
    }
    function layoutRepairNotice(guard) {
        return `<div class="reuse-notice reuse-notice--impact" data-layout-load-guard role="alert"><strong>This visual layout is locked to protect its content</strong>${escapeHtml(guard?.detail||'Its saved structure needs repair.')} Nothing was changed. Ask a website administrator to repair this reusable section, then reload this page. Editing and saving stay unavailable until it is repaired.</div>`;
    }
    const newLayoutRow = () => ({id:newLayoutId(),layout:'full',width:'standard',background:'default',spacing:'standard',columns:[{id:newLayoutId(),elements:[]}]});
    function newLayoutElement(type) {
        const definition = layoutElementCatalog[String(type || '')];
        if (!definition || definition.mode !== 'static') return null;
        const element = cloneLayoutValue(definition.defaults || {type:String(type)});
        element.id = newLayoutId();
        element.type = String(type);
        ensureRepeaterIds(element,definition,new Set([element.id]));
        return element;
    }
    function regenerateLayoutElementIds(element) {
        element.id = newLayoutId();
        Object.entries(layoutElementCatalog[element.type]?.fields || {}).forEach(([field,fieldDefinition]) => {
            if (fieldDefinition?.kind !== 'repeater' || !Array.isArray(element[field])) return;
            const identity = String(fieldDefinition.item_identity || 'id');
            element[field].forEach(item => {
                if (item && typeof item === 'object' && !Array.isArray(item)) item[identity] = newLayoutId();
            });
        });
        return element;
    }
    function duplicateLayoutRow(row) {
        const copy = cloneLayoutValue(row);
        copy.id = newLayoutId();
        copy.columns.forEach(column => {
            column.id = newLayoutId();
            column.elements.forEach(regenerateLayoutElementIds);
        });
        return copy;
    }
    function reshapeLayoutRow(row, nextPreset) {
        const preset = layoutChoice(nextPreset,layoutPresetChoices,'full');
        const desired = layoutColumnCount(preset);
        const current = Array.isArray(row.columns) ? row.columns : [];
        if (current.length > desired) {
            const plannedColumns = planLayoutColumnCollapse(current,desired);
            if (!plannedColumns) return false;
            row.columns = plannedColumns;
        } else {
            const expandedColumns = current.map(column=>({...column,elements:[...layoutColumnElements(column)]}));
            while (expandedColumns.length < desired) expandedColumns.push({id:newLayoutId(),elements:[]});
            row.columns = expandedColumns;
        }
        row.layout = preset;
        return true;
    }
    function layoutStatus(message = '') {
        const status = document.getElementById('reusable-layout-status');
        if (status) status.textContent = message;
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
    const layoutOptionsHtml = (choices,selected) => Object.entries(choices || {}).map(([value,label]) => `<option value="${escapeHtml(value)}" ${String(selected)===String(value)?'selected':''}>${escapeHtml(label)}</option>`).join('');
    const layoutDisabled = () => canEdit ? '' : ' disabled';
    function layoutElementPickerHtml(column) {
        const firstAvailable = layoutCatalogGroups.flatMap(group=>group.elements).find(element=>layoutColumnCanAccept(column,element.type))?.type;
        return layoutCatalogGroups.map(group => `<optgroup label="${escapeHtml(group.label)}">${group.elements.map(element => {
            const available=layoutColumnCanAccept(column,element.type);
            return `<option value="${escapeHtml(element.type)}" ${available?'':'disabled'} ${available&&element.type===firstAvailable?'selected':''}>${escapeHtml(element.label)} — ${escapeHtml(available?element.description:`Limit of ${layoutElementInstanceLimit(element.type)} reached`)}</option>`;
        }).join('')}</optgroup>`).join('');
    }
    function layoutRowSelect(rowIndex, field, label, value, choices, help = '') {
        return `<div class="reuse-field"><label>${escapeHtml(label)}</label><select data-layout-row="${rowIndex}" data-layout-row-field="${escapeHtml(field)}"${layoutDisabled()}>${layoutOptionsHtml(choices,value)}</select>${help?`<small>${escapeHtml(help)}</small>`:''}</div>`;
    }
    function layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path) {
        return `data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-field-path="${pathToken(path)}"`;
    }
    function layoutFieldId(rowIndex,columnIndex,elementIndex,path) {
        return `reusable-layout-${rowIndex}-${columnIndex}-${elementIndex}-${path.map(String).join('-').replace(/[^a-z0-9_-]+/gi,'-')}`;
    }
    function layoutFieldHelp(definition) {
        if (definition?.kind === 'safe_link') return 'Use /page-name, #section, or a complete https:// address.';
        if (definition?.kind === 'managed_image') return 'Choose an approved image from the Media Library, then add useful alternative text where requested.';
        if (definition?.kind === 'managed_file') return 'Choose an approved document from the Media Library. Documents are uploaded and managed there.';
        if (definition?.kind === 'approved_video_source') return 'Uploaded videos must be MP4 or WebM. YouTube links must use a secure YouTube address.';
        return '';
    }
    function layoutTextField(element,definition,path,rowIndex,columnIndex,elementIndex,options={}) {
        const value = valueAt(element,path) ?? '';
        const id = layoutFieldId(rowIndex,columnIndex,elementIndex,path);
        const attributes = layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path);
        const max = Number(definition?.bounds?.max_length || 0);
        const maxlength = max > 0 ? ` maxlength="${max}"` : '';
        const required = definition?.required ? ' required' : '';
        const fieldName = String(path.at(-1) || '');
        const textarea = options.textarea || max > 500 || ['body','description','quote','caption'].includes(fieldName);
        const control = textarea
            ? `<textarea id="${id}" ${attributes}${maxlength}${required}${layoutDisabled()}>${escapeHtml(value)}</textarea>`
            : `<input id="${id}" ${attributes} type="${options.type || 'text'}" value="${escapeHtml(value)}"${maxlength}${required}${layoutDisabled()}>`;
        const help = options.help || layoutFieldHelp(definition);
        return `<div class="reuse-field"><label for="${id}">${escapeHtml(definition?.label || friendlyLabel(fieldName))}</label>${control}${help?`<small>${escapeHtml(help)}</small>`:''}</div>`;
    }
    function layoutChoiceField(element,definition,path,rowIndex,columnIndex,elementIndex,rerender=false) {
        const value = String(valueAt(element,path) ?? '');
        const choices = {...(definition?.options || {})};
        if (!Object.prototype.hasOwnProperty.call(choices,value)) choices[value] = value ? `Current: ${value}` : 'Choose…';
        const id = layoutFieldId(rowIndex,columnIndex,elementIndex,path);
        return `<div class="reuse-field"><label for="${id}">${escapeHtml(definition?.label || friendlyLabel(path.at(-1)))}</label><select id="${id}" ${layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path)}${rerender?' data-layout-rerender':''}${definition?.required?' required':''}${layoutDisabled()}>${layoutOptionsHtml(choices,value)}</select></div>`;
    }
    function layoutBooleanField(element,definition,path,rowIndex,columnIndex,elementIndex) {
        const checked = Boolean(valueAt(element,path));
        return `<label class="reuse-check"><input type="checkbox" ${layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path)} ${checked?'checked':''}${layoutDisabled()}> <span>${escapeHtml(definition?.label || friendlyLabel(path.at(-1)))}</span></label>`;
    }
    function layoutRichField(element,definition,path,rowIndex,columnIndex,elementIndex) {
        const id = layoutFieldId(rowIndex,columnIndex,elementIndex,path);
        return `<div class="reuse-field"><span id="${id}-label">${escapeHtml(definition?.label || 'Formatted text')}</span><div class="reuse-rich-toolbar" role="toolbar" aria-label="Format ${escapeHtml(definition?.label || 'this text')}"><button type="button" data-layout-format="bold" data-layout-rich-editor="${id}" aria-label="Bold"${layoutDisabled()}>B</button><button type="button" data-layout-format="italic" data-layout-rich-editor="${id}" aria-label="Italic"${layoutDisabled()}><em>I</em></button><button type="button" data-layout-format="insertUnorderedList" data-layout-rich-editor="${id}" aria-label="Bulleted list"${layoutDisabled()}>• List</button><button type="button" data-layout-format="createLink" data-layout-rich-editor="${id}" aria-label="Add link"${layoutDisabled()}>Link</button></div><div id="${id}" class="reuse-rich" role="textbox" aria-multiline="true" aria-labelledby="${id}-label" data-layout-rich ${layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path)} ${canEdit?'contenteditable="true"':'aria-readonly="true"'}>${safeLayoutRichHtml(valueAt(element,path))}</div></div>`;
    }
    function layoutMediaChoices(kind) {
        return mediaAssets.filter(asset => {
            const mime = String(asset.mime || '');
            if (kind === 'image') return imageMimeTypes.has(mime);
            if (kind === 'video') return ['video/mp4','video/webm'].includes(mime);
            return documentMimeTypes.has(mime);
        });
    }
    function layoutFieldDefinitionAtPath(element,path) {
        let fields = layoutElementCatalog[String(element?.type || '')]?.fields || {};
        let definition = null;
        for (const segment of path) {
            if (typeof segment === 'number') continue;
            definition = fields?.[segment] || null;
            if (!definition) return null;
            fields = definition.kind === 'repeater' ? (definition.item_fields || {}) : {};
        }
        return definition;
    }
    function layoutMediaField(element,definition,path,rowIndex,columnIndex,elementIndex,kind) {
        const value = String(valueAt(element,path) || '');
        const choices = layoutMediaChoices(kind);
        const id = layoutFieldId(rowIndex,columnIndex,elementIndex,path);
        const currentKnown = choices.some(asset => String(asset.url) === value);
        const currentOption = value && !currentKnown ? `<option value="${escapeHtml(value)}" selected>Current selection</option>` : '';
        const options = choices.map(asset => `<option value="${escapeHtml(asset.url)}" ${String(asset.url)===value?'selected':''}>${escapeHtml(asset.name)}</option>`).join('');
        const noun = kind === 'image' ? 'image' : kind === 'video' ? 'video' : 'document';
        const placeholderDisabled = definition?.required === true || value !== '';
        const chooser = `<select class="reuse-layout-media-select" data-layout-media="${kind}" ${layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path)} aria-label="Choose a ${noun} from the Media Library"${definition?.required?' required':''}${layoutDisabled()}><option value=""${value?'':' selected'}${placeholderDisabled?' disabled':''}>Choose from Media Library…</option>${currentOption}${options}</select>`;
        const clearableImage = kind === 'image' && definition?.required === false && ['card','quote'].includes(String(element.type || ''));
        const clearAction = canEdit && clearableImage && value ? `<button class="reuse-btn reuse-layout-clear-image" type="button" data-layout-clear-image ${layoutFieldAttributes(rowIndex,columnIndex,elementIndex,path)}><i class="fa fa-times" aria-hidden="true"></i> Remove image</button>` : '';
        const preview = kind === 'image' && value ? `<img class="reuse-layout-thumbnail" src="${escapeHtml(value)}" alt="" onerror="this.hidden=true">` : '';
        const libraryLink = kind === 'document' && mediaLibraryUrl ? ` <a href="${escapeHtml(mediaLibraryUrl)}" target="_blank" rel="noopener">Open Media Library</a>` : '';
        return `<div class="reuse-field"><label for="${id}">${escapeHtml(definition?.label || noun)}</label><input id="${id}" value="${escapeHtml(value)}" readonly${definition?.required?' required':''}${layoutDisabled()}>${chooser}${clearAction}${preview}<small>${escapeHtml(layoutFieldHelp(definition))}${libraryLink}</small></div>`;
    }
    function layoutRepeaterItem(definition) {
        const item = {};
        Object.entries(definition?.item_fields || {}).forEach(([field,fieldDefinition]) => {
            item[field] = fieldDefinition?.kind === 'uuid' ? newLayoutId() : cloneLayoutValue(fieldDefinition?.default ?? '');
        });
        const identity = String(definition?.item_identity || 'id');
        if (!validLayoutId(item[identity])) item[identity] = newLayoutId();
        return item;
    }
    function layoutRepeaterField(element,definition,path,rowIndex,columnIndex,elementIndex) {
        const value = valueAt(element,path);
        if (!Array.isArray(value)) return '<p class="reuse-layout-element__note">This saved list has an invalid shape. It has been left unchanged; ask an administrator to repair it before saving.</p>';
        const max = Number(definition?.bounds?.max_items || 12);
        const visibleFields = Object.entries(definition?.item_fields || {}).filter(([,fieldDefinition]) => fieldDefinition?.kind !== 'uuid');
        const items = value.map((item,index) => {
            const itemPath = [...path,index];
            const heading = item?.heading || item?.question || item?.date_label || item?.caption || `${String(definition?.label || 'Item').replace(/s$/,'')} ${index+1}`;
            const fields = visibleFields.map(([field,fieldDefinition]) => layoutElementField(element,fieldDefinition,[...itemPath,field],rowIndex,columnIndex,elementIndex)).join('');
            const actions = canEdit ? `<div class="reuse-repeat-actions"><button type="button" data-layout-repeater-action="up" data-layout-repeater-path="${pathToken(path)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-index="${index}" ${index===0?'disabled':''}>↑ Earlier</button><button type="button" data-layout-repeater-action="down" data-layout-repeater-path="${pathToken(path)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-index="${index}" ${index===value.length-1?'disabled':''}>↓ Later</button><button class="is-danger" type="button" data-layout-repeater-action="remove" data-layout-repeater-path="${pathToken(path)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-index="${index}">Remove</button></div>` : '';
            return `<details class="reuse-repeat-item" ${index===0?'open':''}><summary>${escapeHtml(heading)}</summary>${actions}<div>${fields}</div></details>`;
        }).join('');
        const add = canEdit ? `<button class="reuse-btn" type="button" data-layout-repeater-action="add" data-layout-repeater-path="${pathToken(path)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" ${value.length>=max?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add ${escapeHtml(String(definition?.label || 'item').replace(/s$/i,'').toLowerCase())}</button>` : '';
        return `<div class="reuse-field"><span>${escapeHtml(definition?.label || friendlyLabel(path.at(-1)))}</span><div class="reuse-repeat">${items || '<div class="reuse-empty">No items yet.</div>'}</div>${add}<small>${value.length} of ${max} items used.</small></div>`;
    }
    function layoutElementField(element,definition,path,rowIndex,columnIndex,elementIndex) {
        const kind = String(definition?.kind || '');
        if (kind === 'uuid') return '';
        if (kind === 'rich_text') return layoutRichField(element,definition,path,rowIndex,columnIndex,elementIndex);
        if (kind === 'choice' || kind === 'approved_icon') return layoutChoiceField(element,definition,path,rowIndex,columnIndex,elementIndex,path.at(-1)==='source_type');
        if (kind === 'boolean') return layoutBooleanField(element,definition,path,rowIndex,columnIndex,elementIndex);
        if (kind === 'integer') return layoutTextField(element,definition,path,rowIndex,columnIndex,elementIndex,{type:'number'});
        if (kind === 'managed_image') return layoutMediaField(element,definition,path,rowIndex,columnIndex,elementIndex,'image');
        if (kind === 'managed_file') return layoutMediaField(element,definition,path,rowIndex,columnIndex,elementIndex,'document');
        if (kind === 'approved_video_source' && String(element.source_type || 'upload') === 'upload') return layoutMediaField(element,definition,path,rowIndex,columnIndex,elementIndex,'video');
        if (kind === 'repeater') return layoutRepeaterField(element,definition,path,rowIndex,columnIndex,elementIndex);
        if (['plain_text','safe_link','approved_video_source'].includes(kind)) return layoutTextField(element,definition,path,rowIndex,columnIndex,elementIndex);
        return `<p class="reuse-layout-element__note">${escapeHtml(definition?.label || friendlyLabel(path.at(-1)))} is preserved but cannot be edited in this safe visual editor.</p>`;
    }
    function layoutElementFields(element,rowIndex,columnIndex,elementIndex) {
        const definition = layoutElementCatalog[String(element.type || '')];
        if (!definition) return `<p class="reuse-layout-element__note"><strong>Unsupported saved element.</strong> Its type and fields are preserved exactly, but this editor will not guess how to change them.</p>`;
        const fields = Object.entries(definition.fields || {}).map(([field,fieldDefinition]) => layoutElementField(element,fieldDefinition,[field],rowIndex,columnIndex,elementIndex)).join('');
        return fields || `<p class="reuse-layout-element__note">${escapeHtml(definition.description || 'This visual element has no content fields.')}</p>`;
    }
    function layoutElementTitle(element) {
        const type = String(element.type || '');
        const definition = layoutElementCatalog[type];
        const label = String(definition?.label || `Unsupported element (${type || 'missing type'})`);
        const temporary = document.createElement('div');
        const rawDetail = element.text || element.heading || element.label || element.quote || element.question || element.date_label || element.title || element.caption || element.accessible_label || element.body || '';
        temporary.innerHTML = ['rich_text','callout'].includes(type) ? String(rawDetail) : '';
        const detail = ['rich_text','callout'].includes(type) ? temporary.textContent : rawDetail;
        return String(detail || '').trim() ? `${label} · ${String(detail).trim().slice(0,34)}` : label;
    }
    function renderLayoutElement(element,row,rowIndex,columnIndex,elementIndex,columnCount,columnElements) {
        const type = String(element.type || '');
        const elementLabel = String(layoutElementChoices[type] || `Unsupported element (${type || 'missing type'})`);
        const title = layoutElementTitle(element);
        const path = `${rowIndex}:${columnIndex}:${elementIndex}`;
        const open = layoutOpenPath === path || (layoutOpenPath === null && rowIndex === 0 && columnIndex === 0 && elementIndex === 0);
        const leftColumn = columnIndex > 0 ? row.columns[columnIndex-1] : null;
        const rightColumn = columnIndex < columnCount-1 ? row.columns[columnIndex+1] : null;
        const leftBlocked = !leftColumn || !layoutColumnCanAccept(leftColumn,type);
        const rightBlocked = !rightColumn || !layoutColumnCanAccept(rightColumn,type);
        const duplicateBlocked = !layoutColumnCanAccept({elements:columnElements},type);
        const actions = canEdit ? `<span class="reuse-layout-actions" role="group" aria-label="Arrange ${escapeHtml(elementLabel)}">
            <button type="button" data-layout-element-action="up" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(elementLabel)} up" title="Move up" ${elementIndex===0?'disabled':''}>↑</button>
            <button type="button" data-layout-element-action="down" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(elementLabel)} down" title="Move down" ${elementIndex===columnElements.length-1?'disabled':''}>↓</button>
            <button type="button" data-layout-element-action="left" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(elementLabel)} to the previous column" title="Move to previous column" ${leftBlocked?'disabled':''}>←</button>
            <button type="button" data-layout-element-action="right" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(elementLabel)} to the next column" title="Move to next column" ${rightBlocked?'disabled':''}>→</button>
            <button type="button" data-layout-element-action="duplicate" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Duplicate ${escapeHtml(elementLabel)}" title="Duplicate" ${duplicateBlocked?'disabled':''}><i class="fa fa-copy" aria-hidden="true"></i></button>
            <button type="button" data-layout-element-action="remove" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Delete ${escapeHtml(elementLabel)}" title="Delete"><i class="fa fa-trash" aria-hidden="true"></i></button>
        </span>` : '';
        return `<article class="reuse-layout-element" aria-label="${escapeHtml(title)}"><header class="reuse-layout-element__head"><span class="reuse-layout-element__title"><i class="fa ${layoutElementIcons[type]||'fa-square-o'}" aria-hidden="true"></i><span>${escapeHtml(title)}</span></span>${actions}</header><details data-layout-element-details="${path}" ${open?'open':''}><summary>Edit content</summary><div class="reuse-layout-element__body">${layoutElementFields(element,rowIndex,columnIndex,elementIndex)}</div></details></article>`;
    }
    function renderLayoutRow(row,rowIndex,rows) {
        const preset = layoutChoice(row.layout,layoutPresetChoices,'full');
        const columnCount = layoutColumnCount(preset);
        const rowActions = canEdit ? `<span class="reuse-layout-actions" role="group" aria-label="Arrange row ${rowIndex+1}">
            <button type="button" data-layout-row-action="up" data-layout-row="${rowIndex}" aria-label="Move row ${rowIndex+1} up" title="Move row up" ${rowIndex===0?'disabled':''}>↑</button>
            <button type="button" data-layout-row-action="down" data-layout-row="${rowIndex}" aria-label="Move row ${rowIndex+1} down" title="Move row down" ${rowIndex===rows.length-1?'disabled':''}>↓</button>
            <button type="button" data-layout-row-action="duplicate" data-layout-row="${rowIndex}" aria-label="Duplicate row ${rowIndex+1}" title="Duplicate row" ${rows.length>=12?'disabled':''}><i class="fa fa-copy" aria-hidden="true"></i></button>
            <button type="button" data-layout-row-action="remove" data-layout-row="${rowIndex}" aria-label="Delete row ${rowIndex+1}" title="Delete row"><i class="fa fa-trash" aria-hidden="true"></i></button>
        </span>` : '';
        const columns = row.columns.slice(0,columnCount).map((column,columnIndex) => {
            const elements = Array.isArray(column.elements) ? column.elements : [];
            const selectId = `reusable-layout-add-${rowIndex}-${columnIndex}`;
            const hasAvailableElement = Object.keys(layoutElementCatalog).some(type=>layoutColumnCanAccept(column,type));
            const add = canEdit ? `<div class="reuse-layout-add"><label class="reuse-layout-sr" for="${selectId}">Element type for row ${rowIndex+1}, column ${columnIndex+1}</label><select id="${selectId}" data-layout-new-element data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" aria-label="Choose an element type">${layoutElementPickerHtml(column)}</select><button class="reuse-btn" type="button" data-layout-add-element data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" ${hasAvailableElement?'':'disabled'}><i class="fa fa-plus" aria-hidden="true"></i> Add element</button></div>` : '';
            return `<section class="reuse-layout-column" aria-labelledby="reusable-layout-column-${rowIndex}-${columnIndex}"><header class="reuse-layout-column__head"><strong id="reusable-layout-column-${rowIndex}-${columnIndex}">Column ${columnIndex+1}</strong><small>${elements.length} of 12 elements</small></header>${elements.length?elements.map((element,elementIndex)=>renderLayoutElement(element,row,rowIndex,columnIndex,elementIndex,columnCount,elements)).join(''):'<p class="reuse-layout-empty">This column is empty.</p>'}${add}</section>`;
        }).join('');
        return `<section class="reuse-layout-row" aria-labelledby="reusable-layout-row-${rowIndex}"><header class="reuse-layout-row__head"><span class="reuse-layout-row__title"><strong id="reusable-layout-row-${rowIndex}">Row ${rowIndex+1}</strong><small>${escapeHtml(layoutPresetChoices[preset])} · ${columnCount} ${columnCount===1?'column':'columns'}</small></span>${rowActions}</header><div class="reuse-layout-row__body"><div class="reuse-layout-row__settings">${layoutRowSelect(rowIndex,'layout','Column layout',preset,layoutPresetChoices,'Changing the preset keeps elements when the remaining columns have enough room.')}${layoutRowSelect(rowIndex,'width','Content width',row.width,layoutWidthChoices)}${layoutRowSelect(rowIndex,'background','Background',row.background,layoutBackgroundChoices)}${layoutRowSelect(rowIndex,'spacing','Space inside row',row.spacing,layoutSpacingChoices)}</div><div class="reuse-layout-columns">${columns}</div></div></section>`;
    }
    const globalDesignFields = Object.freeze(['section_presentation','section_spacing','content_alignment','column_count']);
    const globalDesignFieldSet = new Set(globalDesignFields);
    function globalDesignFieldKeys() {
        return globalDesignFields.filter(key => {
            if (key === 'column_count' && !columnCountBlockTypes.has(blockType)) return false;
            return Object.prototype.hasOwnProperty.call(designDefaults,key) && Object.keys(enumChoices[key] || {}).length;
        });
    }
    function renderGlobalDesignFields() {
        const fields = globalDesignFieldKeys().map(key => {
            const value = Object.prototype.hasOwnProperty.call(content,key) ? content[key] : designDefaults[key];
            return fieldHtml('content',content,key,value,[key]);
        }).join('');
        if (!fields) return '';
        return `<fieldset class="reuse-section-group reuse-global-design" data-e2e="reusable-global-design"><legend>Section design</legend><p class="reuse-editor-help">The current site defaults are shown until you choose a different setting.</p>${fields}</fieldset>`;
    }
    function renderLayoutEditor() {
        const guard=layoutLoadGuard();
        if (guard.blocked) return layoutRepairNotice(guard);
        const rows = layoutRows();
        const globalFields = renderGlobalDesignFields();
        return `<div data-e2e="reusable-layout-editor"><div class="reuse-layout-guide"><strong>Build with rows, columns, and visual elements</strong>Choose a column layout, then pick from the categorized text, media, file, and highlight elements. Every choice uses ordinary fields; machine IDs and code stay hidden.</div>${globalFields}${rows.map((row,rowIndex)=>renderLayoutRow(row,rowIndex,rows)).join('')}${rows.length?'':'<p class="reuse-layout-empty">This visual layout has no rows yet.</p>'}<p class="reuse-layout-status" id="reusable-layout-status" role="status" aria-live="polite"></p>${canEdit?`<button class="reuse-btn reuse-layout-add-row" type="button" id="reusable-layout-add-row" ${rows.length>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add row</button>`:''}<p class="reuse-layout-limit">${rows.length} of 12 rows used</p></div>`;
    }
    function renderRoot(element, rootName, root, template = {}) {
        const contentDesignFields = rootName === 'content' ? renderGlobalDesignFields() : '';
        const keys = orderedObjectKeys(root, template).filter(key => rootName !== 'content' || !globalDesignFieldSet.has(key));
        const regularFields = keys.map(key => fieldHtml(rootName, root, key, root[key], [key])).join('');
        element.innerHTML = contentDesignFields + (regularFields || (contentDesignFields ? '' : '<div class="reuse-empty">This section has no optional display settings.</div>'));
        syncHidden();
    }
    function renderAll() {
        const layoutBlocked=blockType === 'layout' && layoutLoadGuard().blocked;
        if (blockType === 'layout') {
            contentRoot.innerHTML = renderLayoutEditor();
            syncHidden();
            wireLayoutEditor();
        } else {
            renderRoot(contentRoot, 'content', content, defaults);
        }
        renderRoot(settingsRoot, 'settings', settings, {});
        wireRichToolbars();
        if (layoutBlocked) {
            form.querySelectorAll('input:not([type="hidden"]),textarea,select,button,[contenteditable="true"]').forEach(control=>{
                if (control.hasAttribute('contenteditable')) control.removeAttribute('contenteditable');
                else control.disabled=true;
            });
        }
    }
    function rootFor(name) { return name === 'settings' ? settings : content; }
    function syncHidden() {
        contentInput.value = JSON.stringify(content);
        settingsInput.value = JSON.stringify(settings);
    }
    function markDirty() {
        if (!canEdit || (blockType === 'layout' && layoutLoadGuard().blocked)) return;
        dirty = true;
        syncHidden();
        saveState.textContent = 'Unsaved changes';
        saveState.className = 'reuse-save-state';
        updateSaveButton();
    }
    function updateSaveButton() {
        if (!saveButton) return;
        const layoutBlocked=blockType === 'layout' && layoutLoadGuard().blocked;
        saveButton.disabled = layoutBlocked || busy || (connectedCount > 0 && !impactAcknowledged?.checked);
        if (layoutBlocked) {
            saveState.textContent='Repair needed before this reusable section can be saved';
            saveState.className='reuse-save-state is-error';
        }
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
    function layoutElementFromControl(control) {
        const rowIndex = Number(control.dataset.layoutRow);
        const columnIndex = Number(control.dataset.layoutColumn);
        const elementIndex = Number(control.dataset.layoutElement);
        const rows = layoutRows();
        const row = rows[rowIndex];
        const column = row?.columns?.[columnIndex];
        return {rows,row,column,element:column?.elements?.[elementIndex],rowIndex,columnIndex,elementIndex};
    }
    function wireLayoutEditor() {
        if (layoutLoadGuard().blocked) return;
        contentRoot.querySelectorAll('[data-layout-element-details]').forEach(details => details.addEventListener('toggle', () => {
            if (details.open) layoutOpenPath = details.dataset.layoutElementDetails;
            else if (layoutOpenPath === details.dataset.layoutElementDetails) layoutOpenPath = '';
        }));
        if (!canEdit) return;
        contentRoot.querySelectorAll('[data-layout-row-field]').forEach(control => control.addEventListener('change', () => {
            const rows = layoutRows();
            const row = rows[Number(control.dataset.layoutRow)];
            if (!row) return;
            const field = control.dataset.layoutRowField;
            if (field === 'layout') {
                const previous = row.layout;
                if (!reshapeLayoutRow(row,control.value)) {
                    control.value = previous;
                    layoutStatus('This preset has no room for every element. Move or remove elements before reducing its columns.');
                    return;
                }
                layoutOpenPath = null;
                markDirty();
                renderAll();
                return;
            }
            const choices = field === 'width' ? layoutWidthChoices : field === 'background' ? layoutBackgroundChoices : layoutSpacingChoices;
            row[field] = layoutChoice(control.value,choices,field === 'background' ? 'default' : 'standard');
            markDirty();
        }));
        document.getElementById('reusable-layout-add-row')?.addEventListener('click', () => {
            const rows = layoutRows();
            if (rows.length >= 12) return layoutStatus('A visual layout can contain up to twelve rows.');
            rows.push(newLayoutRow());
            layoutOpenPath = null;
            markDirty();
            renderAll();
        });
        contentRoot.querySelectorAll('[data-layout-row-action]').forEach(button => button.addEventListener('click', () => {
            const rows = layoutRows();
            const index = Number(button.dataset.layoutRow);
            const action = button.dataset.layoutRowAction;
            if (!rows[index]) return;
            if (action === 'up' || action === 'down') {
                const target = action === 'up' ? index-1 : index+1;
                if (target < 0 || target >= rows.length) return;
                [rows[index],rows[target]] = [rows[target],rows[index]];
            } else if (action === 'duplicate') {
                if (rows.length >= 12) return layoutStatus('A visual layout can contain up to twelve rows.');
                rows.splice(index+1,0,duplicateLayoutRow(rows[index]));
            } else if (action === 'remove') {
                if (!window.confirm(`Delete row ${index+1}? It will be removed when you save this reusable section.`)) return;
                rows.splice(index,1);
            }
            layoutOpenPath = null;
            markDirty();
            renderAll();
        }));
        contentRoot.querySelectorAll('[data-layout-add-element]').forEach(button => button.addEventListener('click', () => {
            const rows = layoutRows();
            const rowIndex = Number(button.dataset.layoutRow);
            const columnIndex = Number(button.dataset.layoutColumn);
            const column = rows[rowIndex]?.columns?.[columnIndex];
            const picker = button.closest('.reuse-layout-add')?.querySelector('[data-layout-new-element]');
            if (!column || !picker) return;
            const element = newLayoutElement(picker.value);
            if (!element) return layoutStatus('Choose one of the available visual elements.');
            const issue = layoutColumnCapacityIssue(column,element.type);
            if (issue==='total') return layoutStatus('A column can contain up to twelve elements.');
            if (issue==='type') return layoutStatus(`This column can contain up to ${layoutElementInstanceLimit(element.type)} ${layoutElementChoices[element.type]} ${layoutElementInstanceLimit(element.type)===1?'element':'elements'}.`);
            column.elements.push(element);
            layoutOpenPath = `${rowIndex}:${columnIndex}:${column.elements.length-1}`;
            markDirty();
            renderAll();
        }));
        contentRoot.querySelectorAll('[data-layout-element-action]').forEach(button => button.addEventListener('click', () => {
            const {row,column,element,rowIndex,columnIndex,elementIndex} = layoutElementFromControl(button);
            if (!row || !column || !element) return;
            const action = button.dataset.layoutElementAction;
            if (action === 'up' || action === 'down') {
                const target = action === 'up' ? elementIndex-1 : elementIndex+1;
                if (target < 0 || target >= column.elements.length) return;
                [column.elements[elementIndex],column.elements[target]] = [column.elements[target],column.elements[elementIndex]];
                layoutOpenPath = `${rowIndex}:${columnIndex}:${target}`;
            } else if (action === 'left' || action === 'right') {
                const targetColumnIndex = action === 'left' ? columnIndex-1 : columnIndex+1;
                const targetColumn = row.columns[targetColumnIndex];
                const type = String(element.type || '');
                const issue = targetColumn ? layoutColumnCapacityIssue(targetColumn,type) : 'total';
                if (issue==='total') return layoutStatus('The adjacent column is full.');
                if (issue==='type') return layoutStatus(`The adjacent column can contain up to ${layoutElementInstanceLimit(type)} ${layoutElementChoices[type]} ${layoutElementInstanceLimit(type)===1?'element':'elements'}.`);
                column.elements.splice(elementIndex,1);
                targetColumn.elements.push(element);
                layoutOpenPath = `${rowIndex}:${targetColumnIndex}:${targetColumn.elements.length-1}`;
            } else if (action === 'duplicate') {
                const type = String(element.type || '');
                const issue = layoutColumnCapacityIssue(column,type);
                if (issue==='total') return layoutStatus('A column can contain up to twelve elements.');
                if (issue==='type') return layoutStatus(`This column can contain up to ${layoutElementInstanceLimit(type)} ${layoutElementChoices[type]} ${layoutElementInstanceLimit(type)===1?'element':'elements'}.`);
                const copy = regenerateLayoutElementIds(cloneLayoutValue(element));
                column.elements.splice(elementIndex+1,0,copy);
                layoutOpenPath = `${rowIndex}:${columnIndex}:${elementIndex+1}`;
            } else if (action === 'remove') {
                const label = String(layoutElementChoices[element.type] || 'saved').toLowerCase();
                if (!window.confirm(`Delete this ${label} element?`)) return;
                column.elements.splice(elementIndex,1);
                layoutOpenPath = '';
            }
            markDirty();
            renderAll();
        }));
        contentRoot.querySelectorAll('[data-layout-field-path]:not([data-layout-rich]):not([data-layout-media])').forEach(control => control.addEventListener('input', () => {
            const {element} = layoutElementFromControl(control);
            if (!element) return;
            const path = JSON.parse(control.dataset.layoutFieldPath);
            const currentValue = valueAt(element,path);
            const value = control.type === 'checkbox' ? control.checked : (typeof currentValue === 'number' ? Number(control.value) : control.value);
            setValue(element,path,value);
            markDirty();
        }));
        contentRoot.querySelectorAll('[data-layout-rerender]').forEach(control => control.addEventListener('change', () => renderAll()));
        contentRoot.querySelectorAll('[data-layout-rich]').forEach(editor => editor.addEventListener('input', () => {
            const {element} = layoutElementFromControl(editor);
            if (!element) return;
            setValue(element,JSON.parse(editor.dataset.layoutFieldPath),editor.innerHTML);
            markDirty();
        }));
        contentRoot.querySelectorAll('[data-layout-format]').forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            const editor = document.getElementById(button.dataset.layoutRichEditor);
            editor?.focus();
            if (button.dataset.layoutFormat === 'createLink') {
                const destination = window.prompt('Enter the link address:','https://');
                if (destination) document.execCommand('createLink',false,destination);
            } else document.execCommand(button.dataset.layoutFormat,false,null);
            editor?.dispatchEvent(new Event('input',{bubbles:true}));
        }));
        contentRoot.querySelectorAll('[data-layout-clear-image]').forEach(button => button.addEventListener('click', () => {
            const {element} = layoutElementFromControl(button);
            if (!element || !['card','quote'].includes(String(element.type || ''))) return;
            const path = JSON.parse(button.dataset.layoutFieldPath);
            const definition = layoutElementCatalog[element.type]?.fields?.[path[0]];
            if (definition?.kind !== 'managed_image' || definition?.required !== false || !String(valueAt(element,path) || '').trim()) return;
            setValue(element,path,'');
            markDirty();
            renderAll();
        }));
        contentRoot.querySelectorAll('[data-layout-media]').forEach(select => select.addEventListener('change', () => {
            const {element} = layoutElementFromControl(select);
            if (!element) return;
            const path = JSON.parse(select.dataset.layoutFieldPath);
            const definition = layoutFieldDefinitionAtPath(element,path);
            const currentValue = String(valueAt(element,path) || '');
            const asset = mediaAssets.find(candidate => String(candidate.url) === String(select.value));
            if (!asset) {
                select.value = currentValue;
                if (definition?.required === true) layoutStatus('This media is required. Choose an approved Media Library item.');
                return;
            }
            setValue(element,path,String(asset.url));
            if (select.dataset.layoutMedia === 'image') {
                const parentPath = path.slice(0,-1);
                const parent = parentPath.length ? valueAt(element,parentPath) : element;
                const altField = parent && Object.prototype.hasOwnProperty.call(parent,'alt')
                    ? 'alt'
                    : parent && Object.prototype.hasOwnProperty.call(parent,'image_alt') ? 'image_alt' : null;
                if (altField && !String(parent[altField] || '').trim() && asset.alt) parent[altField] = String(asset.alt);
            }
            markDirty();
            renderAll();
        }));
        contentRoot.querySelectorAll('[data-layout-new-element]').forEach(picker => picker.addEventListener('change', () => {
            const rows = layoutRows();
            const column = rows[Number(picker.dataset.layoutRow)]?.columns?.[Number(picker.dataset.layoutColumn)];
            const addButton = picker.closest('.reuse-layout-add')?.querySelector('[data-layout-add-element]');
            if (addButton) addButton.disabled = !layoutColumnCanAccept(column,picker.value);
        }));
        contentRoot.querySelectorAll('[data-layout-repeater-action]').forEach(button => button.addEventListener('click', () => {
            const {element} = layoutElementFromControl(button);
            if (!element) return;
            const path = JSON.parse(button.dataset.layoutRepeaterPath);
            const items = valueAt(element,path);
            if (!Array.isArray(items)) return;
            const action = button.dataset.layoutRepeaterAction;
            const index = Number(button.dataset.index);
            if (action === 'add') {
                const definition = layoutElementCatalog[element.type]?.fields?.[path[0]];
                const max = Number(definition?.bounds?.max_items || 12);
                if (!definition || items.length >= max) return layoutStatus(`This list can contain up to ${max} items.`);
                items.push(layoutRepeaterItem(definition));
            } else if (action === 'remove') {
                if (!window.confirm('Remove this item from the element?')) return;
                items.splice(index,1);
            } else if (action === 'up' && index > 0) {
                [items[index-1],items[index]] = [items[index],items[index-1]];
            } else if (action === 'down' && index < items.length-1) {
                [items[index+1],items[index]] = [items[index],items[index+1]];
            }
            markDirty();
            renderAll();
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
        if (blockType === 'layout' && layoutLoadGuard().blocked) {
            updateSaveButton();
            return;
        }
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
