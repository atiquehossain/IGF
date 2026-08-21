@extends('admin.layouts.master')

@section('content')
@php
    $permission = app(\App\Http\Middleware\Permission::class);
    $admin = auth('admin')->user();
    $builderPermissions = [
        'edit' => $permission->allows($admin, 'page.builder.edit'),
        'create' => $permission->allows($admin, 'page.builder.create'),
        'delete' => $permission->allows($admin, 'page.builder.destroy'),
        'editReusable' => $permission->allows($admin, 'reusable-blocks.edit'),
    ];
    $canEditBuilder = $builderPermissions['edit'];
    $canCreateBuilder = $builderPermissions['create'];
    $canDeleteBuilder = $builderPermissions['delete'];
    $canManagePublication = $permission->allows($admin, 'page.status');
    $canEditSeo = $permission->allows($admin, 'seo.content.edit');
    $canManageHomeBanners = $permission->allows($admin, 'banner.index');
    $selectedTagIds = $page->pageTags->pluck('tag_id')->map(fn ($id) => (int) $id)->all();
    $rawThumbnail = trim((string) $page->getRawOriginal('thumbnail'));
    $currentThumbnailUrl = $rawThumbnail === ''
        ? ''
        : (\Illuminate\Support\Str::startsWith($rawThumbnail, ['/', 'http://', 'https://'])
            ? $rawThumbnail
            : '/storage/photos/1/page/' . rawurlencode(basename(str_replace('\\', '/', $rawThumbnail))));
    $keepCurrentCategory = filled($page->category_id) && !$pageCategories->contains('id', (int) $page->category_id);
    $keepCurrentBanner = filled($page->banner_id) && !$pageBanners->contains('id', (int) $page->banner_id);
    $simpleReusableSections = $reusableBlocks->map(fn ($reusable) => [
        'uuid' => $reusable->uuid,
        'name' => $reusable->name,
        'type' => $reusable->type,
        'locale' => $reusable->locale,
    ])->values();
