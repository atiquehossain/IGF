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
    $canViewMediaLibrary = $permission->allows($admin, 'media.index');
    $canCreatePage = $permission->allows($admin, 'page.create');
    $canRestoreRevisions = $canEditBuilder
        && $canManagePublication
        && $permission->allows($admin, 'seo.metadata.edit')
        && $builderPermissions['editReusable'];
    $canManageHomeBanners = $permission->allows($admin, 'banner.index');
    $publicUrl = app(\App\Services\SeoMetadataService::class)->publicUrlForPage($page);
    $publicUrlParts = parse_url($publicUrl) ?: [];
    $publicPath = (string) ($publicUrlParts['path'] ?? '/');
    if (filled($publicUrlParts['query'] ?? null)) $publicPath .= '?'.$publicUrlParts['query'];
    $selectedTagIds = $page->pageTags->pluck('tag_id')->map(fn ($id) => (int) $id)->all();
    $rawThumbnail = trim((string) $page->getRawOriginal('thumbnail'));
    $currentThumbnailUrl = $rawThumbnail === ''
        ? ''
        : (\Illuminate\Support\Str::startsWith($rawThumbnail, ['/', 'http://', 'https://'])
            ? $rawThumbnail
            : '/storage/photos/1/page/' . rawurlencode(basename(str_replace('\\', '/', $rawThumbnail))));
    $keepCurrentCategory = filled($page->category_id) && !$pageCategories->contains('id', (int) $page->category_id);
    $keepCurrentBanner = filled($page->banner_id) && !$pageBanners->contains('id', (int) $page->banner_id);