@endphp
<style>
    body.layout-wrapper .right-panel{height:100vh;min-height:0;padding-top:0!important;overflow:hidden}body.layout-wrapper .right-panel>header.header.igf-topbar,body.layout-wrapper footer.site-footer{display:none!important}body.layout-wrapper .right-panel>.container-fluid,body.layout-wrapper .right-panel>.container-fluid>.row,body.layout-wrapper .right-panel>.container-fluid>.row>.col-md-12{height:100%}
    .simple-editor{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#6d6a67;--line:#e5dfd9;display:flex;height:100vh;min-height:0;flex-direction:column;background:#f4f3f1;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.simple-editor *{box-sizing:border-box}.simple-topbar{z-index:30;display:flex;min-height:72px;align-items:center;justify-content:space-between;gap:18px;padding:10px 22px;border-bottom:1px solid var(--line);background:#fff}.simple-topbar__title{display:flex;min-width:0;align-items:center;gap:14px}.simple-back{display:grid;width:40px;height:40px;flex:0 0 auto;place-content:center;border:1px solid var(--line);border-radius:9px;color:#4d4945;text-decoration:none}.simple-topbar h1{overflow:hidden;margin:0;font:700 20px/1.2 'Literata',Georgia,serif;text-overflow:ellipsis;white-space:nowrap}.simple-topbar p{margin:3px 0 0;color:var(--muted);font-size:11px}.simple-actions{display:flex;align-items:center;gap:8px}.simple-btn{display:inline-flex;min-height:40px;align-items:center;justify-content:center;gap:7px;padding:8px 13px;border:1px solid #d7cfc7;border-radius:8px;background:#fff;color:#3f3b38;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none!important;white-space:nowrap}.simple-btn:hover{border-color:var(--orange);color:var(--brown)}.simple-btn--primary{border-color:var(--orange);background:var(--orange);color:#fff!important;box-shadow:0 5px 13px rgba(255,117,0,.18)}.simple-btn--danger{border-color:#e5b6b1;color:#a52c24}.simple-btn:disabled{cursor:not-allowed;opacity:.55}.simple-save-state{color:var(--muted);font-size:11px;font-weight:750}.simple-save-state.is-dirty{color:#8d570b}.simple-grid{display:grid;grid-template-columns:238px minmax(360px,1fr) 350px;flex:1;min-height:0}.simple-sections,.simple-inspector{min-height:0;overflow-y:auto;background:#fff}.simple-sections{border-right:1px solid var(--line)}.simple-inspector{border-left:1px solid var(--line)}.simple-panel-head{position:sticky;z-index:4;top:0;padding:18px;border-bottom:1px solid var(--line);background:#fff}.simple-panel-head h2{margin:0;font:700 18px/1.2 'Literata',Georgia,serif}.simple-panel-head p{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.45}.simple-sections__body,.simple-inspector__body{padding:16px}.simple-section-list{display:grid;gap:8px;margin:0 0 14px;padding:0;list-style:none}.simple-section-item{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:7px;padding:7px;border:1px solid var(--line);border-radius:9px;background:#fff}.simple-section-item.is-selected{border-color:var(--orange);box-shadow:0 0 0 2px rgba(255,117,0,.1)}.simple-section-item.is-dragging{opacity:.45}.simple-drag{display:grid;width:28px;height:36px;place-content:center;border:0;border-radius:6px;background:#f4f1ee;color:#7c756f;cursor:grab}.simple-select{min-width:0;border:0;background:transparent;text-align:left;cursor:pointer}.simple-select strong,.simple-select small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.simple-select strong{font-size:12px}.simple-select small{margin-top:2px;color:var(--muted);font-size:10px}.simple-order{display:flex;flex-direction:column;gap:2px}.simple-order button{display:grid;width:26px;height:26px;place-content:center;border:1px solid var(--line);border-radius:5px;background:#fff;color:#625c57;cursor:pointer}.simple-order button:disabled{opacity:.3}.simple-page-settings{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.simple-page-settings summary,.simple-options summary{color:var(--brown);font-size:12px;font-weight:850;cursor:pointer}.simple-field{display:grid;gap:6px;margin:0 0 14px}.simple-field>label,.simple-field>span{color:#514c48;font-size:11px;font-weight:850;letter-spacing:.03em;text-transform:uppercase}.simple-field input,.simple-field textarea,.simple-field select{width:100%;min-height:42px;padding:9px 10px;border:1px solid #d9d2cc;border-radius:7px;background:#fff;color:var(--ink);font-size:13px}.simple-field textarea{min-height:90px;resize:vertical}.simple-field input:focus,.simple-field textarea:focus,.simple-field select:focus,.simple-rich:focus{border-color:var(--orange);outline:3px solid rgba(255,117,0,.13)}.simple-check{display:flex;align-items:center;gap:8px;margin:12px 0;color:#494541;font-size:12px;font-weight:750}.simple-check input{width:18px;height:18px;accent-color:var(--orange)}.simple-canvas{min-width:0;min-height:0;padding:20px;overflow:auto}.simple-preview{width:min(100%,1050px);min-height:100%;margin:0 auto;overflow:hidden;border:1px solid #ded9d4;border-radius:11px;background:#fff;box-shadow:0 10px 30px rgba(26,22,20,.05);transition:width .2s}.simple-preview[data-viewport=tablet]{width:min(100%,768px)}.simple-preview[data-viewport=mobile]{width:min(100%,390px)}.simple-preview-block{position:relative;padding:54px 7%;border:3px solid transparent;cursor:pointer}.simple-preview-block:hover,.simple-preview-block.is-selected{border-color:var(--orange)}.simple-preview-block::before{content:attr(data-label);position:absolute;z-index:5;top:9px;left:10px;padding:4px 7px;border-radius:5px;background:#9c4500;color:#fff;font-size:9px;font-weight:850;opacity:0}.simple-preview-block:hover::before,.simple-preview-block.is-selected::before{opacity:1}.simple-preview-block.is-hidden{opacity:.55}.simple-preview-block h2{margin:0 0 12px;font:700 clamp(28px,4vw,54px)/1.08 'Literata',Georgia,serif}.simple-preview-block p{max-width:720px;margin:0 0 16px;line-height:1.6}.simple-preview-block--hero{display:flex;min-height:490px;flex-direction:column;justify-content:center;background:#302d2b center/cover;color:#fff}.simple-preview-block--hero::after{content:'';position:absolute;z-index:0;inset:0;background:rgba(0,0,0,.58)}.simple-preview-block--hero>*{position:relative;z-index:1}.simple-preview-eyebrow{margin-bottom:10px;color:#ff8a26;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.simple-preview-button{display:inline-flex;padding:9px 14px;border-radius:7px;background:var(--orange);color:#fff;font-size:11px;font-weight:850}.simple-preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.simple-preview-stat{padding:18px;border-radius:10px;background:#f7f4f1;text-align:center}.simple-preview-stat strong{display:block;color:var(--brown);font:700 30px 'Literata',serif}.simple-preview-media{display:grid;grid-template-columns:1fr 1.1fr;align-items:center;gap:28px}.simple-preview-media img,.simple-preview-card img{width:100%;height:220px;object-fit:cover;border-radius:10px}.simple-preview-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.simple-preview-card{overflow:hidden;border:1px solid var(--line);border-radius:10px}.simple-preview-card div{padding:14px}.simple-preview-card h3{margin:0 0 7px;font:700 18px 'Literata',serif}.simple-empty{padding:90px 24px;text-align:center;color:var(--muted)}.simple-inspector__head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.simple-type-badge{display:inline-flex;margin-top:7px;padding:4px 7px;border-radius:999px;background:#fff1e5;color:var(--brown);font-size:9px;font-weight:900;text-transform:uppercase}.simple-shared{margin-bottom:14px;padding:11px;border-radius:8px;background:#fff5df;color:#76510e;font-size:11px;line-height:1.5}.simple-rich-toolbar{display:flex;gap:4px;padding:6px;border:1px solid #d9d2cc;border-bottom:0;border-radius:7px 7px 0 0;background:#f7f5f3}.simple-rich-toolbar button{min-width:31px;height:30px;border:1px solid #ddd5ce;border-radius:5px;background:#fff;font-weight:850;cursor:pointer}.simple-rich{min-height:130px;padding:11px;border:1px solid #d9d2cc;border-radius:0 0 7px 7px;background:#fff;font-size:13px;line-height:1.6}.simple-image-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px}.simple-repeat{display:grid;gap:9px;margin-bottom:12px}.simple-repeat-item{padding:11px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-repeat-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}.simple-repeat-head strong{font-size:11px}.simple-repeat-head button{border:0;background:transparent;color:#a52c24;font-size:11px;font-weight:800;cursor:pointer}.simple-options{margin-top:18px;padding-top:14px;border-top:1px solid var(--line)}.simple-inspector-save{position:sticky;bottom:0;padding:13px 16px;border-top:1px solid var(--line);background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}.simple-inspector-save .simple-btn{width:100%}.simple-modal{position:fixed;z-index:1500;inset:0;display:grid;place-items:center;padding:20px;background:rgba(25,28,29,.56)}.simple-modal[hidden]{display:none}.simple-modal__dialog{width:min(820px,100%);max-height:min(760px,92vh);overflow:auto;border-radius:15px;background:#fff;box-shadow:0 25px 70px rgba(0,0,0,.27)}.simple-modal__head{position:sticky;z-index:2;top:0;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid var(--line);background:#fff}.simple-modal__head h2{margin:0;font:700 24px 'Literata',serif}.simple-close{display:grid;width:38px;height:38px;place-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer}.simple-section-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:22px}.simple-section-card{display:grid;grid-template-columns:46px 1fr;gap:13px;padding:16px;border:1px solid var(--line);border-radius:11px;background:#fff;text-align:left;cursor:pointer}.simple-section-card:hover{border-color:var(--orange);background:#fff9f4}.simple-section-card i{display:grid;width:44px;height:44px;place-content:center;border-radius:9px;background:#fff0e3;color:var(--orange);font-size:18px}.simple-section-card strong{display:block;margin-bottom:5px}.simple-section-card span{color:var(--muted);font-size:11px;line-height:1.45}.simple-media-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:20px}.simple-media-option{overflow:hidden;padding:0;border:2px solid transparent;border-radius:9px;background:#f3f1ef;cursor:pointer}.simple-media-option:hover{border-color:var(--orange)}.simple-media-option img{width:100%;aspect-ratio:1;object-fit:cover}.simple-upload{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--line);background:#faf8f6}.simple-notice{position:fixed;z-index:1700;right:24px;bottom:24px;max-width:390px;padding:13px 16px;border-radius:9px;background:#24211f;color:#fff;box-shadow:0 12px 30px rgba(0,0,0,.22);font-size:12px;font-weight:750}.simple-viewport{display:flex;gap:3px;padding:3px;border-radius:8px;background:#efedeb}.simple-viewport button{display:grid;width:32px;height:30px;place-content:center;border:0;border-radius:6px;background:transparent;color:#6c6763;cursor:pointer}.simple-viewport button.is-active{background:#fff;color:var(--brown);box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .simple-preview-cards--four{grid-template-columns:repeat(2,1fr)}
    .simple-order{display:grid;grid-template-columns:repeat(2,26px);gap:2px}.simple-order button[data-delete-section]{grid-column:1/-1;width:54px;height:24px;border-color:#e5b6b1;background:#fff8f7;color:#a52c24}.simple-order button[data-delete-section]:hover{border-color:#a52c24;background:#fff0ee}
    .simple-history{display:flex;gap:5px}.simple-history .simple-btn{min-width:40px;padding:8px 10px}.simple-history .simple-btn:disabled{opacity:.35}.simple-canvas-tip{display:flex;align-items:center;justify-content:center;gap:7px;margin:0 auto 10px;color:var(--muted);font-size:11px;font-weight:750}.simple-canvas-tip i{color:var(--orange)}[data-inline-path]{min-width:12px;border-radius:4px;cursor:text;outline:2px dashed transparent;outline-offset:4px;transition:outline-color .15s,background .15s}[data-inline-path]:hover{outline-color:rgba(255,117,0,.5);background:rgba(255,255,255,.08)}[data-inline-path]:focus{outline:3px solid var(--orange);background:rgba(255,255,255,.13)}[data-inline-path]:empty::before{color:#8b8783;content:attr(data-placeholder)}.simple-link-row{display:grid;grid-template-columns:1fr;gap:7px}.simple-link-row select{background:#fff8f2}.simple-option-actions{display:flex;flex-wrap:wrap;gap:8px}.simple-autosave-note{margin:8px 0 0;color:var(--muted);font-size:10px;line-height:1.4}.simple-animation-panel{margin:0 0 16px;padding:13px;border:1px solid #ead8c8;border-radius:9px;background:#fff8f2}.simple-animation-panel h3{margin:0 0 5px;color:var(--brown);font:700 14px 'Literata',Georgia,serif}.simple-animation-panel>p,.simple-animation-panel fieldset>p{margin:0 0 10px;color:var(--muted);font-size:10px;line-height:1.45}.simple-animation-panel fieldset{margin:0;padding:0;border:0}.simple-animation-panel fieldset:disabled{opacity:.48}.simple-preview-stat.is-animation-count-up,.simple-preview-stat.is-animation-fade-up{animation:simple-stat-fade-up var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both;animation-delay:var(--preview-animation-delay,0ms)}.simple-preview-stat.is-animation-pop{animation:simple-stat-pop var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both;animation-delay:var(--preview-animation-delay,0ms)}@keyframes simple-stat-fade-up{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}@keyframes simple-stat-pop{0%{opacity:0;transform:scale(.84)}70%{opacity:1;transform:scale(1.04)}100%{opacity:1;transform:scale(1)}}@media(prefers-reduced-motion:reduce){.simple-preview-stat{animation:none!important;opacity:1!important;transform:none!important}}
    .simple-read-only{padding:10px 22px;border-bottom:1px solid #e7c785;background:#fff8df;color:#674d12;font-size:12px;line-height:1.5}.simple-read-only strong{margin-right:5px}
    .simple-field small{color:var(--muted);font-size:10px;line-height:1.45}.simple-page-thumbnail{display:block;width:100%;max-height:130px;border:1px solid var(--line);border-radius:7px;object-fit:cover}.simple-tag-options{display:grid;gap:7px;max-height:140px;padding:9px;overflow:auto;border:1px solid #d9d2cc;border-radius:7px;background:#faf9f8}.simple-tag-options label{display:flex;align-items:center;gap:7px;margin:0;color:#494541;font-size:11px;font-weight:700}.simple-tag-options input{width:16px;height:16px;min-height:16px;margin:0;padding:0;border:0;accent-color:var(--orange)}.simple-banner-guidance{margin:4px 0 14px;padding:10px;border-left:3px solid var(--orange);border-radius:5px;background:#fff6eb;color:#694a2c;font-size:10px;line-height:1.5}
    .simple-giving-list{display:grid;gap:8px;margin:0 0 14px}.simple-giving-option{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;min-height:54px;padding:9px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-giving-option.is-unavailable{border-color:#d9a9a4;background:#fff4f2}.simple-giving-option input{width:18px;height:18px;accent-color:var(--orange)}.simple-giving-option strong,.simple-giving-option small{display:block}.simple-giving-option small{margin-top:3px;color:var(--muted);font-size:10px;line-height:1.35}.simple-giving-move{display:flex;gap:4px}.simple-giving-move button{display:grid;min-width:44px;min-height:44px;place-content:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);cursor:pointer}.simple-giving-move button:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:2px}.simple-giving-preview{margin:10px 0 14px;padding:12px;border-radius:8px;background:#f2f6fb;color:#334155;font-size:11px;line-height:1.5}
    .simple-reusable-launch{display:grid;gap:7px;margin-top:8px}.simple-reusable-launch .simple-btn{width:100%;min-height:44px}.simple-reusable-launch small{color:var(--muted);font-size:10px;line-height:1.45}.simple-modal__dialog--compact{width:min(540px,100%)}.simple-reusable-form{display:grid;gap:14px;padding:22px}.simple-reusable-warning{margin:0;padding:12px;border-left:4px solid var(--orange);border-radius:7px;background:#fff5df;color:#6d4a0d;font-size:11px;line-height:1.55}.simple-reusable-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.simple-reusable-empty{grid-column:1/-1;margin:0;padding:30px;text-align:center;color:var(--muted);font-size:12px}.simple-section-card[data-attach-reusable] span span{display:block}.simple-section-card[data-attach-reusable] small{display:block;margin-top:4px;color:var(--brown);font-size:9px;font-weight:800;text-transform:uppercase}
    .simple-draft-conflict{display:grid;gap:10px}.simple-draft-conflict .simple-btn{width:100%}
    @media(max-width:1180px){.simple-grid{grid-template-columns:210px minmax(300px,1fr) 320px}.simple-topbar{padding-inline:14px}.simple-actions .simple-btn:not(.simple-btn--primary),.simple-save-state{display:none}.simple-canvas{padding:12px}}
    @media(max-width:880px){body.layout-wrapper .right-panel{height:auto;min-height:100vh;overflow:visible}.simple-editor{height:auto;min-height:100vh}.simple-grid{display:flex;flex-direction:column}.simple-sections,.simple-inspector{overflow:visible;border:0}.simple-sections{order:1}.simple-canvas{order:2;min-height:600px}.simple-inspector{order:3}.simple-section-list{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-panel-head{position:static}.simple-topbar{position:sticky;top:0;flex-wrap:wrap}.simple-topbar__title p{display:none}}
    @media(max-width:520px){.simple-topbar h1{max-width:145px;font-size:15px}.simple-actions .simple-btn--primary{padding-inline:9px}.simple-history{display:none}.simple-viewport{order:4;width:100%;justify-content:center}.simple-section-list,.simple-section-cards,.simple-media-grid{grid-template-columns:1fr}.simple-preview-media,.simple-preview-cards{grid-template-columns:1fr}.simple-preview-stats{grid-template-columns:1fr}.simple-canvas{padding:8px}.simple-section-cards{padding:14px}}
</style>

<main class="simple-editor" id="simple-editor">
    <header class="simple-topbar">
        <div class="simple-topbar__title">
            <a class="simple-back" href="{{ route('page.index') }}" aria-label="Back to pages"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div><h1 id="simple-page-heading">{{ $page->name }}</h1><p>Simple Editor &middot; {{ strtoupper($page->language) }} &middot; /page/{{ $page->slug }}</p></div>
        </div>
        <div class="simple-viewport" aria-label="Preview size">
            <button type="button" class="is-active" data-viewport="desktop" aria-label="Desktop preview" aria-pressed="true"><i class="fa fa-desktop"></i></button>
            <button type="button" data-viewport="tablet" aria-label="Tablet preview" aria-pressed="false"><i class="fa fa-tablet"></i></button>
            <button type="button" data-viewport="mobile" aria-label="Mobile preview" aria-pressed="false"><i class="fa fa-mobile"></i></button>
        </div>
        <div class="simple-history" aria-label="Editing history">
            <button class="simple-btn" id="simple-undo" type="button" aria-label="Undo last unsaved change" title="Undo" disabled><i class="fa fa-undo" aria-hidden="true"></i></button>
            <button class="simple-btn" id="simple-redo" type="button" aria-label="Redo last unsaved change" title="Redo" disabled><i class="fa fa-repeat" aria-hidden="true"></i></button>
        </div>
        <div class="simple-actions">
            <span class="simple-save-state" id="simple-save-state">{{ $canEditBuilder ? 'All changes saved' : 'Read only' }}</span>
            @if($canEditSeo)<a class="simple-btn" href="{{ route('seo.content.edit', ['type' => 'page', 'id' => $page->getKey(), 'locale' => $page->language]) }}"><i class="fa fa-search" aria-hidden="true"></i> Search &amp; sharing</a>@endif
            @if($canEditBuilder)<a class="simple-btn" href="{{ route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => $page->language, 'mode' => 'advanced']) }}">Advanced mode</a>@endif
            <a class="simple-btn" href="{{ route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]) }}" target="_blank" rel="noopener">Preview</a>
            @if($canEditBuilder)<button class="simple-btn simple-btn--primary" type="button" data-save-changes>Save changes</button>@endif
        </div>
    </header>

    @unless($canEditBuilder)
        <div class="simple-read-only" role="status"><strong>Read-only preview.</strong> You can review this page, but your role cannot edit content or publishing settings.</div>
    @endunless

    <div class="simple-grid">
        <aside class="simple-sections" aria-label="Page sections">
            <div class="simple-panel-head"><h2>Sections</h2><p>{{ $canEditBuilder ? 'Select a section to edit it. Drag sections into the order visitors should see.' : 'Select a section to review its content.' }}</p></div>
            <div class="simple-sections__body">
                @if($canCreateBuilder)<button class="simple-btn simple-btn--primary" style="width:100%" type="button" id="open-add-section"><i class="fa fa-plus"></i> Add section</button>@endif
                @if($canEditBuilder)
                    <div class="simple-reusable-launch">
                        <button class="simple-btn" type="button" id="open-reusable-library" @disabled($reusableBlocks->isEmpty())><i class="fa fa-link" aria-hidden="true"></i> Use a saved section</button>
                        <small id="simple-reusable-library-help">{{ $reusableBlocks->isEmpty() ? 'No reusable sections are available for this language yet.' : 'Add an approved shared section without rebuilding it.' }}</small>
                    </div>
                @endif
                <ol class="simple-section-list" id="simple-section-list" style="margin-top:14px"></ol>
                <details class="simple-page-settings">
                    <summary>Page title and publishing</summary>
                    <div style="padding-top:14px">
                        <label class="simple-field"><span>Page title</span><input id="simple-page-name" maxlength="255" value="{{ $page->name }}" @disabled(!$canEditBuilder)></label>
                        <label class="simple-field"><span>Listing image</span><select id="simple-page-thumbnail" @disabled(!$canEditBuilder)>
                            <option value="" data-url="" @selected($rawThumbnail === '')>No listing image</option>
                            @if($rawThumbnail !== '' && !$selectedThumbnailAssetUuid)<option value="__keep_current" data-url="{{ $currentThumbnailUrl }}" selected>Keep current image</option>@endif
                            @foreach($mediaAssets as $asset)<option value="{{ $asset->uuid }}" data-url="{{ $asset->url }}" @selected($selectedThumbnailAssetUuid === $asset->uuid)>{{ $asset->original_name }}</option>@endforeach
                        </select><small>Choose an uploaded Media Library image for page lists and cards.</small></label>
                        <img id="simple-page-thumbnail-preview" class="simple-page-thumbnail" @if($currentThumbnailUrl !== '') src="{{ $currentThumbnailUrl }}" @endif alt="Selected listing image preview" @if($currentThumbnailUrl === '') hidden @endif>
                        <label class="simple-field" style="margin-top:14px"><span>Category</span><select id="simple-page-category" @disabled(!$canEditBuilder)>
                            <option value="" @selected(blank($page->category_id))>No category</option>
                            @if($keepCurrentCategory)<option value="__keep_current" selected>Keep current unavailable category</option>@endif
                            @foreach($pageCategories as $category)<option value="{{ $category->id }}" @selected((int) $page->category_id === (int) $category->id)>{{ $category->name }}</option>@endforeach
                        </select><small>Only active {{ strtoupper($page->language) }} categories appear here.</small></label>
                        <label class="simple-check"><input id="simple-page-funding-project" type="checkbox" @checked($page->is_funding_project) @disabled(!$canEditBuilder || !$canManageFundingEligibility)> This is a fundable program or project</label>
                        <p class="simple-banner-guidance"><strong>Donation destination:</strong> This applies to every language version. @if($canManageFundingEligibility)Enable it only for a real program or project that donors may fund or finance staff may allocate to.@else Only a Donation Causes editor can change this classification.@endif</p>
                        <label class="simple-check"><input id="simple-page-zakat-eligible" type="checkbox" @checked($page->is_zakat_eligible) @disabled(!$canEditBuilder || !$canManageFundingEligibility || !$page->is_funding_project)> This project may receive Zakat</label>
                        <p class="simple-banner-guidance" id="simple-zakat-eligibility-help"><strong>Zakat setting:</strong> First mark this as a fundable program or project. Then enable Zakat only after confirming it meets the foundation’s Zakat policy. @unless($canManageFundingEligibility)Your role can review these settings, but only a Donation Causes editor can change them.@endunless</p>
                        @if($page->slug === 'home')
                            <div class="simple-banner-guidance"><strong>Home banners are managed separately.</strong> The homepage uses active Home Banner slides, not a page banner selection. @if($canManageHomeBanners)<a href="{{ route('banner.index') }}">Open Home Banners</a>@else Ask a banner editor to update them.@endif An enabled Page Builder Hero still takes precedence over those slides.</div>
                        @else
                            <label class="simple-field"><span>Page banner</span><select id="simple-page-banner" @disabled(!$canEditBuilder)>
                                <option value="" @selected(blank($page->banner_id))>No page banner</option>
                                @if($keepCurrentBanner)<option value="__keep_current" selected>Keep current unavailable banner</option>@endif
                                @foreach($pageBanners as $banner)<option value="{{ $banner->id }}" @selected((int) $page->banner_id === (int) $banner->id)>{{ $banner->name }}</option>@endforeach
                            </select><small>Only active {{ strtoupper($page->language) }} page banners appear here.</small></label>
                            <p class="simple-banner-guidance"><strong>Hero takes precedence:</strong> if this page has an enabled Page Builder Hero section, visitors see that Hero instead of this banner. Hide or remove the Hero to use the selected banner.</p>
                        @endif
                        <div class="simple-field"><span>Tags</span><div class="simple-tag-options" role="group" aria-label="Active page tags">
                            @forelse($activeTags as $tag)
                                <label><input class="simple-page-tag" type="checkbox" value="{{ $tag->id }}" @checked(in_array((int) $tag->id, $selectedTagIds, true)) @disabled(!$canEditBuilder)> {{ $tag->name }}</label>
                            @empty
                                <small>No active tags are available.</small>
                            @endforelse
                        </div><small>Tags group related pages in project lists and visitor browsing.</small></div>
                        <label class="simple-field"><span>Status</span><select id="simple-page-status" @disabled(!$canEditBuilder || !$canManagePublication)>
                            @foreach(['draft'=>'Draft','pending_review'=>'Needs review','published'=>'Published'] as $value => $label)
                                <option value="{{ $value }}" @selected($page->publication_status === $value)>{{ $label }}</option>
                            @endforeach
                            @if(in_array($page->publication_status, ['scheduled','private'], true))
                                <option value="{{ $page->publication_status }}" selected>{{ ucfirst($page->publication_status) }} (manage in Advanced mode)</option>
                            @endif
                        </select><small>@if($canManagePublication)Choose whether visitors can see this page.@else Only a publisher can change status. You can still edit and save the page content safely.@endif</small></label>
                        @if($canEditSeo)
                            <a class="simple-btn" style="width:100%;margin-top:4px" href="{{ route('seo.content.edit', ['type' => 'page', 'id' => $page->getKey(), 'locale' => $page->language]) }}"><i class="fa fa-search" aria-hidden="true"></i> Edit Search &amp; Sharing</a>
                            <p style="color:var(--muted);font-size:11px;line-height:1.5">Search previews and sharing are managed in one guided workspace. Scheduling, private visibility, translations and revisions remain in Advanced mode.</p>
                        @else
                            <p style="color:var(--muted);font-size:11px;line-height:1.5">Your SEO editor manages Search &amp; Sharing. Scheduling, private visibility, translations and revisions remain in Advanced mode.</p>
                        @endif
                    </div>
                </details>
            </div>
        </aside>

        <section class="simple-canvas" aria-label="Live page preview">
            <div class="simple-canvas-tip"><i class="fa {{ $canEditBuilder ? 'fa-pencil' : 'fa-eye' }}" aria-hidden="true"></i> {{ $canEditBuilder ? 'Click any outlined text to edit it directly' : 'Previewing the page in read-only mode' }}</div>
            <div class="simple-preview" id="simple-preview" data-viewport="desktop" aria-live="polite"></div>
        </section>

        <aside class="simple-inspector" aria-label="Selected section editor">
            <div class="simple-panel-head"><div class="simple-inspector__head-row"><div><h2 id="simple-inspector-title">Edit section</h2><span class="simple-type-badge" id="simple-inspector-type">Section</span></div><button type="button" class="simple-btn" id="simple-help" title="Click a section in the preview or section list">?</button></div></div>
            <div class="simple-inspector__body" id="simple-inspector-body"></div>
            @if($canEditBuilder)<div class="simple-inspector-save"><button class="simple-btn simple-btn--primary" type="button" data-save-changes>Save changes</button></div>@endif
        </aside>
    </div>
</main>

@if($canCreateBuilder)
<div class="simple-modal" id="add-section-modal" role="dialog" aria-modal="true" aria-labelledby="add-section-title" hidden>
    <div class="simple-modal__dialog"><header class="simple-modal__head"><div><h2 id="add-section-title">Add a section</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px">Choose what you want visitors to see.</p></div><button class="simple-close" type="button" data-close-modal aria-label="Close">&times;</button></header><div class="simple-section-cards">
        @foreach($simpleSections as $type => $section)
            <button type="button" class="simple-section-card" data-add-section="{{ $type }}"><i class="fa {{ $section['icon'] }}" aria-hidden="true"></i><span><strong>{{ $section['label'] }}</strong><span>{{ $section['description'] }}</span></span></button>
        @endforeach
    </div></div>
</div>
@endif

@if($canEditBuilder)
<div class="simple-modal" id="reusable-library-modal" role="dialog" aria-modal="true" aria-labelledby="reusable-library-title" hidden>
    <div class="simple-modal__dialog">
        <header class="simple-modal__head"><div><h2 id="reusable-library-title">Use a saved section</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px">Choose an approved shared section to add to this page.</p></div><button class="simple-close" type="button" data-close-reusable-library aria-label="Close">&times;</button></header>
        <p class="simple-reusable-warning" style="margin:18px 22px 0"><strong>Shared content:</strong> future library edits can update every page using the section. You can detach it later when this page needs its own copy.</p>
        <div class="simple-section-cards" id="simple-reusable-library"></div>
    </div>
</div>
@endif

@if($canCreateBuilder)
<div class="simple-modal" id="promote-reusable-modal" role="dialog" aria-modal="true" aria-labelledby="promote-reusable-title" hidden>
    <div class="simple-modal__dialog simple-modal__dialog--compact">
        <header class="simple-modal__head"><div><h2 id="promote-reusable-title">Save as a reusable section</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px">Give this saved section a clear library name.</p></div><button class="simple-close" type="button" data-close-promote-reusable aria-label="Close">&times;</button></header>
        <form class="simple-reusable-form" id="simple-promote-reusable-form">
            <label class="simple-field"><span>Reusable section name</span><input id="simple-reusable-name" maxlength="255" required autocomplete="off"><small>Use a name another editor will recognize, such as “Ways to Give — standard cards”.</small></label>
            <p class="simple-reusable-warning" role="note"><strong>This affects pages:</strong> after promotion, future content edits update every page using this reusable section. Visibility and ordering remain page-specific, and you can detach a local copy later.</p>
            <div class="simple-reusable-actions"><button class="simple-btn" type="button" data-close-promote-reusable>Cancel</button><button class="simple-btn simple-btn--primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save as reusable</button></div>
        </form>
    </div>
</div>
@endif

<div class="simple-modal" id="media-modal" role="dialog" aria-modal="true" aria-labelledby="media-modal-title" hidden>
    <div class="simple-modal__dialog"><header class="simple-modal__head"><h2 id="media-modal-title">Choose an image</h2><button class="simple-close" type="button" data-close-media aria-label="Close">&times;</button></header>@if($canCreateBuilder)<div class="simple-upload"><span style="font-size:12px;color:var(--muted)">Upload JPG, PNG, WebP or GIF (maximum 20 MB)</span><label class="simple-btn simple-btn--primary">Upload image<input id="simple-media-upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label></div>@endif<div class="simple-media-grid" id="simple-media-grid">
        @foreach($mediaAssets as $asset)
            <button class="simple-media-option" type="button" data-media-url="{{ $asset->url }}" title="{{ $asset->original_name }}"><img src="{{ $asset->url }}" alt="{{ $asset->alt_text ?: $asset->original_name }}" loading="lazy"></button>
        @endforeach
    </div></div>
</div>
@endsection

@section('custom-js')
<script>
(() => {
    const locale = @json($page->language);
    const pageUuid = @json($page->uuid);
    let editorVersion = @json((int) $page->editor_version);
    const permissions = @json($builderPermissions);
    const canManageFundingEligibility = @json($canManageFundingEligibility);
    const linkTargets = @json($linkTargets);
    const contentOptions = @json($blockContentOptions);
    const reusableSections = @json($simpleReusableSections);
    const routes = {
        simpleSave: @json(route('page.builder.simple.save', $page->uuid)),
        storeBlock: @json(route('page.builder.block.store', $page->uuid)),
        duplicate: @json(route('page.builder.block.duplicate', [$page->uuid, '__BLOCK__'])),
        promote: @json(route('page.builder.block.promote', [$page->uuid, '__BLOCK__'])),
        detach: @json(route('page.builder.block.detach', [$page->uuid, '__BLOCK__'])),
        attachReusable: @json(route('page.builder.reusable.attach', $page->uuid)),
        destroy: @json(route('page.builder.block.destroy', [$page->uuid, '__BLOCK__'])),
        media: @json(route('page.builder.media.store', $page->uuid)),
    };
    const typeLabels = @json($blockTypes);
    const state = {
        blocks: @json($page->blocks),
        selected: @json(optional($page->blocks->first())->uuid),
        dirtyBlocks: new Set(),
        dirtyPage: false,
        dirtyOrder: false,
        busy: false,
        heroSlide: 0,
        mediaTarget: null,
        modalReturn: null,
        undo: [],
        redo: [],
        autosaveTimer: null,
        leaving: false,
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const list = document.getElementById('simple-section-list');
    const preview = document.getElementById('simple-preview');
    const inspector = document.getElementById('simple-inspector-body');
    const saveState = document.getElementById('simple-save-state');
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const plainText = value => String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const safeImage = value => /^(?:https?:\/\/|\/(?!\/))/i.test(String(value || '')) ? String(value) : '';
    const current = () => state.blocks.find(block => block.uuid === state.selected);
    const endpoint = (template, uuid) => template.replace('__BLOCK__', uuid);
    const hasDirty = () => state.dirtyBlocks.size > 0 || state.dirtyPage || state.dirtyOrder;
    const draftKey = `ignite-simple-editor:${pageUuid}:${locale}`;
    const clone = value => JSON.parse(JSON.stringify(value));

    function notify(message) {
        document.querySelector('.simple-notice')?.remove();
        const notice = document.createElement('div');
        notice.className = 'simple-notice'; notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite'); notice.textContent = message;
        document.body.appendChild(notice); setTimeout(() => notice.remove(), 3800);
    }
    function updateSaveState() {
        saveState.textContent = permissions.edit ? (hasDirty() ? 'Unsaved · safely backed up' : 'All changes saved') : 'Read only';
        saveState.classList.toggle('is-dirty', hasDirty());
        document.getElementById('simple-undo').disabled = !permissions.edit || state.undo.length === 0;
        document.getElementById('simple-redo').disabled = !permissions.edit || state.redo.length === 0;
    }
    function markDirty(scope) {
        if (!permissions.edit) return;
        if (scope === 'page') state.dirtyPage = true; else if (scope === 'order') state.dirtyOrder = true; else if (state.selected) state.dirtyBlocks.add(state.selected);
        updateSaveState();
        scheduleDraft();
    }
    function snapshot() {
        return {
            blocks: clone(state.blocks), selected: state.selected, heroSlide: state.heroSlide,
            dirtyBlocks: [...state.dirtyBlocks], dirtyPage: state.dirtyPage, dirtyOrder: state.dirtyOrder,
            pageName: document.getElementById('simple-page-name').value,
            pageStatus: document.getElementById('simple-page-status').value,
            pageThumbnail: document.getElementById('simple-page-thumbnail').value,
            pageCategory: document.getElementById('simple-page-category').value,
            pageBanner: document.getElementById('simple-page-banner')?.value ?? null,
            pageTags: [...document.querySelectorAll('.simple-page-tag:checked')].map(input => input.value),
            pageZakatEligible: document.getElementById('simple-page-zakat-eligible').checked,
            pageFundingProject: document.getElementById('simple-page-funding-project').checked,
        };
    }
    function applySnapshot(saved) {
        state.blocks = clone(saved.blocks || []); state.selected = saved.selected || state.blocks[0]?.uuid || null; state.heroSlide = Number(saved.heroSlide || 0);
        state.dirtyBlocks = new Set(saved.dirtyBlocks || []); state.dirtyPage = !!saved.dirtyPage; state.dirtyOrder = !!saved.dirtyOrder;
        document.getElementById('simple-page-name').value = saved.pageName || '';
        document.getElementById('simple-page-status').value = saved.pageStatus || 'draft';
        if (Object.hasOwn(saved, 'pageThumbnail')) document.getElementById('simple-page-thumbnail').value = saved.pageThumbnail;
        if (Object.hasOwn(saved, 'pageCategory')) document.getElementById('simple-page-category').value = saved.pageCategory;
        if (Object.hasOwn(saved, 'pageBanner') && document.getElementById('simple-page-banner')) document.getElementById('simple-page-banner').value = saved.pageBanner;
        if (Array.isArray(saved.pageTags)) document.querySelectorAll('.simple-page-tag').forEach(input => { input.checked = saved.pageTags.includes(input.value); });
        if (Object.hasOwn(saved, 'pageZakatEligible')) document.getElementById('simple-page-zakat-eligible').checked = !!saved.pageZakatEligible;
        if (Object.hasOwn(saved, 'pageFundingProject')) document.getElementById('simple-page-funding-project').checked = !!saved.pageFundingProject;
        syncFundingProjectControls();
        refreshSimplePageThumbnailPreview();
        renderAll(); updateSaveState(); scheduleDraft();
    }
    function recordHistory() {
        if (!permissions.edit) return;
        state.undo.push(snapshot());
        if (state.undo.length > 40) state.undo.shift();
        state.redo = [];
        updateSaveState();
    }
    function undoChange() {
        if (!state.undo.length) return;
        state.redo.push(snapshot()); applySnapshot(state.undo.pop()); notify('Last unsaved change undone.');
    }
    function redoChange() {
        if (!state.redo.length) return;
        state.undo.push(snapshot()); applySnapshot(state.redo.pop()); notify('Change restored.');
    }
    function scheduleDraft() {
        if (!permissions.edit) return;
        clearTimeout(state.autosaveTimer);
        if (!hasDirty()) { clearDraft(); return; }
        state.autosaveTimer = setTimeout(() => {
            try { sessionStorage.setItem(draftKey, JSON.stringify({version:2,baseEditorVersion:editorVersion,savedAt:Date.now(),...snapshot()})); updateSaveState(); }
            catch (error) { notify('Your browser could not back up this draft. Please save your changes.'); }
        }, 600);
    }
    function clearDraft() {
        clearTimeout(state.autosaveTimer);
        try { sessionStorage.removeItem(draftKey); } catch (error) {}
    }
    function offerStaleDraftBackup(saved) {
        document.querySelector('.simple-draft-conflict')?.remove();
        const notice = document.createElement('div');
        notice.className = 'simple-notice simple-draft-conflict';
        notice.setAttribute('role', 'alert');
        notice.setAttribute('aria-live', 'assertive');
        const message = document.createElement('span');
        message.textContent = 'This browser has an older unsaved draft, but the page changed after that draft was created. It was not applied, so newer website changes remain safe.';
        const download = document.createElement('button');
        download.type = 'button';
        download.className = 'simple-btn';
        download.textContent = 'Download old draft';
        download.addEventListener('click', () => {
            const blob = new Blob([JSON.stringify(saved, null, 2)], {type:'application/json'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `ignite-page-draft-${pageUuid}-${locale}.json`;
            link.click();
            URL.revokeObjectURL(url);
        });
        const discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'simple-btn';
        discard.textContent = 'Discard old draft';
        discard.addEventListener('click', () => { clearDraft(); notice.remove(); });
        notice.append(message, download, discard);
        document.body.appendChild(notice);
    }
    function restoreDraft() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
            if (!saved) return false;
            if (!saved.savedAt) { clearDraft(); return false; }
            if (saved.version !== 2 || Number(saved.baseEditorVersion) !== editorVersion) {
                offerStaleDraftBackup(saved);
                return false;
            }
            applySnapshot(saved); notify('Your locally autosaved draft was recovered.'); return true;
        } catch (error) { clearDraft(); return false; }
    }
    function captureOnFocus(element) {
        element.addEventListener('focus', () => { if (element.dataset.historyReady === 'true') return; element.dataset.historyReady = 'true'; recordHistory(); });
        element.addEventListener('blur', () => { delete element.dataset.historyReady; });
    }
    async function request(url, method, body, form = false) {
        const versionedBody = !form && body && typeof body === 'object'
            ? {...body, expected_version: editorVersion}
            : body;
        const response = await fetch(url, {method, headers: form ? {'Accept':'application/json','X-CSRF-TOKEN':csrf} : {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body: form ? body : (versionedBody ? JSON.stringify(versionedBody) : undefined)});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'The request could not be completed.'));
        if (Number.isInteger(Number(payload.editor_version))) editorVersion = Number(payload.editor_version);
        return payload;
    }
    function selectSection(uuid) {
        if (!uuid || uuid === state.selected) return;
        state.selected = uuid; state.heroSlide = 0; updateSaveState(); renderAll();
    }

    function heroSlides(block) {
        const content = block.content || (block.content = {});
        if (!Array.isArray(content.slides) || !content.slides.length) content.slides = [{
            eyebrow: content.eyebrow || '', heading: content.heading || 'New hero heading', body: content.body || '',
            primary_label: content.primary_label || '', primary_url: content.primary_url || '', secondary_label: content.secondary_label || '', secondary_url: content.secondary_url || '',
            report_label: content.report_label || '', report_url: content.report_url || '', image: content.image || '', overlay_opacity: Number(content.overlay_opacity ?? 64),
        }];
        return content.slides;
    }
    const textField = (key, label, value, options = {}) => `<label class="simple-field"><span>${escapeHtml(label)}</span>${options.textarea ? `<textarea data-content-key="${key}" ${options.max ? `maxlength="${options.max}"` : ''}>${escapeHtml(value)}</textarea>` : `<input data-content-key="${key}" type="${options.type || 'text'}" value="${escapeHtml(value)}" ${options.max ? `maxlength="${options.max}"` : ''}>`}</label>`;
    const selectField = (key, label, value, choices) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-content-key="${key}">${Object.entries(choices).map(([optionValue, optionLabel]) => `<option value="${escapeHtml(optionValue)}" ${String(value) === optionValue ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></label>`;
    const cardSelectField = (index, key, label, value, choices) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-card-index="${index}" data-card-key="${key}">${Object.entries(choices).map(([optionValue, optionLabel]) => `<option value="${escapeHtml(optionValue)}" ${String(value || '') === optionValue ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></label>`;
    const imageField = (key, label, value, slide = false) => `<div class="simple-field"><span>${escapeHtml(label)}</span><div class="simple-image-row"><input ${slide ? `data-slide-key="${key}"` : `data-content-key="${key}"`} value="${escapeHtml(value)}" placeholder="Choose an image">${permissions.edit ? `<button class="simple-btn" type="button" data-choose-image="${key}" ${slide ? 'data-slide-image="true"' : ''}>Choose</button>` : ''}</div></div>`;
    const richField = (key, label, value) => `<div class="simple-field"><span>${escapeHtml(label)}</span><div class="simple-rich-toolbar" role="toolbar" aria-label="Text formatting"><button type="button" data-format="bold" aria-label="Bold">B</button><button type="button" data-format="italic" aria-label="Italic"><em>I</em></button><button type="button" data-format="insertUnorderedList" aria-label="Bulleted list">•</button><button type="button" data-format="createLink" aria-label="Add link">↗</button></div><div class="simple-rich" contenteditable="true" data-rich-key="${key}">${value || ''}</div></div>`;
    function linkField(key, label, value, options = {}) {
        const id = `simple-link-${options.cardIndex ?? 'content'}-${options.slide ? 'slide-' : ''}${key}`;
        const attributes = options.cardIndex !== undefined
            ? `data-card-index="${options.cardIndex}" data-card-key="${key}"`
            : (options.slide ? `data-slide-key="${key}"` : `data-content-key="${key}"`);
        const known = linkTargets.some(target => target.url === value);
        return `<div class="simple-field"><span>${escapeHtml(label)}</span><div class="simple-link-row"><select data-link-picker="${id}" aria-label="Choose ${escapeHtml(label)}"><option value="" ${value ? '' : 'selected'}>Choose a page...</option>${linkTargets.map(target=>`<option value="${escapeHtml(target.url)}" ${target.url===value?'selected':''}>${escapeHtml(target.label)}</option>`).join('')}<option value="__custom" ${value&&!known?'selected':''}>Custom web address...</option></select><input id="${id}" ${attributes} value="${escapeHtml(value || '')}" placeholder="Or enter /page-name or https://..."></div></div>`;
    }

    function contentSource(block) {
        if (block.content?.content_source) return block.content.content_source;
        if (block.type === 'cards') return 'manual';
        if (block.type === 'gallery' && Array.isArray(block.content?.items) && block.content.items.length) return 'manual';
        return Object.keys(contentOptions.sources?.[block.type] || {})[0] || 'manual';
    }
    function sourceChoiceField(block) {
        const choices = contentOptions.sources?.[block.type] || {};
        const source = contentSource(block);
        block.content.content_source = source;
        if (Object.keys(choices).length < 2) return '';
        return `<label class="simple-field"><span>Where should the items come from?</span><select data-content-key="content_source" data-auto-rerender>${Object.entries(choices).map(([value,label])=>`<option value="${escapeHtml(value)}" ${source===value?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select></label>`;
    }
    function availableManagedItems(block, source) {
        let items = [...(contentOptions.items?.[source] || [])];
        if (source === 'projects' && block.content?.tag_slug) items = items.filter(item => (item.tags || []).includes(block.content.tag_slug));
        if (source === 'category' && block.content?.category_slug) items = items.filter(item => item.category === block.content.category_slug);
        return items;
    }
    function renderAutomaticEditor(block) {
        const content = block.content || (block.content = {});
        const source = contentSource(block);
        content.content_source = source;
        content.selection_mode ||= 'automatic';
        content.sort ||= 'featured';
        content.selected_items = Array.isArray(content.selected_items) ? content.selected_items : [];
        const categories = Object.fromEntries((contentOptions.categories || []).map(item => [item.value,item.label]));
        const tags = Object.fromEntries((contentOptions.tags || []).map(item => [item.value,item.label]));
        const candidates = availableManagedItems(block, source);
        const selected = new Set(content.selected_items.map(String));
        const selection = content.selection_mode === 'manual'
            ? `<label class="simple-field"><span>Choose the items to show</span><select multiple size="${Math.min(8,Math.max(3,candidates.length))}" data-content-array-key="selected_items">${candidates.map(item=>`<option value="${escapeHtml(item.value)}" ${selected.has(String(item.value))?'selected':''}>${escapeHtml(item.label)}</option>`).join('')}</select></label><p style="color:var(--muted);font-size:11px">Use Ctrl or Command to choose more than one. Their selected order is preserved.</p>`
            : '';
        const sourceSpecific = source === 'category'
            ? selectField('category_slug','Which category?',content.category_slug || 'our-causes',categories).replace('<select ','<select data-auto-rerender ')
            : source === 'projects'
                ? selectField('tag_slug','Which project group?',content.tag_slug || '',{'':'All published projects',...tags}).replace('<select ','<select data-auto-rerender ')
                : '';
        const linkFields = ['cards','causes','events','team'].includes(block.type)
            ? textField('item_link_label','Item link text',content.item_link_label || '')
            : '';
        const viewAllFields = ['cards','causes','events','gallery'].includes(block.type)
            ? `${textField('view_all_label','“View all” link text',content.view_all_label || '')}${linkField('view_all_url','“View all” destination',content.view_all_url || '')}`
            : '';

        return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true})}${sourceChoiceField(block)}${sourceSpecific}${selectField('sort','Item order',content.sort,contentOptions.sorts || {})}<label class="simple-field"><span>Maximum number of items</span><input data-content-key="limit" type="number" min="1" max="12" value="${Math.min(12,Math.max(1,Number(content.limit || 3)))}"></label>${selectField('selection_mode','How should items be chosen?',content.selection_mode,{automatic:'Keep this section updated automatically',manual:'Choose specific managed items'}).replace('<select ','<select data-auto-rerender ')}${selection}${linkFields}${viewAllFields}${textField('empty_state','Message when there are no published items',content.empty_state || '',{textarea:true,max:300})}<p style="color:var(--muted);font-size:11px">Only published content appears to visitors. Update the individual records in the relevant Content manager.</p>`;
    }
    function renderWaysToGiveEditor(block) {
        const content = block.content || (block.content = {});
        content.layout = ['single_cta','card_grid','banner'].includes(content.layout) ? content.layout : 'card_grid';
        content.selection_mode = ['automatic','manual'].includes(content.selection_mode) ? content.selection_mode : 'automatic';
        content.selected_items = Array.isArray(content.selected_items) ? [...new Set(content.selected_items.map(String))] : [];
        const active = contentOptions.ways_to_give?.items || [];
        const known = contentOptions.ways_to_give?.known_items || [];
        const optionMap = new Map([...known,...active].map(option => [String(option.value), option]));
        const selected = content.selected_items;
        const selectedRows = selected.map((token,index) => {
            const option = optionMap.get(token) || {value:token,label:'Unavailable giving option',active:false,destination:'This managed cause no longer exists.'};
            const unavailable = option.active === false;
            const controlId = `giving-selected-${block.uuid}-${index}`;
            return `<div class="simple-giving-option${unavailable?' is-unavailable':''}"><input id="${escapeHtml(controlId)}" type="checkbox" data-giving-toggle value="${escapeHtml(token)}" checked><label for="${escapeHtml(controlId)}"><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(unavailable?'Unavailable: visitors will not see this option. Remove it or publish the cause again.':option.destination || '')}</small></label><span class="simple-giving-move"><button type="button" data-giving-move="up" data-giving-index="${index}" aria-label="Move ${escapeHtml(option.label)} up" ${index===0?'disabled':''}>↑</button><button type="button" data-giving-move="down" data-giving-index="${index}" aria-label="Move ${escapeHtml(option.label)} down" ${index===selected.length-1?'disabled':''}>↓</button></span></div>`;
        }).join('');
        const unselectedRows = active.filter(option => !selected.includes(String(option.value))).map((option,index) => { const controlId=`giving-available-${block.uuid}-${index}`; return `<div class="simple-giving-option"><input id="${escapeHtml(controlId)}" type="checkbox" data-giving-toggle value="${escapeHtml(option.value)}"><label for="${escapeHtml(controlId)}"><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(option.destination || '')}</small></label><span></span></div>`; }).join('');
        const chooser = content.selection_mode === 'manual'
            ? `<div class="simple-giving-list" role="group" aria-label="Giving options in website order">${selectedRows}${unselectedRows}</div>${selected.length?'<p style="color:var(--muted);font-size:11px">Checked options appear in this order. Use the arrow buttons to reorder them.</p>':'<p class="simple-banner-guidance"><strong>No options selected.</strong> This section will show the empty-state message until you choose an option.</p>'}`
            : `<div class="simple-giving-preview"><strong>Automatically managed:</strong> all ${active.length} active giving options will appear. Newly published causes are added automatically; unavailable causes disappear safely.</div>`;
        const chosenOption = selected.length === 1 ? optionMap.get(selected[0]) : null;
        const singleCauseDestination = content.selection_mode === 'manual'
            && ['single_cta','banner'].includes(content.layout)
            && selected.length === 1
            && chosenOption?.kind === 'cause'
            && chosenOption?.active !== false;
        const projectAllowed = singleCauseDestination && chosenOption?.project_selection === 'optional';
        const fixedProject = singleCauseDestination && chosenOption?.project_selection === 'fixed';
        const allProjects = contentOptions.ways_to_give?.projects || [];
        const allowedValues = new Set(chosenOption?.project_values || []);
        const projects = allProjects.filter(project => allowedValues.has(String(project.value)));
        const selectedProject = projects.find(project => String(project.value) === String(content.project_uuid || ''));
        const projectField = fixedProject
            ? `<div class="simple-giving-preview"><strong>Fixed project:</strong> ${escapeHtml(chosenOption.destination || 'This cause already chooses its exact project.')} Donors do not need another project choice.</div>`
            : projectAllowed
            ? `<label class="simple-field"><span>Preselect a project (optional)</span><select data-content-key="project_uuid"><option value="">Let the donor choose</option>${content.project_uuid&&!selectedProject?`<option value="${escapeHtml(content.project_uuid)}" selected>Previously selected project is unavailable</option>`:''}${projects.map(project=>`<option value="${escapeHtml(project.value)}" ${String(content.project_uuid||'')===String(project.value)?'selected':''}>${escapeHtml(project.label)}</option>`).join('')}</select><small>Only projects accepted by this managed cause are listed. The donation form validates the choice again.</small></label>`
            : (content.project_uuid ? '<p class="simple-banner-guidance"><strong>Project must be cleared.</strong> A project can be preselected only for one managed cause in a Single CTA or Banner. <button class="simple-btn" type="button" data-giving-clear-project>Clear project</button></p>' : '<p style="color:var(--muted);font-size:11px">Project preselection becomes available for one compatible managed cause in a Single CTA or Banner.</p>');
        const previewOption = content.selection_mode === 'manual' ? chosenOption : active[0];
        const behavior = previewOption
            ? `${previewOption.label} → ${selectedProject ? `donate to ${selectedProject.label}` : previewOption.destination}`
            : 'No public giving destination is selected.';

        return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true,max:1200})}${selectField('layout','Presentation',content.layout,{single_cta:'Single CTA',card_grid:'Card grid',banner:'Banner'}).replace('<select ','<select data-giving-rerender ')}${selectField('selection_mode','Giving options',content.selection_mode,{automatic:'All active giving options',manual:'Choose specific options and order'}).replace('<select ','<select data-giving-rerender ')}${chooser}${projectField}${textField('link_label','Button text for managed causes',content.link_label || 'Give now',{max:80})}${textField('empty_state','Message when no option is available',content.empty_state || '',{textarea:true,max:300})}<div class="simple-giving-preview"><strong>Destination preview:</strong> ${escapeHtml(behavior)}</div><p style="color:var(--muted);font-size:11px">Names, descriptions, images, and destinations come from Donation Causes, the Zakat page, and the Sponsor-a-Child page. No web addresses are entered here.</p>`;
    }
    const canEditBlockContent = block => permissions.edit && (!block?.is_reusable || permissions.editReusable);
    const blockForEditorRender = block => block?.is_reusable && !permissions.editReusable
        ? {...block, content: JSON.parse(JSON.stringify(block.content || {}))}
        : block;
    const inlineElement = (tag, value, path, label, options = {}) => `<${tag} ${options.className ? `class="${options.className}"` : ''} ${permissions.edit ? `contenteditable="true" role="textbox" aria-label="Edit ${escapeHtml(label)}" spellcheck="true" data-inline-path="${escapeHtml(path)}" data-placeholder="${escapeHtml(options.placeholder || label)}" ${options.rich ? 'data-inline-rich="true"' : ''} ${options.single ? 'data-inline-single="true"' : ''}` : ''}>${escapeHtml(value || '')}</${tag}>`;

    function renderHeroEditor(block) {
        const slides = heroSlides(block); state.heroSlide = Math.min(state.heroSlide, slides.length - 1); const slide = slides[state.heroSlide];
        return `<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:14px"><strong>Slide ${state.heroSlide + 1} of ${slides.length}</strong><span style="display:flex;gap:5px"><button class="simple-btn" type="button" data-hero-nav="previous" ${state.heroSlide === 0 ? 'disabled' : ''}>←</button><button class="simple-btn" type="button" data-hero-nav="next" ${state.heroSlide === slides.length - 1 ? 'disabled' : ''}>→</button></span></div>
            ${textField('eyebrow','Small heading',slide.eyebrow,{max:120})}${textField('heading','Main heading',slide.heading,{max:180})}${textField('body','Description',slide.body,{textarea:true,max:1200})}${imageField('image','Background image',slide.image,true)}
            ${textField('primary_label','Main button text',slide.primary_label,{max:80})}${linkField('primary_url','Main button destination',slide.primary_url,{slide:true})}${textField('secondary_label','Second button text',slide.secondary_label,{max:80})}${linkField('secondary_url','Second button destination',slide.secondary_url,{slide:true})}
            <div style="display:flex;gap:8px;margin-bottom:14px"><button class="simple-btn" type="button" data-hero-action="add"><i class="fa fa-plus"></i> Add slide</button>${slides.length > 1 ? '<button class="simple-btn simple-btn--danger" type="button" data-hero-action="remove">Remove slide</button>' : ''}</div>`;
    }
    function renderStatsEditor(block) {
        const items = Array.isArray(block.content?.items) ? block.content.items : [];
        const animationEnabled = block.content?.animation_enabled !== false;
        const animationType = ['count_up','fade_up','pop'].includes(block.content?.animation_type) ? block.content.animation_type : 'count_up';
        const animationDuration = Math.min(5000,Math.max(300,Number(block.content?.animation_duration || 1600)));
        const animationDelay = Math.min(1000,Math.max(0,Number(block.content?.animation_delay ?? 120)));
        Object.assign(block.content, {animation_enabled:animationEnabled,animation_type:animationType,animation_duration:animationDuration,animation_delay:animationDelay});
        return `${textField('eyebrow','Small heading',block.content?.eyebrow || '')}${textField('heading','Section heading',block.content?.heading || '')}<section class="simple-animation-panel"><h3>Number animation</h3><p>Choose how these figures appear when a visitor reaches this section.</p><label class="simple-check"><input id="simple-stat-animation-enabled" data-content-key="animation_enabled" type="checkbox" ${animationEnabled?'checked':''}> Animate statistics</label><fieldset id="simple-stat-animation-options" ${animationEnabled?'':'disabled'}>${selectField('animation_type','Animation style',animationType,{count_up:'Count up from zero',fade_up:'Fade upward',pop:'Gentle pop'})}<label class="simple-field"><span>Animation speed</span><select data-content-key="animation_duration"><option value="800" ${animationDuration===800?'selected':''}>Fast</option><option value="1600" ${animationDuration===1600?'selected':''}>Normal</option><option value="2600" ${animationDuration===2600?'selected':''}>Slow</option></select></label><label class="simple-field"><span>Delay between each number</span><select data-content-key="animation_delay"><option value="0" ${animationDelay===0?'selected':''}>Together</option><option value="120" ${animationDelay===120?'selected':''}>Short stagger</option><option value="250" ${animationDelay===250?'selected':''}>Long stagger</option></select></label><p>Visitors who prefer reduced motion automatically see the final numbers without animation.</p></fieldset></section><div class="simple-repeat">${items.map((item,index)=>`<div class="simple-repeat-item"><div class="simple-repeat-head"><strong>Statistic ${index+1}</strong><button type="button" data-remove-stat="${index}">Remove</button></div><label class="simple-field"><span>Number or value</span><input data-stat-index="${index}" data-stat-key="value" value="${escapeHtml(item.value || '')}"></label><label class="simple-field" style="margin-bottom:0"><span>Label</span><input data-stat-index="${index}" data-stat-key="label" value="${escapeHtml(item.label || '')}"></label></div>`).join('')}</div><button class="simple-btn" type="button" id="add-stat"><i class="fa fa-plus"></i> Add statistic</button>`;
    }
    function renderCardsEditor(block) {
        const items = Array.isArray(block.content?.items) ? block.content.items : [];
        const isContributionList = block.type === 'cards' && block.content?.variant === 'contributions';
        const settings = {
            cards:{item:'card',heading:'Card heading',body:isContributionList?'Checklist (one item per line)':'Description',image:'Image',imageAlt:'Describe the image for screen readers',icon:true,linkLabel:block.content?.variant !== 'initiatives',url:'Destination'},
            partners:{item:'partner',heading:'Partner name',body:null,image:'Logo',imageAlt:'Logo description for screen readers',url:'Partner website'},
            faq:{item:'question',heading:'Question',body:'Answer',image:null,url:null},
            timeline:{item:'milestone',heading:'Milestone or year',body:'Description',image:null,url:null},
            gallery:{item:'photo',heading:'Photo caption',body:null,image:'Photo',imageAlt:'Describe the photo for screen readers',url:'Optional destination'},
        }[block.type] || {item:'item',heading:'Heading',body:'Description',image:'Image',url:'Destination'};
        const iconChoices = {'':'No icon',people:'People',map:'Location',heart:'Care and support',school:'Education',health:'Health',water:'Water',leaf:'Environment',relief:'Emergency relief',child:'Children',report:'Report',financials:'Finance',security:'Safeguarding',policy:'Policy'};
        return `${textField('eyebrow','Small heading',block.content?.eyebrow || '')}${textField('heading','Section heading',block.content?.heading || '')}${textField('body','Introduction',block.content?.body || '',{textarea:true})}<div class="simple-repeat">${items.map((item,index)=>`<details class="simple-repeat-item" ${index===0?'open':''}><summary><strong>${escapeHtml(item.heading || `${settings.item} ${index+1}`)}</strong></summary><div style="padding-top:11px"><div class="simple-repeat-head"><span></span><button type="button" data-remove-card="${index}">Remove ${settings.item}</button></div><label class="simple-field"><span>${settings.heading}</span><input data-card-index="${index}" data-card-key="heading" value="${escapeHtml(item.heading || '')}"></label>${settings.body?`<label class="simple-field"><span>${settings.body}</span><textarea data-card-index="${index}" data-card-key="body">${escapeHtml(item.body || '')}</textarea>${isContributionList?'<small>Press Enter after each checklist item. No JSON or special formatting is needed.</small>':''}</label>`:''}${settings.image ? imageField(`card-image-${index}`,settings.image,item.image || '').replace(`data-content-key="card-image-${index}"`,`data-card-index="${index}" data-card-key="image"`).replace(`data-choose-image="card-image-${index}"`,`data-choose-card-image="${index}"`) : ''}${settings.imageAlt ? `<label class="simple-field"><span>${settings.imageAlt}</span><input data-card-index="${index}" data-card-key="image_alt" maxlength="255" value="${escapeHtml(item.image_alt || item.heading || '')}"><small>Describe useful visual information; do not write “image of”.</small></label>` : ''}${settings.icon ? cardSelectField(index,'icon','Icon shown when there is no image',item.icon || '',iconChoices) : ''}${settings.linkLabel ? `<label class="simple-field"><span>Link text</span><input data-card-index="${index}" data-card-key="link_label" maxlength="120" value="${escapeHtml(item.link_label || '')}" placeholder="Learn more"></label>` : ''}${settings.url ? linkField('url',settings.url,item.url || '',{cardIndex:index}) : ''}</div></details>`).join('')}</div><button class="simple-btn" type="button" id="add-card"><i class="fa fa-plus"></i> Add ${settings.item}</button>`;
    }
    function renderEssentialFields(block) {
        const content = block.content || (block.content = {});
        if (block.type === 'hero') return renderHeroEditor(block);
        if (block.type === 'stats') return renderStatsEditor(block);
        if (block.type === 'ways_to_give') return renderWaysToGiveEditor(block);
        if (contentOptions.sources?.[block.type]) {
            const source = contentSource(block);
            if (source !== 'manual') return renderAutomaticEditor(block);
            if (['cards','gallery'].includes(block.type)) return `${sourceChoiceField(block)}${renderCardsEditor(block)}`;
        }
        if (['cards','partners','faq','timeline','gallery'].includes(block.type)) return renderCardsEditor(block);
        if (block.type === 'rich_text') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${richField('body','Body text',content.body || '')}`;
        if (block.type === 'media_text') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${richField('body','Body text',content.body || '')}${imageField('image','Image',content.image || '')}${textField('image_alt','Describe the image',content.image_alt || '')}${selectField('image_position','Image position',content.image_position || 'left',{left:'Left',right:'Right'})}${textField('link_label','Link text',content.link_label || '')}${linkField('link_url','Link destination',content.link_url || '')}`;
        if (block.type === 'video') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true})}${textField('video_url','YouTube, Vimeo, or uploaded video URL',content.video_url || '')}${imageField('poster','Poster image (uploaded videos)',content.poster || '')}${textField('caption','Video caption',content.caption || '')}`;
        if (block.type === 'cta') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Main message',content.heading || '')}${textField('body','Description',content.body || '',{textarea:true})}${textField('primary_label','Main button text',content.primary_label || '')}${linkField('primary_url','Main button destination',content.primary_url || '')}${textField('secondary_label','Second button text',content.secondary_label || '')}${linkField('secondary_url','Second button destination',content.secondary_url || '')}`;
        if (block.type === 'newsletter') return `${textField('heading','Section heading',content.heading || '')}${textField('body','Description',content.body || '',{textarea:true})}${textField('button_label','Button text',content.button_label || '')}`;
        return `<div class="simple-shared">This specialized section is available in Advanced mode. Its content is preserved.</div>`;
    }
    function renderInspector() {
        const block = current();
        if (!block) { document.getElementById('simple-inspector-title').textContent='Edit section'; document.getElementById('simple-inspector-type').textContent='No section selected'; inspector.innerHTML='<div class="simple-empty" style="padding:50px 10px">Add a section or select one from the page preview.</div>'; return; }
        const sharedContentReadOnly = block.is_reusable && !permissions.editReusable;
        const editorBlock = blockForEditorRender(block);
        document.getElementById('simple-inspector-title').textContent = block.label || typeLabels[block.type] || 'Edit section';
        document.getElementById('simple-inspector-type').textContent = typeLabels[block.type] || block.type;
        const duplicateAction = permissions.create ? '<button class="simple-btn" type="button" id="simple-duplicate"><i class="fa fa-copy"></i> Duplicate section</button>' : '';
        const deleteAction = permissions.delete ? '<button class="simple-btn simple-btn--danger" type="button" id="simple-delete"><i class="fa fa-trash"></i> Move to trash</button>' : '';
        const actionGroup = duplicateAction || deleteAction ? `<div class="simple-option-actions">${duplicateAction}${deleteAction}</div>` : '';
        const editNote = sharedContentReadOnly
            ? '<p class="simple-autosave-note">You can still show or hide this section on this page and save that placement change.</p>'
            : (permissions.edit ? '<p class="simple-autosave-note">Your unsaved work is automatically backed up in this browser.</p>' : '<p class="simple-autosave-note">This section is read only for your role.</p>');
        const sharedNotice = block.is_reusable
            ? (permissions.editReusable
                ? `<div class="simple-shared"><strong>Shared section:</strong> Saving content changes updates “${escapeHtml(block.reusable_name || block.label)}” on every page using it. ${permissions.edit?'<button class="simple-btn" type="button" id="simple-detach-reusable"><i class="fa fa-unlink" aria-hidden="true"></i> Detach for this page</button>':''}</div>`
                : `<div class="simple-shared"><strong>Shared content is read only for your role.</strong> Ask a Reusable Sections editor to update “${escapeHtml(block.reusable_name || block.label)}” everywhere, or detach a local copy that you can edit only on this page. ${permissions.edit?'<button class="simple-btn" type="button" id="simple-detach-reusable"><i class="fa fa-unlink" aria-hidden="true"></i> Detach for local editing</button>':''}</div>`)
            : '';
        const reusableAction = !block.is_reusable && permissions.create
            ? '<div class="simple-shared"><strong>Reuse this section on other pages.</strong> Save it to the shared library with a clear name. <button class="simple-btn" type="button" id="simple-promote-reusable"><i class="fa fa-share-alt" aria-hidden="true"></i> Save as reusable</button></div>'
            : '';
        inspector.innerHTML = `${sharedNotice}${reusableAction}${renderEssentialFields(editorBlock)}<details class="simple-options"><summary>Section options</summary><div style="padding-top:14px"><label class="simple-field"><span>Editor label</span><input id="simple-block-label" value="${escapeHtml(block.label || typeLabels[block.type] || '')}"></label><label class="simple-check"><input id="simple-block-enabled" type="checkbox" ${block.is_enabled ? 'checked' : ''}> Show this section on the website</label>${actionGroup}${editNote}</div></details>`;
        if (!permissions.edit || sharedContentReadOnly) {
            inspector.querySelectorAll('input,textarea,select,button,[contenteditable="true"]').forEach(control => {
                const pageOnlyControl = sharedContentReadOnly && control.matches('#simple-block-enabled,#simple-duplicate,#simple-delete,#simple-detach-reusable,[data-hero-nav]');
                const permittedReadOnlyAction = !permissions.edit && control.matches('#simple-duplicate,#simple-delete,#simple-promote-reusable,[data-hero-nav]');
                if (pageOnlyControl || permittedReadOnlyAction) return;
                if (control.hasAttribute('contenteditable')) control.removeAttribute('contenteditable');
                else control.disabled = true;
            });
        }
        wireInspector(block);
    }
    function wireInspector(block) {
        inspector.querySelectorAll('[data-hero-nav]').forEach(button => button.addEventListener('click', () => { state.heroSlide += button.dataset.heroNav === 'next' ? 1 : -1; renderInspector(); }));
        if (permissions.edit) {
            const enabled = inspector.querySelector('#simple-block-enabled');
            if (enabled) {
                captureOnFocus(enabled);
                enabled.addEventListener('change',event=>{block.is_enabled=event.target.checked;markDirty('block');renderList();renderPreview()});
            }
        }
        if (canEditBlockContent(block)) {
            inspector.querySelectorAll('input,textarea,select,[contenteditable="true"]').forEach(control => {
                if (control.id !== 'simple-block-enabled') captureOnFocus(control);
            });
            inspector.querySelectorAll('[data-content-key]').forEach(input => input.addEventListener('input', () => { block.content[input.dataset.contentKey] = input.type === 'checkbox' ? input.checked : input.type === 'number' || ['animation_duration','animation_delay'].includes(input.dataset.contentKey) ? Number(input.value) : input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-content-array-key]').forEach(input => input.addEventListener('change', () => { block.content[input.dataset.contentArrayKey] = [...input.selectedOptions].map(option => option.value); markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-auto-rerender]').forEach(input => input.addEventListener('change', () => { if (input.dataset.contentKey === 'content_source') block.content.selected_items = []; renderInspector(); renderPreview(); }));
            inspector.querySelectorAll('[data-giving-rerender]').forEach(input => input.addEventListener('change', () => {
                const selectionMode = block.content.selection_mode;
                const layout = block.content.layout;
                if (selectionMode !== 'manual' || !['single_cta','banner'].includes(layout) || block.content.selected_items?.length !== 1) block.content.project_uuid = '';
                renderInspector(); renderPreview();
            }));
            inspector.querySelectorAll('[data-giving-toggle]').forEach(input => input.addEventListener('change', () => {
                recordHistory();
                const selected = Array.isArray(block.content.selected_items) ? block.content.selected_items : [];
                block.content.selected_items = input.checked ? [...selected,input.value] : selected.filter(token => token !== input.value);
                block.content.project_uuid = '';
                markDirty('block'); renderAll();
            }));
            inspector.querySelectorAll('[data-giving-move]').forEach(button => button.addEventListener('click', () => {
                const index = Number(button.dataset.givingIndex);
                const target = button.dataset.givingMove === 'up' ? index - 1 : index + 1;
                if (target < 0 || target >= block.content.selected_items.length) return;
                recordHistory();
                [block.content.selected_items[index],block.content.selected_items[target]] = [block.content.selected_items[target],block.content.selected_items[index]];
                markDirty('block'); renderAll();
            }));
            inspector.querySelector('[data-giving-clear-project]')?.addEventListener('click', () => { recordHistory(); block.content.project_uuid=''; markDirty('block'); renderAll(); });
            inspector.querySelectorAll('[data-rich-key]').forEach(editor => editor.addEventListener('input', () => { block.content[editor.dataset.richKey] = editor.innerHTML; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-slide-key]').forEach(input => input.addEventListener('input', () => { heroSlides(block)[state.heroSlide][input.dataset.slideKey] = input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-stat-index]').forEach(input => input.addEventListener('input', () => { block.content.items[Number(input.dataset.statIndex)][input.dataset.statKey] = input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-card-index]').forEach(input => input.addEventListener('input', () => { block.content.items[Number(input.dataset.cardIndex)][input.dataset.cardKey] = input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-link-picker]').forEach(picker => picker.addEventListener('change', () => { if(picker.dataset.historyReady!=='true')recordHistory(); if (picker.value === '__custom') return document.getElementById(picker.dataset.linkPicker)?.focus(); const input=document.getElementById(picker.dataset.linkPicker); if(!input)return; input.value=picker.value; input.dispatchEvent(new Event('input',{bubbles:true})); }));
            inspector.querySelectorAll('[data-format]').forEach(button => button.addEventListener('click', () => { const editor=inspector.querySelector('[contenteditable]'); editor?.focus(); if(button.dataset.format==='createLink'){const url=prompt('Enter the link address:','https://');if(url)document.execCommand('createLink',false,url)}else document.execCommand(button.dataset.format,false,null); editor?.dispatchEvent(new Event('input',{bubbles:true})); }));
            inspector.querySelectorAll('[data-choose-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:button.dataset.slideImage?'slide':'content',key:button.dataset.chooseImage})));
            inspector.querySelectorAll('[data-choose-card-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:'card',index:Number(button.dataset.chooseCardImage),key:'image'})));
        }
        if (canEditBlockContent(block)) {
            inspector.querySelector('[data-hero-action="add"]')?.addEventListener('click', () => { if(heroSlides(block).length>=8)return notify('A hero can contain up to eight slides.'); recordHistory(); heroSlides(block).push({eyebrow:'',heading:'New slide',body:'',primary_label:'Learn more',primary_url:'#',secondary_label:'',secondary_url:'',report_label:'',report_url:'',image:'',overlay_opacity:64}); state.heroSlide=heroSlides(block).length-1; markDirty('block'); renderAll(); });
            inspector.querySelector('[data-hero-action="remove"]')?.addEventListener('click', () => { if(!confirm('Remove this slide?'))return; recordHistory(); heroSlides(block).splice(state.heroSlide,1); state.heroSlide=Math.max(0,state.heroSlide-1); markDirty('block'); renderAll(); });
            inspector.querySelector('#add-stat')?.addEventListener('click',()=>{recordHistory();(block.content.items||(block.content.items=[])).push({value:'0',label:'New statistic'});markDirty('block');renderAll()});
            inspector.querySelector('#simple-stat-animation-enabled')?.addEventListener('change',event=>{const options=inspector.querySelector('#simple-stat-animation-options');if(options)options.disabled=!event.target.checked});
            inspector.querySelectorAll('[data-remove-stat]').forEach(button=>button.addEventListener('click',()=>{recordHistory();block.content.items.splice(Number(button.dataset.removeStat),1);markDirty('block');renderAll()}));
            inspector.querySelector('#add-card')?.addEventListener('click',()=>{recordHistory();const labels={partners:'New partner',faq:'New question',timeline:'New milestone',gallery:'New photo'};const heading=labels[block.type]||'New card';(block.content.items||(block.content.items=[])).push({heading,body:'',image:'',image_alt:['cards','partners','gallery'].includes(block.type)?heading:'',icon:'',url:'',link_label:'Learn more'});markDirty('block');renderAll()});
            inspector.querySelectorAll('[data-remove-card]').forEach(button=>button.addEventListener('click',()=>{recordHistory();block.content.items.splice(Number(button.dataset.removeCard),1);markDirty('block');renderAll()}));
            inspector.querySelector('#simple-block-label')?.addEventListener('input',event=>{block.label=event.target.value;markDirty('block');renderList();renderPreview()});
        }
        inspector.querySelector('#simple-duplicate')?.addEventListener('click',duplicateSection);
        inspector.querySelector('#simple-delete')?.addEventListener('click',()=>deleteSection());
        inspector.querySelector('#simple-promote-reusable')?.addEventListener('click',openPromoteReusable);
        inspector.querySelector('#simple-detach-reusable')?.addEventListener('click',detachReusableSection);
    }

    function renderList() {
        list.innerHTML = state.blocks.length ? state.blocks.map((block,index) => {
            const dragHandle = permissions.edit ? `<button class="simple-drag" type="button" aria-label="Drag ${escapeHtml(block.label)} to reorder">⋮⋮</button>` : '<span aria-hidden="true"></span>';
            const orderActions = permissions.edit ? `<button type="button" data-move="up" data-index="${index}" aria-label="Move ${escapeHtml(block.label)} up" ${index===0?'disabled':''}>↑</button><button type="button" data-move="down" data-index="${index}" aria-label="Move ${escapeHtml(block.label)} down" ${index===state.blocks.length-1?'disabled':''}>↓</button>` : '';
            const deleteAction = permissions.delete ? `<button type="button" data-delete-section="${block.uuid}" aria-label="Move ${escapeHtml(block.label)} to trash" title="Move to trash"><i class="fa fa-trash" aria-hidden="true"></i></button>` : '';
            const actions = orderActions || deleteAction ? `<span class="simple-order">${orderActions}${deleteAction}</span>` : '<span aria-hidden="true"></span>';
            return `<li class="simple-section-item ${block.uuid===state.selected?'is-selected':''}" data-section="${block.uuid}" draggable="${permissions.edit ? 'true' : 'false'}">${dragHandle}<button class="simple-select" type="button" data-select="${block.uuid}" aria-pressed="${block.uuid===state.selected}"><strong>${escapeHtml(block.label || typeLabels[block.type])}</strong><small>${escapeHtml(typeLabels[block.type] || block.type)}${block.is_enabled?'':' · Hidden'}</small></button>${actions}</li>`;
        }).join('') : '<li class="simple-empty" style="padding:25px 5px">This page has no sections yet.</li>';
        wireOrdering();
    }
    function previewBlock(block) {
        const c=block.content||{};const selected=block.uuid===state.selected?' is-selected':'';const hidden=block.is_enabled?'':' is-hidden';const label=escapeHtml(block.label||typeLabels[block.type]);
        if(block.type==='hero'){const slide=heroSlides(block)[state.heroSlide]||heroSlides(block)[0];const image=safeImage(slide.image);return `<section class="simple-preview-block simple-preview-block--hero${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}" ${image?`style="background-image:url(&quot;${escapeHtml(image)}&quot;)"`:''}>${inlineElement('div',slide.eyebrow,'slide.eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',slide.heading,'slide.heading','main heading',{single:true})}${inlineElement('p',slide.body,'slide.body','description')}${slide.primary_label?inlineElement('span',slide.primary_label,'slide.primary_label','main button text',{className:'simple-preview-button',single:true}):''}</section>`}
        if(block.type==='stats'){const animated=c.animation_enabled!==false;const animationType=['count_up','fade_up','pop'].includes(c.animation_type)?c.animation_type:'count_up';const duration=Math.min(5000,Math.max(300,Number(c.animation_duration||1600)));const delay=Math.min(1000,Math.max(0,Number(c.animation_delay??120)));return `<section class="simple-preview-block${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}<div class="simple-preview-stats">${(c.items||[]).map((item,index)=>`<div class="simple-preview-stat${animated?` is-animation-${animationType.replace('_','-')}`:''}" style="--preview-animation-duration:${duration}ms;--preview-animation-delay:${delay*index}ms">${inlineElement('strong',item.value,`items.${index}.value`,'statistic value',{single:true})}${inlineElement('span',item.label,`items.${index}.label`,'statistic label',{single:true})}</div>`).join('')}</div></section>`}
        if(block.type==='media_text'){return `<section class="simple-preview-block${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}"><div class="simple-preview-media">${safeImage(c.image)?`<img src="${escapeHtml(safeImage(c.image))}" alt="${escapeHtml(c.image_alt||'')}">`:'<div style="min-height:220px;border-radius:10px;background:#f2efec"></div>'}<div>${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body),'body','body text',{rich:true})}</div></div></section>`}
        if(block.type==='ways_to_give'){const available=contentOptions.ways_to_give?.items||[];const known=new Map([...(contentOptions.ways_to_give?.known_items||[]),...available].map(option=>[String(option.value),option]));let options=c.selection_mode==='manual'?(c.selected_items||[]).map(token=>known.get(String(token))).filter(option=>option?.active!==false):available;if(c.layout==='single_cta')options=options.slice(0,1);return `<section class="simple-preview-block${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body||''),'body','introduction')}<div class="simple-preview-cards${c.layout==='banner'?' simple-preview-cards--four':''}">${options.map(option=>`<article class="simple-preview-card"><div><i class="fa fa-gift" aria-hidden="true"></i><h3>${escapeHtml(option.label)}</h3><p>${escapeHtml(option.destination||'Managed giving destination')}</p><span class="simple-preview-button">${escapeHtml(c.link_label||'Give now')}</span></div></article>`).join('')}</div>${options.length?'':`<p class="simple-banner-guidance">${escapeHtml(c.empty_state||'No giving options selected.')}</p>`}</section>`}
        if(['cards','partners','faq','timeline','gallery'].includes(block.type)){return `<section class="simple-preview-block${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body||''),'body','introduction')}<div class="simple-preview-cards${(c.items||[]).length===4?' simple-preview-cards--four':''}">${(c.items||[]).slice(0,6).map((item,index)=>`<article class="simple-preview-card">${safeImage(item.image)?`<img src="${escapeHtml(safeImage(item.image))}" alt="">`:''}<div>${inlineElement('h3',item.heading||'Item',`items.${index}.heading`,'item heading',{single:true})}${inlineElement('p',plainText(item.body||''),`items.${index}.body`,'item description')}</div></article>`).join('')}</div></section>`}
        return `<section class="simple-preview-block${selected}${hidden}" data-preview-block="${block.uuid}" data-label="${label}">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body||''),'body','description',{rich:block.type==='rich_text'})}${c.primary_label?inlineElement('span',c.primary_label,'primary_label','main button text',{className:'simple-preview-button',single:true}):''}</section>`;
    }
    function setInlineValue(block, path, value, rich = false) {
        if (path.startsWith('slide.')) heroSlides(block)[state.heroSlide][path.split('.')[1]] = value;
        else if (path.startsWith('items.')) { const [,index,key]=path.split('.'); block.content.items[Number(index)][key]=value; }
        else block.content[path] = rich ? `<p>${escapeHtml(value).replace(/\n+/g,'</p><p>')}</p>` : value;
    }
    function wireInlinePreview() {
        preview.querySelectorAll('[data-inline-path]').forEach(element => {
            element.addEventListener('click', event => event.stopPropagation());
            element.addEventListener('focus', () => {
                const section=element.closest('[data-preview-block]'); const uuid=section?.dataset.previewBlock;
                if(uuid && uuid!==state.selected){state.selected=uuid;state.heroSlide=0;renderList();renderInspector();preview.querySelectorAll('[data-preview-block]').forEach(item=>item.classList.toggle('is-selected',item.dataset.previewBlock===uuid));}
                recordHistory();
            });
            element.addEventListener('input', () => { const block=state.blocks.find(item=>item.uuid===element.closest('[data-preview-block]')?.dataset.previewBlock); if(!block)return; setInlineValue(block,(element.dataset.inlinePath || ''),element.textContent.trim(),element.dataset.inlineRich==='true'); markDirty('block'); });
            element.addEventListener('blur', () => renderInspector());
            element.addEventListener('keydown', event => { if(element.dataset.inlineSingle==='true' && event.key==='Enter'){event.preventDefault();element.blur();} });
        });
    }
    function renderPreview(){
        const emptyMessage=permissions.create?'<div class="simple-empty"><h2>Build this page</h2><p>Choose Add section to begin.</p></div>':'<div class="simple-empty"><h2>No sections yet</h2><p>This page does not have any sections to preview.</p></div>';
        preview.innerHTML=state.blocks.length?state.blocks.map(block => previewBlock(blockForEditorRender(block))).join(''):emptyMessage;
        if (!permissions.editReusable) {
            preview.querySelectorAll('[data-preview-block]').forEach(section => {
                const block = state.blocks.find(item => item.uuid === section.dataset.previewBlock);
                if (!block?.is_reusable) return;
                section.dataset.sharedReadOnly = 'true';
                section.querySelectorAll('[data-inline-path]').forEach(element => {
                    element.removeAttribute('contenteditable');
                    element.removeAttribute('role');
                    element.removeAttribute('spellcheck');
                    element.removeAttribute('data-inline-path');
                    element.removeAttribute('data-inline-rich');
                    element.removeAttribute('data-inline-single');
                    element.setAttribute('aria-readonly', 'true');
                    element.title = 'Shared content is read only for your role';
                });
            });
        }
        preview.querySelectorAll('[data-preview-block]').forEach(section=>section.addEventListener('click',event=>{if(!event.target.closest('[data-inline-path]'))selectSection(section.dataset.previewBlock)}));
        if(permissions.edit)wireInlinePreview();
    }
    function renderAll(){renderList();renderPreview();renderInspector()}

    async function saveChanges(){
        if(!permissions.edit||state.busy)return;
        if(!hasDirty())return notify('Everything is already saved.');
        state.busy=true;
        document.querySelectorAll('[data-save-changes]').forEach(button=>button.disabled=true);
        try{
            const body={locale};
            if(state.dirtyPage){
                const categoryId=document.getElementById('simple-page-category').value;
                const bannerSelect=document.getElementById('simple-page-banner');
                const bannerId=bannerSelect?.value;
                const thumbnailAssetUuid=document.getElementById('simple-page-thumbnail').value;
                body.page={
                    name:document.getElementById('simple-page-name').value,
                    publication_status:document.getElementById('simple-page-status').value,
                    tag_ids:[...document.querySelectorAll('.simple-page-tag:checked')].map(input=>Number(input.value)),
                    is_zakat_eligible:document.getElementById('simple-page-zakat-eligible').checked,
                    is_funding_project:document.getElementById('simple-page-funding-project').checked,
                };
                if(categoryId!=='__keep_current')body.page.category_id=categoryId?Number(categoryId):null;
                if(bannerSelect&&bannerId!=='__keep_current')body.page.banner_id=bannerId?Number(bannerId):null;
                if(thumbnailAssetUuid!=='__keep_current')body.page.thumbnail_asset_uuid=thumbnailAssetUuid||null;
            }
            if(state.dirtyBlocks.size)body.blocks=state.blocks.filter(block=>state.dirtyBlocks.has(block.uuid)).map(block=>({uuid:block.uuid,label:block.label,content:block.content||{},is_enabled:!!block.is_enabled,expected_reusable_version:block.reusable_version}));
            if(state.dirtyOrder)body.order=state.blocks.map(item=>item.uuid);
            const payload=await request(routes.simpleSave,'PUT',body);
            (payload.blocks||[]).forEach(saved=>{const block=state.blocks.find(item=>item.uuid===saved.uuid);if(block)Object.assign(block,saved)});
            if(payload.page){document.getElementById('simple-page-heading').textContent=payload.page.name;document.getElementById('simple-page-name').value=payload.page.name;document.getElementById('simple-page-zakat-eligible').checked=!!payload.page.is_zakat_eligible;document.getElementById('simple-page-funding-project').checked=!!payload.page.is_funding_project;syncFundingProjectControls()}
            state.dirtyBlocks=new Set();state.dirtyPage=false;state.dirtyOrder=false;state.undo=[];state.redo=[];clearDraft();updateSaveState();renderAll();notify(payload.message);
        }catch(error){notify(error.message)}finally{state.busy=false;document.querySelectorAll('[data-save-changes]').forEach(button=>button.disabled=false)}
    }
    async function addSection(type){if(!permissions.create)return;try{const payload=await request(routes.storeBlock,'POST',{locale,type,is_enabled:false});payload.block.is_enabled=true;state.blocks.push(payload.block);state.selected=payload.block.uuid;state.heroSlide=0;state.dirtyBlocks.add(payload.block.uuid);updateSaveState();scheduleDraft();closeModal(document.getElementById('add-section-modal'));renderAll();notify('Section added as a draft. Add your content, then choose Save changes to show it on the website.')}catch(error){notify(error.message)}}
    function renderReusableLibrary(){
        const library=document.getElementById('simple-reusable-library');
        const launch=document.getElementById('open-reusable-library');
        const help=document.getElementById('simple-reusable-library-help');
        if(launch)launch.disabled=reusableSections.length===0;
        if(help)help.textContent=reusableSections.length?'Add an approved shared section without rebuilding it.':'No reusable sections are available for this language yet.';
        if(!library)return;
        library.innerHTML=reusableSections.length?reusableSections.map(section=>`<button type="button" class="simple-section-card" data-attach-reusable="${escapeHtml(section.uuid)}"><i class="fa fa-link" aria-hidden="true"></i><span><strong>${escapeHtml(section.name)}</strong><span>${escapeHtml(typeLabels[section.type]||section.type)}</span><small>Shared section</small></span></button>`).join(''):'<p class="simple-reusable-empty">No reusable sections are available for this language yet.</p>';
        library.querySelectorAll('[data-attach-reusable]').forEach(button=>button.addEventListener('click',()=>attachReusableSection(button.dataset.attachReusable)));
    }
    async function attachReusableSection(reusableUuid){
        if(!permissions.edit)return;
        if(hasDirty())return notify('Save your current changes before adding a saved section.');
        try{
            const payload=await request(routes.attachReusable,'POST',{locale,reusable_uuid:reusableUuid});
            state.blocks.push(payload.block);state.selected=payload.block.uuid;state.heroSlide=0;state.undo=[];state.redo=[];
            closeModal(document.getElementById('reusable-library-modal'));renderAll();updateSaveState();notify(payload.message);
        }catch(error){notify(error.message)}
    }
    function openPromoteReusable(){
        if(!permissions.create)return;
        const block=current();if(!block||block.is_reusable)return;
        if(state.dirtyBlocks.has(block.uuid))return notify('Save this section first, then save the confirmed version as reusable.');
        state.modalReturn=document.activeElement;
        const modal=document.getElementById('promote-reusable-modal');
        const input=document.getElementById('simple-reusable-name');
        input.value=block.label||typeLabels[block.type]||'Reusable section';
        modal.hidden=false;requestAnimationFrame(()=>{input.focus();input.select()});
    }
    async function promoteReusableSection(event){
        event.preventDefault();
        if(!permissions.create||state.busy)return;
        const block=current();const input=document.getElementById('simple-reusable-name');
        if(!block||block.is_reusable||!input.reportValidity())return;
        if(state.dirtyBlocks.has(block.uuid))return notify('Save this section first, then save the confirmed version as reusable.');
        const submit=event.currentTarget.querySelector('[type="submit"]');state.busy=true;submit.disabled=true;
        try{
            const payload=await request(endpoint(routes.promote,block.uuid),'POST',{locale,name:input.value.trim(),library_locale:locale});
            Object.assign(block,payload.block);
            if(payload.reusable&&!reusableSections.some(section=>section.uuid===payload.reusable.uuid))reusableSections.push({uuid:payload.reusable.uuid,name:payload.reusable.name,type:payload.reusable.type,locale:payload.reusable.locale});
            renderReusableLibrary();closeModal(document.getElementById('promote-reusable-modal'));renderAll();notify(`${payload.message} Future content edits affect every page using it.`);
        }catch(error){notify(error.message)}finally{state.busy=false;submit.disabled=false}
    }
    async function detachReusableSection(){
        if(!permissions.edit)return;
        const block=current();if(!block?.is_reusable)return;
        if(state.dirtyBlocks.has(block.uuid))return notify('Save or undo this section’s pending changes before detaching it.');
        if(!confirm('Detach this shared section for local editing? This page keeps the current content, but future library updates will no longer apply here.'))return;
        try{
            const payload=await request(endpoint(routes.detach,block.uuid),'POST',{locale});
            Object.assign(block,payload.block);state.undo=[];state.redo=[];renderAll();updateSaveState();notify(payload.message);
        }catch(error){notify(error.message)}
    }
    async function duplicateSection(){if(!permissions.create)return;const block=current();if(!block)return;try{const payload=await request(endpoint(routes.duplicate,block.uuid),'POST',{locale,as_draft:true});payload.block.is_enabled=true;state.blocks.push(payload.block);state.selected=payload.block.uuid;state.heroSlide=0;state.dirtyBlocks.add(payload.block.uuid);state.dirtyOrder=true;state.undo=[];state.redo=[];updateSaveState();scheduleDraft();renderAll();notify('Section duplicated as a draft. Update it, then save your changes.')}catch(error){notify(error.message)}}
	    async function deleteSection(blockUuid=state.selected){if(!permissions.delete)return;const block=state.blocks.find(item=>item.uuid===blockUuid);if(!block)return;if(!confirm(`Move “${block.label}” to trash? It will disappear from this page. A page revision will be kept so an administrator can restore it.`))return;try{const payload=await request(endpoint(routes.destroy,block.uuid),'DELETE',{locale});const wasSelected=block.uuid===state.selected;state.dirtyBlocks.delete(block.uuid);state.blocks=state.blocks.filter(item=>item.uuid!==block.uuid);if(wasSelected)state.selected=state.blocks[0]?.uuid||null;state.undo=[];state.redo=[];scheduleDraft();renderAll();updateSaveState();notify(payload.message)}catch(error){notify(error.message)}}
	    function wireOrdering(){let dragged=null;list.querySelectorAll('[data-select]').forEach(button=>button.addEventListener('click',()=>selectSection(button.dataset.select)));list.querySelectorAll('[data-delete-section]').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();deleteSection(button.dataset.deleteSection)}));if(!permissions.edit)return;list.querySelectorAll('[data-move]').forEach(button=>button.addEventListener('click',()=>{const index=Number(button.dataset.index),target=button.dataset.move==='up'?index-1:index+1;if(target<0||target>=state.blocks.length)return;recordHistory();[state.blocks[index],state.blocks[target]]=[state.blocks[target],state.blocks[index]];markDirty('order');renderAll()}));list.querySelectorAll('[data-section]').forEach(item=>{item.addEventListener('dragstart',event=>{if(!event.target.closest('.simple-drag')){event.preventDefault();return}dragged=item;item.classList.add('is-dragging')});item.addEventListener('dragend',()=>{item.classList.remove('is-dragging');dragged=null});item.addEventListener('dragover',event=>{event.preventDefault();if(!dragged||dragged===item)return;const rect=item.getBoundingClientRect();list.insertBefore(dragged,event.clientY<rect.top+rect.height/2?item:item.nextElementSibling)});item.addEventListener('drop',event=>{event.preventDefault();if(!dragged)return;recordHistory();state.blocks=[...list.querySelectorAll('[data-section]')].map(node=>state.blocks.find(block=>block.uuid===node.dataset.section));markDirty('order');renderAll()})})}
    function openMedia(target){if(!permissions.edit)return;state.mediaTarget=target;state.modalReturn=document.activeElement;document.getElementById('media-modal').hidden=false;document.querySelector('#media-modal .simple-close').focus()}
    function closeModal(modal){modal.hidden=true;state.modalReturn?.focus();state.modalReturn=null}
    function chooseMedia(url){if(!permissions.edit)return;const block=current();if(!block||!state.mediaTarget)return;recordHistory();const target=state.mediaTarget;if(target.kind==='slide')heroSlides(block)[state.heroSlide][target.key]=url;else if(target.kind==='card')block.content.items[target.index][target.key]=url;else block.content[target.key]=url;markDirty('block');closeModal(document.getElementById('media-modal'));renderAll()}
    document.getElementById('simple-media-grid').addEventListener('click',event=>{const option=event.target.closest('[data-media-url]');if(option)chooseMedia(option.dataset.mediaUrl)});
    document.getElementById('simple-media-upload')?.addEventListener('change',async event=>{if(!permissions.create||!permissions.edit)return;const file=event.target.files[0];if(!file)return;const form=new FormData();form.append('locale',locale);form.append('file',file);try{const payload=await request(routes.media,'POST',form,true);const grid=document.getElementById('simple-media-grid');grid.insertAdjacentHTML('afterbegin',`<button class="simple-media-option" type="button" data-media-url="${escapeHtml(payload.asset.url)}"><img src="${escapeHtml(payload.asset.url)}" alt=""></button>`);chooseMedia(payload.asset.url);notify(payload.message)}catch(error){notify(error.message)}finally{event.target.value=''}});

    document.querySelectorAll('[data-save-changes]').forEach(button=>button.addEventListener('click',saveChanges));
    function refreshSimplePageThumbnailPreview(){const select=document.getElementById('simple-page-thumbnail'),image=document.getElementById('simple-page-thumbnail-preview'),url=select.selectedOptions[0]?.dataset.url||'';image.src=url;image.hidden=url===''}
    function syncFundingProjectControls(clearZakat = false){const funding=document.getElementById('simple-page-funding-project'),zakat=document.getElementById('simple-page-zakat-eligible');if(!funding||!zakat)return;if(clearZakat&&!funding.checked)zakat.checked=false;zakat.disabled=!permissions.edit||!canManageFundingEligibility||!funding.checked}
    const pageSettingInputs=document.querySelectorAll('#simple-page-name,#simple-page-status,#simple-page-thumbnail,#simple-page-category,#simple-page-banner,#simple-page-zakat-eligible,#simple-page-funding-project,.simple-page-tag');if(permissions.edit){pageSettingInputs.forEach(input=>{captureOnFocus(input);input.addEventListener(input.matches('select,input[type="checkbox"]')?'change':'input',()=>{if(input.id==='simple-page-funding-project')syncFundingProjectControls(true);markDirty('page');if(input.id==='simple-page-thumbnail')refreshSimplePageThumbnailPreview()})})}syncFundingProjectControls();
    document.getElementById('simple-undo').addEventListener('click',()=>{if(permissions.edit)undoChange()});document.getElementById('simple-redo').addEventListener('click',()=>{if(permissions.edit)redoChange()});
    document.getElementById('open-add-section')?.addEventListener('click',()=>{if(!permissions.create)return;state.modalReturn=document.activeElement;const modal=document.getElementById('add-section-modal');modal.hidden=false;modal.querySelector('[data-add-section]')?.focus()});
    document.getElementById('open-reusable-library')?.addEventListener('click',()=>{if(!permissions.edit||!reusableSections.length)return;state.modalReturn=document.activeElement;const modal=document.getElementById('reusable-library-modal');modal.hidden=false;modal.querySelector('[data-attach-reusable]')?.focus()});
    document.getElementById('simple-promote-reusable-form')?.addEventListener('submit',promoteReusableSection);
    document.querySelectorAll('[data-add-section]').forEach(button=>button.addEventListener('click',()=>addSection(button.dataset.addSection)));
    document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('add-section-modal'))));document.querySelectorAll('[data-close-media]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('media-modal'))));
    document.querySelectorAll('[data-close-reusable-library]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('reusable-library-modal'))));
    document.querySelectorAll('[data-close-promote-reusable]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('promote-reusable-modal'))));
    document.querySelectorAll('.simple-modal').forEach(modal=>modal.addEventListener('click',event=>{if(event.target===modal)closeModal(modal)}));
    document.addEventListener('keydown',event=>{const modal=document.querySelector('.simple-modal:not([hidden])');if(!modal)return;if(event.key==='Escape'){closeModal(modal);return}if(event.key!=='Tab')return;const focusable=[...modal.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href]')].filter(item=>item.getClientRects().length);if(!focusable.length)return;const first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}});
    document.querySelectorAll('.simple-viewport [data-viewport]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.simple-viewport [data-viewport]').forEach(item=>{const active=item===button;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active))});preview.dataset.viewport=button.dataset.viewport}));
    document.getElementById('simple-help').addEventListener('click',()=>notify(permissions.edit?'Click text in the preview to edit it directly, or use the fields on the right. Your draft is backed up automatically until you save.':'This is a read-only preview. Ask an administrator for Page Builder edit access to make changes.'));
    document.addEventListener('keydown',event=>{if(!permissions.edit||!(event.ctrlKey||event.metaKey)||event.altKey||!['z','y'].includes(event.key.toLowerCase())||event.target.matches('input,textarea,select,[contenteditable="true"]'))return;event.preventDefault();if(event.key.toLowerCase()==='y'||event.shiftKey)redoChange();else undoChange()});
    window.addEventListener('beforeunload',event=>{if(!hasDirty()||state.leaving)return;event.preventDefault();event.returnValue='' });
    document.addEventListener('click',event=>{const link=event.target.closest('a[href]');if(!link||!hasDirty()||link.target==='_blank')return;if(!confirm('Leave this page and discard your unsaved changes?'))event.preventDefault();else{state.leaving=true;clearDraft()}},true);
    renderReusableLibrary();
    if(!permissions.edit){clearDraft();updateSaveState();renderAll()}else if(!restoreDraft()){updateSaveState();renderAll()}
})();
</script>
@endsection