@endphp
<style>
    body.layout-wrapper .right-panel { height:100vh; min-height:0; padding-top:0!important; overflow:hidden; }
    body.layout-wrapper .right-panel > header.header.igf-topbar,
    body.layout-wrapper footer.site-footer { display:none!important; }
    body.layout-wrapper .right-panel > .container-fluid,
    body.layout-wrapper .right-panel > .container-fluid > .row,
    body.layout-wrapper .right-panel > .container-fluid > .row > .col-md-12 { height:100%; }
    .igf-builder { --igf-orange:#ff7500; --igf-primary:#9c4500; --igf-ink:#191c1d; --igf-muted:#5e5d66; --igf-canvas:#f3f4f5; display:flex; flex-direction:column; height:100vh; min-height:0; background:var(--igf-canvas); color:var(--igf-ink); }
    .igf-builder__topbar { z-index:20; display:flex; flex:0 0 64px; align-items:center; justify-content:space-between; gap:20px; min-width:0; padding:8px 28px; background:#f8f9fa; border-bottom:1px solid rgba(25,28,29,.08); }
    .igf-builder__heading { display:flex; align-items:center; min-width:0; gap:24px; }
    .igf-builder__title { min-width:0; }
    .igf-builder__topbar h1 { max-width:520px; margin:0; overflow:hidden; color:var(--igf-ink); font:700 20px/1.15 'Literata',serif; text-overflow:ellipsis; white-space:nowrap; }
    .igf-builder__topbar small { display:block; margin-top:3px; overflow:hidden; color:var(--igf-muted); font-size:11px; text-overflow:ellipsis; white-space:nowrap; }
    .igf-builder__topbar small a { color:var(--igf-primary); font-weight:800; text-decoration:none; }
    .igf-builder__topbar small a:hover,.igf-builder__topbar small a:focus-visible { text-decoration:underline; }
    .igf-builder__viewport { display:flex; flex:0 0 auto; gap:2px; padding:3px; border-radius:8px; background:#edeeef; }
    .igf-viewport-button { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; padding:0; border:0; border-radius:6px; background:transparent; color:#6f6e75; cursor:pointer; }
    .igf-viewport-button.is-active { background:#fff; color:var(--igf-primary); box-shadow:0 1px 3px rgba(36,36,43,.12); }
    .igf-builder__actions { display:flex; align-items:center; gap:9px; min-width:0; }
    .igf-save-state { display:inline-flex; align-items:center; gap:6px; color:var(--igf-muted); font-size:12px; white-space:nowrap; }
    .igf-save-state::before { content:'\f0c7'; font-family:FontAwesome; font-size:11px; }
    .igf-locale { max-width:164px; }
    .igf-builder__grid { display:grid; grid-template-columns:minmax(0,1fr) 340px; flex:1 1 auto; min-height:0; }
    .igf-builder__canvas { grid-column:1; grid-row:1; min-width:0; min-height:0; padding:24px; overflow:auto; background:#f3f4f5; }
    .igf-builder__preview { container-type:inline-size; width:min(100%,1200px); min-height:1000px; margin:0 auto; overflow:hidden; border:1px solid rgba(25,28,29,.06); border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(36,36,43,.05); transform-origin:top center; transition:width .25s ease; }
    .igf-builder__preview[data-viewport="tablet"] { width:min(100%,768px); }
    .igf-builder__preview[data-viewport="mobile"] { width:min(100%,390px); }
    .igf-builder__preview[data-viewport="desktop"] [data-hide-desktop="true"],
    .igf-builder__preview[data-viewport="mobile"] [data-hide-mobile="true"] { display:none; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block { padding-inline:28px; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block h2 { font-size:42px; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block--hero { min-height:560px; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block--stats { margin:48px 0; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block--stats h2 { font-size:30px; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-block--stats .igf-stats { grid-template-columns:1fr; }
    .igf-builder__preview[data-viewport="mobile"] .igf-preview-focus-grid { grid-template-columns:1fr; }
    .igf-builder__inspector { grid-column:2; grid-row:1; display:flex; min-width:0; min-height:0; flex-direction:column; overflow:hidden; border-left:1px solid rgba(25,28,29,.08); background:#fff; }
    .igf-builder__tabs { display:grid; grid-template-columns:repeat(3,1fr); flex:0 0 auto; border-bottom:1px solid rgba(25,28,29,.08); background:#fff; }
    .igf-builder__tab { min-height:49px; padding:10px 6px; border:0; border-bottom:2px solid transparent; background:transparent; color:var(--igf-muted); font-size:12px; font-weight:800; cursor:pointer; }
    .igf-builder__tab.is-active { border-color:var(--igf-primary); background:rgba(156,69,0,.04); color:var(--igf-primary); }
    .igf-builder__panel-scroll { flex:1 1 auto; min-height:0; overflow-y:auto; }
    .igf-panel { padding:22px; }
    .igf-panel[hidden] { display:none!important; }
    .igf-panel > h3,.igf-panel > strong { display:block; margin:0 0 14px; color:var(--igf-ink); font:700 19px/1.25 'Literata',serif; }
    .igf-block-list { display:grid; gap:8px; margin:14px 0 18px; }
    .igf-block-list__item { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:8px; padding:7px; border:1px solid #e1e3e4; border-radius:8px; background:#fff; text-align:left; }
    .igf-block-list__item:hover { border-color:#ffb68a; }
    .igf-block-list__item.is-active { border-color:var(--igf-primary); box-shadow:0 0 0 2px rgba(156,69,0,.09); }
    .igf-block-list__item small { display:block; color:#77777c; }
    .igf-block-select { display:grid; width:100%; min-width:0; grid-template-columns:22px minmax(0,1fr); align-items:center; gap:8px; padding:3px; border:0; background:transparent; color:inherit; cursor:pointer; text-align:left; }
    .igf-block-select:focus-visible { border-radius:5px; outline:2px solid var(--igf-orange); outline-offset:2px; }
    .igf-order { display:flex; flex-direction:column; gap:2px; }
    .igf-order button { width:44px; height:44px; padding:0; border:1px solid #d9dadb; border-radius:4px; background:#fff; color:#5e5d66; line-height:1; }
    .igf-field { margin-bottom:15px; }
    .igf-field label { display:flex; justify-content:space-between; margin-bottom:7px; color:#525158; font-size:12px; font-weight:800; letter-spacing:.02em; }
    .igf-field input,.igf-field textarea,.igf-field select { width:100%; min-height:40px; border:1px solid #d9dadb; border-radius:7px; padding:9px 10px; background:#f8f9fa; color:var(--igf-ink); font-size:13px; outline:0; }
    .igf-field input:focus,.igf-field textarea:focus,.igf-field select:focus { border-color:var(--igf-primary); box-shadow:0 0 0 2px rgba(156,69,0,.08); }
    .igf-field textarea { min-height:86px; resize:vertical; }
    .igf-field-help { display:block; margin-top:6px; color:#747379; font-size:11px; line-height:1.45; }
    .igf-page-thumbnail { display:block; width:100%; max-height:150px; margin-top:8px; border:1px solid #e2e0de; border-radius:8px; object-fit:cover; }
    .igf-tag-options { display:grid; gap:7px; max-height:150px; padding:9px; overflow:auto; border:1px solid #d9dadb; border-radius:7px; background:#f8f9fa; }
    .igf-tag-options label { display:flex; align-items:center; justify-content:flex-start; gap:8px; margin:0; font-size:12px; font-weight:650; letter-spacing:0; }
    .igf-tag-options input { width:16px; height:16px; min-height:16px; margin:0; padding:0; border:0; accent-color:var(--igf-orange); }
    .igf-banner-guidance { margin:8px 0 15px; padding:10px; border-left:3px solid var(--igf-orange); border-radius:5px; background:#fff7ee; color:#65472c; font-size:11px; line-height:1.5; }
    .igf-manage-links { display:flex; flex-wrap:wrap; gap:7px; margin:0 0 14px; }.igf-manage-link { display:inline-flex; min-height:44px; align-items:center; gap:7px; padding:9px 11px; border:1px solid #efb789; border-radius:8px; background:#fff8f2; color:var(--igf-primary)!important; font-size:11px; font-weight:800; text-decoration:none!important; }
    .igf-check { display:flex; align-items:center; gap:8px; margin:10px 0; color:#313135; font-size:13px; }
    .igf-check input { width:auto; accent-color:var(--igf-primary); }
    .igf-giving-list { display:grid; gap:8px; margin:0 0 14px; }.igf-giving-option { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:9px; min-height:56px; padding:9px; border:1px solid #dddfe1; border-radius:8px; background:#f8f9fa; }.igf-giving-option.is-unavailable { border-color:#d9a9a4; background:#fff4f2; }.igf-giving-option input { width:18px; height:18px; accent-color:var(--igf-primary); }.igf-giving-option strong,.igf-giving-option small { display:block; }.igf-giving-option small { margin-top:3px; color:#747379; font-size:10px; line-height:1.35; }.igf-giving-move { display:flex; gap:4px; }.igf-giving-move button { display:grid; min-width:44px; min-height:44px; place-content:center; border:1px solid #d8dadd; border-radius:7px; background:#fff; color:var(--igf-primary); cursor:pointer; }.igf-giving-move button:focus-visible { outline:3px solid rgba(255,117,0,.32); outline-offset:2px; }.igf-giving-preview { margin:10px 0 14px; padding:12px; border-radius:8px; background:#f2f6fb; color:#334155; font-size:11px; line-height:1.5; }
    .igf-add { padding:14px; border:1px dashed #c8c5c2; border-radius:8px; background:#f8f9fa; }
    .igf-media-picker { margin-bottom:15px; }
    .igf-media-dropzone { display:flex; min-height:78px; align-items:center; justify-content:center; margin-top:8px; border:1px dashed #a7a4a1; border-radius:8px; padding:12px; background:#fffaf6; color:#6d5547; font-size:12px; font-weight:800; text-align:center; cursor:pointer; }
    .igf-media-dropzone:hover,.igf-media-dropzone.is-dragging,.igf-media-dropzone:focus-visible { border-color:var(--igf-orange); background:#fff3e9; outline:2px solid rgba(255,117,0,.2); outline-offset:2px; }
    .igf-media-picker__links { display:flex; justify-content:space-between; gap:8px; margin-top:7px; color:var(--igf-muted); font-size:11px; }
    .igf-carousel-settings { margin:14px 0 18px; padding:14px; border:1px solid #e1ddd8; border-radius:9px; background:#fffaf6; }
    .igf-carousel-settings h4 { margin:0 0 10px; color:var(--igf-primary); font-size:13px; }
    .igf-carousel-settings__row { display:grid; grid-template-columns:1fr 110px; align-items:end; gap:12px; }
    .igf-slide-editor { display:grid; gap:12px; margin:16px 0; }
    .igf-slide-card { overflow:hidden; border:1px solid #dedbd7; border-radius:10px; background:#fff; }
    .igf-slide-card__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #ebe7e3; background:#f6f5f3; }
    .igf-slide-card__head strong { color:var(--igf-primary); font-size:12px; }
    .igf-slide-card__actions { display:flex; gap:4px; }
    .igf-slide-card__actions button { min-width:44px; min-height:44px; border:1px solid #d5d0cb; border-radius:5px; background:#fff; color:#514c48; cursor:pointer; }
    .igf-slide-card__actions button:disabled { cursor:not-allowed; opacity:.38; }
    .igf-slide-card__body { padding:13px; }
    .igf-slide-card__body .igf-field:last-child { margin-bottom:0; }
    .igf-slide-limit { margin:-6px 0 12px; color:var(--igf-muted); font-size:11px; }
    .igf-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:44px; border:1px solid #8c7163; border-radius:7px; padding:7px 13px; background:#fff; color:var(--igf-ink); font-size:12px; font-weight:800; cursor:pointer; text-decoration:none!important; white-space:nowrap; }
    .igf-btn:hover { background:#f3f4f5; color:var(--igf-primary); }
    .igf-btn--primary { border-color:#9c4500; background:#9c4500; color:#fff!important; box-shadow:0 4px 10px rgba(120,51,0,.15); }
    .igf-btn--primary:hover,.igf-btn--primary:focus-visible { border-color:#783300; background:#783300; }
    .igf-btn--danger { border-color:#b42318; color:#b42318; }
    .igf-btn--small { min-height:44px; padding:4px 8px; font-size:11px; }
    .igf-publish-control { position:relative; display:inline-flex; }
    .igf-publish-control > .igf-btn:first-child { border-radius:7px 0 0 7px; }
    .igf-publish-control > .igf-btn:nth-child(2) { min-width:44px; margin-left:-1px; border-radius:0 7px 7px 0; padding-inline:8px; }
    .igf-publish-menu { position:absolute; z-index:50; top:calc(100% + 7px); right:0; width:190px; overflow:hidden; border:1px solid #dedbd9; border-radius:8px; padding:5px; background:#fff; box-shadow:0 12px 30px rgba(25,28,29,.16); }
    .igf-publish-menu[hidden] { display:none; }
    .igf-publish-menu button { display:flex; width:100%; min-height:44px; align-items:center; gap:9px; border:0; border-radius:5px; padding:8px 10px; background:transparent; color:var(--igf-ink); font-size:12px; font-weight:800; text-align:left; cursor:pointer; }
    .igf-publish-menu button:hover,.igf-publish-menu button:focus-visible { background:#fff3e9; color:var(--igf-primary); outline:0; }
    .igf-muted { color:#747379; font-size:12px; line-height:1.55; }
    .igf-empty { padding:80px 24px; text-align:center; color:#77777c; }
    .igf-empty h2 { font:700 32px/1.2 'Literata',serif; }
    .igf-preview-block { position:relative; padding:64px 7%; border:2px solid transparent; }
    .igf-preview-block::before { content:attr(data-label); position:absolute; z-index:3; top:10px; left:14px; padding:4px 8px; border-radius:5px; background:var(--igf-primary); color:#fff; font:700 10px/1.2 'Hanken Grotesk',sans-serif; opacity:0; transition:.18s; }
    .igf-preview-block:hover::before,.igf-preview-block.is-selected::before { opacity:1; }
    .igf-preview-block.is-selected { border-color:var(--igf-orange); }
    .igf-preview-block h2 { margin:0 0 16px; font:700 clamp(34px,4.5vw,64px)/1.05 'Literata',serif; letter-spacing:-.02em; }
    .igf-preview-block p { max-width:720px; font-size:17px; line-height:1.55; }
    .igf-preview-block--hero { display:flex; min-height:570px; flex-direction:column; justify-content:center; color:#fff; background:#252525 center/cover; }
    .igf-preview-block--hero::after { content:""; position:absolute; z-index:0; inset:0; background:rgba(0,0,0,var(--overlay-opacity,.64)); }
    .igf-preview-block--hero > * { position:relative; z-index:1; }
    .igf-preview-block--hero .igf-eyebrow { display:inline-flex; align-self:flex-start; padding:5px 9px; border-radius:999px; background:rgba(255,117,0,.92); color:#fff; }
    .igf-preview-block--stats { margin:80px 0; }
    .igf-preview-block--stats h2 { font-size:34px; }
    .igf-preview-block--stats .igf-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
    .igf-preview-giving { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-top:24px; }.igf-preview-giving article { padding:20px; border:1px solid #e3ded9; border-radius:12px; background:#fff8f2; }.igf-preview-giving i { color:var(--igf-orange); font-size:28px; }.igf-preview-giving h3 { margin:12px 0 7px; font:700 18px/1.25 'Literata',serif; }.igf-preview-giving p { margin:0; color:#747379; font-size:12px; }.igf-preview-giving--single { grid-template-columns:1fr; max-width:720px; }.igf-preview-giving--banner { grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); padding:18px; border-radius:14px; background:#2c2723; }
    .igf-preview-block--testimonials { background:#242220; color:#fff; }.igf-preview-block--testimonials h2 { color:inherit; }.igf-preview-testimonials { display:grid; gap:20px; margin-top:28px; }.igf-preview-testimonials article { min-width:0; }.igf-preview-testimonials blockquote { margin:0; font:650 clamp(17px,2.4cqw,24px)/1.45 'Hanken Grotesk',sans-serif; }.igf-preview-testimonial-person { display:flex; align-items:center; gap:12px; margin-top:24px; }.igf-preview-testimonial-person img,.igf-preview-testimonial-avatar { width:52px; height:52px; flex:0 0 52px; border-radius:50%; object-fit:cover; }.igf-preview-testimonial-avatar { display:grid; place-items:center; background:#f4d8c3; color:var(--igf-primary); font-weight:900; }.igf-preview-testimonial-person span { display:grid; gap:2px; }.igf-preview-testimonial-person small { color:inherit; opacity:.72; }.igf-preview-testimonials--spotlight { max-width:760px; margin-inline:auto; }.igf-preview-testimonials--spotlight article { padding:clamp(28px,5cqw,52px); border:1px solid rgba(255,255,255,.18); border-radius:20px; background:#30302f; text-align:center; }.igf-preview-testimonials--spotlight .igf-preview-testimonial-person { justify-content:center; text-align:left; }.igf-preview-block--testimonials-split { background:#f8f7f2; color:var(--igf-ink); }.igf-preview-testimonials--split { grid-template-columns:repeat(2,minmax(0,1fr)); gap:clamp(28px,6cqw,72px); }.igf-preview-testimonials--split article { display:flex; min-height:210px; flex-direction:column; padding:20px 0; }.igf-preview-testimonials--split .igf-preview-testimonial-person { margin-top:auto; }.igf-preview-testimonial-controls { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-top:26px; }.igf-preview-testimonial-dots { display:flex; gap:7px; }.igf-preview-testimonial-dots i { width:7px; height:7px; border-radius:50%; background:#b9b4ad; }.igf-preview-testimonial-dots i:first-child { background:var(--igf-primary); }.igf-preview-testimonial-arrows { display:flex; gap:8px; }.igf-preview-testimonial-arrows i { display:grid; width:42px; height:42px; place-items:center; border-radius:50%; background:#ead8c8; color:var(--igf-primary); font-style:normal; font-weight:900; }.igf-preview-testimonial-arrows i:last-child { background:var(--igf-primary); color:#fff; } @container (max-width:560px) { .igf-preview-testimonials--split { grid-template-columns:1fr; } .igf-preview-testimonials--split article:nth-child(n+2) { display:none; } }
    .igf-preview-block--focus { padding:clamp(48px,6cqw,72px) clamp(20px,4.6cqw,48px); }.igf-preview-focus-grid { position:relative; display:grid; isolation:isolate; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; align-items:stretch; }.igf-preview-focus-grid::before { position:absolute; z-index:-1; top:50%; left:50%; width:min(900px,100%); height:600px; background:radial-gradient(circle,rgba(255,117,0,.18) 0,rgba(255,117,0,0) 70%); content:""; pointer-events:none; transform:translate(-50%,-50%); }.igf-preview-focus-tile { min-width:0; min-height:390px; animation:igf-builder-focus-rise .5s ease-out both; animation-delay:var(--focus-preview-delay,0ms); }.igf-preview-focus-heading { container-type:inline-size; display:flex; overflow:hidden; padding:clamp(28px,4.4cqw,46px); flex-direction:column; justify-content:center; border-radius:16px; background:var(--igf-orange); color:#fff; }.igf-preview-focus-heading .igf-eyebrow { color:#572500; }.igf-preview-focus-heading h2 { max-width:100%; margin:0; font-size:clamp(30px,10.5cqi,44px); line-height:1.08; overflow-wrap:anywhere; }.igf-preview-focus-heading>p { margin:18px 0 0; color:rgba(255,255,255,.9); }.igf-preview-focus-view-all { display:inline-flex; width:fit-content; align-items:center; gap:6px; margin-top:28px; color:#fff; font-size:14px; font-weight:800; }.igf-preview-focus-card { position:relative; z-index:0; display:flex; overflow:hidden; padding:clamp(26px,3.6cqw,38px); flex-direction:column; align-items:flex-start; isolation:isolate; border:1px solid #e3ded9; border-radius:16px; background:#fff; box-shadow:0 8px 22px rgba(25,28,29,.08); color:var(--igf-ink); transition:color .3s ease-out,border-color .3s ease-out,box-shadow .3s ease-out; }.igf-preview-focus-card::before { position:absolute; z-index:-1; inset:0; background:var(--igf-orange); content:""; transform:scaleX(0); transform-origin:left center; transition:transform .5s ease-out; }.igf-preview-focus-card:hover,.igf-preview-focus-card:focus-within { border-color:var(--igf-orange); box-shadow:0 14px 32px rgba(156,69,0,.2); color:#fff; }.igf-preview-focus-card:hover::before,.igf-preview-focus-card:focus-within::before { transform:scaleX(1); }.igf-preview-focus-card img { width:72px; height:72px; flex:0 0 auto; margin-bottom:28px; border-radius:16px; background:#fff2e8; object-fit:cover; transition:background-color .3s ease-out; }.igf-preview-focus-card>i { display:grid; width:72px; height:72px; flex:0 0 auto; margin-bottom:28px; place-items:center; border-radius:16px; background:#fff2e8; color:var(--igf-primary); font-size:34px; transition:background-color .3s ease-out,color .3s ease-out; }.igf-preview-focus-card:hover img,.igf-preview-focus-card:focus-within img,.igf-preview-focus-card:hover>i,.igf-preview-focus-card:focus-within>i { background:rgba(255,255,255,.2); color:#fff; }.igf-preview-focus-card h3 { margin:0 0 16px; font:700 clamp(24px,2.95cqw,31px)/1.22 'Literata',serif; }.igf-preview-focus-card p { margin:0 0 24px; color:var(--igf-muted); font-size:16px; line-height:1.65; transition:color .3s ease-out; }.igf-preview-focus-card:hover p,.igf-preview-focus-card:focus-within p { color:rgba(255,255,255,.92); }.igf-preview-focus-card span { width:fit-content; margin-top:auto; padding:8px 14px; border:1px dashed currentColor; border-radius:999px; background:transparent; color:inherit; font-size:13px; font-weight:800; } @container (max-width:960px) { .igf-preview-focus-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } } @container (max-width:560px) { .igf-preview-focus-grid { grid-template-columns:1fr; } .igf-preview-focus-tile { min-height:320px; } } @keyframes igf-builder-focus-rise { from { opacity:0; transform:translateY(100px); } to { opacity:1; transform:translateY(0); } }
    .igf-preview-block--cta { padding:clamp(38px,5cqw,58px) clamp(18px,4cqw,42px); }
    .igf-preview-cta-panel { position:relative; display:grid; overflow:hidden; grid-template-columns:64px minmax(0,1fr) minmax(205px,auto); align-items:center; gap:clamp(20px,3.5cqw,40px); isolation:isolate; padding:clamp(30px,4.5cqw,48px); border-radius:26px; background:radial-gradient(circle at 92% 2%,rgba(255,117,0,.24),transparent 30%),linear-gradient(135deg,#1c1e20,#292624); box-shadow:0 18px 42px rgba(39,29,22,.18); color:#fff; }
    .igf-preview-cta-panel[data-actions="false"] { grid-template-columns:64px minmax(0,1fr); }
    .igf-preview-cta-panel::before { position:absolute; z-index:-1; top:0; bottom:0; left:0; width:7px; background:linear-gradient(180deg,#ff9b4c,#ff7500 55%,#b94e00); content:""; }
    .igf-preview-cta-signal { display:grid; width:64px; height:64px; place-items:center; align-self:start; border:1px solid rgba(255,172,105,.35); border-radius:19px; background:rgba(255,117,0,.13); color:#ff9b4c; font-size:26px; }
    .igf-preview-cta-content { min-width:0; }
    .igf-preview-cta-content .igf-eyebrow { color:#ffad70; }
    .igf-preview-cta-content h2 { margin-bottom:14px; color:#fff; font-size:clamp(31px,5.2cqw,48px); line-height:1.06; overflow-wrap:anywhere; }
    .igf-preview-cta-content p { margin:0; color:#d9d5d1; }
    .igf-preview-cta-actions { display:grid; min-width:0; gap:10px; }
    .igf-preview-cta-actions>:only-child { grid-column:1/-1; }
    .igf-preview-cta-button { display:flex; min-width:0; min-height:48px; align-items:center; justify-content:space-between; padding:0 18px; border:1px solid #ff7500; border-radius:12px; background:#ff7500; color:#1c1e20; font-size:12px; font-weight:800; line-height:1.25; overflow-wrap:anywhere; }
    .igf-preview-cta-button::after { flex:0 0 auto; margin-left:12px; content:'\2192'; font-size:17px; }
    .igf-preview-cta-button--outline { border-color:rgba(255,255,255,.42); background:rgba(255,255,255,.04); color:#fff; }
    @container (max-width:960px) { .igf-preview-cta-panel { grid-template-columns:58px minmax(0,1fr); } .igf-preview-cta-signal { width:58px; height:58px; } .igf-preview-cta-actions { grid-column:2; grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @container (max-width:520px) { .igf-preview-block--cta { padding:30px 14px; } .igf-preview-cta-panel { grid-template-columns:1fr; gap:20px; padding:28px 22px; border-radius:22px; } .igf-preview-cta-actions { grid-column:auto; grid-template-columns:1fr; } .igf-preview-cta-content h2 { font-size:clamp(30px,10cqw,39px); } }
    .igf-stat { padding:28px 18px; border:1px solid rgba(25,28,29,.06); border-radius:12px; background:#f8f9fa; text-align:center; }
    .igf-stat strong { display:block; margin-bottom:6px; color:var(--igf-primary); font:700 38px/1.1 'Literata',serif; }
    .igf-stat.is-animation-count-up,.igf-stat.is-animation-fade-up { animation:igf-builder-stat-fade-up var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both; animation-delay:var(--preview-animation-delay,0ms); }
    .igf-stat.is-animation-pop { animation:igf-builder-stat-pop var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both; animation-delay:var(--preview-animation-delay,0ms); }
    @keyframes igf-builder-stat-fade-up { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
    @keyframes igf-builder-stat-pop { 0% { opacity:0; transform:scale(.84); } 70% { opacity:1; transform:scale(1.04); } 100% { opacity:1; transform:scale(1); } }
    .igf-eyebrow { margin-bottom:12px; color:#c65300; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .igf-revisions { display:grid; gap:8px; }
    .igf-revision { padding:10px; border:1px solid #e1e3e4; border-radius:7px; }
    .igf-seo-handoff { margin:14px 0; padding:16px; border:1px solid #f0c5aa; border-radius:10px; background:#fff8f3; }
    .igf-seo-handoff strong { display:block; margin-bottom:6px; color:#191c1d; }
    .igf-seo-handoff p { margin:0 0 12px; color:#625e5b; font-size:13px; line-height:1.55; }
    .igf-count-over { color:#b42318; }
    .igf-section-divider { margin:24px -22px 20px; border:0; border-top:1px solid rgba(25,28,29,.08); }
    .igf-accordion { margin:18px 0 0; border-top:1px solid rgba(25,28,29,.08); }
    .igf-accordion summary { padding:16px 0; color:var(--igf-primary); font-weight:800; cursor:pointer; }
    .igf-notice { position:fixed; right:24px; bottom:24px; z-index:1200; max-width:360px; padding:12px 16px; border-radius:8px; background:#24242b; color:#fff; box-shadow:0 8px 24px rgba(0,0,0,.2); }
    .igf-read-only { flex:0 0 auto; padding:11px 28px; border-bottom:1px solid #e7c785; background:#fff8df; color:#674d12; font-size:12px; line-height:1.5; }
    .igf-read-only strong { margin-right:5px; }
    @media (prefers-reduced-motion:reduce) { .igf-stat,.igf-preview-focus-tile { animation:none!important; opacity:1!important; transform:none!important; } .igf-preview-focus-card::before { transition:none!important; } }
    @media (max-width:1199px) {
        .igf-builder__topbar { padding-inline:18px; }
        .igf-builder__heading { gap:12px; }
        .igf-builder__topbar h1 { max-width:330px; }
        .igf-locale { display:none; }
        .igf-builder__grid { grid-template-columns:minmax(0,1fr) 310px; }
        .igf-builder__canvas { padding:16px; }
    }
    @media (max-width:760px) {
        html,body.layout-wrapper { width:100%!important; max-width:100%; min-width:0!important; overflow-x:hidden; }
        body.layout-wrapper .right-panel { width:100%!important; max-width:100%; height:auto; min-height:100vh; overflow:visible; }
        .igf-builder { width:100%; max-width:100%; height:auto; min-height:100vh; }
        .igf-builder__topbar { position:sticky; top:0; flex-basis:auto; align-items:flex-start; padding:10px 14px; }
        .igf-builder__heading { align-items:flex-start; }
        .igf-builder__topbar h1 { max-width:42vw; font-size:16px; }
        .igf-builder__topbar small,.igf-save-state { display:none; }
        .igf-builder__viewport { gap:0; }
        .igf-viewport-button { width:44px; }
        .igf-builder__actions { gap:5px; }
        #block-inspector .igf-builder__actions { flex-wrap:wrap; }
        .igf-btn { padding-inline:9px; }
        .igf-builder__grid { display:flex; flex-direction:column; }
        .igf-builder__canvas { order:1; min-height:620px; padding:10px; overflow:visible; }
        .igf-builder__inspector { order:2; min-height:620px; border-top:1px solid rgba(25,28,29,.08); border-left:0; }
        .igf-builder__panel-scroll { overflow:visible; }
        .igf-preview-block--hero { min-height:480px; }
        .igf-preview-block--stats .igf-stats { grid-template-columns:1fr; }
    }
    @media (max-width:480px) {
        .igf-builder__title { display:none; }
        .igf-builder__topbar { align-items:center; }
    }
    .igf-section-presentation-help{margin:-8px 0 16px}
    .igf-preview-block--presentation-standard{box-shadow:inset 0 0 0 1px rgba(25,28,29,.04)}
    .igf-preview-block--presentation-soft{background-color:#fbf5ef;box-shadow:inset 0 0 0 4px rgba(156,69,0,.08)}
    .igf-preview-block--presentation-framed{margin-inline:clamp(12px,2.5vw,28px);border-color:#d7cbc0;border-radius:20px;background-color:#fff;box-shadow:0 16px 38px rgba(55,42,32,.11)}
    .igf-preview-block--presentation-framed.is-selected{border-color:var(--igf-orange)}
    .igf-preview-block--presentation-contrast{background-color:#282421;color:#fff;box-shadow:inset 0 5px 0 var(--igf-orange)}
    .igf-preview-block--presentation-contrast :is(h1,h2,h3,p,blockquote){color:inherit}
    .igf-preview-block--presentation-contrast :is(.igf-preview-giving article,.igf-preview-focus-card,.igf-stat){background-color:#fff;color:var(--igf-ink)}
    .igf-events-news-guide{margin:0 0 16px;padding:12px;border-left:4px solid var(--igf-orange);border-radius:7px;background:#fff7ee;color:#65472c;font-size:11px;line-height:1.5}.igf-events-news-guide strong{display:block;margin-bottom:3px;color:var(--igf-primary);font-size:12px}.igf-events-news-group{margin:0 0 17px;padding:13px;border:1px solid #dddfe1;border-radius:9px;background:#fafafa}.igf-events-news-group>h4{margin:0 0 4px;font:700 15px/1.25 'Literata',serif}.igf-events-news-group>p{margin:0 0 12px;color:var(--igf-muted);font-size:10px;line-height:1.45}.igf-events-news-list{display:grid;max-height:410px;gap:8px;margin:0 0 10px;overflow:auto}.igf-events-news-option{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;min-height:58px;padding:9px;border:1px solid #dddfe1;border-radius:8px;background:#fff}.igf-events-news-option.is-unavailable{border-color:#d9a9a4;background:#fff4f2}.igf-events-news-option>input{width:18px;height:18px;accent-color:var(--igf-primary)}.igf-events-news-option strong,.igf-events-news-option small{display:block}.igf-events-news-option small{margin-top:3px;color:var(--igf-muted);font-size:10px;line-height:1.35}.igf-events-news-order{display:flex;gap:4px}.igf-events-news-order button{display:grid;min-width:44px;min-height:44px;place-content:center;border:1px solid #d8dadd;border-radius:7px;background:#fff;color:var(--igf-primary);font-weight:850;cursor:pointer}.igf-events-news-order button:disabled{cursor:not-allowed;opacity:.35}
    .igf-preview-block--events-news{background:#fff}.igf-preview-events-news__intro{max-width:760px;margin:0 0 clamp(28px,4cqw,48px)}.igf-preview-events-news__intro p{margin:8px 0 0;color:var(--igf-muted);font-size:14px;line-height:1.65}.igf-preview-events-news{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(270px,.95fr);gap:clamp(28px,5cqw,64px);align-items:start}.igf-preview-events-news__column{min-width:0}.igf-preview-events-news__heading{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid #e3ded9}.igf-preview-events-news__heading h2{margin:0!important;font-size:clamp(27px,4cqw,43px)!important}.igf-preview-events-news__heading h2::after{display:block;width:82px;height:3px;margin-top:10px;border-radius:99px;background:var(--igf-orange);content:''}.igf-preview-events-news__heading span{flex:0 0 auto;color:var(--igf-primary);font-size:11px;font-weight:850}.igf-preview-events-news__list{display:grid}.igf-preview-events-news__event{display:grid;grid-template-columns:116px minmax(0,1fr);gap:18px;padding:17px 0;border-bottom:1px solid #e3ded9}.igf-preview-events-news__event:first-child{padding-top:0}.igf-preview-events-news__event-media{display:grid;width:116px;height:102px;place-items:center;overflow:hidden;border-radius:10px;background:#f2ece6;color:var(--igf-orange);font-size:27px}.igf-preview-events-news__event-media img{width:100%;height:100%;object-fit:cover}.igf-preview-events-news__event-copy{min-width:0}.igf-preview-events-news__event-copy h3{margin:0 0 5px;font:700 clamp(17px,2.25cqw,23px)/1.2 'Literata',serif}.igf-preview-events-news__meta{display:flex;flex-wrap:wrap;gap:5px 12px;margin-bottom:7px;color:#807870;font-size:9px;font-weight:750}.igf-preview-events-news__event-copy p{display:-webkit-box;overflow:hidden;margin:0 0 7px;color:var(--igf-muted);font-size:11px;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}.igf-preview-events-news__link{color:var(--igf-primary);font-size:10px;font-weight:850}.igf-preview-events-news__feature{overflow:hidden;border:1px solid #e3ded9;border-radius:18px;background:#fff;box-shadow:0 14px 34px rgba(39,29,22,.09)}.igf-preview-events-news__feature-media{display:grid;width:100%;aspect-ratio:16/10;place-items:center;overflow:hidden;background:linear-gradient(145deg,#fff3e7,#eee8e1);color:var(--igf-orange);font-size:40px}.igf-preview-events-news__feature-media img{width:100%;height:100%;object-fit:cover}.igf-preview-events-news__feature-copy{padding:clamp(20px,3.2cqw,32px)}.igf-preview-events-news__feature-copy h3{margin:0 0 9px;font:700 clamp(22px,3.1cqw,31px)/1.18 'Literata',serif}.igf-preview-events-news__feature-copy p{display:-webkit-box;overflow:hidden;margin:0 0 14px;color:var(--igf-muted);font-size:12px;line-height:1.55;-webkit-box-orient:vertical;-webkit-line-clamp:4}.igf-preview-events-news__cta{display:inline-flex;min-height:42px;align-items:center;margin-top:18px;padding:9px 16px;border-radius:999px;background:var(--igf-orange);color:#20150c;font-size:11px;font-weight:900}.igf-preview-events-news__empty{margin:0;padding:22px;border:1px dashed #d6cbc1;border-radius:11px;background:#faf8f6;color:var(--igf-muted);font-size:11px;line-height:1.5}.igf-builder__preview[data-viewport="tablet"] .igf-preview-events-news,.igf-builder__preview[data-viewport="mobile"] .igf-preview-events-news{grid-template-columns:1fr}.igf-builder__preview[data-viewport="mobile"] .igf-preview-events-news__heading{align-items:flex-start;flex-direction:column}.igf-builder__preview[data-viewport="mobile"] .igf-preview-events-news__event{grid-template-columns:82px minmax(0,1fr);gap:12px}.igf-builder__preview[data-viewport="mobile"] .igf-preview-events-news__event-media{width:82px;height:78px}@container (max-width:760px){.igf-preview-events-news{grid-template-columns:1fr}}@container (max-width:480px){.igf-preview-events-news__heading{align-items:flex-start;flex-direction:column}.igf-preview-events-news__event{grid-template-columns:82px minmax(0,1fr);gap:12px}.igf-preview-events-news__event-media{width:82px;height:78px}.igf-events-news-option{grid-template-columns:auto minmax(0,1fr)}.igf-events-news-order{grid-column:2}}
    .igf-preview-events-news__cta{display:flex;width:100%;min-height:48px;align-items:center;justify-content:space-between;padding:0 18px;border-radius:14px;box-shadow:0 8px 20px rgba(255,117,0,.2)}
    .igf-team-directory-settings{margin:0 0 18px;padding:14px;border:1px solid #e2c5ab;border-radius:10px;background:#fff8f1}.igf-team-directory-settings legend{padding:0 6px;color:var(--igf-primary);font-size:12px;font-weight:850}.igf-team-directory-settings>p{margin:0 0 13px;color:var(--igf-muted);font-size:11px;line-height:1.5}.igf-team-directory-help{margin:-8px 0 16px}.igf-preview-team-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:24px}.igf-preview-team-grid article{min-width:0;padding:12px 12px 18px;border:1px solid #e1ddd9;border-radius:15px;background:#fff;text-align:center;box-shadow:0 8px 24px rgba(35,28,24,.06)}.igf-preview-team-card__media{display:grid;aspect-ratio:4/5;place-items:center;overflow:hidden;border-radius:11px;background:linear-gradient(145deg,#fff1e5,#e9e3de);color:var(--igf-primary);font:700 clamp(26px,5cqw,48px) 'Literata',serif}.igf-preview-team-card__media img{width:100%;height:100%;object-fit:cover}.igf-preview-team-grid h3{margin:14px 0 5px;font:700 18px 'Literata',serif}.igf-preview-team-grid p{margin:0;color:var(--igf-muted);font-size:12px}@container (max-width:620px){.igf-preview-team-grid{grid-template-columns:1fr 1fr}}@container (max-width:420px){.igf-preview-team-grid{grid-template-columns:1fr}}
    .igf-preview-team-directory{display:grid;grid-template-columns:minmax(170px,.72fr) minmax(220px,1fr) minmax(220px,.9fr);gap:16px;align-items:stretch}.igf-preview-team-directory.is-map-right .igf-preview-team-directory__map{order:3}.igf-preview-team-directory.is-map-right .igf-preview-team-directory__profile{order:1}.igf-preview-team-directory__map,.igf-preview-team-directory__people,.igf-preview-team-directory__profile{min-width:0;padding:16px;border:1px solid #e1ddd9;border-radius:15px;background:#fff}.igf-preview-team-directory__map{background:linear-gradient(160deg,#302c29,#47352a);color:#fff}.igf-preview-team-directory__map h3,.igf-preview-team-directory__people h3,.igf-preview-team-directory__profile h3{margin:0 0 5px;font:700 18px/1.2 'Literata',serif}.igf-preview-team-directory__map h3{color:#fff}.igf-preview-team-directory__map p{margin:0;color:#e8ddd4;font-size:10px}.igf-preview-team-directory__map svg{display:block;width:min(150px,82%);height:auto;margin:14px auto 10px}.igf-preview-team-directory__map path{fill:#ff8c2b;stroke:#fff;stroke-width:2}.igf-preview-team-directory__filters{display:flex;flex-wrap:wrap;gap:5px;margin-top:10px}.igf-preview-team-directory__filters span{padding:4px 7px;border:1px solid rgba(255,255,255,.32);border-radius:99px;font-size:8px}.igf-preview-team-directory__people ul{display:grid;gap:7px;margin:10px 0 0;padding:0;list-style:none}.igf-preview-team-directory__person{display:grid;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:9px;padding:7px;border:1px solid #e1ddd9;border-radius:9px;background:#faf8f6}.igf-preview-team-directory__person:first-child{border-color:var(--igf-orange);box-shadow:0 0 0 2px rgba(255,117,0,.12)}.igf-preview-team-directory__avatar{display:grid;width:38px;height:38px;place-items:center;overflow:hidden;border-radius:50%;background:#f6dfcc;color:var(--igf-primary);font-size:10px;font-weight:900}.igf-preview-team-directory__avatar img{width:100%;height:100%;object-fit:cover}.igf-preview-team-directory__person strong,.igf-preview-team-directory__person small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.igf-preview-team-directory__person strong{font-size:10px}.igf-preview-team-directory__person small{margin-top:2px;color:var(--igf-muted);font-size:8px}.igf-preview-team-directory__profile{display:flex;flex-direction:column;justify-content:center;background:#f7f1eb}.igf-preview-team-directory__profile>small{width:fit-content;margin-bottom:9px;padding:4px 7px;border-radius:99px;background:#ffe3cc;color:var(--igf-primary);font-size:8px;font-weight:900;text-transform:uppercase}.igf-preview-team-directory__profile p{margin:4px 0 0;color:var(--igf-muted);font-size:10px;line-height:1.45}.igf-preview-team-directory__profile .igf-preview-team-directory__behavior{margin-top:12px;color:var(--igf-primary);font-weight:850}.igf-preview-team-directory.is-profile-modal .igf-preview-team-directory__profile{border:2px solid var(--igf-orange);box-shadow:0 16px 36px rgba(47,32,21,.2)}.igf-preview-team-directory.is-profile-link .igf-preview-team-directory__profile{border-style:dashed;background:#fff}.igf-preview-team-directory--no-map{grid-template-columns:minmax(220px,1fr) minmax(220px,.9fr)}@container (max-width:820px){.igf-preview-team-directory,.igf-preview-team-directory--no-map{grid-template-columns:1fr 1fr}.igf-preview-team-directory__profile{grid-column:1/-1}.igf-preview-team-directory.is-map-right .igf-preview-team-directory__map{order:initial}.igf-preview-team-directory.is-map-right .igf-preview-team-directory__profile{order:initial}}@container (max-width:500px){.igf-preview-team-directory,.igf-preview-team-directory--no-map{grid-template-columns:1fr}.igf-preview-team-directory__profile{grid-column:auto}}
    .igf-team-showcase-settings .igf-check{margin-bottom:13px}.igf-preview-team-heroes{display:grid;grid-template-columns:minmax(240px,.9fr) minmax(270px,1.1fr);gap:clamp(18px,3cqw,38px);align-items:stretch;margin-top:26px}.igf-preview-team-heroes.is-map-right .igf-preview-team-heroes__story{order:1}.igf-preview-team-heroes.is-map-right .igf-preview-team-heroes__map{order:2}.igf-preview-team-heroes--no-map{grid-template-columns:minmax(0,720px)}.igf-preview-team-heroes__map,.igf-preview-team-heroes__story{min-width:0;overflow:hidden;border-radius:22px}.igf-preview-team-heroes__map{display:flex;min-height:430px;flex-direction:column;padding:22px;background:linear-gradient(155deg,#173c35,#005547);color:#fff}.igf-preview-team-heroes__map h3{margin:0;font:700 clamp(22px,3cqw,32px)/1.15 'Literata',serif;color:#fff}.igf-preview-team-heroes__map p{margin:6px 0 0;color:#e4f0ed;font-size:11px;line-height:1.45}.igf-preview-team-heroes__map svg{display:block;width:100%;max-width:330px;max-height:365px;margin:auto;overflow:visible}.igf-preview-team-heroes__map path{fill:#005547;stroke:#ffcb07;stroke-width:2.8;vector-effect:non-scaling-stroke;transition:fill .2s ease}.igf-preview-team-heroes__map g:first-of-type path{fill:#087162}.igf-preview-team-heroes__map text{fill:#fff;font-size:11px;font-weight:850;text-anchor:middle;paint-order:stroke;stroke:#005547;stroke-width:3px;stroke-linejoin:round}.igf-preview-team-heroes__story{display:flex;min-height:430px;flex-direction:column;justify-content:center;padding:clamp(24px,4cqw,44px);border:1px solid #eadfd5;background:#fff8f1;box-shadow:0 18px 42px rgba(40,31,24,.1)}.igf-preview-team-heroes__story.is-animated{animation:igf-team-heroes-in .45s ease both}.igf-preview-team-heroes__eyebrow{margin:0 0 8px!important;color:var(--igf-orange)!important;font-size:10px!important;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.igf-preview-team-heroes__story h3{margin:0 0 5px;font:700 clamp(25px,4cqw,40px)/1.13 'Literata',serif}.igf-preview-team-heroes__role{margin:0!important;color:var(--igf-primary)!important;font-size:12px!important;font-weight:850}.igf-preview-team-heroes__bio{display:-webkit-box;overflow:hidden;margin:14px 0 0!important;color:var(--igf-muted)!important;font-size:12px!important;line-height:1.65!important;-webkit-box-orient:vertical;-webkit-line-clamp:5}.igf-preview-team-heroes__qualification{margin:10px 0 0!important;color:#554b44!important;font-size:10px!important}.igf-preview-team-heroes__profiles{display:flex;gap:10px;margin:22px 0 0;padding:0;list-style:none}.igf-preview-team-heroes__profile{display:grid;width:66px;min-width:66px;justify-items:center;gap:5px}.igf-preview-team-heroes__avatar{display:grid;width:60px;height:60px;place-items:center;overflow:hidden;border:3px solid #fff;border-radius:50%;background:#f2d7c0;color:var(--igf-primary);font-size:13px;font-weight:900;box-shadow:0 0 0 1px #d7c6b8}.igf-preview-team-heroes__avatar img{width:100%;height:100%;object-fit:cover}.igf-preview-team-heroes__profile.is-selected .igf-preview-team-heroes__avatar{border-color:#ffcb07;box-shadow:0 0 0 3px rgba(255,117,0,.24)}.igf-preview-team-heroes__profile small{display:block;width:100%;overflow:hidden;color:var(--igf-muted);font-size:8px;font-weight:800;text-align:center;text-overflow:ellipsis;white-space:nowrap}.igf-preview-team-heroes__links{display:flex;align-items:center;gap:7px;margin:18px 0 0!important;color:var(--igf-primary)!important;font-size:10px!important;font-weight:850}.igf-preview-team-heroes__status{width:fit-content;margin:13px 0 0!important;padding:5px 8px;border-radius:99px;background:#fff;color:#65564a!important;font-size:8px!important;font-weight:800}@keyframes igf-team-heroes-in{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}@container (max-width:720px){.igf-preview-team-heroes,.igf-preview-team-heroes--no-map{grid-template-columns:1fr}.igf-preview-team-heroes.is-map-right .igf-preview-team-heroes__map,.igf-preview-team-heroes.is-map-right .igf-preview-team-heroes__story{order:initial}.igf-preview-team-heroes__map{min-height:360px}.igf-preview-team-heroes__map svg{max-height:290px}.igf-preview-team-heroes__story{min-height:0}}@media (prefers-reduced-motion:reduce){.igf-preview-team-heroes__story.is-animated{animation:none}.igf-preview-team-heroes__map path{transition:none}}
    .igf-preview-block--team-heroes{background:#f5efe8}.igf-preview-team-heroes{grid-template-areas:'map content';grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start;margin-top:0;background:transparent}.igf-preview-team-heroes.is-map-right{grid-template-areas:'content map'}.igf-preview-team-heroes--no-map{grid-template-areas:'content';grid-template-columns:minmax(0,1fr)}.igf-preview-team-heroes__content{grid-area:content;min-width:0}.igf-preview-team-heroes__heading{max-width:620px;margin-bottom:26px}.igf-preview-team-heroes__eyebrow{display:flex;align-items:center;gap:7px;margin:0 0 7px!important;color:#9b4308!important;font-size:10px!important;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.igf-preview-team-heroes__eyebrow::before{content:'○ ○';color:var(--igf-orange);font-size:10px;letter-spacing:1px}.igf-preview-team-heroes__heading h2{margin:0;color:var(--igf-ink);font:700 clamp(30px,4cqw,44px)/1 'Literata',serif;letter-spacing:-.035em}.igf-preview-team-heroes__lead{max-width:590px;margin:14px 0 0!important;color:#574c45!important;font-size:12px!important;line-height:1.6!important}.igf-preview-team-heroes__profiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0;padding:0;list-style:none}.igf-preview-team-heroes__person{display:flex;min-width:0;min-height:205px;align-items:center;flex-direction:column;gap:6px;padding:16px 5px;border-radius:8px;background:transparent;text-align:center}.igf-preview-team-heroes__person.is-selected{background:#fff0df;box-shadow:0 5px 15px rgba(95,46,14,.16)}.igf-preview-team-heroes__avatar{width:min(116px,calc(100% - 8px));height:auto;aspect-ratio:1;border:0;box-shadow:none;font-size:27px}.igf-preview-team-heroes__person strong{margin-top:3px;color:var(--igf-ink);font-size:12px;line-height:1.35}.igf-preview-team-heroes__person small{color:#6a5a50;font-size:9px;line-height:1.4}.igf-preview-team-heroes__profile{display:block;width:auto;min-width:0;min-height:230px;margin-top:25px;padding:0;border:0;border-radius:0;background:transparent;box-shadow:none}.igf-preview-team-heroes__profile.is-animated{animation:igf-team-heroes-in .3s ease both}.igf-preview-team-heroes__profile-heading{display:flex;align-items:flex-start;flex-direction:column}.igf-preview-team-heroes__profile-heading h3{margin:3px 0 0;color:var(--igf-ink);font:700 21px/1.3 'Poppins','Hanken Grotesk',Arial,sans-serif;letter-spacing:-.02em}.igf-preview-team-heroes__role{order:-1;margin:0!important;color:#6b625d!important;font-size:12px!important;font-weight:400}.igf-preview-team-heroes__socials{display:flex;gap:6px;margin-top:7px}.igf-preview-team-heroes__socials i{display:grid;width:28px;height:28px;place-items:center;border-radius:50%;background:#27211d;color:#fff;font-size:11px}.igf-preview-team-heroes__biography{margin-top:18px;color:#3f3732;font-size:12px;line-height:1.55}.igf-preview-team-heroes__biography p{margin:0}.igf-preview-team-heroes__biography p+p{margin-top:12px}.igf-preview-team-heroes__qualification{margin:13px 0 0!important;color:#5c5048!important;font-size:11px!important;line-height:1.5!important}.igf-preview-team-heroes__map{grid-area:map;display:block;min-width:0;min-height:0;align-self:start;overflow:visible;padding:0;border:0;border-radius:0;background:transparent;box-shadow:none;color:var(--igf-ink)}.igf-preview-team-heroes__map-stage{width:min(100%,500px);margin:0 auto}.igf-preview-team-heroes__map svg{display:block;width:100%;max-width:none;max-height:none;margin:0;overflow:visible}.igf-preview-team-heroes__map path,.igf-preview-team-heroes__map g:first-of-type path{fill:#5e2a0a;stroke:var(--igf-orange);stroke-width:2;stroke-linejoin:round;vector-effect:non-scaling-stroke}.igf-preview-team-heroes__empty{margin:0;padding:24px;border:1px dashed #d7b99f;border-radius:16px;background:#fff8f2;color:#65584f;font-size:11px;text-align:center}@container (min-width:1050px){.igf-preview-team-heroes__profiles{grid-template-columns:repeat(3,minmax(0,1fr))}}@container (max-width:820px){.igf-preview-team-heroes,.igf-preview-team-heroes.is-map-right{grid-template-areas:'content' 'map';grid-template-columns:1fr}.igf-preview-team-heroes:not(.is-map-right){grid-template-areas:'map' 'content'}.igf-preview-team-heroes__map-stage{width:min(100%,360px)}}@media (prefers-reduced-motion:reduce){.igf-preview-team-heroes__profile.is-animated{animation:none}}
    .igf-preview-team-heroes__eyebrow::before{content:none}.igf-preview-team-heroes__eyebrow-marks{display:inline-flex;gap:4px}.igf-preview-team-heroes__eyebrow-marks span{width:7px;height:7px;border:1.5px solid var(--igf-orange);border-radius:50%}.igf-preview-team-heroes__heading h2{font-family:'Poppins','Hanken Grotesk',Arial,sans-serif;font-weight:500}.igf-preview-team-heroes__heading h2 strong{font-weight:850}
    @keyframes igf-team-heroes-in{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}
</style>

<div class="igf-builder" id="igf-builder">
    <header class="igf-builder__topbar">
        <div class="igf-builder__heading">
            <div class="igf-builder__title">
                <h1>Editing: {{ $page->name }}</h1>
                <small>{{ strtoupper($page->language) }} &middot; Live: <a href="{{ $publicUrl }}" target="_blank" rel="noopener">{{ $publicPath }}</a></small>
            </div>
            <div class="igf-builder__viewport" aria-label="Preview viewport">
                <button type="button" class="igf-viewport-button is-active" data-viewport="desktop" aria-label="Desktop preview" aria-pressed="true"><i class="fa fa-desktop" aria-hidden="true"></i></button>
                <button type="button" class="igf-viewport-button" data-viewport="tablet" aria-label="Tablet preview" aria-pressed="false"><i class="fa fa-tablet" aria-hidden="true"></i></button>
                <button type="button" class="igf-viewport-button" data-viewport="mobile" aria-label="Mobile preview" aria-pressed="false"><i class="fa fa-mobile" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="igf-builder__actions">
            <span class="igf-save-state" id="save-state">Saved</span>
            <select id="locale-switch" class="igf-btn igf-locale" aria-label="Editing language">
                @foreach ($locales as $localePage)
                    <option value="{{ $localePage->language }}" @selected($localePage->language === $page->language)>{{ strtoupper($localePage->language) }} &mdash; {{ $localePage->name }}</option>
                @endforeach
            </select>
            <a class="igf-btn" href="{{ route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]) }}" target="_blank" rel="noopener">Preview draft</a>
            <a class="igf-btn" href="{{ $publicUrl }}" target="_blank" rel="noopener">View live</a>
            @if($canEditBuilder)
                <div class="igf-publish-control">
                    <button type="button" class="igf-btn igf-btn--primary" id="save-page" disabled>Save page</button>
                    @if($canManagePublication && !$isRequiredSystemPage)
                        <button type="button" class="igf-btn" id="publish-menu-toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="publish-menu" aria-label="Choose publication action"><i class="fa fa-caret-down" aria-hidden="true"></i></button>
                        <div class="igf-publish-menu" id="publish-menu" role="menu" hidden>
                            @foreach(['draft' => 'Save as draft', 'pending_review' => 'Submit for review', 'published' => 'Publish now', 'scheduled' => 'Schedule publication', 'private' => 'Make private'] as $value => $label)
                                <button type="button" role="menuitem" data-publish-state="{{ $value }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </header>

    @unless($canEditBuilder)
        <div class="igf-read-only" role="status"><strong>Read-only preview.</strong> You can review this page, but your role cannot change page settings or section content.</div>
    @endunless

    <div class="igf-builder__grid">
        <aside class="igf-builder__inspector">
            <nav class="igf-builder__tabs" role="tablist" aria-label="Page builder panels">
                <button id="builder-tab-blocks" class="igf-builder__tab is-active" type="button" role="tab" aria-selected="true" aria-controls="builder-panel-blocks" tabindex="0" data-tab="blocks">Settings</button>
                <button id="builder-tab-library" class="igf-builder__tab" type="button" role="tab" aria-selected="false" aria-controls="builder-panel-library" tabindex="-1" data-tab="library">Blocks</button>
                <button id="builder-tab-page" class="igf-builder__tab" type="button" role="tab" aria-selected="false" aria-controls="builder-panel-page" tabindex="-1" data-tab="page">Page</button>
            </nav>
            <div class="igf-builder__panel-scroll">
            <div id="builder-panel-blocks" class="igf-panel" role="tabpanel" aria-labelledby="builder-tab-blocks" data-panel="blocks">
                <div id="block-inspector"><p class="igf-muted">Select a block to edit its content and visibility.</p></div>
            </div>
            <div id="builder-panel-library" class="igf-panel" role="tabpanel" aria-labelledby="builder-tab-library" data-panel="library" hidden>
                <h3>Page blocks</h3>
                <p class="igf-muted">Select, reorder, duplicate, hide, or delete any section.</p>
                @if($legacyContentNeedsConversion)
                    <p class="igf-banner-guidance" id="advanced-legacy-conversion-note" role="note"><strong>Your existing article is protected.</strong> Before the first section is shown, the editor will ask to copy that article into an editable Rich text block. Nothing will be discarded.</p>
                @endif
                <div class="igf-block-list" id="block-list"></div>
                @if($canCreateBuilder)
                <div class="igf-add">
                    <div class="igf-field">
                        <label for="new-block-type">Add section</label>
                        <select id="new-block-type">
                            @foreach ($blockTypes as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" class="igf-btn" id="add-block"><i class="fa fa-plus" aria-hidden="true"></i> Add block</button>
                </div>
                @endif
                @if($canEditBuilder && $reusableBlocks->isNotEmpty())
                    <div class="igf-add" style="margin-top:12px">
                        <div class="igf-field">
                            <label for="reusable-block">Reusable library</label>
                            <select id="reusable-block">
                                @foreach($reusableBlocks as $reusable)
                                    <option value="{{ $reusable->uuid }}">{{ $reusable->name }} ({{ $blockTypes[$reusable->type] ?? $reusable->type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="igf-btn" id="attach-reusable"><i class="fa fa-link" aria-hidden="true"></i> Use reusable section</button>
                    </div>
                @endif
            </div>
            <div id="builder-panel-page" class="igf-panel" role="tabpanel" aria-labelledby="builder-tab-page" data-panel="page" hidden>
                <strong>Page settings</strong>
                <div class="igf-field"><label for="page-name">Title</label><input id="page-name" value="{{ $page->name }}" @disabled(!$canEditBuilder)></div>
                <div class="igf-field"><label for="page-subtitle">Subtitle</label><textarea id="page-subtitle" @disabled(!$canEditBuilder)>{{ $page->sub_title }}</textarea></div>
                <div class="igf-field">
                    <label for="page-thumbnail-asset">Listing image</label>
                    <select id="page-thumbnail-asset" @disabled(!$canEditBuilder)>
                        <option value="" data-url="" @selected($rawThumbnail === '')>No listing image</option>
                        @if($rawThumbnail !== '' && !$selectedThumbnailAssetUuid)
                            <option value="__keep_current" data-url="{{ $currentThumbnailUrl }}" selected>Keep current image</option>
                        @endif
                        @foreach($mediaAssets as $asset)
                            <option value="{{ $asset->uuid }}" data-url="{{ $asset->url }}" @selected($selectedThumbnailAssetUuid === $asset->uuid)>{{ $asset->original_name }}</option>
                        @endforeach
                    </select>
                    <span class="igf-field-help">Choose an uploaded Media Library image used on page lists and cards.</span>
                    <img id="page-thumbnail-preview" class="igf-page-thumbnail" @if($currentThumbnailUrl !== '') src="{{ $currentThumbnailUrl }}" @endif alt="Selected listing image preview" @if($currentThumbnailUrl === '') hidden @endif>
                </div>
                <div class="igf-field">
                    <label for="page-category">Category</label>
                    <select id="page-category" @disabled(!$canEditBuilder)>
                        <option value="" @selected(blank($page->category_id))>No category</option>
                        @if($keepCurrentCategory)<option value="__keep_current" selected>Keep current unavailable category</option>@endif
                        @foreach($pageCategories as $category)
                            <option value="{{ $category->id }}" @selected((int) $page->category_id === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <span class="igf-field-help">Only active {{ strtoupper($page->language) }} categories are available.</span>
                </div>
                <label class="igf-check"><input id="page-funding-project" type="checkbox" @checked($page->is_funding_project) @disabled(!$canEditBuilder || !$canManageFundingEligibility)> This is a fundable program or project</label>
                <p class="igf-banner-guidance"><strong>Donation setting:</strong> Turn this on only when donors may direct a gift to this program or project. It applies to every language version. @unless($canManageFundingEligibility) Your role can review this setting, but only a Donation Causes editor can change it.@endunless</p>
                <label class="igf-check"><input id="page-zakat-eligible" type="checkbox" @checked($page->is_zakat_eligible) @disabled(!$canEditBuilder || !$canManageFundingEligibility || !$page->is_funding_project)> This project may receive Zakat</label>
                <p class="igf-banner-guidance"><strong>Zakat setting:</strong> First mark the page as fundable, then enable Zakat only after confirming the project meets the foundation’s Zakat policy. This also applies to every language version.</p>
                @if($page->slug === 'home')
                    <div class="igf-banner-guidance"><strong>Home banners are managed separately.</strong> The homepage uses active Home Banner slides, not a page banner selection. @if($canManageHomeBanners)<a href="{{ route('banner.index') }}">Open Home Banners</a>@else Ask a banner editor to update them.@endif An enabled Page Builder Hero still takes precedence over those slides.</div>
                @else
                    <div class="igf-field">
                        <label for="page-banner">Page banner</label>
                        <select id="page-banner" @disabled(!$canEditBuilder)>
                            <option value="" @selected(blank($page->banner_id))>No page banner</option>
                            @if($keepCurrentBanner)<option value="__keep_current" selected>Keep current unavailable banner</option>@endif
                            @foreach($pageBanners as $banner)
                                <option value="{{ $banner->id }}" @selected((int) $page->banner_id === (int) $banner->id)>{{ $banner->name }}</option>
                            @endforeach
                        </select>
                        <span class="igf-field-help">Only active {{ strtoupper($page->language) }} page banners are available.</span>
                    </div>
                    <p class="igf-banner-guidance"><strong>Hero takes precedence:</strong> when this page has an enabled Page Builder Hero section, visitors see that Hero instead of the selected page banner. Hide or remove the Hero to use this banner.</p>
                @endif
                <div class="igf-field">
                    <label>Tags</label>
                    <div class="igf-tag-options" role="group" aria-label="Active page tags">
                        @forelse($activeTags as $tag)
                            <label><input class="page-tag-input" type="checkbox" value="{{ $tag->id }}" @checked(in_array((int) $tag->id, $selectedTagIds, true)) @disabled(!$canEditBuilder)> <span>{{ $tag->name }}</span></label>
                        @empty
                            <span class="igf-muted">No active tags are available.</span>
                        @endforelse
                    </div>
                    <span class="igf-field-help">Tags help group related pages for project lists and visitor browsing.</span>
                </div>
                <div class="igf-field"><label for="publication-status">Publication status</label>
                    <select id="publication-status" @disabled(!$canEditBuilder || !$canManagePublication || ($isRequiredSystemPage && $page->publication_status === 'published'))>
                        @if($isRequiredSystemPage)
                            <option value="published" selected>Published — required website page</option>
                            @if($page->publication_status !== 'published')<option value="{{ $page->publication_status }}" selected>{{ ucfirst($page->publication_status) }} — repair by publishing</option>@endif
                        @else
                            @foreach(['draft' => 'Draft', 'pending_review' => 'Pending review', 'scheduled' => 'Scheduled', 'published' => 'Published', 'private' => 'Private'] as $value => $label)
                                <option value="{{ $value }}" @selected(($page->publication_status ?: ($page->status ? 'published' : 'draft')) === $value)>{{ $label }}</option>
                            @endforeach
                        @endif
                    </select>
                    <span class="igf-field-help">{{ $isRequiredSystemPage ? $requiredSystemPageLabel . ' stays published so this essential visitor route cannot be accidentally taken offline. Its content remains fully editable.' : ($canManagePublication ? 'Controls whether and when visitors can see this page.' : 'Only a publisher can change publication status. Your content edits can still be saved.') }}</span>
                </div>
                <div class="igf-field"><label for="page-visibility">Visibility</label><select id="page-visibility" @disabled(!$canEditBuilder || !$canManagePublication || $isRequiredSystemPage)><option value="public" @selected($page->visibility === 'public')>Public</option><option value="unlisted" @selected($page->visibility === 'unlisted')>Unlisted</option>@unless($isRequiredSystemPage)<option value="private" @selected($page->visibility === 'private')>Private</option>@endunless</select></div>
                <div class="igf-field" id="schedule-field" @if($page->publication_status !== 'scheduled') hidden @endif><label for="scheduled-for">Publish at</label><input id="scheduled-for" type="datetime-local" value="{{ $page->scheduled_for?->format('Y-m-d\TH:i') }}" @disabled(!$canEditBuilder || !$canManagePublication)></div>
                <div class="igf-seo-handoff">
                    <strong><i class="fa fa-search" aria-hidden="true"></i> Search &amp; Sharing</strong>
                    <p>SEO now has one guided workspace with live Google and social previews, URL controls, issue checks, schema templates, and restore points.</p>
                    @if($canEditSeo)
                        <a class="igf-btn" href="{{ route('seo.content.edit', ['type' => 'page', 'id' => $page->getKey(), 'locale' => $page->language]) }}">Open Search &amp; Sharing</a>
                    @else
                        <p class="igf-muted">Your SEO editor manages this workspace.</p>
                    @endif
                </div>
                <details class="igf-accordion">
                <summary>Revision history</summary>
                <p class="igf-muted">Every structural or SEO edit creates a restore point. A full restore can also change publishing, Search &amp; Sharing, and shared sections.</p>
                @unless($canRestoreRevisions)
                    <p class="igf-muted" role="note">Only an administrator with publishing, Search &amp; Sharing, and Reusable Sections permissions can restore a full revision.</p>
                @endunless
                <div class="igf-revisions">
                    @forelse ($page->revisions as $revision)
                        <div class="igf-revision">
                            <strong>Revision {{ $revision->revision }}</strong>
                            <div class="igf-muted">{{ $revision->note ?: 'Page update' }}<br>{{ $revision->created_at?->format('M j, Y g:i A') }}</div>
                            @if($canRestoreRevisions)
                                <button type="button" class="igf-btn igf-btn--small restore-revision" data-uuid="{{ $revision->uuid }}">Restore</button>
                            @endif
                        </div>
                    @empty
                        <p class="igf-muted">No revisions yet.</p>
                    @endforelse
                </div>
                </details>
            </div>
            </div>
        </aside>

        <main class="igf-builder__canvas">
            <div class="igf-builder__preview" id="page-preview" data-viewport="desktop" aria-live="polite"></div>
        </main>

    </div>
</div>
<datalist id="media-library-options">
    @foreach($mediaAssets as $asset)
        <option value="{{ $asset->url }}">{{ $asset->original_name }}</option>
    @endforeach
</datalist>
<datalist id="video-library-options">
    @foreach(($videoAssets ?? collect()) as $asset)
        <option value="{{ $asset->url }}">{{ $asset->original_name }}</option>
    @endforeach
</datalist>
@endsection

@section('custom-js')
<script>
(() => {
    const pageUuid = @json($page->uuid);
    const locale = @json($page->language);
    const eventsNewsTimeZone = @json(config('app.timezone'));
    let editorVersion = @json((int) $page->editor_version);
    const revisionReusableVersions = @json($revisionReusableVersions);
    const permissions = @json($builderPermissions);
    let legacyContentNeedsConversion = @json($legacyContentNeedsConversion);
    const canManageFundingEligibility = @json($canManageFundingEligibility);
    const routes = {
        createPage: @json($canCreatePage ? route('page.create') : null),
        edit: @json(route('page.builder.edit', $page->uuid)),
        updatePage: @json(route('page.builder.update', $page->uuid)),
        storeMedia: @json(route('page.builder.media.store', $page->uuid)),
        mediaLibrary: @json($canViewMediaLibrary ? route('media.index') : null),
        storeBlock: @json(route('page.builder.block.store', $page->uuid)),
        reorder: @json(route('page.builder.block.reorder', $page->uuid)),
        updateBlock: @json(route('page.builder.block.update', [$page->uuid, '__BLOCK__'])),
        duplicateBlock: @json(route('page.builder.block.duplicate', [$page->uuid, '__BLOCK__'])),
        promoteBlock: @json(route('page.builder.block.promote', [$page->uuid, '__BLOCK__'])),
        detachBlock: @json(route('page.builder.block.detach', [$page->uuid, '__BLOCK__'])),
        attachReusable: @json(route('page.builder.reusable.attach', $page->uuid)),
        destroyBlock: @json(route('page.builder.block.destroy', [$page->uuid, '__BLOCK__'])),
        restoreRevision: @json(route('page.builder.revision.restore', [$page->uuid, '__REVISION__'])),
    };
    const blockTypeLabels = @json($blockTypes);
    const contentOptions = @json($blockContentOptions);
    const teamPresentationChoices = Object.freeze({
        ...(contentOptions.presentations?.team || {cards:'Profile cards',list:'Simple list',compact:'Compact profiles'}),
        directory_map: 'Interactive directory + Bangladesh map',
        heroes_showcase: 'Meet the Heroes showcase',
    });
    const teamMapPositionChoices = Object.freeze(contentOptions.team_directory?.map_positions || {
        left: 'Map on the left',
        right: 'Map on the right',
    });
    const teamProfileBehaviorChoices = Object.freeze(contentOptions.team_directory?.profile_behaviors || {
        panel: 'Show the profile in the directory',
        modal: 'Open the profile in a dialog',
        link: 'Open the profile link',
    });
    const fallbackSectionPresentations = Object.freeze({
        standard: 'Standard',
        soft: 'Soft background',
        framed: 'Framed panel',
        contrast: 'Dark contrast',
    });
    const configuredSectionPresentations = contentOptions.presentations?.sections;
    const sectionPresentationChoices = Object.fromEntries(Object.entries(fallbackSectionPresentations).map(([token, fallbackLabel]) => [
        token,
        typeof configuredSectionPresentations?.[token] === 'string' && configuredSectionPresentations[token].trim() ? configuredSectionPresentations[token] : fallbackLabel,
    ]));
    const normalizedSectionPresentation = value => Object.prototype.hasOwnProperty.call(sectionPresentationChoices, String(value || 'standard').trim().toLowerCase())
        ? String(value || 'standard').trim().toLowerCase() : 'standard';
    const state = { blocks: @json($page->blocks), selected: @json(optional($page->blocks->first())->uuid), dirtyScopes: new Set(), uploadingScopes: new Set() };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const list = document.getElementById('block-list');
    const preview = document.getElementById('page-preview');
    const inspector = document.getElementById('block-inspector');

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    function confirmLegacyContentConversion(action='add this section') {
        if (!legacyContentNeedsConversion) return true;
        return confirm(`Keep your existing page article while you ${action}?\n\nChoose OK to copy it into the first editable Rich text block. The original version will also remain available in page history.`);
    }
    function acceptConvertedLegacyBlock(payload) {
        const converted = payload?.converted_legacy_block;
        if (!converted) return;
        if (!state.blocks.some(block => block.uuid === converted.uuid)) state.blocks.unshift(converted);
        legacyContentNeedsConversion = false;
        document.getElementById('advanced-legacy-conversion-note')?.remove();
    }
    const mediaLibraryLink = routes.mediaLibrary
        ? `<a href="${escapeHtml(routes.mediaLibrary)}" target="_blank" rel="noopener">Open media library</a>`
        : '';
    const formatDateTimeLocal = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
    const current = () => state.blocks.find(block => block.uuid === state.selected);
    const endpoint = (template, value, token) => template.replace(token, value);
    const blockScope = (uuid = state.selected) => `block:${uuid || 'none'}`;
    const hasDirty = () => state.dirtyScopes.size > 0;
    const setDirty = (dirty, scope = 'all') => {
        if (dirty) state.dirtyScopes.add(scope);
        else if (scope === 'all') state.dirtyScopes.clear();
        else state.dirtyScopes.delete(scope);
        document.getElementById('save-state').textContent = hasDirty() ? 'Unsaved changes' : 'Saved';
        const savePageButton = document.getElementById('save-page');
        if (savePageButton) savePageButton.disabled = !state.dirtyScopes.has('page');
        const saveBlockButton = document.getElementById('save-block');
        if (saveBlockButton) saveBlockButton.disabled = !state.dirtyScopes.has(blockScope());
    };
    const confirmBlockDiscard = action => {
        if (state.uploadingScopes.has(blockScope())) {
            notify('Wait for the current media upload to finish.');
            return false;
        }
        if (!state.dirtyScopes.has(blockScope())) return true;
        return confirm(`This section has unsaved changes. Discard them and ${action}?`);
    };
    const selectBlock = uuid => {
        if (!uuid || uuid === state.selected) return;
        const previousScope = blockScope();
        if (!confirmBlockDiscard('switch sections')) return;
        setDirty(false, previousScope);
        state.selected = uuid;
        renderAll();
    };
    const notify = message => {
        const old = document.querySelector('.igf-notice');
        if (old) old.remove();
        const notice = document.createElement('div');
        notice.className = 'igf-notice';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        notice.setAttribute('aria-atomic', 'true');
        notice.textContent = message;
        document.body.appendChild(notice);
        setTimeout(() => notice.remove(), 3500);
    };
    const request = async (url, method, body) => {
        const versionedBody = body && typeof body === 'object'
            ? {...body, expected_version: editorVersion}
            : body;
        const response = await fetch(url, {
            method,
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
            body: versionedBody ? JSON.stringify(versionedBody) : undefined,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
            throw new Error(validation || payload.message || 'The request could not be completed.');
        }
        if (Number.isInteger(Number(payload.editor_version))) {
            editorVersion = Number(payload.editor_version);
        }
        return payload;
    };

    const canEditBlockContent = block => permissions.edit && (!block?.is_reusable || permissions.editReusable);
    const managedSource = block => {
        if (block.content?.content_source) return block.content.content_source;
        if (block.type === 'cards') return 'manual';
        if (block.type === 'gallery' && Array.isArray(block.content?.items) && block.content.items.length) return 'manual';
        return Object.keys(contentOptions.sources?.[block.type] || {})[0] || 'manual';
    };
    const managedSelect = (key, label, value, choices, rerender = false) => `<div class="igf-field"><label for="managed-${key}">${escapeHtml(label)}</label><select id="managed-${key}" data-content-key="${escapeHtml(key)}" ${rerender ? 'data-managed-rerender' : ''}>${Object.entries(choices).map(([optionValue,optionLabel])=>`<option value="${escapeHtml(optionValue)}" ${String(value)===String(optionValue)?'selected':''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></div>`;
    const safeManageUrl = value => /^(?:https?:\/\/|\/(?!\/))/i.test(String(value || '').trim()) ? String(value).trim() : '';
    function guidedPageCreateLink(block, source) {
        if (!routes.createPage || !['category','projects'].includes(source)) return null;
        const url = new URL(routes.createPage, window.location.origin);
        url.searchParams.set('language', locale);
        if (source === 'category') {
            url.searchParams.set('kind', 'program');
            url.searchParams.set('category_slug', String(block.content?.category_slug || 'our-causes'));
            return {url:url.toString(),label:'Add program',icon:'fa-plus'};
        }
        url.searchParams.set('kind', 'project');
        const tagSlug = String(block.content?.tag_slug || '').trim();
        if (tagSlug) url.searchParams.set('tag_slug', tagSlug);
        return {url:url.toString(),label:'Add project',icon:'fa-plus'};
    }
    function contentManagementLinks(block, source) {
        const map = contentOptions.manage_urls || {};
        const entry = map[block.type] ?? map[source];
        const links = [];
        if (typeof entry === 'string') links.push({url:entry,label:`Manage ${blockTypeLabels[block.type] || 'content'}`,icon:'fa-pencil'});
        else if (entry && typeof entry === 'object') {
            const manageUrl = entry.manage_url || entry.url;
            const addUrl = entry.add_url || entry.create_url;
            if (manageUrl) links.push({url:manageUrl,label:entry.manage_label || entry.label || 'Manage content',icon:'fa-pencil'});
            if (addUrl) links.push({url:addUrl,label:entry.add_label || `Add ${entry.item_label || 'content'}`,icon:'fa-plus'});
        }
        const guidedCreate = guidedPageCreateLink(block, source);
        if (guidedCreate) links.push(guidedCreate);
        const safeLinks = links.filter(link => safeManageUrl(link.url));
        return safeLinks.length ? `<nav class="igf-manage-links" aria-label="Manage section content">${safeLinks.map(link=>`<a class="igf-manage-link" href="${escapeHtml(link.url)}"><i class="fa ${link.icon}" aria-hidden="true"></i> ${escapeHtml(link.label)}</a>`).join('')}</nav>` : '';
    }
    function renderSectionPresentationField(block) {
        return `${managedSelect('section_presentation', 'Section presentation', normalizedSectionPresentation(block.content?.section_presentation), sectionPresentationChoices, true)}<p class="igf-muted igf-section-presentation-help">Changes the section’s surrounding surface while keeping its content layout.</p>`;
    }
    function managedItems(block, source) {
        let items = [...(contentOptions.items?.[source] || [])];
        if (source === 'projects' && block.content?.tag_slug) items = items.filter(item => (item.tags || []).includes(block.content.tag_slug));
        if (source === 'category') {
            const categorySlug = String(block.content?.category_slug ?? 'our-causes').trim();
            items = items.filter(item => item.category === categorySlug);
        }
        return items;
    }
    function managedPreviewItems(block) {
        const content = block.content || {};
        const candidates = managedItems(block, managedSource(block));
        const limit = Math.min(12, Math.max(1, Number(content.limit || 3)));
        if (content.selection_mode === 'manual') {
            const known = new Map(candidates.map(item => [String(item.value), item]));
            return (content.selected_items || []).map(value => known.get(String(value))).filter(Boolean).slice(0, limit);
        }
        const number = value => Number(value || 0);
        const items = [...candidates];
        items.sort((left, right) => {
            if (content.sort === 'newest') return number(right.published_at) - number(left.published_at) || number(right.sort_id) - number(left.sort_id);
            if (content.sort === 'oldest') return number(left.published_at) - number(right.published_at) || number(left.sort_id) - number(right.sort_id);
            if (content.sort === 'title') return String(left.label || '').localeCompare(String(right.label || ''), locale, {sensitivity:'base'}) || number(left.sort_id) - number(right.sort_id);
            return number(right.featured_order) - number(left.featured_order) || number(right.sort_id) - number(left.sort_id);
        });
        return items.slice(0, limit);
    }
    function teamPresentationInspector(block, content) {
        if (block.type !== 'team') return '';
        const presentation = Object.prototype.hasOwnProperty.call(teamPresentationChoices,String(content.team_presentation || 'cards'))
            ? String(content.team_presentation || 'cards') : 'cards';
        const presentationField = managedSelect('team_presentation','Team presentation',presentation,teamPresentationChoices,true);
        const mapPosition = Object.prototype.hasOwnProperty.call(teamMapPositionChoices,String(content.map_position || 'left'))
            ? String(content.map_position || 'left') : 'left';
        if (presentation === 'heroes_showcase') {
            return `${presentationField}<fieldset class="igf-team-directory-settings igf-team-showcase-settings" aria-describedby="igf-team-showcase-help"><legend>Showcase behavior</legend><p id="igf-team-showcase-help">Feature one person beside the Bangladesh map, with circular portraits for choosing another profile. Profile details always stay on this page.</p><input type="hidden" data-content-key="profile_behavior" value="panel"><label class="igf-check"><input type="checkbox" data-content-key="show_map" ${content.show_map !== false?'checked':''}> Show the Bangladesh map</label>${managedSelect('map_position','Map position',mapPosition,teamMapPositionChoices,true)}<label class="igf-check"><input type="checkbox" data-content-key="animation_enabled" ${content.animation_enabled !== false?'checked':''}> Use gentle profile transitions</label><label class="igf-check"><input type="checkbox" data-content-key="autoplay" ${[true,1,'1','true'].includes(content.autoplay)?'checked':''}> Rotate featured profiles automatically</label><p class="igf-muted">Visitors can still choose any portrait with touch or a keyboard. Reduced-motion preferences are respected on the public page.</p></fieldset>`;
        }
        if (presentation !== 'directory_map') return `${presentationField}<p class="igf-muted igf-team-directory-help">Choose how published profiles appear in this Team section.</p>`;
        const profileBehavior = Object.prototype.hasOwnProperty.call(teamProfileBehaviorChoices,String(content.profile_behavior || 'panel'))
            ? String(content.profile_behavior || 'panel') : 'panel';
        return `${presentationField}<fieldset class="igf-team-directory-settings" aria-describedby="igf-team-directory-help"><legend>Directory behavior</legend><p id="igf-team-directory-help">Visitors can filter published people by Bangladesh division. The preview mirrors the map placement and profile interaction.</p><label class="igf-check"><input type="checkbox" data-content-key="show_map" ${content.show_map !== false?'checked':''}> Show the Bangladesh map</label>${managedSelect('map_position','Map position',mapPosition,teamMapPositionChoices,true)}${managedSelect('profile_behavior','When a visitor chooses a person',profileBehavior,teamProfileBehaviorChoices,true)}</fieldset>`;
    }
    function renderManagedInspector(block) {
        const content = {...(block.content || {})};
        const source = managedSource(block);
        content.content_source = source;
        content.selection_mode ||= 'automatic';
        content.sort ||= 'featured';
        content.selected_items = Array.isArray(content.selected_items) ? content.selected_items : [];
        const sourceChoices = contentOptions.sources?.[block.type] || {};
        const categories = Object.fromEntries((contentOptions.categories || []).map(item=>[item.value,item.label]));
        const tags = Object.fromEntries((contentOptions.tags || []).map(item=>[item.value,item.label]));
        const selected = new Set(content.selected_items.map(String));
        const candidates = managedItems(block, source);
        const sourceField = Object.keys(sourceChoices).length > 1 ? managedSelect('content_source','Where items come from',source,sourceChoices,true) : '';

        if (source === 'manual') {
            return `${sourceField}<div class="igf-field"><label for="managed-eyebrow">Small heading</label><input id="managed-eyebrow" data-content-key="eyebrow" value="${escapeHtml(content.eyebrow || '')}"></div><div class="igf-field"><label for="managed-heading">Section heading</label><input id="managed-heading" data-content-key="heading" value="${escapeHtml(content.heading || '')}"></div><div class="igf-field"><label for="managed-body">Introduction</label><textarea id="managed-body" data-content-key="body">${escapeHtml(content.body || '')}</textarea></div><div class="igf-field"><label for="managed-items">Manual items (advanced)</label><textarea id="managed-items" data-content-key="items" data-json="true">${escapeHtml(JSON.stringify(content.items || [],null,2))}</textarea><small>For a guided card editor, open Simple editor.</small></div>`;
        }

        const sourceSpecific = source === 'category'
            ? managedSelect('category_slug','Category',content.category_slug || 'our-causes',categories,true)
            : source === 'projects'
                ? managedSelect('tag_slug','Project group',content.tag_slug || '',{'':'All published projects',...tags},true)
                : '';
        const selection = content.selection_mode === 'manual'
            ? `<div class="igf-field"><label for="managed-selected-items">Choose managed items</label><select id="managed-selected-items" multiple size="${Math.min(10,Math.max(3,candidates.length))}" data-content-array-key="selected_items">${candidates.map(item=>`<option value="${escapeHtml(item.value)}" ${selected.has(String(item.value))?'selected':''}>${escapeHtml(item.label)}</option>`).join('')}</select><small>Use Ctrl or Command to choose more than one.</small></div>`
            : '';
        const itemLink = ['cards','causes','events'].includes(block.type) ? `<div class="igf-field"><label for="managed-item-link">Item link text</label><input id="managed-item-link" data-content-key="item_link_label" value="${escapeHtml(content.item_link_label || '')}"></div>` : '';
        const viewAll = ['cards','causes','events','gallery'].includes(block.type) ? `<div class="igf-field"><label for="managed-view-all-label">View-all link text</label><input id="managed-view-all-label" data-content-key="view_all_label" value="${escapeHtml(content.view_all_label || '')}"></div><div class="igf-field"><label for="managed-view-all-url">View-all destination</label><input id="managed-view-all-url" data-content-key="view_all_url" value="${escapeHtml(content.view_all_url || '')}"></div>` : '';
        const presentation = block.type === 'causes'
            ? `${managedSelect('presentation','Content layout',content.presentation || 'card_grid',contentOptions.presentations?.causes || {card_grid:'Standard image cards',focus_areas:'Animated focus areas'},true)}<p class="igf-muted">Animated focus areas uses the heading as the first tile, then reveals program cards in a short stagger. Five items fill two complete desktop rows.</p>`
            : block.type === 'testimonials'
                ? `${managedSelect('display_style','Testimonial layout',content.display_style || 'spotlight',contentOptions.presentations?.testimonials || {spotlight:'Spotlight story (classic)',split:'Two stories side by side'},true)}<p class="igf-muted">Choose the classic dark single-story slider or the light two-story layout. Add another Community stories section to use both designs on one page.</p>`
                : '';
        const teamPresentation = teamPresentationInspector(block,content);

        return `<div class="igf-field"><label for="managed-eyebrow">Small heading</label><input id="managed-eyebrow" data-content-key="eyebrow" value="${escapeHtml(content.eyebrow || '')}"></div><div class="igf-field"><label for="managed-heading">Section heading</label><input id="managed-heading" data-content-key="heading" value="${escapeHtml(content.heading || '')}"></div><div class="igf-field"><label for="managed-body">Introduction</label><textarea id="managed-body" data-content-key="body">${escapeHtml(content.body || '')}</textarea></div>${presentation}${teamPresentation}${sourceField}${sourceSpecific}${contentManagementLinks(block,source)}${managedSelect('sort','Item order',content.sort,contentOptions.sorts || {})}<div class="igf-field"><label for="managed-limit">Maximum items</label><input id="managed-limit" type="number" min="1" max="12" data-content-key="limit" value="${Math.min(12,Math.max(1,Number(content.limit || 3)))}"></div>${managedSelect('selection_mode','How items are chosen',content.selection_mode,{automatic:'Keep updated automatically',manual:'Choose specific managed items'},true)}${selection}${itemLink}${viewAll}<div class="igf-field"><label for="managed-empty">Empty-section message</label><textarea id="managed-empty" maxlength="300" data-content-key="empty_state">${escapeHtml(content.empty_state || '')}</textarea></div>`;
    }

    function eventsNewsOptions(kind) {
        const key = kind === 'news' ? 'news_options' : 'event_options';
        return Array.isArray(contentOptions[key]) ? contentOptions[key] : [];
    }
    function eventsNewsDate(raw) {
        if (raw === null || raw === undefined || raw === '') return null;
        let date;
        if (typeof raw === 'number' || /^\d{9,13}$/.test(String(raw))) {
            const number = Number(raw);
            date = new Date(number < 100000000000 ? number * 1000 : number);
        } else date = new Date(raw);
        return Number.isNaN(date.getTime()) ? null : date;
    }
    function eventsNewsDateLabel(item, includeTime = false) {
        const date = eventsNewsDate(item?.event_start_at || item?.event_date || item?.start_date || item?.published_at);
        if (!date) return '';
        const language = locale === 'bn' ? 'bn-BD' : 'en-US';
        return new Intl.DateTimeFormat(language, includeTime
            ? {day:'numeric',month:'short',year:'numeric',hour:'numeric',minute:'2-digit',timeZone:eventsNewsTimeZone}
            : {day:'numeric',month:'short',year:'numeric',timeZone:eventsNewsTimeZone}).format(date);
    }
    function eventsNewsOptionMeta(item, kind = 'event') {
        const parts = [];
        const date = eventsNewsDateLabel(item, kind === 'event');
        if (date) parts.push(date);
        if (kind === 'event' && (item.location || item.venue || item.event_attendance_mode || item.attendance_mode)) parts.push(item.location || item.venue || item.event_attendance_mode || item.attendance_mode);
        return parts.join(' · ') || (kind === 'event' ? 'Scheduled event' : 'Published news');
    }
    function eventsNewsField(key, label, value, options = {}) {
        const id = `events-news-${key}`;
        const maximum = options.max ? ` maxlength="${Number(options.max)}"` : '';
        const help = options.help ? `<small class="igf-field-help">${escapeHtml(options.help)}</small>` : '';
        return `<div class="igf-field"><label for="${escapeHtml(id)}">${escapeHtml(label)}</label>${options.textarea?`<textarea id="${escapeHtml(id)}" data-content-key="${escapeHtml(key)}"${maximum}>${escapeHtml(value || '')}</textarea>`:`<input id="${escapeHtml(id)}" data-content-key="${escapeHtml(key)}" value="${escapeHtml(value || '')}"${maximum}>`}${help}</div>`;
    }
    function renderEventsNewsInspector(block) {
        const content = block.content || (block.content = {});
        content.events_selection_mode = ['automatic','manual'].includes(content.events_selection_mode) ? content.events_selection_mode : 'automatic';
        content.event_limit = Math.min(6,Math.max(1,Number(content.event_limit || 3)));
        content.selected_event_ids = Array.isArray(content.selected_event_ids) ? [...new Set(content.selected_event_ids.map(String))] : [];
        content.featured_news_id = String(content.featured_news_id || '');

        const events = eventsNewsOptions('event');
        const news = eventsNewsOptions('news');
        const eventMap = new Map(events.map(item => [String(item.value),item]));
        const selectedSet = new Set(content.selected_event_ids);
        const selectedRows = content.selected_event_ids.map((token,index) => {
            const item = eventMap.get(token) || {value:token,label:'Unavailable event',unavailable:true};
            const id = `advanced-events-news-selected-${block.uuid}-${index}`;
            return `<div class="igf-events-news-option${item.unavailable?' is-unavailable':''}"><input id="${escapeHtml(id)}" type="checkbox" data-events-news-event-toggle value="${escapeHtml(token)}" checked><label for="${escapeHtml(id)}"><strong>${escapeHtml(item.label || 'Scheduled event')}</strong><small>${escapeHtml(item.unavailable?'This event is no longer eligible. Remove it or publish and schedule it again.':eventsNewsOptionMeta(item))}</small></label><span class="igf-events-news-order"><button type="button" data-events-news-event-move="up" data-events-news-event-index="${index}" aria-label="Move ${escapeHtml(item.label || 'event')} up" ${index===0?'disabled':''}>↑</button><button type="button" data-events-news-event-move="down" data-events-news-event-index="${index}" aria-label="Move ${escapeHtml(item.label || 'event')} down" ${index===content.selected_event_ids.length-1?'disabled':''}>↓</button></span></div>`;
        }).join('');
        const availableRows = events.filter(item => !selectedSet.has(String(item.value))).map((item,index) => {
            const id = `advanced-events-news-available-${block.uuid}-${index}`;
            return `<div class="igf-events-news-option"><input id="${escapeHtml(id)}" type="checkbox" data-events-news-event-toggle value="${escapeHtml(item.value)}"><label for="${escapeHtml(id)}"><strong>${escapeHtml(item.label || 'Scheduled event')}</strong><small>${escapeHtml(eventsNewsOptionMeta(item))}</small></label><span></span></div>`;
        }).join('');
        const eventChooser = content.events_selection_mode === 'manual'
            ? `<div class="igf-events-news-list" role="group" aria-label="Upcoming events in website order">${selectedRows}${availableRows || (!selectedRows?'<p class="igf-banner-guidance">No eligible upcoming events are available yet.</p>':'')}</div><p class="igf-muted">Checked events appear in this exact order. Use the arrow buttons to reorder them. You can choose up to six.</p>`
            : '<p class="igf-events-news-guide"><strong>Automatic event list</strong>The soonest eligible upcoming events are kept current for you.</p>';
        const selectedNews = news.some(item => String(item.value) === content.featured_news_id);
        const missingNews = content.featured_news_id && !selectedNews
            ? `<option value="${escapeHtml(content.featured_news_id)}" selected>Previously selected news is unavailable</option>`
            : '';
        const newsOptions = news.map(item => `<option value="${escapeHtml(item.value)}" ${String(item.value)===content.featured_news_id?'selected':''}>${escapeHtml(item.label || 'Published news')}${eventsNewsDateLabel(item)?` — ${escapeHtml(eventsNewsDateLabel(item))}`:''}</option>`).join('');

        return `<div class="igf-events-news-guide"><strong>One managed section, two current lists</strong>Add or update the actual event and news records using the buttons below. Only eligible upcoming events and published news can be shown here; there are no IDs or JSON to enter.</div>${contentManagementLinks(block,'events_news')}<section class="igf-events-news-group"><h4>Section introduction and headings</h4><p>Every visible heading and introductory line can be changed here.</p>${eventsNewsField('eyebrow','Small heading',content.eyebrow || '',{max:120})}${eventsNewsField('body','Introduction',content.body || '',{textarea:true,max:600})}${eventsNewsField('events_heading','Events heading',content.events_heading || 'Upcoming Events',{max:180})}${eventsNewsField('news_heading','News heading',content.news_heading || 'Featured News',{max:180})}</section><section class="igf-events-news-group"><h4>Upcoming events</h4><p>Keep the list automatic or choose and order particular scheduled events.</p>${managedSelect('events_selection_mode','How are events chosen?',content.events_selection_mode,{automatic:'Automatically show the next events',manual:'Choose and order events myself'},false).replace('<select ','<select data-events-news-rerender ')}<div class="igf-field"><label for="events-news-event-limit">Maximum events to show</label><input id="events-news-event-limit" data-content-key="event_limit" type="number" min="1" max="6" value="${content.event_limit}"><small class="igf-field-help">The public layout stays readable with one to six events.</small></div>${eventChooser}</section><section class="igf-events-news-group"><h4>Featured news</h4><p>Automatic uses the highest-priority published story. Set story priority in Events &amp; News, or choose one story here.</p><div class="igf-field"><label for="events-news-featured">Story to feature</label><select id="events-news-featured" data-content-key="featured_news_id"><option value="" ${content.featured_news_id?'':'selected'}>Automatic — highest-priority published news</option>${missingNews}${newsOptions}</select></div></section><section class="igf-events-news-group"><h4>Links and fallback messages</h4><p>Use friendly link text. Leave the optional call-to-action text empty to hide that button.</p>${eventsNewsField('item_link_label','Event and news link text',content.item_link_label || 'Learn more',{max:80})}${eventsNewsField('events_view_all_label','Events “view all” text',content.events_view_all_label || 'View all events',{max:80})}${eventsNewsField('events_view_all_url','Events “view all” destination',content.events_view_all_url || '',{help:'Choose a page path such as /events, or enter a full web address.'})}${eventsNewsField('news_view_all_label','News “view all” text',content.news_view_all_label || 'View all news',{max:80})}${eventsNewsField('news_view_all_url','News “view all” destination',content.news_view_all_url || '',{help:'Choose a page path such as /news, or enter a full web address.'})}${eventsNewsField('cta_label','Optional call-to-action text',content.cta_label || '',{max:80})}${eventsNewsField('cta_url','Optional call-to-action destination',content.cta_url || '')}${eventsNewsField('events_empty_state','Message when there are no upcoming events',content.events_empty_state || 'No upcoming events are scheduled right now.',{textarea:true,max:300})}${eventsNewsField('news_empty_state','Message when there is no featured news',content.news_empty_state || 'No featured news is available right now.',{textarea:true,max:300})}</section>`;
    }

    function renderWaysToGiveInspector(block) {
        const content = block.content || (block.content = {});
        content.layout = ['single_cta','card_grid','banner'].includes(content.layout) ? content.layout : 'card_grid';
        content.selection_mode = ['automatic','manual'].includes(content.selection_mode) ? content.selection_mode : 'automatic';
        content.selected_items = Array.isArray(content.selected_items) ? [...new Set(content.selected_items.map(String))] : [];
        const active = contentOptions.ways_to_give?.items || [];
        const known = contentOptions.ways_to_give?.known_items || [];
        const optionMap = new Map([...known,...active].map(option => [String(option.value),option]));
        const selected = content.selected_items;
        const selectedRows = selected.map((token,index) => {
            const option = optionMap.get(token) || {value:token,label:'Unavailable giving option',active:false,destination:'This managed cause no longer exists.'};
            const unavailable = option.active === false;
            const controlId = `advanced-giving-selected-${block.uuid}-${index}`;
            return `<div class="igf-giving-option${unavailable?' is-unavailable':''}"><input id="${escapeHtml(controlId)}" type="checkbox" data-giving-toggle value="${escapeHtml(token)}" checked><label for="${escapeHtml(controlId)}"><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(unavailable?'Unavailable: visitors will not see this option. Remove it or publish the cause again.':option.destination || '')}</small></label><span class="igf-giving-move"><button type="button" data-giving-move="up" data-giving-index="${index}" aria-label="Move ${escapeHtml(option.label)} up" ${index===0?'disabled':''}>↑</button><button type="button" data-giving-move="down" data-giving-index="${index}" aria-label="Move ${escapeHtml(option.label)} down" ${index===selected.length-1?'disabled':''}>↓</button></span></div>`;
        }).join('');
        const unselectedRows = active.filter(option => !selected.includes(String(option.value))).map((option,index) => { const controlId=`advanced-giving-available-${block.uuid}-${index}`; return `<div class="igf-giving-option"><input id="${escapeHtml(controlId)}" type="checkbox" data-giving-toggle value="${escapeHtml(option.value)}"><label for="${escapeHtml(controlId)}"><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(option.destination || '')}</small></label><span></span></div>`; }).join('');
        const chooser = content.selection_mode === 'manual'
            ? `<div class="igf-giving-list" role="group" aria-label="Giving options in website order">${selectedRows}${unselectedRows}</div>${selected.length?'<p class="igf-muted">Checked options appear in this order. Use the arrow buttons to reorder.</p>':'<p class="igf-banner-guidance"><strong>No options selected.</strong> Visitors will see the empty-state message.</p>'}`
            : `<div class="igf-giving-preview"><strong>Automatically managed:</strong> all ${active.length} active giving options appear. Newly published causes are added automatically.</div>`;
        const chosenOption = selected.length === 1 ? optionMap.get(selected[0]) : null;
        const singleCauseDestination = content.selection_mode === 'manual' && ['single_cta','banner'].includes(content.layout) && selected.length === 1 && chosenOption?.kind === 'cause' && chosenOption?.active !== false;
        const allowedValues = new Set(chosenOption?.project_values || []);
        const projects = (contentOptions.ways_to_give?.projects || []).filter(project => allowedValues.has(String(project.value)));
        const projectAllowed = singleCauseDestination && chosenOption?.project_selection === 'optional';
        const fixedProject = singleCauseDestination && chosenOption?.project_selection === 'fixed' ? projects[0] : null;
        const selectedProject = projects.find(project => String(project.value) === String(content.project_uuid || ''));
        const projectField = projectAllowed
            ? `<div class="igf-field"><label for="ways-project">Preselect a project (optional)</label><select id="ways-project" data-content-key="project_uuid"><option value="">Let the donor choose</option>${content.project_uuid&&!selectedProject?`<option value="${escapeHtml(content.project_uuid)}" selected>Previously selected project is unavailable</option>`:''}${projects.map(project=>`<option value="${escapeHtml(project.value)}" ${String(content.project_uuid||'')===String(project.value)?'selected':''}>${escapeHtml(project.label)}</option>`).join('')}</select><small>Only projects accepted by this managed cause are shown.</small></div>`
            : fixedProject
                ? `<div class="igf-giving-preview" role="status"><strong>Fixed project:</strong> ${escapeHtml(fixedProject.label)}<br><small>Donors do not need another project choice.</small></div>`
            : (content.project_uuid ? '<p class="igf-banner-guidance"><strong>Project must be cleared.</strong> Use one managed cause in a Single CTA or Banner. <button class="igf-btn igf-btn--small" type="button" data-giving-clear-project>Clear project</button></p>' : '<p class="igf-muted">Project preselection is available for one compatible managed cause in a Single CTA or Banner.</p>');
        const previewOption = content.selection_mode === 'manual' ? chosenOption : active[0];
        const behavior = previewOption ? `${previewOption.label} → ${selectedProject || fixedProject ? `donate to ${(selectedProject || fixedProject).label}` : previewOption.destination}` : 'No public giving destination is selected.';
        const layoutSelect = managedSelect('layout','Giving layout',content.layout,{single_cta:'Single CTA',card_grid:'Card grid',banner:'Banner'},true).replace('data-managed-rerender','data-giving-rerender');
        const modeSelect = managedSelect('selection_mode','Giving options',content.selection_mode,{automatic:'All active giving options',manual:'Choose specific options and order'},true).replace('data-managed-rerender','data-giving-rerender');

        return `<div class="igf-field"><label for="ways-eyebrow">Small heading</label><input id="ways-eyebrow" data-content-key="eyebrow" value="${escapeHtml(content.eyebrow || '')}"></div><div class="igf-field"><label for="ways-heading">Section heading</label><input id="ways-heading" data-content-key="heading" maxlength="180" value="${escapeHtml(content.heading || '')}"></div><div class="igf-field"><label for="ways-body">Introduction</label><textarea id="ways-body" data-content-key="body" maxlength="1200">${escapeHtml(content.body || '')}</textarea></div>${layoutSelect}${modeSelect}${chooser}${projectField}<div class="igf-field"><label for="ways-link-label">Button text for managed causes</label><input id="ways-link-label" data-content-key="link_label" maxlength="80" value="${escapeHtml(content.link_label || 'Give now')}"></div><div class="igf-field"><label for="ways-empty">Empty-section message</label><textarea id="ways-empty" data-content-key="empty_state" maxlength="300">${escapeHtml(content.empty_state || '')}</textarea></div><div class="igf-giving-preview"><strong>Destination preview:</strong> ${escapeHtml(behavior)}</div><p class="igf-muted">Names, descriptions, images, and destinations come from managed Donation Causes, Zakat, and Sponsor-a-Child content. No URLs or JSON are entered here.</p>`;
    }

    function renderList() {
        list.innerHTML = state.blocks.length ? state.blocks.map((block, index) => `
            <div class="igf-block-list__item ${block.uuid === state.selected ? 'is-active' : ''}">
                <button type="button" class="igf-block-select" data-select="${block.uuid}" aria-pressed="${block.uuid === state.selected ? 'true' : 'false'}">
                    <span aria-hidden="true">${block.is_enabled ? '▦' : '◫'}</span>
                    <span><strong>${escapeHtml(block.label || blockTypeLabels[block.type])}</strong><small>${escapeHtml(blockTypeLabels[block.type] || block.type)}</small></span>
                </button>
                ${permissions.edit ? `<span class="igf-order">
                    <button type="button" data-move="up" data-index="${index}" aria-label="Move up" ${index === 0 ? 'disabled' : ''}>▲</button>
                    <button type="button" data-move="down" data-index="${index}" aria-label="Move down" ${index === state.blocks.length - 1 ? 'disabled' : ''}>▼</button>
                </span>` : ''}
            </div>`).join('') : '<p class="igf-muted">No blocks yet. Add the first section below.</p>';
    }

    const heroSlideKeys = ['eyebrow','heading','body','primary_label','primary_url','secondary_label','secondary_url','report_label','report_url','image','overlay_opacity'];
    const normalizeHeroSlide = (slide = {}) => ({
        eyebrow: String(slide.eyebrow || ''),
        heading: String(slide.heading || 'New carousel slide'),
        body: String(slide.body || ''),
        primary_label: String(slide.primary_label || ''),
        primary_url: String(slide.primary_url || ''),
        secondary_label: String(slide.secondary_label || ''),
        secondary_url: String(slide.secondary_url || ''),
        report_label: String(slide.report_label || ''),
        report_url: String(slide.report_url || ''),
        image: String(slide.image || ''),
        overlay_opacity: Math.min(100, Math.max(0, Number(slide.overlay_opacity ?? 64))),
    });
    const heroSlidesForEditor = block => {
        const content = block.content || {};
        return Array.isArray(content.slides) && content.slides.length
            ? content.slides.slice(0, 8).map(normalizeHeroSlide)
            : [normalizeHeroSlide(content)];
    };
    const renderHeroSlideField = (slide, index, key, label, multiline = false) => {
        const fieldId = `hero-slide-${index}-${key}`;
        const value = slide[key] ?? '';
        if (key === 'image') {
            const uploader = permissions.create ? `<div class="igf-media-dropzone" tabindex="0" role="button" data-media-dropzone data-media-key="image" data-media-slide-index="${index}" aria-label="Upload image for slide ${index + 1}"><span>Drop an image here<br>or click to upload</span><input class="igf-media-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></div>` : '';
            return `<div class="igf-media-picker"><div class="igf-field"><label for="${fieldId}">${label}</label><input id="${fieldId}" data-slide-key="image" value="${escapeHtml(value)}" list="media-library-options"></div>${uploader}<div class="igf-media-picker__links"><span>${permissions.create ? '20 MB maximum' : 'Choose an existing asset'}</span>${mediaLibraryLink}</div></div>`;
        }
        const type = key === 'overlay_opacity' ? ' type="number" min="0" max="100" step="1"' : '';
        return `<div class="igf-field"><label for="${fieldId}">${label}</label>${multiline ? `<textarea id="${fieldId}" data-slide-key="${key}">${escapeHtml(value)}</textarea>` : `<input id="${fieldId}"${type} data-slide-key="${key}" value="${escapeHtml(value)}">`}</div>`;
    };
    const renderHeroEditor = block => {
        const slides = heroSlidesForEditor(block);
        const cards = slides.map((slide, index) => `<section class="igf-slide-card" data-slide-card data-slide-index="${index}">
            <header class="igf-slide-card__head"><strong>Slide ${index + 1}</strong>${permissions.edit ? `<span class="igf-slide-card__actions">
                <button type="button" data-slide-action="up" data-slide-index="${index}" aria-label="Move slide ${index + 1} left" ${index === 0 ? 'disabled' : ''}>&#8593;</button>
                <button type="button" data-slide-action="down" data-slide-index="${index}" aria-label="Move slide ${index + 1} right" ${index === slides.length - 1 ? 'disabled' : ''}>&#8595;</button>
                <button type="button" data-slide-action="remove" data-slide-index="${index}" aria-label="Remove slide ${index + 1}" ${slides.length === 1 ? 'disabled' : ''}>&#10005;</button>
            </span>` : ''}</header>
            <div class="igf-slide-card__body">
                ${renderHeroSlideField(slide, index, 'eyebrow', 'Eyebrow')}
                ${renderHeroSlideField(slide, index, 'heading', 'Heading')}
                ${renderHeroSlideField(slide, index, 'body', 'Description', true)}
                ${renderHeroSlideField(slide, index, 'primary_label', 'Primary button label')}
                ${renderHeroSlideField(slide, index, 'primary_url', 'Primary button link')}
                ${renderHeroSlideField(slide, index, 'secondary_label', 'Secondary button label')}
                ${renderHeroSlideField(slide, index, 'secondary_url', 'Secondary button link')}
                ${renderHeroSlideField(slide, index, 'report_label', 'Report link label')}
                ${renderHeroSlideField(slide, index, 'report_url', 'Report link URL')}
                ${renderHeroSlideField(slide, index, 'image', 'Background image')}
                ${renderHeroSlideField(slide, index, 'overlay_opacity', 'Overlay opacity (0-100)')}
            </div>
        </section>`).join('');
        return `<section class="igf-carousel-settings"><h4>Carousel behavior</h4><label class="igf-check"><input id="hero-autoplay" type="checkbox" ${(block.content?.autoplay ?? true) ? 'checked' : ''}> Change slides automatically</label><label class="igf-check"><input id="hero-pause-hover" type="checkbox" ${(block.content?.pause_on_hover ?? true) ? 'checked' : ''}> Pause while a visitor hovers</label><div class="igf-carousel-settings__row"><p class="igf-muted">Visitors can also use arrows, dots, keyboard keys, or swipe.</p><div class="igf-field"><label for="hero-interval">Delay (ms)</label><input id="hero-interval" type="number" min="3000" max="20000" step="500" value="${Math.min(20000, Math.max(3000, Number(block.content?.interval || 6000)))}"></div></div></section><div class="igf-slide-editor">${cards}</div><p class="igf-slide-limit">Up to 8 slides. Each slide can have its own image, message, buttons, report link, and overlay.</p>${permissions.edit ? `<button type="button" class="igf-btn" id="add-hero-slide" ${slides.length >= 8 ? 'disabled' : ''}>Add slide</button>` : ''}`;
    };
    const renderMediaUploadField = (key, label, value, mediaKind = 'image') => {
        const fieldId = `block-content-${key.replace(/[^a-z0-9_-]/gi, '-')}`;
        const isVideo = mediaKind === 'video';
        const accept = isVideo ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif';
        const list = isVideo ? 'video-library-options' : 'media-library-options';
        const format = isVideo ? 'MP4 or WebM' : 'JPG, PNG, WebP or GIF';
        const uploader = permissions.create ? `<div class="igf-media-dropzone" tabindex="0" role="button" data-media-dropzone data-media-kind="${mediaKind}" data-media-key="${escapeHtml(key)}" aria-label="Upload ${escapeHtml(label)}"><span>Drop ${isVideo ? 'a video' : 'an image'} here<br>or click to upload</span><input class="igf-media-file" type="file" accept="${accept}" hidden></div>` : '';
        return `<div class="igf-media-picker"><div class="igf-field"><label for="${fieldId}">${escapeHtml(label)}</label><input id="${fieldId}" data-content-key="${escapeHtml(key)}" value="${escapeHtml(value || '')}" list="${list}"></div>${uploader}<div class="igf-media-picker__links"><span>${permissions.create ? `${format}, 20 MB maximum` : 'Choose an existing asset'}</span>${mediaLibraryLink}</div></div>`;
    };
    const renderMediaTextInspector = block => {
        const content = block.content || {};
        const mediaType = ['image','video','youtube'].includes(content.media_type) ? content.media_type : 'image';
        const field = (key, label, value, multiline = false, type = 'text', max = null) => {
            const id = `block-content-${key.replace(/[^a-z0-9_-]/gi, '-')}`;
            const maximum = max ? ` maxlength="${max}"` : '';
            return `<div class="igf-field"><label for="${id}">${escapeHtml(label)}</label>${multiline ? `<textarea id="${id}" data-content-key="${key}"${maximum}>${escapeHtml(value || '')}</textarea>` : `<input id="${id}" type="${type}" data-content-key="${key}" value="${escapeHtml(value || '')}"${maximum}>`}</div>`;
        };
        const typeField = `<div class="igf-field"><label for="block-content-media-type">Media type</label><select id="block-content-media-type" data-content-key="media_type" data-media-type-rerender><option value="image" ${mediaType === 'image' ? 'selected' : ''}>Image</option><option value="video" ${mediaType === 'video' ? 'selected' : ''}>Uploaded video</option><option value="youtube" ${mediaType === 'youtube' ? 'selected' : ''}>YouTube video</option></select></div>`;
        let mediaFields = '';
        if (mediaType === 'image') {
            mediaFields = `${renderMediaUploadField('image','Image',content.image,'image')}${field('image_alt','Describe the image',content.image_alt,false,'text',255)}`;
        } else if (mediaType === 'video') {
            mediaFields = `${renderMediaUploadField('video_url','Uploaded video',content.video_url,'video')}${renderMediaUploadField('poster','Poster image',content.poster,'image')}${field('caption','Video caption',content.caption,false,'text',2000)}`;
        } else {
            mediaFields = `${field('youtube_url','YouTube video link',content.youtube_url,false,'url',2048)}${field('caption','Video caption',content.caption,false,'text',2000)}`;
        }
        const position = `<div class="igf-field"><label for="block-content-image-position">Media position</label><select id="block-content-image-position" data-content-key="image_position"><option value="left" ${content.image_position !== 'right' ? 'selected' : ''}>Left</option><option value="right" ${content.image_position === 'right' ? 'selected' : ''}>Right</option></select></div>`;
        return `${field('eyebrow','Small heading',content.eyebrow)}${field('heading','Section heading',content.heading)}${field('body','Body text',content.body,true)}${typeField}${mediaFields}${position}${field('link_label','Link text',content.link_label)}${field('link_url','Link destination',content.link_url)}`;
    };

    function eventsNewsPreviewItems(block) {
        const content = block.content || {};
        const limit = Math.min(6,Math.max(1,Number(content.event_limit || 3)));
        const candidates = eventsNewsOptions('event');
        let upcoming;
        if (content.events_selection_mode === 'manual') {
            const known = new Map(candidates.map(item => [String(item.value),item]));
            upcoming = (Array.isArray(content.selected_event_ids) ? content.selected_event_ids : []).map(token => known.get(String(token))).filter(Boolean);
        } else {
            upcoming = [...candidates].sort((left,right) => {
                const leftDate = eventsNewsDate(left?.event_start_at || left?.event_date || left?.start_date)?.getTime() || Number.MAX_SAFE_INTEGER;
                const rightDate = eventsNewsDate(right?.event_start_at || right?.event_date || right?.start_date)?.getTime() || Number.MAX_SAFE_INTEGER;
                return leftDate - rightDate;
            });
        }
        const news = eventsNewsOptions('news');
        const automaticNews = [...news].sort((left,right) => {
            const priority = Number(right?.featured_order || 0) - Number(left?.featured_order || 0);
            if (priority !== 0) return priority;
            const published = Number(right?.published_at || 0) - Number(left?.published_at || 0);
            if (published !== 0) return published;
            return Number(right?.sort_id || 0) - Number(left?.sort_id || 0);
        });
        const featured = content.featured_news_id
            ? news.find(item => String(item.value) === String(content.featured_news_id)) || null
            : automaticNews[0] || null;
        return {upcoming:upcoming.slice(0,limit),featured};
    }
    const eventsNewsImage = item => item?.image || item?.thumbnail || item?.photo || '';
    const eventsNewsPlainText = value => String(value || '').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    const teamPreviewMap = contentOptions.team_directory?.map || {};
    const teamPreviewMapDivisions = Array.isArray(teamPreviewMap.divisions) ? teamPreviewMap.divisions : [];
    const teamPreviewMapLocale = String(locale || 'en').toLowerCase().startsWith('bn') ? 'bn' : 'en';
    const teamPreviewMapDivisionName = division => String(division?.names?.[teamPreviewMapLocale] || division?.names?.en || division?.key || '').trim();
    const teamPreviewName = item => String(item?.heading || item?.label || item?.name || '').trim();
    const teamPreviewImage = item => item?.image || item?.photo || item?.thumbnail || '';
    const teamPreviewDivisionName = item => String(item?.division_name || item?.division_label || item?.division?.name || '').trim();
    function renderTeamDirectoryPreview(block, items) {
        const content = block.content || {};
        const showMap = content.show_map !== false;
        const mapPosition = String(content.map_position || 'left') === 'right' ? 'right' : 'left';
        const profileBehavior = ['panel','modal','link'].includes(String(content.profile_behavior || 'panel')) ? String(content.profile_behavior || 'panel') : 'panel';
        const featured = items[0] || null;
        const mapHeadingId = `igf-team-directory-map-${block.uuid}`;
        const peopleHeadingId = `igf-team-directory-people-${block.uuid}`;
        const profileHeadingId = `igf-team-directory-profile-${block.uuid}`;
        const mapViewBox = String(teamPreviewMap.viewBox || '0 0 420 560');
        const map = showMap && teamPreviewMapDivisions.length ? `<svg viewBox="${escapeHtml(mapViewBox)}" role="img" aria-labelledby="${escapeHtml(mapHeadingId)}"><title>Bangladesh division map preview</title>${teamPreviewMapDivisions.map(division=>`<g data-division="${escapeHtml(division.key)}"><title>${escapeHtml(teamPreviewMapDivisionName(division))}</title><path d="${escapeHtml(division.path)}"></path></g>`).join('')}</svg>` : '';
        const filterLabels = ['All divisions',...teamPreviewMapDivisions.map(teamPreviewMapDivisionName).filter(Boolean)];
        const people = items.slice(0,4).map(item=>{const name=teamPreviewName(item)||'Team member';const image=teamPreviewImage(item);const initials=name.split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]||'').join('').toUpperCase();const division=teamPreviewDivisionName(item);return `<li class="igf-preview-team-directory__person"><span class="igf-preview-team-directory__avatar">${image?`<img src="${escapeHtml(image)}" alt="">`:`<span aria-hidden="true">${escapeHtml(initials||'IGF')}</span>`}</span><span><strong>${escapeHtml(name)}</strong><small>${escapeHtml([item.designation||item.position||item.title,division].filter(Boolean).join(' · '))}</small></span></li>`}).join('');
        const behaviorCopy = {panel:'Profile details stay beside the directory.',modal:'Profile details open in an accessible dialog.',link:'The person’s public profile link opens.'}[profileBehavior];
        const profile = featured ? `<article class="igf-preview-team-directory__profile" role="region" aria-labelledby="${escapeHtml(profileHeadingId)}"><small>${profileBehavior === 'modal' ? 'Dialog preview' : profileBehavior === 'link' ? 'Profile link preview' : 'Selected profile'}</small><h3 id="${escapeHtml(profileHeadingId)}">${escapeHtml(teamPreviewName(featured)||'Team member')}</h3>${featured.designation||featured.position||featured.title?`<p>${escapeHtml(featured.designation||featured.position||featured.title)}</p>`:''}${teamPreviewDivisionName(featured)?`<p><i class="fa fa-map-marker" aria-hidden="true"></i> ${escapeHtml(teamPreviewDivisionName(featured))}</p>`:''}<p class="igf-preview-team-directory__behavior">${escapeHtml(behaviorCopy)}</p></article>` : '';
        return `<div class="igf-preview-team-directory is-map-${mapPosition} is-profile-${profileBehavior}${showMap?'':' igf-preview-team-directory--no-map'}" role="region" aria-label="Interactive directory and Bangladesh map preview"><aside class="igf-preview-team-directory__map" aria-labelledby="${escapeHtml(mapHeadingId)}"><h3 id="${escapeHtml(mapHeadingId)}">Bangladesh divisions</h3><p>${showMap?'Map and text filters work together for visitors.':'The map is hidden; division filters remain available.'}</p>${map}<div class="igf-preview-team-directory__filters" role="group" aria-label="Division filter preview">${filterLabels.map(label=>`<span>${escapeHtml(label)}</span>`).join('')}</div></aside><section class="igf-preview-team-directory__people" aria-labelledby="${escapeHtml(peopleHeadingId)}"><h3 id="${escapeHtml(peopleHeadingId)}">People</h3>${people?`<ul>${people}</ul>`:`<p class="igf-banner-guidance">${escapeHtml(content.empty_state || 'Published board and team members will appear here automatically.')}</p>`}</section>${profile}</div>`;
    }
    function renderTeamHeroesPreview(block, items) {
        const content = block.content || {};
        const showMap = content.show_map !== false;
        const mapPosition = String(content.map_position || 'left') === 'right' ? 'right' : 'left';
        const animationEnabled = content.animation_enabled !== false;
        const autoplay = [true,1,'1','true'].includes(content.autoplay);
        const featured = items[0] || null;
        const profiles = items.slice(0,3);
        const profileHeadingId = `igf-team-heroes-profile-${block.uuid}`;
        const mapViewBox = String(teamPreviewMap.viewBox || '0 0 420 560');
        const map = showMap ? `<aside class="igf-preview-team-heroes__map" aria-label="Bangladesh map preview">${teamPreviewMapDivisions.length?`<div class="igf-preview-team-heroes__map-stage"><svg viewBox="${escapeHtml(mapViewBox)}" role="img" aria-label="Bangladesh division map preview"><title>Bangladesh division map preview</title>${teamPreviewMapDivisions.map(division=>`<g data-division="${escapeHtml(division.key)}"><title>${escapeHtml(teamPreviewMapDivisionName(division))}</title><path d="${escapeHtml(division.path)}" fill-rule="evenodd"></path></g>`).join('')}</svg></div>`:'<p class="igf-preview-team-heroes__empty">The shared Bangladesh map is unavailable in this preview.</p>'}</aside>` : '';
        const selectors = profiles.map((item,index)=>{const name=teamPreviewName(item)||'Team member';const image=teamPreviewImage(item);const initials=name.split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]||'').join('').toUpperCase();const role=item.designation||item.position||item.title||'';return `<li class="igf-preview-team-heroes__person${index===0?' is-selected':''}" ${index===0?'aria-current="true"':''}><span class="igf-preview-team-heroes__avatar">${image?`<img src="${escapeHtml(image)}" alt="">`:`<span aria-hidden="true">${escapeHtml(initials||'IGF')}</span>`}</span><strong>${escapeHtml(name)}</strong>${role?`<small>${escapeHtml(role)}</small>`:''}</li>`}).join('');
        const name = featured ? teamPreviewName(featured) || 'Team member' : 'Featured profiles';
        const role = featured ? featured.designation || featured.position || featured.title || '' : '';
        const biography = featured ? String(featured.biography || featured.body || featured.description || '').replace(/\r\n?/g,'\n').trim() : '';
        const biographyParagraphs = biography.split(/\n\s*\n+/).map(eventsNewsPlainText).filter(Boolean).slice(0,2);
        const qualification = featured ? eventsNewsPlainText(featured.qualification || '') : '';
        const profile = featured ? `<article class="igf-preview-team-heroes__profile${animationEnabled?' is-animated':''}" role="region" aria-labelledby="${escapeHtml(profileHeadingId)}"><div class="igf-preview-team-heroes__profile-heading">${role?`<p class="igf-preview-team-heroes__role">${escapeHtml(role)}</p>`:''}<h3 id="${escapeHtml(profileHeadingId)}">${escapeHtml(name)}</h3><span class="igf-preview-team-heroes__socials" aria-label="Social links preview"><i class="fa fa-linkedin" aria-hidden="true"></i><i class="fa fa-globe" aria-hidden="true"></i></span></div><div class="igf-preview-team-heroes__biography">${biographyParagraphs.length?biographyParagraphs.map(paragraph=>`<p>${escapeHtml(paragraph)}</p>`).join(''):'<p>Biography and leadership story appear here when supplied in the team manager.</p><p>A second paragraph can add more context about this person’s work and experience.</p>'}</div>${qualification?`<p class="igf-preview-team-heroes__qualification"><strong>Background:</strong> ${escapeHtml(qualification)}</p>`:''}</article>` : `<p class="igf-preview-team-heroes__empty">${escapeHtml(content.empty_state || 'Published board and team members will appear here automatically.')}</p>`;
        const showcaseHeading = String(content.heading || block.label || 'Meet the Heroes').trim();
        const showcaseHeadingWords = showcaseHeading.split(/\s+/).filter(Boolean);
        const showcaseHeadingEmphasis = showcaseHeadingWords.pop() || '';
        const showcaseHeadingPrefix = showcaseHeadingWords.join(' ');
        const showcaseHeadingMarkup = `${showcaseHeadingPrefix?`<span>${escapeHtml(showcaseHeadingPrefix)}</span> `:''}<strong>${escapeHtml(showcaseHeadingEmphasis)}</strong>`;
        const introduction = `<header class="igf-preview-team-heroes__heading"><p class="igf-preview-team-heroes__eyebrow"><span class="igf-preview-team-heroes__eyebrow-marks" aria-hidden="true"><span></span><span></span></span>${escapeHtml(content.eyebrow || 'Meet the Heroes')}</p><h2>${showcaseHeadingMarkup}</h2>${content.body?`<p class="igf-preview-team-heroes__lead">${escapeHtml(eventsNewsPlainText(content.body))}</p>`:''}</header>`;
        const memberContent = `<div class="igf-preview-team-heroes__content">${introduction}${selectors?`<ul class="igf-preview-team-heroes__profiles" aria-label="Profile selector preview">${selectors}</ul>`:''}${profile}</div>`;
        return `<div class="igf-preview-team-heroes is-map-${mapPosition}${showMap?'':' igf-preview-team-heroes--no-map'}${animationEnabled?' is-motion-enabled':''}" data-autoplay="${autoplay}" role="region" aria-label="Meet the Heroes showcase preview">${memberContent}${map}</div>`;
    }

    function renderPreviewBlock(block) {
        const content = block.content || {};
        const selected = ` igf-preview-block--presentation-${normalizedSectionPresentation(content.section_presentation)}${block.uuid === state.selected ? ' is-selected' : ''}`;
        const visibility = `${block.show_on_desktop ? '' : ' data-hide-desktop="true"'}${block.show_on_mobile ? '' : ' data-hide-mobile="true"'}`;
        if (block.type === 'hero') {
            const slides = heroSlidesForEditor(block);
            const slide = slides[0];
            const opacity = Math.min(100, Math.max(0, Number(slide.overlay_opacity ?? 64))) / 100;
            const backgroundImage = slide.image ? `background-image:url('${escapeHtml(slide.image)}');` : '';
            const background = ` style="${backgroundImage}--overlay-opacity:${opacity}"`;
            const label = slides.length > 1 ? `${block.label} (${slides.length} slides)` : block.label;
            return `<section class="igf-preview-block igf-preview-block--hero${selected}" data-block="${block.uuid}" data-label="${escapeHtml(label)}"${background}${visibility}><div class="igf-eyebrow">${escapeHtml(slide.eyebrow)}</div><h2>${escapeHtml(slide.heading)}</h2><p>${escapeHtml(slide.body)}</p><div><span class="igf-btn igf-btn--primary">${escapeHtml(slide.primary_label || 'Donate now')}</span></div></section>`;
        }
        if (block.type === 'stats') {
            const stats = Array.isArray(content.items) ? content.items : [];
            const animated = content.animation_enabled !== false;
            const animationType = ['count_up','fade_up','pop'].includes(content.animation_type) ? content.animation_type : 'count_up';
            const duration = Math.min(5000, Math.max(300, Number(content.animation_duration || 1600)));
            const delay = Math.min(1000, Math.max(0, Number(content.animation_delay ?? 120)));
            return `<section class="igf-preview-block igf-preview-block--stats${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}><h2>${escapeHtml(content.heading)}</h2><div class="igf-stats">${stats.map((item,index) => `<div class="igf-stat${animated ? ` is-animation-${animationType.replace('_','-')}` : ''}" style="--preview-animation-duration:${duration}ms;--preview-animation-delay:${delay * index}ms"><strong>${escapeHtml(item.value)}</strong>${escapeHtml(item.label)}</div>`).join('')}</div></section>`;
        }
        if (block.type === 'ways_to_give') {
            const available = contentOptions.ways_to_give?.items || [];
            const known = new Map([...(contentOptions.ways_to_give?.known_items || []),...available].map(option => [String(option.value),option]));
            let options = content.selection_mode === 'manual'
                ? (content.selected_items || []).map(token => known.get(String(token))).filter(option => option?.active !== false)
                : available;
            if (content.layout === 'single_cta') options = options.slice(0,1);
            const layoutClass = content.layout === 'single_cta' ? ' igf-preview-giving--single' : content.layout === 'banner' ? ' igf-preview-giving--banner' : '';
            return `<section class="igf-preview-block${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}><div class="igf-eyebrow">${escapeHtml(content.eyebrow || '')}</div><h2>${escapeHtml(content.heading || block.label)}</h2><p>${escapeHtml(String(content.body || '').replace(/<[^>]*>/g,' '))}</p><div class="igf-preview-giving${layoutClass}">${options.map(option => `<article><i class="fa fa-gift" aria-hidden="true"></i><h3>${escapeHtml(option.label)}</h3><p>${escapeHtml(option.destination || 'Managed giving destination')}</p></article>`).join('')}</div>${options.length?'':`<p class="igf-banner-guidance">${escapeHtml(content.empty_state || 'No giving options selected.')}</p>`}</section>`;
        }
        if (block.type === 'events_news') {
            const resolved = eventsNewsPreviewItems(block);
            const eventRows = resolved.upcoming.map(item => {
                const image = eventsNewsImage(item);
                const date = eventsNewsDateLabel(item,true);
                const location = item.location || item.venue || item.event_attendance_mode || item.attendance_mode || '';
                const body = eventsNewsPlainText(item.body || item.excerpt || item.description || '');
                return `<article class="igf-preview-events-news__event"><div class="igf-preview-events-news__event-media">${image?`<img src="${escapeHtml(image)}" alt="${escapeHtml(item.image_alt||item.label||'')}">`:'<i class="fa fa-calendar" aria-hidden="true"></i>'}</div><div class="igf-preview-events-news__event-copy"><h3>${escapeHtml(item.label||'Upcoming event')}</h3>${date||location?`<div class="igf-preview-events-news__meta">${date?`<span><i class="fa fa-calendar" aria-hidden="true"></i> ${escapeHtml(date)}</span>`:''}${location?`<span><i class="fa fa-map-marker" aria-hidden="true"></i> ${escapeHtml(location)}</span>`:''}</div>`:''}${body?`<p>${escapeHtml(body)}</p>`:''}<span class="igf-preview-events-news__link">${escapeHtml(content.item_link_label||item.link_label||'Learn more')} →</span></div></article>`;
            }).join('');
            const featured = resolved.featured;
            const featuredImage = eventsNewsImage(featured);
            const featuredBody = featured ? eventsNewsPlainText(featured.body || featured.excerpt || featured.description || '') : '';
            const feature = featured
                ? `<article class="igf-preview-events-news__feature"><div class="igf-preview-events-news__feature-media">${featuredImage?`<img src="${escapeHtml(featuredImage)}" alt="${escapeHtml(featured.image_alt||featured.label||'')}">`:'<i class="fa fa-newspaper-o" aria-hidden="true"></i>'}</div><div class="igf-preview-events-news__feature-copy">${eventsNewsDateLabel(featured)?`<div class="igf-preview-events-news__meta"><span>${escapeHtml(eventsNewsDateLabel(featured))}</span></div>`:''}<h3>${escapeHtml(featured.label||'Featured news')}</h3>${featuredBody?`<p>${escapeHtml(featuredBody)}</p>`:''}<span class="igf-preview-events-news__link">${escapeHtml(content.item_link_label||featured.link_label||'Learn more')} →</span>${content.cta_label?`<span class="igf-preview-events-news__cta"><span>${escapeHtml(content.cta_label)}</span><span aria-hidden="true">→</span></span>`:''}</div></article>`
                : `<p class="igf-preview-events-news__empty">${escapeHtml(content.news_empty_state||'No featured news is available right now.')}</p>`;
            const introduction = eventsNewsPlainText(content.body || '');
            return `<section class="igf-preview-block igf-preview-block--events-news${selected}" data-block="${escapeHtml(block.uuid)}" data-label="${escapeHtml(block.label)}"${visibility}><header class="igf-preview-events-news__intro">${content.eyebrow?`<div class="igf-eyebrow">${escapeHtml(content.eyebrow)}</div>`:''}${introduction?`<p>${escapeHtml(introduction)}</p>`:''}</header><div class="igf-preview-events-news"><section class="igf-preview-events-news__column" aria-label="Upcoming events preview"><header class="igf-preview-events-news__heading"><h2>${escapeHtml(content.events_heading||'Upcoming Events')}</h2>${content.events_view_all_label?`<span>${escapeHtml(content.events_view_all_label)} →</span>`:''}</header><div class="igf-preview-events-news__list">${eventRows||`<p class="igf-preview-events-news__empty">${escapeHtml(content.events_empty_state||'No upcoming events are scheduled right now.')}</p>`}</div></section><section class="igf-preview-events-news__column" aria-label="Featured news preview"><header class="igf-preview-events-news__heading"><h2>${escapeHtml(content.news_heading||'Featured News')}</h2>${content.news_view_all_label?`<span>${escapeHtml(content.news_view_all_label)} →</span>`:''}</header>${feature}</section></div></section>`;
        }
        if (block.type === 'team') {
            const options = managedPreviewItems(block);
            const heading = `<div class="igf-eyebrow">${escapeHtml(content.eyebrow || '')}</div><h2>${escapeHtml(content.heading || block.label)}</h2>${content.body?`<p>${escapeHtml(String(content.body).replace(/<[^>]*>/g,' '))}</p>`:''}`;
            if (content.team_presentation === 'heroes_showcase') {
                return `<section class="igf-preview-block igf-preview-block--team-heroes${selected}" data-block="${escapeHtml(block.uuid)}" data-label="${escapeHtml(block.label)}"${visibility}>${renderTeamHeroesPreview(block,options)}</section>`;
            }
            if (content.team_presentation === 'directory_map') {
                return `<section class="igf-preview-block igf-preview-block--team-directory${selected}" data-block="${escapeHtml(block.uuid)}" data-label="${escapeHtml(block.label)}"${visibility}>${heading}${renderTeamDirectoryPreview(block,options)}</section>`;
            }
            const cards = options.map(item=>{const name=teamPreviewName(item)||'Team member';const image=teamPreviewImage(item);const initials=name.split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]||'').join('').toUpperCase();return `<article><div class="igf-preview-team-card__media">${image?`<img src="${escapeHtml(image)}" alt="${escapeHtml(item.image_alt||name)}">`:`<span aria-hidden="true">${escapeHtml(initials||'IGF')}</span>`}</div><h3>${escapeHtml(name)}</h3>${item.designation||item.position||item.title?`<p>${escapeHtml(item.designation||item.position||item.title)}</p>`:''}</article>`}).join('');
            return `<section class="igf-preview-block${selected}" data-block="${escapeHtml(block.uuid)}" data-label="${escapeHtml(block.label)}"${visibility}>${heading}<div class="igf-preview-team-grid">${cards}</div>${options.length?'':`<p class="igf-banner-guidance">${escapeHtml(content.empty_state || 'Published board and team members will appear here automatically.')}</p>`}</section>`;
        }
        if (block.type === 'testimonials') {
            const options = managedPreviewItems(block);
            const split = content.display_style === 'split';
            const visible = options.slice(0, split ? 2 : 1);
            const cards = visible.map(option => {
                const initials = String(option.label || 'Community member').split(/\s+/).filter(Boolean).slice(0,2).map(part => part[0]).join('').toUpperCase();
                const portrait = option.photo
                    ? `<img src="${escapeHtml(option.photo)}" alt="${escapeHtml(option.label || '')}">`
                    : `<span class="igf-preview-testimonial-avatar" aria-hidden="true">${escapeHtml(initials)}</span>`;
                return `<article><blockquote>${escapeHtml(option.quote || '')}</blockquote><div class="igf-preview-testimonial-person">${portrait}<span><strong>${escapeHtml(option.label || 'Community member')}</strong>${option.designation?`<small>${escapeHtml(option.designation)}</small>`:''}</span></div></article>`;
            }).join('');
            const style = split ? 'split' : 'spotlight';
            return `<section class="igf-preview-block igf-preview-block--testimonials igf-preview-block--testimonials-${style}${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}><div class="igf-eyebrow">${escapeHtml(content.eyebrow || '')}</div><h2>${escapeHtml(content.heading || block.label)}</h2>${content.body?`<p>${escapeHtml(String(content.body).replace(/<[^>]*>/g,' '))}</p>`:''}<div class="igf-preview-testimonials igf-preview-testimonials--${style}">${cards}</div>${options.length?`<div class="igf-preview-testimonial-controls" aria-hidden="true"><span class="igf-preview-testimonial-dots"><i></i><i></i><i></i></span><span class="igf-preview-testimonial-arrows"><i>←</i><i>→</i></span></div>`:`<p class="igf-banner-guidance">${escapeHtml(content.empty_state || 'No approved community stories are available.')}</p>`}</section>`;
        }
        if (block.type === 'causes' && content.presentation === 'focus_areas') {
            const options = managedPreviewItems(block);
            const body = String(content.body || '').replace(/<[^>]*>/g, ' ');
            return `<section class="igf-preview-block igf-preview-block--focus${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}><div class="igf-preview-focus-grid"><header class="igf-preview-focus-tile igf-preview-focus-heading" style="--focus-preview-delay:0ms"><div class="igf-eyebrow">${escapeHtml(content.eyebrow || '')}</div><h2>${escapeHtml(content.heading || block.label)}</h2>${body?`<p>${escapeHtml(body)}</p>`:''}<span class="igf-preview-focus-view-all">${escapeHtml(content.view_all_label || 'View all programs')} →</span></header>${options.map((option,index) => `<article class="igf-preview-focus-tile igf-preview-focus-card" style="--focus-preview-delay:${Math.min(index + 1, 5) * 100}ms">${option.image?`<img src="${escapeHtml(option.image)}" alt="${escapeHtml(option.image_alt || option.label || '')}">`:'<i class="fa fa-compass" aria-hidden="true"></i>'}<h3>${escapeHtml(option.label || 'Published item')}</h3>${option.body?`<p>${escapeHtml(option.body)}</p>`:''}<span>${escapeHtml(content.item_link_label || 'Learn more')} →</span></article>`).join('')}</div>${options.length?'':`<p class="igf-banner-guidance">${escapeHtml(content.empty_state || 'No published items are available for this selection.')}</p>`}</section>`;
        }
        if (block.type === 'cta' && !['campaign', 'campus-actions'].includes(content.variant)) {
            const body = String(content.body || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            const primary = content.primary_label ? `<span class="igf-preview-cta-button">${escapeHtml(content.primary_label)}</span>` : '';
            const secondary = content.secondary_label ? `<span class="igf-preview-cta-button igf-preview-cta-button--outline">${escapeHtml(content.secondary_label)}</span>` : '';
            const hasActions = Boolean(primary || secondary);
            return `<section class="igf-preview-block igf-preview-block--cta${selected}" data-block="${escapeHtml(block.uuid)}" data-label="${escapeHtml(block.label)}"${visibility}><div class="igf-preview-cta-panel" data-actions="${hasActions}"><span class="igf-preview-cta-signal" aria-hidden="true"><i class="fa fa-heart"></i></span><div class="igf-preview-cta-content">${content.eyebrow ? `<div class="igf-eyebrow">${escapeHtml(content.eyebrow)}</div>` : ''}<h2>${escapeHtml(content.heading || block.label)}</h2>${body ? `<p>${escapeHtml(body)}</p>` : ''}</div>${hasActions ? `<div class="igf-preview-cta-actions">${primary}${secondary}</div>` : ''}</div></section>`;
        }
        if (block.type === 'spacer') return `<section class="igf-preview-block${selected}" style="padding:${content.size === 'large' ? 90 : content.size === 'small' ? 24 : 54}px" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"></section>`;
        if (block.type === 'custom_html') return `<section class="igf-preview-block${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}>${content.html || '<p>Custom HTML block</p>'}</section>`;
        const body = content.body || content.html || '';
        return `<section class="igf-preview-block${selected}" data-block="${block.uuid}" data-label="${escapeHtml(block.label)}"${visibility}><div class="igf-eyebrow">${escapeHtml(content.eyebrow || '')}</div><h2>${escapeHtml(content.heading || block.label)}</h2><div>${body}</div></section>`;
    }

    function renderPreview() {
        const emptyMessage = permissions.create
            ? '<div class="igf-empty"><h2>Build this page</h2><p>Add sections from the block panel. Every section remains editable, reorderable, hideable, and recoverable.</p></div>'
            : '<div class="igf-empty"><h2>No sections yet</h2><p>This page does not have any sections to preview.</p></div>';
        preview.innerHTML = state.blocks.length ? state.blocks.filter(block => block.is_enabled).map(renderPreviewBlock).join('') : emptyMessage;
    }

    async function uploadMedia(file, key, dropzone, blockUuid, slideIndex = null) {
        if (!permissions.create || !permissions.edit) return;
        const mediaKind = dropzone.dataset.mediaKind === 'video' ? 'video' : 'image';
        const validType = mediaKind === 'video'
            ? /^video\/(mp4|webm)$/.test(file?.type || '')
            : /^image\/(jpeg|png|webp|gif)$/.test(file?.type || '');
        if (!file || !validType) {
            notify(mediaKind === 'video' ? 'Choose a supported MP4 or WebM video.' : 'Choose a supported JPG, PNG, WebP, or GIF image.');
            return;
        }
        const label = dropzone.querySelector('span');
        const originalLabel = label.innerHTML;
        const scope = blockScope(blockUuid);
        const wasDirty = state.dirtyScopes.has(scope);
        state.uploadingScopes.add(scope);
        setDirty(true, scope);
        dropzone.setAttribute('aria-busy', 'true');
        label.textContent = `Uploading ${file.name}â€¦`;
        try {
            label.textContent = `Uploading ${file.name}...`;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('locale', locale);
            formData.append('media_kind', mediaKind);
            const response = await fetch(routes.storeMedia, {
                method: 'POST',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf},
                body: formData,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(validation || payload.message || 'The upload could not be completed.');
            }
            const targetBlock = state.blocks.find(item => item.uuid === blockUuid);
            if (targetBlock && Number.isInteger(slideIndex)) {
                const slides = heroSlidesForEditor(targetBlock);
                slides[slideIndex] = {...slides[slideIndex], [key]: payload.asset.url};
                targetBlock.content = {...(targetBlock.content || {}), slides};
                if (slideIndex === 0) targetBlock.content[key] = payload.asset.url;
            } else if (targetBlock) {
                targetBlock.content = {...(targetBlock.content || {}), [key]: payload.asset.url};
            }
            const input = state.selected === blockUuid
                ? (Number.isInteger(slideIndex)
                    ? inspector.querySelector(`[data-slide-card][data-slide-index="${slideIndex}"] [data-slide-key="${CSS.escape(key)}"]`)
                    : inspector.querySelector(`[data-content-key="${CSS.escape(key)}"]`))
                : null;
            if (input) input.value = payload.asset.url;
            const options = document.getElementById(mediaKind === 'video' ? 'video-library-options' : 'media-library-options');
            options.insertAdjacentHTML('afterbegin', `<option value="${escapeHtml(payload.asset.url)}">${escapeHtml(payload.asset.original_name)}</option>`);
            setDirty(true, blockScope(blockUuid));
            notify(payload.message);
        } catch (error) {
            if (!wasDirty) setDirty(false, scope);
            notify(error.message);
        } finally {
            state.uploadingScopes.delete(scope);
            dropzone.removeAttribute('aria-busy');
            label.innerHTML = originalLabel;
        }
    }

    function wireMediaPickers(blockUuid) {
        if (!permissions.create || !permissions.edit) return;
        inspector.querySelectorAll('[data-media-dropzone]').forEach(dropzone => {
            const input = dropzone.querySelector('.igf-media-file');
            const slideIndex = dropzone.dataset.mediaSlideIndex === undefined ? null : Number(dropzone.dataset.mediaSlideIndex);
            const choose = () => input.click();
            dropzone.addEventListener('click', choose);
            dropzone.addEventListener('keydown', event => {
                if (!['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                choose();
            });
            input.addEventListener('click', event => event.stopPropagation());
            input.addEventListener('change', () => uploadMedia(input.files?.[0], dropzone.dataset.mediaKey, dropzone, blockUuid, slideIndex));
            dropzone.addEventListener('dragover', event => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
            dropzone.addEventListener('drop', event => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
                uploadMedia(event.dataTransfer?.files?.[0], dropzone.dataset.mediaKey, dropzone, blockUuid, slideIndex);
            });
        });
    }

    function captureHeroEditor(block) {
        if (block?.type !== 'hero' || !document.getElementById('hero-autoplay')) return;
        block.label = document.getElementById('block-label')?.value || block.label;
        block.is_enabled = document.getElementById('block-enabled')?.checked ?? block.is_enabled;
        block.show_on_desktop = document.getElementById('block-desktop')?.checked ?? block.show_on_desktop;
        block.show_on_mobile = document.getElementById('block-mobile')?.checked ?? block.show_on_mobile;
        block.available_from = document.getElementById('block-available-from')?.value || null;
        block.available_until = document.getElementById('block-available-until')?.value || null;
        const slides = [...inspector.querySelectorAll('[data-slide-card]')].map(card => {
            const slide = {};
            card.querySelectorAll('[data-slide-key]').forEach(input => {
                slide[input.dataset.slideKey] = input.type === 'number' ? Number(input.value) : input.value;
            });
            return normalizeHeroSlide(slide);
        });
        const first = slides[0] || normalizeHeroSlide();
        const content = {
            ...(block.content || {}),
            autoplay: document.getElementById('hero-autoplay').checked,
            pause_on_hover: document.getElementById('hero-pause-hover').checked,
            interval: Math.min(20000, Math.max(3000, Number(document.getElementById('hero-interval').value || 6000))),
            slides,
        };
        heroSlideKeys.forEach(key => { content[key] = first[key]; });
        block.content = content;
    }

    function wireHeroEditor(block) {
        if (block?.type !== 'hero') return;
        document.getElementById('add-hero-slide')?.addEventListener('click', () => {
            captureHeroEditor(block);
            const slides = heroSlidesForEditor(block);
            if (slides.length >= 8) return notify('A carousel can contain up to 8 slides.');
            slides.push(normalizeHeroSlide({heading: `Carousel slide ${slides.length + 1}`, overlay_opacity: 64}));
            block.content = {...(block.content || {}), slides};
            setDirty(true, blockScope(block.uuid));
            renderPreview(); renderInspector();
        });
        inspector.querySelectorAll('[data-slide-action]').forEach(button => button.addEventListener('click', () => {
            captureHeroEditor(block);
            const slides = heroSlidesForEditor(block);
            const index = Number(button.dataset.slideIndex);
            if (button.dataset.slideAction === 'remove') {
                if (slides.length === 1) return;
                slides.splice(index, 1);
            } else {
                const target = button.dataset.slideAction === 'up' ? index - 1 : index + 1;
                if (target < 0 || target >= slides.length) return;
                [slides[index], slides[target]] = [slides[target], slides[index]];
            }
            block.content = {...(block.content || {}), slides};
            setDirty(true, blockScope(block.uuid));
            renderPreview(); renderInspector();
        }));
    }

    function renderInspector() {
        const block = current();
        if (!block) { inspector.innerHTML = '<p class="igf-muted">Select a block to edit its content and visibility.</p>'; return; }
        const canEditContent = canEditBlockContent(block);
        const inspectorContent = block.type === 'stats' ? {
            eyebrow: block.content?.eyebrow || '',
            heading: block.content?.heading || '',
            animation_enabled: block.content?.animation_enabled !== false,
            animation_type: ['count_up','fade_up','pop'].includes(block.content?.animation_type) ? block.content.animation_type : 'count_up',
            animation_duration: Math.min(5000, Math.max(300, Number(block.content?.animation_duration || 1600))),
            animation_delay: Math.min(1000, Math.max(0, Number(block.content?.animation_delay ?? 120))),
            items: Array.isArray(block.content?.items) ? block.content.items : [],
        } : (block.content || {});
        const fields = block.type === 'hero'
            ? renderHeroEditor(block)
            : block.type === 'media_text'
                ? renderMediaTextInspector(block)
            : block.type === 'ways_to_give'
                ? renderWaysToGiveInspector(block)
                : block.type === 'events_news'
                    ? renderEventsNewsInspector(block)
                : contentOptions.sources?.[block.type]
                    ? renderManagedInspector(block)
                    : Object.entries(inspectorContent).filter(([key]) => key !== 'section_presentation').map(([key, value]) => {
            const labels = {animation_enabled:'Animate statistics',animation_type:'Animation style',animation_duration:'Animation duration (milliseconds)',animation_delay:'Delay between statistics (milliseconds)'};
            const label = labels[key] || key.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
            const fieldId = `block-content-${String(key).replace(/[^a-z0-9_-]/gi, '-')}`;
            if (typeof value === 'boolean') return `<label class="igf-check" for="${fieldId}"><input id="${fieldId}" type="checkbox" data-content-key="${escapeHtml(key)}" ${value ? 'checked' : ''}> ${escapeHtml(label)}</label>`;
            if (Array.isArray(value) || (value && typeof value === 'object')) return `<div class="igf-field"><label for="${fieldId}">${escapeHtml(label)}</label><textarea id="${fieldId}" data-content-key="${escapeHtml(key)}" data-json="true">${escapeHtml(JSON.stringify(value, null, 2))}</textarea></div>`;
            if (key === 'animation_type') return `<div class="igf-field"><label for="${fieldId}">${escapeHtml(label)}</label><select id="${fieldId}" data-content-key="${escapeHtml(key)}"><option value="count_up" ${value === 'count_up' ? 'selected' : ''}>Count up from zero</option><option value="fade_up" ${value === 'fade_up' ? 'selected' : ''}>Fade upward</option><option value="pop" ${value === 'pop' ? 'selected' : ''}>Gentle pop</option></select></div>`;
            const multiline = ['body','html','description'].includes(key);
            const isMedia = ['image','background_image','video','poster'].includes(key);
            const inputType = key === 'overlay_opacity' ? ' type="number" min="0" max="100" step="1"' : key === 'animation_duration' ? ' type="number" min="300" max="5000" step="100"' : key === 'animation_delay' ? ' type="number" min="0" max="1000" step="25"' : '';
            if (isMedia) {
                const uploader = permissions.create ? `<div class="igf-media-dropzone" tabindex="0" role="button" data-media-dropzone data-media-key="${escapeHtml(key)}" aria-label="Upload ${escapeHtml(label)}"><span>Drop an image here<br>or click to upload</span><input class="igf-media-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></div>` : '';
                return `<div class="igf-media-picker"><div class="igf-field"><label for="${fieldId}">${escapeHtml(label)}</label><input id="${fieldId}" data-content-key="${escapeHtml(key)}" value="${escapeHtml(value)}" list="media-library-options"></div>${uploader}<div class="igf-media-picker__links"><span>${permissions.create ? '20 MB maximum' : 'Choose an existing asset'}</span>${mediaLibraryLink}</div></div>`;
            }
            return `<div class="igf-field"><label for="${fieldId}">${escapeHtml(label)}</label>${multiline ? `<textarea id="${fieldId}" data-content-key="${escapeHtml(key)}">${escapeHtml(value)}</textarea>` : `<input id="${fieldId}"${inputType} data-content-key="${escapeHtml(key)}" value="${escapeHtml(value)}">`}</div>`;
        }).join('');
        const libraryControl = block.is_reusable
            ? (permissions.editReusable
                ? `<div class="igf-add"><strong>Reusable section</strong><p class="igf-muted">Content edits synchronize everywhere “${escapeHtml(block.reusable_name || block.label)}” is used. Detach it first for a page-only copy.</p>${permissions.edit ? '<button type="button" class="igf-btn igf-btn--small" id="detach-block">Detach from library</button>' : ''}</div>`
                : `<div class="igf-add"><strong>Shared content is read only for your role</strong><p class="igf-muted">Visibility and schedule controls below apply only to this page. Detach for a page-only copy, or ask a Reusable Sections editor to update “${escapeHtml(block.reusable_name || block.label)}” everywhere.</p>${permissions.edit ? '<button type="button" class="igf-btn igf-btn--small" id="detach-block">Detach for this page</button>' : ''}</div>`)
            : (permissions.create ? '<button type="button" class="igf-btn" id="promote-block">Make reusable</button>' : '');
        const saveAction = permissions.edit ? `<button type="button" class="igf-btn" id="save-block"${state.dirtyScopes.has(blockScope(block.uuid)) ? '' : ' disabled'}>Save section</button>` : '';
        const duplicateAction = permissions.create ? '<button type="button" class="igf-btn" id="duplicate-block">Duplicate</button>' : '';
        const promoteAction = permissions.create && !block.is_reusable ? '<button type="button" class="igf-btn" id="promote-block-bottom">Make reusable</button>' : '';
        const deleteAction = permissions.delete ? '<button type="button" class="igf-btn igf-btn--danger" id="delete-block">Delete</button>' : '';
        inspector.innerHTML = `
            <h3>${escapeHtml(blockTypeLabels[block.type] || block.type)}</h3>
            ${libraryControl}
            <div data-block-content-controls>
                <div class="igf-field"><label for="block-label">Admin label</label><input id="block-label" value="${escapeHtml(block.label || '')}"></div>
                ${renderSectionPresentationField(block)}
                ${fields}
                ${block.type === 'stats' ? '<p class="igf-muted">Count up animates numeric values such as 23,000+. Visitors who prefer reduced motion always see the final values without animation.</p>' : ''}
            </div>
            <label class="igf-check"><input id="block-enabled" type="checkbox" ${block.is_enabled ? 'checked' : ''}> Published</label>
            <label class="igf-check"><input id="block-desktop" type="checkbox" ${block.show_on_desktop ? 'checked' : ''}> Show on desktop</label>
            <label class="igf-check"><input id="block-mobile" type="checkbox" ${block.show_on_mobile ? 'checked' : ''}> Show on mobile</label>
            <div class="igf-field"><label for="block-available-from">Publish from (optional)</label><input id="block-available-from" type="datetime-local" value="${escapeHtml(formatDateTimeLocal(block.available_from))}"></div>
            <div class="igf-field"><label for="block-available-until">Publish until (optional)</label><input id="block-available-until" type="datetime-local" value="${escapeHtml(formatDateTimeLocal(block.available_until))}"></div>
            <div class="igf-builder__actions">
                ${saveAction}${duplicateAction}${promoteAction}${deleteAction}
            </div>`;
        if (!permissions.edit) {
            inspector.querySelectorAll('input,textarea,select').forEach(input => input.disabled = true);
        } else {
            if (!canEditContent) {
                const contentControls = inspector.querySelector('[data-block-content-controls]');
                contentControls?.querySelectorAll('input,textarea,select,button').forEach(control => control.disabled = true);
                contentControls?.querySelectorAll('[data-media-dropzone]').forEach(dropzone => {
                    dropzone.setAttribute('aria-disabled', 'true');
                    dropzone.removeAttribute('tabindex');
                });
            }
            inspector.querySelectorAll('input,textarea,select').forEach(input => {
                if (!input.disabled) input.addEventListener('input', () => {
                    if (['ways_to_give','events_news'].includes(block.type) && input.dataset.contentKey) {
                        block.content[input.dataset.contentKey] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? Number(input.value) : input.value;
                        renderPreview();
                    }
                    if (block.type === 'team' && ['show_map','animation_enabled','autoplay'].includes(input.dataset.contentKey)) {
                        block.content[input.dataset.contentKey] = input.checked;
                        renderPreview();
                    }
                    setDirty(true, blockScope(block.uuid));
                });
            });
            if (canEditContent) {
                inspector.querySelector('[data-media-type-rerender]')?.addEventListener('change', input => {
                    const content = {...(block.content || {})};
                    inspector.querySelectorAll('[data-content-key]').forEach(control => {
                        content[control.dataset.contentKey] = control.type === 'checkbox' ? control.checked : control.value;
                    });
                    block.content = {...content, media_type: input.target.value};
                    setDirty(true, blockScope(block.uuid));
                    renderPreview();
                    renderInspector();
                });
                inspector.querySelectorAll('[data-managed-rerender]').forEach(input => input.addEventListener('change', () => { if (input.dataset.contentKey === 'content_source') block.content.selected_items = []; block.content[input.dataset.contentKey] = input.value; if (input.dataset.contentKey === 'team_presentation' && input.value === 'heroes_showcase') block.content.profile_behavior = 'panel'; setDirty(true, blockScope(block.uuid)); renderPreview(); renderInspector(); }));
                inspector.querySelectorAll('[data-events-news-rerender]').forEach(input => input.addEventListener('change', () => {
                    block.content.events_selection_mode = input.value === 'manual' ? 'manual' : 'automatic';
                    setDirty(true,blockScope(block.uuid)); renderPreview(); renderInspector();
                }));
                inspector.querySelectorAll('[data-events-news-event-toggle]').forEach(input => input.addEventListener('change', () => {
                    const selectedEvents = Array.isArray(block.content.selected_event_ids) ? block.content.selected_event_ids.map(String) : [];
                    if (input.checked && selectedEvents.length >= 6) {
                        input.checked = false;
                        return notify('Choose no more than six upcoming events.');
                    }
                    block.content.selected_event_ids = input.checked ? [...selectedEvents,input.value] : selectedEvents.filter(token => token !== input.value);
                    setDirty(true,blockScope(block.uuid)); renderPreview(); renderInspector();
                }));
                inspector.querySelectorAll('[data-events-news-event-move]').forEach(button => button.addEventListener('click', () => {
                    const index = Number(button.dataset.eventsNewsEventIndex);
                    const target = button.dataset.eventsNewsEventMove === 'up' ? index - 1 : index + 1;
                    if (target < 0 || target >= block.content.selected_event_ids.length) return;
                    [block.content.selected_event_ids[index],block.content.selected_event_ids[target]] = [block.content.selected_event_ids[target],block.content.selected_event_ids[index]];
                    setDirty(true,blockScope(block.uuid)); renderPreview(); renderInspector();
                }));
                inspector.querySelectorAll('[data-giving-rerender]').forEach(input => input.addEventListener('change', () => {
                    block.content[input.dataset.contentKey] = input.value;
                    if (block.content.selection_mode !== 'manual' || !['single_cta','banner'].includes(block.content.layout) || block.content.selected_items?.length !== 1) block.content.project_uuid = '';
                    renderPreview(); renderInspector();
                }));
                inspector.querySelectorAll('[data-giving-toggle]').forEach(input => input.addEventListener('change', () => {
                    const selected = Array.isArray(block.content.selected_items) ? block.content.selected_items : [];
                    block.content.selected_items = input.checked ? [...selected,input.value] : selected.filter(token => token !== input.value);
                    block.content.project_uuid = '';
                    setDirty(true, blockScope(block.uuid)); renderPreview(); renderInspector();
                }));
                inspector.querySelectorAll('[data-giving-move]').forEach(button => button.addEventListener('click', () => {
                    const index = Number(button.dataset.givingIndex);
                    const target = button.dataset.givingMove === 'up' ? index - 1 : index + 1;
                    if (target < 0 || target >= block.content.selected_items.length) return;
                    [block.content.selected_items[index],block.content.selected_items[target]] = [block.content.selected_items[target],block.content.selected_items[index]];
                    setDirty(true, blockScope(block.uuid)); renderPreview(); renderInspector();
                }));
                inspector.querySelector('[data-giving-clear-project]')?.addEventListener('click', () => { block.content.project_uuid=''; setDirty(true,blockScope(block.uuid)); renderPreview(); renderInspector(); });
                wireMediaPickers(block.uuid);
                wireHeroEditor(block);
            }
        }
        document.getElementById('save-block')?.addEventListener('click', saveBlock);
        document.getElementById('duplicate-block')?.addEventListener('click', duplicateBlock);
        document.getElementById('delete-block')?.addEventListener('click', deleteBlock);
        document.getElementById('detach-block')?.addEventListener('click', detachReusable);
        document.getElementById('promote-block')?.addEventListener('click', promoteReusable);
        document.getElementById('promote-block-bottom')?.addEventListener('click', promoteReusable);
    }

    function renderAll() { renderList(); renderPreview(); renderInspector(); }

    async function saveBlock() {
        if (!permissions.edit) return;
        const block = current();
        const canEditContent = canEditBlockContent(block);
        const scope = blockScope(block?.uuid);
        if (state.uploadingScopes.has(scope)) {
            notify('Wait for the current media upload to finish.');
            return;
        }
        if (canEditContent) captureHeroEditor(block);
        const content = {...(block.content || {})};
        const convertLegacyContent = legacyContentNeedsConversion
            && document.getElementById('block-enabled').checked;
        if (convertLegacyContent && !confirmLegacyContentConversion('show this first section')) return;
        try {
            if (canEditContent) {
                inspector.querySelectorAll('[data-content-key]').forEach(input => {
                    let value = input.type === 'checkbox' ? input.checked : input.value;
                    if (input.dataset.json) value = JSON.parse(value || '[]');
                    if (input.type === 'number') value = Number(value);
                    content[input.dataset.contentKey] = value;
                });
                inspector.querySelectorAll('[data-content-array-key]').forEach(input => {
                    content[input.dataset.contentArrayKey] = [...input.selectedOptions].map(option => option.value);
                });
            }
            const payload = await request(endpoint(routes.updateBlock, block.uuid, '__BLOCK__'), 'PUT', {
                locale,
                label: canEditContent ? document.getElementById('block-label').value : block.label,
                content,
                settings: block.settings || {},
                is_enabled: document.getElementById('block-enabled').checked,
                show_on_desktop: document.getElementById('block-desktop').checked,
                show_on_mobile: document.getElementById('block-mobile').checked,
                available_from: document.getElementById('block-available-from').value || null,
                available_until: document.getElementById('block-available-until').value || null,
                expected_reusable_version: block.reusable_version,
                convert_legacy_content: convertLegacyContent,
            });
            acceptConvertedLegacyBlock(payload);
            Object.assign(block, payload.block);
            setDirty(false, scope); renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    async function duplicateBlock() {
        if (!permissions.create) return;
        const block = current();
        const scope = blockScope(block?.uuid);
        if (!confirmBlockDiscard('duplicate this section')) return;
        try {
            const payload = await request(endpoint(routes.duplicateBlock, block.uuid, '__BLOCK__'), 'POST', {locale});
            setDirty(false, scope); state.blocks.push(payload.block); state.selected = payload.block.uuid; renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    async function promoteReusable() {
        if (!permissions.create) return;
        const block = current();
        const scope = blockScope(block?.uuid);
        if (!confirmBlockDiscard('add the saved version to the reusable library')) return;
        const name = prompt('Name this reusable section:', block.label || blockTypeLabels[block.type]);
        if (!name?.trim()) return;
        try {
            const payload = await request(endpoint(routes.promoteBlock, block.uuid, '__BLOCK__'), 'POST', {
                locale,
                name: name.trim(),
                library_locale: locale,
            });
            setDirty(false, scope); Object.assign(block, payload.block); renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    async function detachReusable() {
        if (!permissions.edit) return;
        const block = current();
        const scope = blockScope(block?.uuid);
        if (!confirmBlockDiscard('detach the saved version')) return;
        if (!confirm('Detach this section? It will keep the current content, but future library edits will no longer synchronize.')) return;
        try {
            const payload = await request(endpoint(routes.detachBlock, block.uuid, '__BLOCK__'), 'POST', {locale});
            setDirty(false, scope); Object.assign(block, payload.block); renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    async function deleteBlock() {
        if (!permissions.delete) return;
        const block = current();
        const scope = blockScope(block?.uuid);
        if (!confirmBlockDiscard('delete the saved version')) return;
        if (!confirm(`Move “${block.label}” to trash? A revision will be kept.`)) return;
        try {
            const payload = await request(endpoint(routes.destroyBlock, block.uuid, '__BLOCK__'), 'DELETE', {locale});
            setDirty(false, scope); state.blocks = state.blocks.filter(item => item.uuid !== block.uuid); state.selected = state.blocks[0]?.uuid || null; renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    async function saveOrder() {
        if (!permissions.edit) return false;
        try {
            const payload = await request(routes.reorder, 'PUT', {locale, blocks: state.blocks.map(block => block.uuid)});
            setDirty(false, 'order');
            notify(payload.message);
            return true;
        } catch (error) {
            setDirty(true, 'order');
            notify(`${error.message} The displayed order is not saved; retry the move.`);
            return false;
        }
    }

    list.addEventListener('click', async event => {
        const move = event.target.closest('[data-move]');
        if (move) {
            event.stopPropagation();
            const scope = blockScope();
            if (!confirmBlockDiscard('reorder sections')) return;
            setDirty(false, scope);
            const index = Number(move.dataset.index); const target = move.dataset.move === 'up' ? index - 1 : index + 1;
            if (target < 0 || target >= state.blocks.length) return;
            [state.blocks[index], state.blocks[target]] = [state.blocks[target], state.blocks[index]];
            setDirty(true, 'order');
            renderAll(); await saveOrder(); return;
        }
        const item = event.target.closest('[data-select]');
        if (item) selectBlock(item.dataset.select);
    });
    preview.addEventListener('click', event => { const block = event.target.closest('[data-block]'); if (block) selectBlock(block.dataset.block); });

    document.getElementById('add-block')?.addEventListener('click', async () => {
        if (!permissions.create) return;
        const scope = blockScope();
        if (!confirmBlockDiscard('add a new section')) return;
        const convertLegacyContent = legacyContentNeedsConversion;
        if (convertLegacyContent && !confirmLegacyContentConversion('add a new section')) return;
        try {
            const payload = await request(routes.storeBlock, 'POST', {
                locale,
                type: document.getElementById('new-block-type').value,
                convert_legacy_content: convertLegacyContent,
            });
            acceptConvertedLegacyBlock(payload);
            setDirty(false, scope); state.blocks.push(payload.block); state.selected = payload.block.uuid; renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    });

    document.getElementById('attach-reusable')?.addEventListener('click', async () => {
        if (!permissions.edit) return;
        const scope = blockScope();
        if (!confirmBlockDiscard('attach a reusable section')) return;
        const convertLegacyContent = legacyContentNeedsConversion;
        if (convertLegacyContent && !confirmLegacyContentConversion('add this reusable section')) return;
        try {
            const payload = await request(routes.attachReusable, 'POST', {
                locale,
                reusable_uuid: document.getElementById('reusable-block').value,
                convert_legacy_content: convertLegacyContent,
            });
            acceptConvertedLegacyBlock(payload);
            setDirty(false, scope); state.blocks.push(payload.block); state.selected = payload.block.uuid; renderAll(); notify(payload.message);
        } catch (error) { notify(error.message); }
    });

    const tabs = [...document.querySelectorAll('.igf-builder__tab')];
    const activateTab = (tab, focus = false) => {
        tabs.forEach(item => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        document.querySelectorAll('.igf-panel').forEach(panel => panel.hidden = panel.dataset.panel !== tab.dataset.tab);
        if (focus) tab.focus();
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', event => {
            const nextIndex = event.key === 'ArrowRight' ? (index + 1) % tabs.length
                : event.key === 'ArrowLeft' ? (index - 1 + tabs.length) % tabs.length
                : event.key === 'Home' ? 0
                : event.key === 'End' ? tabs.length - 1
                : null;
            if (nextIndex === null) return;
            event.preventDefault();
            activateTab(tabs[nextIndex], true);
        });
    });

    document.querySelectorAll('.igf-viewport-button').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('.igf-viewport-button').forEach(item => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        preview.dataset.viewport = button.dataset.viewport;
    }));

    document.getElementById('locale-switch').addEventListener('change', event => {
        if (hasDirty() && !confirm('Discard your unsaved changes and switch language?')) {
            event.target.value = locale;
            return;
        }
        setDirty(false);
        window.location.href = `${routes.edit}?locale=${encodeURIComponent(event.target.value)}`;
    });
    if (permissions.edit) {
        document.querySelectorAll('#page-name,#page-subtitle,#page-thumbnail-asset,#page-category,#page-banner,#page-funding-project,#page-zakat-eligible,#publication-status,#page-visibility,#scheduled-for,.page-tag-input').forEach(input => input.addEventListener('input', () => { setDirty(true, 'page'); }));
        document.getElementById('publication-status').addEventListener('change', event => {
            document.getElementById('schedule-field').hidden = event.target.value !== 'scheduled';
        });
        document.getElementById('page-thumbnail-asset').addEventListener('change', event => {
            const previewImage = document.getElementById('page-thumbnail-preview');
            const imageUrl = event.target.selectedOptions[0]?.dataset.url || '';
            previewImage.src = imageUrl;
            previewImage.hidden = imageUrl === '';
        });
    }

    async function savePage() {
        if (!permissions.edit) return;
        try {
            const body = {
                locale,
                name: document.getElementById('page-name').value,
                sub_title: document.getElementById('page-subtitle').value,
                status: ['published', 'scheduled'].includes(document.getElementById('publication-status').value),
                publication_status: document.getElementById('publication-status').value,
                visibility: document.getElementById('page-visibility').value,
                scheduled_for: document.getElementById('scheduled-for').value || null,
                tag_ids: [...document.querySelectorAll('.page-tag-input:checked')].map(input => Number(input.value)),
                is_funding_project: document.getElementById('page-funding-project').checked,
                is_zakat_eligible: document.getElementById('page-zakat-eligible').checked,
            };
            const categoryId = document.getElementById('page-category').value;
            const bannerSelect = document.getElementById('page-banner');
            const bannerId = bannerSelect?.value;
            const thumbnailAssetUuid = document.getElementById('page-thumbnail-asset').value;
            if (categoryId !== '__keep_current') body.category_id = categoryId ? Number(categoryId) : null;
            if (bannerSelect && bannerId !== '__keep_current') body.banner_id = bannerId ? Number(bannerId) : null;
            if (thumbnailAssetUuid !== '__keep_current') body.thumbnail_asset_uuid = thumbnailAssetUuid || null;
            const payload = await request(routes.updatePage, 'PUT', body);
            document.getElementById('page-funding-project').checked = !!payload.page?.is_funding_project;
            document.getElementById('page-zakat-eligible').checked = !!payload.page?.is_zakat_eligible;
            syncFundingProjectControls();
            setDirty(false, 'page'); notify(payload.message);
        } catch (error) { notify(error.message); }
    }

    const syncFundingProjectControls = (clearZakat = false) => {
        const funding = document.getElementById('page-funding-project');
        const zakat = document.getElementById('page-zakat-eligible');
        if (!funding || !zakat) return;
        if (clearZakat && !funding.checked) zakat.checked = false;
        zakat.disabled = !permissions.edit || !canManageFundingEligibility || !funding.checked;
    };
    document.getElementById('page-funding-project')?.addEventListener('change', () => syncFundingProjectControls(true));
    syncFundingProjectControls();
    document.getElementById('save-page')?.addEventListener('click', savePage);
    const publishToggle = document.getElementById('publish-menu-toggle');
    const publishMenu = document.getElementById('publish-menu');
    const closePublishMenu = () => {
        if (!publishMenu || !publishToggle) return;
        publishMenu.hidden = true;
        publishToggle.setAttribute('aria-expanded', 'false');
    };
    publishToggle?.addEventListener('click', event => {
        event.stopPropagation();
        const willOpen = publishMenu.hidden;
        publishMenu.hidden = !willOpen;
        publishToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) publishMenu.querySelector('[role="menuitem"]')?.focus();
    });
    const publishItems = [...(publishMenu?.querySelectorAll('[role="menuitem"]') || [])];
    publishItems.forEach((item, index) => item.addEventListener('keydown', event => {
        const nextIndex = event.key === 'ArrowDown' ? (index + 1) % publishItems.length
            : event.key === 'ArrowUp' ? (index - 1 + publishItems.length) % publishItems.length
            : event.key === 'Home' ? 0
            : event.key === 'End' ? publishItems.length - 1
            : null;
        if (nextIndex === null) return;
        event.preventDefault();
        publishItems[nextIndex].focus();
    }));
    publishMenu?.addEventListener('click', async event => {
        const action = event.target.closest('[data-publish-state]');
        if (!action) return;
        const status = action.dataset.publishState;
        const statusInput = document.getElementById('publication-status');
        statusInput.value = status;
        statusInput.dispatchEvent(new Event('input', {bubbles:true}));
        statusInput.dispatchEvent(new Event('change', {bubbles:true}));
        closePublishMenu();
        if (status === 'scheduled' && !document.getElementById('scheduled-for').value) {
            activateTab(document.querySelector('[data-tab="page"]'));
            document.getElementById('scheduled-for').focus();
            notify('Choose a publication date and time, then save the page.');
            return;
        }
        await savePage();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.igf-publish-control')) closePublishMenu();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && publishMenu && !publishMenu.hidden) {
            closePublishMenu();
            publishToggle.focus();
        }
    });

    document.querySelectorAll('.restore-revision').forEach(button => button.addEventListener('click', async () => {
        if (!permissions.edit) return;
        if (!confirm('Restore this revision and discard any unsaved changes? The current saved state will be backed up first.')) return;
        try { const payload = await request(endpoint(routes.restoreRevision, button.dataset.uuid, '__REVISION__'), 'POST', {locale, expected_reusable_versions: revisionReusableVersions[button.dataset.uuid] || {}}); setDirty(false); notify(payload.message); setTimeout(() => location.reload(), 700); }
        catch (error) { notify(error.message); }
    }));

    window.addEventListener('beforeunload', event => {
        if (!hasDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || !hasDirty() || link.target === '_blank' || link.getAttribute('href').startsWith('#')) return;
        if (!confirm('You have unsaved changes. Leave this page and discard them?')) event.preventDefault();
        else setDirty(false);
    }, true);

    renderAll();
})();
</script>
@endsection
