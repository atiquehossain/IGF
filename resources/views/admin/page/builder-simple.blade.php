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
    $canCreatePage = $permission->allows($admin, 'page.create');
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
    $simpleReusableSections = $reusableBlocks->map(fn ($reusable) => [
        'uuid' => $reusable->uuid,
        'name' => $reusable->name,
        'type' => $reusable->type,
        'locale' => $reusable->locale,
    ])->values();
@endphp
<style>
    body.layout-wrapper .left-panel{display:none!important}body.layout-wrapper .right-panel{width:100%!important;max-width:100vw!important;height:100vh;min-height:0;margin-left:0!important;padding-top:0!important;overflow:hidden}body.layout-wrapper .right-panel>header.header.igf-topbar,body.layout-wrapper footer.site-footer{display:none!important}body.layout-wrapper .right-panel>.container-fluid,body.layout-wrapper .right-panel>.container-fluid>.row,body.layout-wrapper .right-panel>.container-fluid>.row>.col-md-12{height:100%}
    .simple-preview{container-type:inline-size}
    .simple-editor{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#6d6a67;--line:#e5dfd9;display:flex;height:100vh;min-height:0;flex-direction:column;background:#f4f3f1;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.simple-editor *{box-sizing:border-box}.simple-topbar{z-index:30;display:flex;min-height:72px;align-items:center;justify-content:space-between;gap:16px;padding:10px 22px;border-bottom:1px solid var(--line);background:#fff}.simple-topbar__title{display:flex;min-width:0;align-items:center;gap:14px}.simple-back{display:grid;width:44px;height:44px;flex:0 0 auto;place-content:center;border:1px solid var(--line);border-radius:9px;color:#4d4945;text-decoration:none}.simple-topbar h1{overflow:hidden;margin:0;font:700 20px/1.2 'Literata',Georgia,serif;text-overflow:ellipsis;white-space:nowrap}.simple-topbar p{margin:3px 0 0;color:var(--muted);font-size:11px}.simple-live-address{color:var(--brown);font-weight:800;text-decoration:none}.simple-live-address:hover,.simple-live-address:focus-visible{text-decoration:underline}.simple-actions{display:flex;align-items:center;gap:8px}.simple-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:7px;padding:9px 14px;border:1px solid #d7cfc7;border-radius:8px;background:#fff;color:#3f3b38;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none!important;white-space:nowrap}.simple-btn:hover{border-color:var(--orange);color:var(--brown)}.simple-btn:focus-visible,.simple-back:focus-visible,.simple-viewport button:focus-visible{outline:3px solid rgba(255,117,0,.32)!important;outline-offset:2px}.simple-btn--primary{border-color:var(--brown);background:var(--brown);color:#fff!important;box-shadow:0 5px 13px rgba(120,51,0,.2)}.simple-btn--primary:hover{border-color:#783300;background:#783300}.simple-btn--danger{border-color:#e5b6b1;color:#a52c24}.simple-btn:disabled{cursor:not-allowed;opacity:.48;box-shadow:none}.simple-save-state{color:var(--muted);font-size:11px;font-weight:750}.simple-save-state.is-dirty{color:#8d570b}.simple-grid{display:grid;grid-template-columns:220px minmax(520px,1fr) 320px;flex:1;min-height:0}.simple-sections,.simple-inspector{min-height:0;overflow-y:auto;background:#fff}.simple-sections{border-right:1px solid var(--line)}.simple-inspector{border-left:1px solid var(--line)}.simple-panel-head{position:sticky;z-index:4;top:0;padding:18px;border-bottom:1px solid var(--line);background:#fff}.simple-panel-head h2{margin:0;font:700 18px/1.2 'Literata',Georgia,serif}.simple-panel-head p{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.45}.simple-sections__body,.simple-inspector__body{padding:16px}.simple-section-list{display:grid;gap:8px;margin:0 0 14px;padding:0;list-style:none}.simple-section-item{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:7px;padding:7px;border:1px solid var(--line);border-radius:9px;background:#fff}.simple-section-item.is-selected{border-color:var(--orange);box-shadow:0 0 0 2px rgba(255,117,0,.1)}.simple-section-item.is-dragging{opacity:.45}.simple-drag{display:grid;width:44px;height:44px;place-content:center;border:0;border-radius:6px;background:#f4f1ee;color:#7c756f;cursor:grab}.simple-select{min-width:0;min-height:44px;border:0;background:transparent;text-align:left;cursor:pointer}.simple-select strong,.simple-select small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.simple-select strong{font-size:12px}.simple-select small{margin-top:2px;color:var(--muted);font-size:10px}.simple-order{display:flex;flex-direction:column;gap:4px}.simple-order button{display:grid;width:44px;height:44px;place-content:center;border:1px solid var(--line);border-radius:6px;background:#fff;color:#625c57;cursor:pointer}.simple-order button:disabled{opacity:.3}.simple-page-settings{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.simple-page-settings summary,.simple-options summary{color:var(--brown);font-size:12px;font-weight:850;cursor:pointer}.simple-field{display:grid;gap:6px;margin:0 0 14px}.simple-field>label,.simple-field>span{color:#514c48;font-size:11px;font-weight:850;letter-spacing:.03em;text-transform:uppercase}.simple-field input,.simple-field textarea,.simple-field select{width:100%;min-height:44px;padding:9px 10px;border:1px solid #d9d2cc;border-radius:7px;background:#fff;color:var(--ink);font-size:13px}.simple-field textarea{min-height:90px;resize:vertical}.simple-field input:focus,.simple-field textarea:focus,.simple-field select:focus,.simple-rich:focus{border-color:var(--orange);outline:3px solid rgba(255,117,0,.13)}.simple-check{display:flex;align-items:center;gap:8px;min-height:44px;margin:12px 0;color:#494541;font-size:12px;font-weight:750}.simple-check input{width:18px;height:18px;accent-color:var(--orange)}.simple-canvas{min-width:0;min-height:0;padding:20px;overflow:auto}.simple-preview{width:min(100%,1050px);min-height:100%;margin:0 auto;overflow:hidden;border:1px solid #ded9d4;border-radius:11px;background:#fff;box-shadow:0 10px 30px rgba(26,22,20,.05);transition:width .2s}.simple-preview[data-viewport=tablet]{width:min(100%,768px)}.simple-preview[data-viewport=mobile]{width:min(100%,390px)}.simple-preview-block{position:relative;padding:54px 7%;border:3px solid transparent;cursor:pointer}.simple-preview-block:hover,.simple-preview-block.is-selected{border-color:var(--orange)}.simple-preview-block::before{content:attr(data-label);position:absolute;z-index:5;top:9px;left:10px;padding:4px 7px;border-radius:5px;background:#9c4500;color:#fff;font-size:9px;font-weight:850;opacity:0}.simple-preview-block:hover::before,.simple-preview-block.is-selected::before{opacity:1}.simple-preview-block.is-hidden{opacity:.55}.simple-preview-block h2{margin:0 0 12px;font:700 clamp(28px,4vw,54px)/1.08 'Literata',Georgia,serif}.simple-preview-block p{max-width:720px;margin:0 0 16px;line-height:1.6}.simple-preview-block--hero{display:flex;min-height:490px;flex-direction:column;justify-content:center;background:#302d2b center/cover;color:#fff}.simple-preview-block--hero::after{position:absolute;z-index:0;inset:0;background:rgba(0,0,0,.58);content:''}.simple-preview-block--hero>*{position:relative;z-index:1}.simple-preview-eyebrow{margin-bottom:10px;color:#ff8a26;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.simple-preview-button{display:inline-flex;padding:9px 14px;border-radius:7px;background:var(--brown);color:#fff;font-size:11px;font-weight:850}.simple-preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.simple-preview-stat{padding:18px;border-radius:10px;background:#f7f4f1;text-align:center}.simple-preview-stat strong{display:block;color:var(--brown);font:700 30px 'Literata',serif}.simple-preview-media{display:grid;grid-template-columns:1fr 1.1fr;align-items:center;gap:28px}.simple-preview-media img,.simple-preview-card img{width:100%;height:220px;object-fit:cover;border-radius:10px}.simple-preview-media video,.simple-preview-media iframe{width:100%;aspect-ratio:16/9;border:0;border-radius:10px;background:#171717;object-fit:contain}.simple-preview-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.simple-preview-card{overflow:hidden;border:1px solid var(--line);border-radius:10px}.simple-preview-card div{padding:14px}.simple-preview-card h3{margin:0 0 7px;font:700 18px 'Literata',serif}.simple-empty{padding:90px 24px;text-align:center;color:var(--muted)}.simple-inspector__head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.simple-type-badge{display:inline-flex;margin-top:7px;padding:4px 7px;border-radius:999px;background:#fff1e5;color:var(--brown);font-size:9px;font-weight:900;text-transform:uppercase}.simple-shared{margin-bottom:14px;padding:11px;border-radius:8px;background:#fff5df;color:#76510e;font-size:11px;line-height:1.5}.simple-rich-toolbar{display:flex;gap:4px;padding:6px;border:1px solid #d9d2cc;border-bottom:0;border-radius:7px 7px 0 0;background:#f7f5f3}.simple-rich-toolbar button{min-width:44px;height:44px;border:1px solid #ddd5ce;border-radius:5px;background:#fff;font-weight:850;cursor:pointer}.simple-rich{min-height:130px;padding:11px;border:1px solid #d9d2cc;border-radius:0 0 7px 7px;background:#fff;font-size:13px;line-height:1.6}.simple-image-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px}.simple-repeat{display:grid;gap:9px;margin-bottom:12px}.simple-repeat-item{padding:11px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-repeat-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}.simple-repeat-head strong{font-size:11px}.simple-repeat-head button{min-height:44px;border:0;background:transparent;color:#a52c24;font-size:11px;font-weight:800;cursor:pointer}.simple-options{margin-top:18px;padding-top:14px;border-top:1px solid var(--line)}.simple-modal{position:fixed;z-index:1500;inset:0;display:grid;place-items:center;padding:20px;background:rgba(25,28,29,.56)}.simple-modal[hidden]{display:none}.simple-modal__dialog{width:min(820px,100%);max-height:min(760px,92vh);overflow:auto;border-radius:15px;background:#fff;box-shadow:0 25px 70px rgba(0,0,0,.27)}.simple-modal__head{position:sticky;z-index:2;top:0;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid var(--line);background:#fff}.simple-modal__head h2{margin:0;font:700 24px 'Literata',serif}.simple-close{display:grid;width:44px;height:44px;place-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer}.simple-section-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:22px}.simple-section-card{display:grid;grid-template-columns:46px 1fr;gap:13px;min-height:72px;padding:16px;border:1px solid var(--line);border-radius:11px;background:#fff;text-align:left;cursor:pointer}.simple-section-card:hover{border-color:var(--orange);background:#fff9f4}.simple-section-card i{display:grid;width:44px;height:44px;place-content:center;border-radius:9px;background:#fff0e3;color:var(--orange);font-size:18px}.simple-section-card strong{display:block;margin-bottom:5px}.simple-section-card span{color:var(--muted);font-size:11px;line-height:1.45}.simple-media-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:20px}.simple-media-option{overflow:hidden;padding:0;border:2px solid transparent;border-radius:9px;background:#f3f1ef;cursor:pointer}.simple-media-option:hover{border-color:var(--orange)}.simple-media-option img{width:100%;aspect-ratio:1;object-fit:cover}.simple-media-option video{display:block;width:100%;aspect-ratio:16/9;background:#171717;object-fit:contain;pointer-events:none}.simple-upload{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--line);background:#faf8f6}.simple-notice{position:fixed;z-index:1700;right:24px;bottom:24px;max-width:390px;padding:13px 16px;border-radius:9px;background:#24211f;color:#fff;box-shadow:0 12px 30px rgba(0,0,0,.22);font-size:12px;font-weight:750}.simple-viewport{display:flex;gap:3px;padding:3px;border-radius:8px;background:#efedeb}.simple-viewport button{display:grid;width:44px;height:44px;place-content:center;border:0;border-radius:6px;background:transparent;color:#6c6763;cursor:pointer}.simple-viewport button.is-active{background:#fff;color:var(--brown);box-shadow:0 1px 4px rgba(0,0,0,.1)}.simple-more{position:relative;margin:0}.simple-more>summary{list-style:none}.simple-more>summary::-webkit-details-marker{display:none}.simple-more__menu{position:absolute;top:calc(100% + 7px);right:0;z-index:50;display:grid;min-width:230px;gap:4px;padding:7px;border:1px solid var(--line);border-radius:10px;background:#fff;box-shadow:0 16px 38px rgba(25,28,29,.16)}.simple-more__menu .simple-btn{justify-content:flex-start;width:100%;border-color:transparent}.simple-add-section{width:100%;border-color:#efb789;background:#fff8f2;color:var(--brown)}
    .simple-preview-cards--four{grid-template-columns:repeat(2,1fr)}
    .simple-preview-card--full-width{grid-column:1/-1}.simple-preview-card[data-preview-card-index]{position:relative;cursor:pointer;transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}.simple-preview-card[data-preview-card-index]:hover{border-color:var(--orange);box-shadow:0 8px 22px rgba(156,69,0,.12);transform:translateY(-1px)}.simple-preview-card.is-card-selected{border:3px solid var(--orange);box-shadow:0 10px 24px rgba(156,69,0,.2)}.simple-preview-card [data-inline-path]{cursor:text}.simple-preview-card__edit{position:absolute;z-index:3;top:10px;right:10px;display:inline-flex;min-height:36px;align-items:center;gap:6px;padding:7px 10px;border:1px solid #e4c3a7;border-radius:999px;background:#fff;color:var(--brown);box-shadow:0 4px 12px rgba(25,28,29,.12);font-size:10px;font-weight:900;cursor:pointer}.simple-preview-card__edit:hover,.simple-preview-card__edit:focus-visible,.simple-preview-card__edit[aria-pressed=true]{border-color:var(--orange);background:var(--orange);color:#20150c;outline:none}.simple-preview-card__edit:focus-visible{box-shadow:0 0 0 3px rgba(255,117,0,.32),0 4px 12px rgba(25,28,29,.12)}.simple-repeat-item[data-card-editor-index]{scroll-margin-block:18px}.simple-repeat-item.is-current-card{border:2px solid var(--orange);box-shadow:0 0 0 2px rgba(255,117,0,.14)}.simple-repeat-item[data-card-editor-index]>summary{display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:pointer}.simple-repeat-current{padding:3px 7px;border-radius:999px;background:#ffead8;color:var(--brown);font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.simple-field>.simple-check{margin:0}.simple-field>small{color:var(--muted);font-size:11px;line-height:1.45}
    .simple-preview{container-type:inline-size}.simple-preview-block--focus{padding:clamp(48px,6cqw,72px) clamp(20px,4.6cqw,48px)}.simple-focus-grid{position:relative;display:grid;isolation:isolate;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:stretch}.simple-focus-grid::before{position:absolute;z-index:-1;top:50%;left:50%;width:min(900px,100%);height:600px;background:radial-gradient(circle,rgba(255,117,0,.18) 0,rgba(255,117,0,0) 70%);content:'';pointer-events:none;transform:translate(-50%,-50%)}.simple-focus-tile{min-width:0;min-height:390px;animation:simple-focus-rise .5s ease-out both;animation-delay:var(--simple-focus-delay,0ms)}.simple-focus-heading{container-type:inline-size;display:flex;overflow:hidden;padding:clamp(28px,4.4cqw,46px);flex-direction:column;justify-content:center;border-radius:16px;background:var(--orange);color:#fff}.simple-focus-heading .simple-preview-eyebrow{color:#572500}.simple-focus-heading h2{max-width:100%;margin:0;font-size:clamp(30px,10.5cqi,44px);line-height:1.08;overflow-wrap:anywhere}.simple-focus-heading>p{margin:18px 0 0;color:rgba(255,255,255,.9)}.simple-focus-view-all{display:inline-flex;width:fit-content;align-items:center;gap:6px;margin-top:28px;color:#fff;font-size:14px;font-weight:800}.simple-focus-card{position:relative;z-index:0;display:flex;overflow:hidden;padding:clamp(26px,3.6cqw,38px);flex-direction:column;align-items:flex-start;isolation:isolate;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 8px 22px rgba(25,28,29,.08);color:var(--ink);transition:color .3s ease-out,border-color .3s ease-out,box-shadow .3s ease-out}.simple-focus-card::before{position:absolute;z-index:-1;inset:0;background:var(--orange);content:'';transform:scaleX(0);transform-origin:left center;transition:transform .5s ease-out}.simple-focus-card:hover,.simple-focus-card:focus-within{border-color:var(--orange);box-shadow:0 14px 32px rgba(156,69,0,.2);color:#fff}.simple-focus-card:hover::before,.simple-focus-card:focus-within::before{transform:scaleX(1)}.simple-focus-card__visual{display:grid;width:72px;height:72px;flex:0 0 auto;margin-bottom:28px;place-items:center;overflow:hidden;border-radius:16px;background:#fff2e8;color:var(--brown);font-size:34px;transition:background-color .3s ease-out,color .3s ease-out}.simple-focus-card__visual img{width:100%;height:100%;border-radius:0;object-fit:cover}.simple-focus-card:hover .simple-focus-card__visual,.simple-focus-card:focus-within .simple-focus-card__visual{background:rgba(255,255,255,.2);color:#fff}.simple-focus-card__copy{display:flex;min-width:0;flex:1;flex-direction:column}.simple-focus-card h3{margin:0 0 16px;font:700 clamp(24px,2.95cqw,31px)/1.22 'Literata',Georgia,serif}.simple-focus-card p{margin:0 0 24px;color:var(--muted);font-size:16px;line-height:1.65;transition:color .3s ease-out}.simple-focus-card:hover p,.simple-focus-card:focus-within p{color:rgba(255,255,255,.92)}.simple-focus-card__link{width:fit-content;margin-top:auto;padding:8px 14px;border:1px dashed currentColor;border-radius:999px;font-size:13px;font-weight:800}.simple-preview[data-viewport=tablet] .simple-focus-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-preview[data-viewport=mobile] .simple-focus-grid{grid-template-columns:1fr}.simple-preview[data-viewport=mobile] .simple-focus-tile{min-height:320px}@container (max-width:960px){.simple-focus-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@container (max-width:560px){.simple-focus-grid{grid-template-columns:1fr}.simple-focus-tile{min-height:320px}}@keyframes simple-focus-rise{from{opacity:0;transform:translateY(100px)}to{opacity:1;transform:translateY(0)}}
    .simple-preview-block--testimonials{background:#242220;color:#fff}.simple-preview-block--testimonials h2{color:#fff}.simple-preview-block--testimonials>p{color:#d4d0cc}.simple-testimonial-card{position:relative;max-width:820px;margin:34px auto 0;padding:clamp(28px,5vw,56px);border:1px solid rgba(255,255,255,.15);border-radius:22px;background:#30302f;text-align:center}.simple-testimonial-card>.fa-quote-left{color:var(--orange);font-size:38px}.simple-testimonial-card blockquote{max-width:690px;margin:20px auto 28px;color:#f1efec;font:500 clamp(20px,3vw,30px)/1.5 'Literata',Georgia,serif}.simple-testimonial-person{display:flex;align-items:center;justify-content:center;gap:13px}.simple-testimonial-person img{width:64px;height:64px;border:3px solid #fff;border-radius:50%;object-fit:cover}.simple-testimonial-person span{display:grid;gap:3px;text-align:left}.simple-testimonial-person strong{color:#fff}.simple-testimonial-person small{color:#bbb}.simple-testimonial-nav{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:28px}.simple-testimonial-nav button{display:grid;min-width:42px;min-height:42px;padding:0;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:50%;background:transparent;color:#fff;cursor:pointer}.simple-testimonial-nav button:hover,.simple-testimonial-nav button:focus-visible{border-color:var(--orange);background:var(--brown);outline:3px solid rgba(255,117,0,.3);outline-offset:2px}.simple-testimonial-nav .simple-testimonial-dot{min-width:28px;border-color:transparent}.simple-testimonial-dot span{width:8px;height:8px;border-radius:50%;background:#777}.simple-testimonial-dot[aria-current=true] span{width:18px;border-radius:99px;background:var(--orange)}
    .simple-preview-block--cta{padding:clamp(38px,5cqw,58px) clamp(18px,4cqw,42px)}.simple-cta-panel{position:relative;display:grid;overflow:hidden;grid-template-columns:64px minmax(0,1fr) minmax(205px,auto);align-items:center;gap:clamp(20px,3.5cqw,40px);isolation:isolate;padding:clamp(30px,4.5cqw,48px);border-radius:26px;background:radial-gradient(circle at 92% 2%,rgba(255,117,0,.24),transparent 30%),linear-gradient(135deg,#1c1e20,#292624);box-shadow:0 18px 42px rgba(39,29,22,.18);color:#fff}.simple-cta-panel[data-actions=false]{grid-template-columns:64px minmax(0,1fr)}.simple-cta-panel::before{position:absolute;z-index:-1;top:0;bottom:0;left:0;width:7px;background:linear-gradient(180deg,#ff9b4c,#ff7500 55%,#b94e00);content:''}.simple-cta-signal{display:grid;width:64px;height:64px;place-items:center;align-self:start;border:1px solid rgba(255,172,105,.35);border-radius:19px;background:rgba(255,117,0,.13);color:#ff9b4c;font-size:26px}.simple-cta-content{min-width:0}.simple-cta-content .simple-preview-eyebrow{color:#ffad70}.simple-cta-content h2{margin-bottom:14px;color:#fff;font-size:clamp(31px,5.2cqw,48px);line-height:1.06;overflow-wrap:anywhere}.simple-cta-content p{margin:0;color:#d9d5d1}.simple-cta-actions{display:grid;min-width:0;gap:10px}.simple-cta-actions>:only-child{grid-column:1/-1}.simple-cta-actions .simple-preview-button{min-width:0;min-height:48px;align-items:center;justify-content:space-between;padding:0 18px;border:1px solid #ff7500;border-radius:12px;background:#ff7500;color:#1c1e20;line-height:1.25;overflow-wrap:anywhere}.simple-cta-actions .simple-preview-button::after{margin-left:12px;content:'\2192';font-size:17px}.simple-cta-actions .simple-preview-button--outline{border-color:rgba(255,255,255,.42);background:rgba(255,255,255,.04);color:#fff}@container (max-width:960px){.simple-cta-panel{grid-template-columns:58px minmax(0,1fr)}.simple-cta-signal{width:58px;height:58px}.simple-cta-actions{grid-column:2;grid-template-columns:repeat(2,minmax(0,1fr))}}@container (max-width:520px){.simple-preview-block--cta{padding:30px 14px}.simple-cta-panel{grid-template-columns:1fr;gap:20px;padding:28px 22px;border-radius:22px}.simple-cta-actions{grid-column:auto;grid-template-columns:1fr}.simple-cta-content h2{font-size:clamp(30px,10cqw,39px)}}
    .simple-order{display:grid;grid-template-columns:repeat(2,44px);gap:4px}.simple-order button[data-delete-section]{grid-column:1/-1;width:92px;height:44px;border-color:#e5b6b1;background:#fff8f7;color:#a52c24}.simple-order button[data-delete-section]:hover{border-color:#a52c24;background:#fff0ee}
    .simple-section-item{grid-template-columns:44px minmax(0,1fr);grid-template-areas:'drag select' 'actions actions'}.simple-drag,.simple-drag-placeholder{grid-area:drag}.simple-drag{touch-action:none;user-select:none}.simple-select{grid-area:select;width:100%;padding:0 4px}.simple-order{grid-area:actions;grid-template-columns:repeat(3,44px);justify-content:end}.simple-order button[data-delete-section]{grid-column:auto;width:44px}
    .simple-history{display:flex;gap:5px}.simple-history .simple-btn{min-width:40px;padding:8px 10px}.simple-history .simple-btn:disabled{opacity:.35}.simple-canvas-tip{display:flex;align-items:center;justify-content:center;gap:7px;margin:0 auto 10px;color:var(--muted);font-size:11px;font-weight:750}.simple-canvas-tip i{color:var(--orange)}[data-inline-path]{min-width:12px;border-radius:4px;cursor:text;outline:2px dashed transparent;outline-offset:4px;transition:outline-color .15s,background .15s}[data-inline-path]:hover{outline-color:rgba(255,117,0,.5);background:rgba(255,255,255,.08)}[data-inline-path]:focus{outline:3px solid var(--orange);background:rgba(255,255,255,.13)}[data-inline-path]:empty::before{color:#8b8783;content:attr(data-placeholder)}.simple-link-row{display:grid;grid-template-columns:1fr;gap:7px}.simple-link-row select{background:#fff8f2}.simple-option-actions{display:flex;flex-wrap:wrap;gap:8px}.simple-autosave-note{margin:8px 0 0;color:var(--muted);font-size:10px;line-height:1.4}.simple-animation-panel{margin:0 0 16px;padding:13px;border:1px solid #ead8c8;border-radius:9px;background:#fff8f2}.simple-animation-panel h3{margin:0 0 5px;color:var(--brown);font:700 14px 'Literata',Georgia,serif}.simple-animation-panel>p,.simple-animation-panel fieldset>p{margin:0 0 10px;color:var(--muted);font-size:10px;line-height:1.45}.simple-animation-panel fieldset{margin:0;padding:0;border:0}.simple-animation-panel fieldset:disabled{opacity:.48}.simple-preview-stat.is-animation-count-up,.simple-preview-stat.is-animation-fade-up{animation:simple-stat-fade-up var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both;animation-delay:var(--preview-animation-delay,0ms)}.simple-preview-stat.is-animation-pop{animation:simple-stat-pop var(--preview-animation-duration,900ms) cubic-bezier(.22,1,.36,1) both;animation-delay:var(--preview-animation-delay,0ms)}@keyframes simple-stat-fade-up{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}@keyframes simple-stat-pop{0%{opacity:0;transform:scale(.84)}70%{opacity:1;transform:scale(1.04)}100%{opacity:1;transform:scale(1)}}@media(prefers-reduced-motion:reduce){.simple-preview-stat{animation:none!important;opacity:1!important;transform:none!important}}
    @media(prefers-reduced-motion:reduce){.simple-focus-tile{animation:none!important;opacity:1!important;transform:none!important}.simple-focus-card::before{transition:none!important}}
    .simple-read-only{padding:10px 22px;border-bottom:1px solid #e7c785;background:#fff8df;color:#674d12;font-size:12px;line-height:1.5}.simple-read-only strong{margin-right:5px}
    .simple-field small{color:var(--muted);font-size:10px;line-height:1.45}.simple-page-thumbnail{display:block;width:100%;max-height:130px;border:1px solid var(--line);border-radius:7px;object-fit:cover}.simple-tag-options{display:grid;gap:7px;max-height:140px;padding:9px;overflow:auto;border:1px solid #d9d2cc;border-radius:7px;background:#faf9f8}.simple-tag-options label{display:flex;align-items:center;gap:7px;margin:0;color:#494541;font-size:11px;font-weight:700}.simple-tag-options input{width:16px;height:16px;min-height:16px;margin:0;padding:0;border:0;accent-color:var(--orange)}.simple-banner-guidance{margin:4px 0 14px;padding:10px;border-left:3px solid var(--orange);border-radius:5px;background:#fff6eb;color:#694a2c;font-size:10px;line-height:1.5}
    .simple-giving-list{display:grid;gap:8px;margin:0 0 14px}.simple-giving-option{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;min-height:54px;padding:9px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-giving-option.is-unavailable{border-color:#d9a9a4;background:#fff4f2}.simple-giving-option input{width:18px;height:18px;accent-color:var(--orange)}.simple-giving-option strong,.simple-giving-option small{display:block}.simple-giving-option small{margin-top:3px;color:var(--muted);font-size:10px;line-height:1.35}.simple-giving-move{display:flex;gap:4px}.simple-giving-move button{display:grid;min-width:44px;min-height:44px;place-content:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);cursor:pointer}.simple-giving-move button:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:2px}.simple-giving-preview{margin:10px 0 14px;padding:12px;border-radius:8px;background:#f2f6fb;color:#334155;font-size:11px;line-height:1.5}
    .simple-reusable-launch{display:grid;gap:7px;margin-top:8px}.simple-reusable-launch .simple-btn{width:100%;min-height:44px}.simple-reusable-launch small{color:var(--muted);font-size:10px;line-height:1.45}.simple-modal__dialog--compact{width:min(540px,100%)}.simple-reusable-form{display:grid;gap:14px;padding:22px}.simple-reusable-warning{margin:0;padding:12px;border-left:4px solid var(--orange);border-radius:7px;background:#fff5df;color:#6d4a0d;font-size:11px;line-height:1.55}.simple-reusable-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.simple-reusable-empty{grid-column:1/-1;margin:0;padding:30px;text-align:center;color:var(--muted);font-size:12px}.simple-section-card[data-attach-reusable] span span{display:block}.simple-section-card[data-attach-reusable] small{display:block;margin-top:4px;color:var(--brown);font-size:9px;font-weight:800;text-transform:uppercase}.simple-delete-confirmation{display:grid;gap:16px;padding:22px}.simple-delete-confirmation>p{margin:0;color:#554f4a;font-size:13px;line-height:1.6}.simple-delete-confirmation__status{min-height:20px;color:#9c4500!important;font-size:12px!important;font-weight:800}.simple-delete-confirmation__status.is-error{color:#a52c24!important}.simple-delete-confirmation .simple-reusable-actions{padding-top:2px}
    .simple-draft-conflict{display:grid;gap:10px}.simple-draft-conflict .simple-btn{width:100%}
    .simple-hero-nav{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px}.simple-hero-nav__buttons{display:flex;gap:5px}.simple-hero-reorder{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;margin:0 0 16px;padding:11px;border:1px solid #ead8c8;border-radius:9px;background:#fff8f2}.simple-hero-reorder__copy strong,.simple-hero-reorder__copy small{display:block}.simple-hero-reorder__copy strong{color:var(--brown);font-size:11px}.simple-hero-reorder__copy small{margin-top:3px;color:var(--muted);font-size:9px;line-height:1.4}.simple-hero-reorder__actions{display:flex;flex-wrap:wrap;gap:6px}.simple-hero-reorder__actions .simple-btn{min-width:104px;justify-content:center}
    @media(max-width:1180px){.simple-grid{grid-template-columns:210px minmax(420px,1fr) 300px}.simple-topbar{padding-inline:14px}.simple-canvas{padding:12px}}
    @media(max-width:880px){body.layout-wrapper .right-panel{height:auto;min-height:100vh;overflow:visible}.simple-editor{height:auto;min-height:100vh}.simple-grid{display:flex;flex-direction:column}.simple-sections,.simple-inspector{overflow:visible;border:0}.simple-sections{order:1}.simple-canvas{order:2;min-height:600px}.simple-inspector{order:3}.simple-section-list{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-panel-head{position:static}.simple-topbar{position:sticky;top:0;flex-wrap:wrap}.simple-topbar__title p{display:none}.simple-save-state{order:3}.simple-more__menu{position:fixed;top:78px;right:12px}}
    @media(max-width:520px){.simple-topbar{gap:8px}.simple-topbar h1{max-width:145px;font-size:15px}.simple-actions{width:100%;margin-left:auto;flex-wrap:wrap;justify-content:flex-end}.simple-actions .simple-btn--primary{padding-inline:10px}.simple-history{display:none}.simple-save-state{order:3;width:100%;text-align:center}.simple-viewport{order:4;width:100%;justify-content:center}.simple-section-list,.simple-section-cards,.simple-media-grid{grid-template-columns:1fr}.simple-preview-media,.simple-preview-cards,.simple-focus-grid{grid-template-columns:1fr}.simple-preview-stats{grid-template-columns:1fr}.simple-canvas{min-height:520px;padding:8px}.simple-section-cards{padding:14px}}
    .simple-page-settings summary,.simple-options summary{display:flex;min-height:44px;align-items:center}
    .simple-section-presentation-help{margin:-7px 0 16px;color:var(--muted);font-size:11px;line-height:1.45}
    .simple-preview-block--presentation-standard{box-shadow:inset 0 0 0 1px rgba(25,28,29,.04)}
    .simple-preview-block--presentation-soft{background-color:#fbf5ef;box-shadow:inset 0 0 0 4px rgba(156,69,0,.08)}
    .simple-preview-block--presentation-framed{margin-inline:clamp(12px,2.5vw,28px);border-color:#d7cbc0;border-radius:20px;background-color:#fff;box-shadow:0 16px 38px rgba(55,42,32,.11)}
    .simple-preview-block--presentation-framed.is-selected{border-color:var(--orange)}
    .simple-preview-block--presentation-contrast{background-color:#282421;color:#fff;box-shadow:inset 0 5px 0 var(--orange)}
    .simple-preview-block--presentation-contrast :is(h1,h2,h3,p,blockquote){color:inherit}
    .simple-preview-block--presentation-contrast :is(.simple-preview-card,.simple-preview-stat,.simple-focus-card){background-color:#fff;color:var(--ink)}
    .simple-design-options{margin:0 0 18px;padding:0 0 4px;border-bottom:1px solid var(--line)}.simple-design-options summary{display:flex;min-height:44px;align-items:center;color:var(--brown);font-size:12px;font-weight:850;cursor:pointer}.simple-placement-options{margin:10px 0 16px;padding:12px;border:1px solid var(--line);border-radius:9px}.simple-placement-options legend{padding:0 6px;color:#514c48;font-size:11px;font-weight:850;text-transform:uppercase}.simple-editor-hint,.simple-preview-edit-hint{display:flex;align-items:flex-start;gap:7px;padding:9px 10px;border-left:3px solid var(--orange);border-radius:6px;background:#fff6eb;color:#694a2c!important;font-size:10px!important;line-height:1.5!important}.simple-managed-list{display:grid;gap:8px;max-height:430px;margin:0 0 10px;padding:2px;overflow:auto}.simple-managed-option{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;min-height:56px;padding:9px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-managed-option.is-unavailable{border-color:#d9a9a4;background:#fff4f2}.simple-managed-option>input{width:18px;height:18px;accent-color:var(--orange)}.simple-managed-option strong,.simple-managed-option small{display:block}.simple-managed-option small{margin-top:3px;color:var(--muted);font-size:10px;line-height:1.35}.simple-managed-move,.simple-repeat-move{display:flex;align-items:center;gap:4px}.simple-managed-move button,.simple-repeat-move button{display:grid;min-width:44px;min-height:44px;padding:5px;place-content:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);font-size:10px;font-weight:800;cursor:pointer}.simple-managed-move button:disabled,.simple-repeat-move button:disabled{cursor:not-allowed;opacity:.35}.simple-repeat-head .simple-repeat-move button:last-child{color:#a52c24}.simple-manage-links{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 14px}.simple-manage-link{display:inline-flex;min-height:44px;align-items:center;gap:7px;padding:9px 11px;border:1px solid #efb789;border-radius:8px;background:#fff8f2;color:var(--brown)!important;font-size:11px;font-weight:800;text-decoration:none!important}
    .simple-preview-block--spacing-compact{padding-top:34px;padding-bottom:34px}.simple-preview-block--spacing-standard{padding-top:54px;padding-bottom:54px}.simple-preview-block--spacing-spacious{padding-top:78px;padding-bottom:78px}.simple-preview-block--align-center{text-align:center}.simple-preview-block--align-center :is(.simple-preview-heading,.simple-preview-rich-text,.simple-preview-video__caption)>p,.simple-preview-block--align-center .simple-preview-heading h2{margin-right:auto;margin-left:auto}.simple-preview-block--align-center .simple-preview-heading{align-items:center}.simple-preview-block--align-center :is(.simple-preview-actions,.simple-preview-hero-report){justify-content:center}.simple-preview-block--columns-auto{--simple-column-count:3}.simple-preview-block--columns-2{--simple-column-count:2}.simple-preview-block--columns-3{--simple-column-count:3}.simple-preview-block--columns-4{--simple-column-count:4}.simple-preview-block :is(.simple-preview-stats,.simple-preview-cards,.simple-preview-events,.simple-preview-team,.simple-preview-gallery){grid-template-columns:repeat(var(--simple-column-count,3),minmax(0,1fr))}.simple-preview[data-viewport=desktop] [data-hide-desktop=true],.simple-preview[data-viewport=tablet] [data-hide-desktop=true],.simple-preview[data-viewport=mobile] [data-hide-mobile=true],.simple-preview-block.is-outside-schedule{filter:grayscale(.55);opacity:.42}.simple-preview-block.is-outside-schedule::before{content:attr(data-label) ' · Outside schedule'}
    .simple-preview-heading{display:flex;flex-direction:column;align-items:flex-start;margin-bottom:28px}.simple-preview-heading .simple-preview-view-all{display:inline-flex;align-items:center;gap:4px;margin-top:7px;color:var(--brown);font-size:12px;font-weight:850}.simple-preview-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:4px}.simple-preview-button--outline{border:1px solid currentColor;background:transparent}.simple-preview-card{display:flex;min-width:0;min-height:100%;flex-direction:column;background:#fff;box-shadow:0 8px 24px rgba(35,28,24,.06)}.simple-preview-card>img{border-radius:10px 10px 0 0}.simple-preview-card>div{display:flex;flex:1;flex-direction:column}.simple-preview-card p{color:var(--muted)}.simple-preview-card__placeholder{display:grid;height:180px;place-items:center;background:linear-gradient(145deg,#fff6ed,#f1ece7);color:var(--orange);font-size:34px}.simple-preview-card__icon{display:grid;width:50px;height:50px;margin:16px 16px 0;place-items:center;border-radius:12px;background:#fff0e4;color:var(--orange);font-size:21px}.simple-preview-card__link{display:inline-flex;width:fit-content;margin-top:auto;color:var(--brown);font-size:11px;font-weight:850}.simple-preview-card__status{display:inline-flex;width:fit-content;margin:0 0 12px;padding:4px 8px;border-radius:99px;background:#e5f4e9;color:#176c32;font-size:9px;font-weight:850}.simple-preview-ways--single_cta{max-width:620px}.simple-preview-ways--banner{grid-template-columns:1fr!important}.simple-preview-way{padding-top:7px}
    .simple-preview-block--hero{isolation:isolate;overflow:hidden;background:#302d2b;color:#fff}.simple-preview-block--hero::after{z-index:1;background:rgba(0,0,0,var(--simple-hero-overlay,.64))}.simple-preview-block--hero>.simple-preview-hero-image{position:absolute;z-index:0;inset:0;width:100%;height:100%;object-fit:cover}.simple-preview-hero-copy{position:relative!important;z-index:2!important;max-width:760px}.simple-preview-hero-copy h2{color:#fff}.simple-preview-hero-copy>p{color:#f2efec;font-size:clamp(16px,2.4cqw,20px)}.simple-preview-hero-report{display:flex;align-items:center;gap:7px;width:fit-content;margin-top:20px;color:#fff;font-size:11px;font-weight:850}.simple-preview-carousel-status{display:block;width:fit-content;margin-top:18px;padding:5px 9px;border:1px solid rgba(255,255,255,.3);border-radius:99px;color:#eee;font-size:9px}.simple-preview-block--hero .simple-preview-button--outline{color:#fff}.simple-preview-stat__icon{display:grid;width:42px;height:42px;margin:0 auto 10px;place-items:center;border-radius:50%;background:#fff0e4;color:var(--orange);font-size:17px}
    .simple-preview-media{grid-template-areas:'media copy'}.simple-preview-media--reverse{grid-template-columns:1.1fr 1fr;grid-template-areas:'copy media'}.simple-preview-media>figure{grid-area:media;margin:0}.simple-preview-media__copy{grid-area:copy}.simple-preview-media figcaption{margin-top:8px;color:var(--muted);font-size:10px}.simple-preview-media-placeholder{display:grid;min-height:260px;place-items:center;align-content:center;gap:10px;border-radius:10px;background:#f2efec;color:var(--muted)}.simple-preview-media-placeholder i{color:var(--orange);font-size:32px}.simple-preview-rich-text{max-width:820px}.simple-preview-block--align-center .simple-preview-rich-text{margin:auto}.simple-preview-rich-copy{color:inherit;line-height:1.7}.simple-preview-rich-copy :is(p,ul,ol,blockquote){max-width:100%;margin:0 0 14px}.simple-preview-rich-copy a{color:var(--brown);text-decoration:underline}.simple-preview-edit-hint{width:fit-content;margin-top:22px!important}
    .simple-preview-events{display:grid;gap:16px}.simple-preview-event{position:relative;display:flex;min-width:0;min-height:260px;overflow:hidden;flex-direction:column;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(35,28,24,.07)}.simple-preview-event>img{width:100%;height:150px;object-fit:cover}.simple-preview-event__date{position:absolute;top:12px;left:12px;display:grid;min-width:58px;padding:7px 9px;border-radius:10px;background:#fff;text-align:center;box-shadow:0 5px 16px rgba(0,0,0,.14)}.simple-preview-event__date strong{color:var(--brown);font:700 22px 'Literata',serif}.simple-preview-event__date small{text-transform:uppercase}.simple-preview-event>div{display:flex;flex:1;flex-direction:column;padding:18px}.simple-preview-event h3{margin:0 0 8px;font:700 19px 'Literata',serif}.simple-preview-event p{color:var(--muted)}.simple-preview-event b{margin-top:auto;color:var(--brown);font-size:11px}
    .simple-preview-team{display:grid;gap:16px}.simple-preview-team-card{min-width:0;padding:12px 12px 18px;border:1px solid var(--line);border-radius:15px;background:#fff;text-align:center;box-shadow:0 8px 24px rgba(35,28,24,.06)}.simple-preview-team-card__media{display:grid;aspect-ratio:4/5;place-items:center;overflow:hidden;border-radius:11px;background:linear-gradient(145deg,#fff1e5,#e9e3de);color:var(--brown);font:700 clamp(26px,5cqw,48px) 'Literata',serif}.simple-preview-team-card__media img{width:100%;height:100%;object-fit:cover}.simple-preview-team-card h3{margin:14px 0 5px;font:700 18px 'Literata',serif}.simple-preview-team-card p{margin:0;color:var(--muted);font-size:12px}.simple-preview-team-card small{display:block;margin-top:5px;color:var(--brown);font-size:9px;font-weight:850;text-transform:uppercase}
    .simple-preview-partners{text-align:center}.simple-preview-partners .simple-preview-heading{align-items:center}.simple-preview-partners ul{display:grid;grid-template-columns:repeat(var(--simple-column-count,3),minmax(0,1fr));gap:12px;margin:0;padding:0;list-style:none}.simple-preview-partners li{display:grid;min-height:130px;place-items:center;align-content:center;gap:9px;padding:17px;border:1px solid var(--line);border-radius:12px;background:#fff}.simple-preview-partners img{max-width:130px;max-height:72px;object-fit:contain}.simple-preview-partners li>i{color:var(--orange);font-size:29px}.simple-preview-partners strong{font-size:12px}
    .simple-preview-faq{max-width:880px;margin:auto}.simple-preview-faq__items{display:grid;gap:9px}.simple-preview-faq details{overflow:hidden;border:1px solid var(--line);border-radius:10px;background:#fff}.simple-preview-faq summary{display:flex;min-height:58px;align-items:center;justify-content:space-between;gap:12px;padding:14px 17px;font-weight:850;cursor:pointer;list-style:none}.simple-preview-faq summary::-webkit-details-marker{display:none}.simple-preview-faq summary i{color:var(--orange)}.simple-preview-faq details[open] summary i{transform:rotate(45deg)}.simple-preview-faq details>.simple-preview-rich-copy{padding:0 17px 16px;color:var(--muted)}
    .simple-preview-timeline{max-width:900px;margin:auto}.simple-preview-timeline>ol{position:relative;display:grid;gap:14px;margin:0;padding:0 0 0 74px;list-style:none}.simple-preview-timeline>ol::before{position:absolute;top:0;bottom:0;left:28px;width:2px;background:#e9c6a9;content:''}.simple-preview-timeline li{position:relative}.simple-preview-timeline li>span{position:absolute;top:18px;left:-74px;display:grid;width:58px;height:58px;place-items:center;border:3px solid #fff;border-radius:50%;background:var(--orange);color:#fff;font-size:10px;box-shadow:0 0 0 1px #dfb18d}.simple-preview-timeline li>div{padding:21px 23px;border:1px solid var(--line);border-radius:12px;background:#fff}.simple-preview-timeline h3{margin:0 0 8px;font:700 20px 'Literata',serif}
    .simple-preview-gallery{display:grid;gap:12px}.simple-preview-gallery figure{position:relative;min-width:0;overflow:hidden;margin:0;border-radius:12px;background:#eee}.simple-preview-gallery img,.simple-preview-gallery__placeholder{display:grid;width:100%;aspect-ratio:4/3;place-items:center;object-fit:cover}.simple-preview-gallery__placeholder{color:var(--orange);font-size:30px}.simple-preview-gallery figcaption{position:absolute;right:0;bottom:0;left:0;padding:25px 12px 10px;background:linear-gradient(transparent,rgba(0,0,0,.76));color:#fff;font-size:11px;font-weight:800;text-align:left}
    .simple-preview-video{max-width:900px;margin:auto}.simple-preview-video .simple-preview-heading{align-items:center;text-align:center}.simple-preview-video__frame{overflow:hidden;border-radius:15px;background:#181818;box-shadow:0 14px 34px rgba(0,0,0,.16)}.simple-preview-video iframe,.simple-preview-video video{display:block;width:100%;aspect-ratio:16/9;border:0;object-fit:contain}.simple-preview-video__placeholder{display:grid;aspect-ratio:16/9;place-items:center;align-content:center;gap:12px;padding:30px;color:#eee;text-align:center}.simple-preview-video__placeholder i{color:var(--orange);font-size:52px}.simple-preview-video__caption{margin:11px auto 0!important;color:var(--muted);font-size:11px;text-align:center}
    .simple-preview-newsletter{display:grid;grid-template-columns:minmax(0,1fr) minmax(310px,.85fr);align-items:center;gap:36px;padding:clamp(26px,5cqw,48px);border-radius:20px;background:linear-gradient(135deg,#e9eaeb,#f8f5f1)}.simple-preview-newsletter h2{margin-bottom:12px}.simple-preview-newsletter__form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}.simple-preview-newsletter__form label>span{display:block;margin-bottom:6px;font-size:10px;font-weight:850;text-transform:uppercase}.simple-preview-newsletter__form input{width:100%;height:48px;padding:0 12px;border:1px solid #cfc8c2;border-radius:8px;background:#fff}.simple-preview-newsletter__form>.simple-preview-button{min-height:48px;align-items:center}.simple-preview-newsletter__consent{display:flex;grid-column:1/-1;align-items:flex-start;gap:7px;margin:3px 0 0!important;color:var(--muted);font-size:9px;line-height:1.45!important}.simple-preview-checkbox{width:14px;height:14px;flex:0 0 auto;border:1px solid #928980;border-radius:3px;background:#fff}
    .simple-layout-guide{margin:0 0 16px;padding:12px;border-left:3px solid var(--orange);border-radius:7px;background:#fff7ef;color:#614d3d;font-size:11px;line-height:1.5}.simple-layout-guide strong{display:block;margin-bottom:3px;color:var(--brown);font-size:12px}.simple-layout-row-editor{margin:0 0 14px;border:1px solid #ddd5ce;border-radius:11px;background:#f8f6f4}.simple-layout-row-header,.simple-layout-element-header{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:9px 10px}.simple-layout-row-header{min-height:64px}.simple-layout-row-title{min-width:0}.simple-layout-row-title strong,.simple-layout-row-title small{display:block}.simple-layout-row-title strong{font-size:12px}.simple-layout-row-title small{margin-top:3px;color:var(--muted);font-size:9px}.simple-layout-row-actions,.simple-layout-element-actions{display:flex;flex-wrap:wrap;gap:4px}.simple-layout-row-actions button,.simple-layout-element-actions button{display:grid;min-width:44px;min-height:44px;padding:6px;place-items:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);cursor:pointer}.simple-layout-row-actions button:disabled,.simple-layout-element-actions button:disabled{cursor:not-allowed;opacity:.34}.simple-layout-row-actions button[data-layout-row-action=remove],.simple-layout-element-actions button[data-layout-element-action=remove]{color:#a52c24}.simple-layout-row-body{padding:13px;border-top:1px solid var(--line)}.simple-layout-row-settings{display:grid;grid-template-columns:1fr 1fr;gap:0 9px}.simple-layout-row-settings .simple-field:first-child{grid-column:1/-1}.simple-layout-columns-editor{display:grid;gap:11px}.simple-layout-column-editor{padding:10px;border:1px dashed #d4c9bf;border-radius:9px;background:#fff}.simple-layout-column-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}.simple-layout-column-head strong{font-size:11px}.simple-layout-column-head small{color:var(--muted);font-size:9px}.simple-layout-element-editor{margin-bottom:8px;border:1px solid var(--line);border-radius:8px;background:#faf9f8}.simple-layout-element-header{align-items:flex-start;padding:7px 8px}.simple-layout-element-details>summary{display:flex;min-height:44px;align-items:center;padding:8px 10px;border-top:1px solid var(--line);color:var(--brown);font-size:10px;font-weight:850;cursor:pointer;list-style:none}.simple-layout-element-details>summary::-webkit-details-marker{display:none}.simple-layout-element-details>summary::after{margin-left:auto;content:'+';font-size:16px}.simple-layout-element-details[open]>summary::after{content:'−'}.simple-layout-element-title{display:flex;min-width:0;align-items:center;gap:7px;padding-top:8px;font-size:10px;font-weight:850}.simple-layout-element-title>span{overflow:hidden;text-overflow:ellipsis}.simple-layout-element-title i{display:grid;width:28px;height:28px;flex:0 0 auto;place-items:center;border-radius:6px;background:#fff0e4;color:var(--brown)}.simple-layout-element-body{padding:10px;border-top:1px solid var(--line)}.simple-layout-element-note{margin:0;color:var(--muted);font-size:10px;line-height:1.45}.simple-layout-add{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;margin-top:9px}.simple-layout-add select{min-width:0;min-height:44px;padding:8px;border:1px solid #d9d2cc;border-radius:7px;background:#fff}.simple-layout-add .simple-btn{padding-inline:11px}.simple-layout-empty{margin:0 0 8px;padding:14px 8px;border:1px dashed #ddd4cc;border-radius:7px;color:var(--muted);font-size:10px;text-align:center}.simple-layout-add-row{width:100%;margin-top:2px}.simple-layout-limit{margin:8px 0 0;color:var(--muted);font-size:10px;text-align:center}.simple-layout-sr{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(1px,1px,1px,1px)!important;white-space:nowrap!important}
    .simple-preview-block--layout{padding-right:0!important;padding-left:0!important;background:#f5f2ef}.simple-layout-preview{display:grid;gap:14px}.simple-layout-preview-row{position:relative;width:100%;border-top:1px dashed rgba(156,69,0,.28);border-bottom:1px dashed rgba(156,69,0,.28)}.simple-layout-preview-row__inner{margin-inline:auto;padding-right:clamp(16px,4cqw,42px);padding-left:clamp(16px,4cqw,42px);transition:max-width .2s}.simple-layout-preview-columns{display:grid;align-items:start;gap:clamp(10px,2.3cqw,24px)}.simple-layout-preview-row--full .simple-layout-preview-columns{grid-template-columns:minmax(0,1fr)}.simple-layout-preview-row--halves .simple-layout-preview-columns{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-layout-preview-row--thirds .simple-layout-preview-columns{grid-template-columns:repeat(3,minmax(0,1fr))}.simple-layout-preview-row--quarter .simple-layout-preview-columns{grid-template-columns:repeat(4,minmax(0,1fr))}.simple-layout-preview-row--third-two-thirds .simple-layout-preview-columns{grid-template-columns:minmax(0,1fr) minmax(0,2fr)}.simple-layout-preview-row--two-thirds-third .simple-layout-preview-columns{grid-template-columns:minmax(0,2fr) minmax(0,1fr)}.simple-layout-preview-row--width-standard{width:86%;max-width:840px}.simple-layout-preview-row--width-wide{width:95%;max-width:980px}.simple-layout-preview-row--width-full{width:100%;max-width:none}.simple-layout-preview-row--background-default{background:#fff;color:var(--ink)}.simple-layout-preview-row--background-soft{background:linear-gradient(135deg,#fffaf5 0%,#f5f1ed 100%);color:var(--ink)}.simple-layout-preview-row--background-accent{background:linear-gradient(135deg,#ff7500 0%,#e65d00 100%);color:#fff}.simple-layout-preview-row--background-dark{background:#24211f;color:#fff}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) :is(h2,h3,h4,p){color:inherit}.simple-layout-preview-row--spacing-compact .simple-layout-preview-row__inner{padding-top:18px;padding-bottom:18px}.simple-layout-preview-row--spacing-standard .simple-layout-preview-row__inner{padding-top:32px;padding-bottom:32px}.simple-layout-preview-row--spacing-generous .simple-layout-preview-row__inner{padding-top:52px;padding-bottom:52px}.simple-layout-preview-column{display:flex;min-width:0;min-height:76px;flex-direction:column;align-items:flex-start;gap:16px;padding:8px;border:1px dashed rgba(109,106,103,.3);border-radius:7px}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-column{border-color:rgba(255,255,255,.32)}.simple-layout-preview-column>*{max-width:100%}.simple-layout-preview-column h2,.simple-layout-preview-column h3,.simple-layout-preview-column h4{width:100%;margin:0;color:inherit;font-family:'Literata',Georgia,serif;line-height:1.14;overflow-wrap:anywhere}.simple-layout-preview-column h2{font-size:clamp(25px,4.5cqw,46px)}.simple-layout-preview-column h3{font-size:clamp(21px,3.2cqw,32px)}.simple-layout-preview-column h4{font-size:clamp(17px,2.4cqw,24px)}.simple-layout-preview-rich{width:100%;line-height:1.65}.simple-layout-preview-rich :is(p,ul,ol,blockquote){margin:0 0 11px}.simple-layout-preview-media{width:100%;margin:0}.simple-layout-preview-media img,.simple-layout-preview-media video,.simple-layout-preview-media iframe{display:block;width:100%;border:0;border-radius:9px;background:#171717}.simple-layout-preview-media img{height:auto;max-height:430px;object-fit:cover}.simple-layout-preview-media video,.simple-layout-preview-media iframe{aspect-ratio:16/9;object-fit:contain}.simple-layout-preview-media figcaption{margin-top:6px;color:inherit;font-size:10px;opacity:.78}.simple-layout-preview-button{display:inline-flex;min-height:43px;align-items:center;padding:9px 14px;border-radius:8px;font-size:11px;font-weight:850}.simple-layout-preview-button--primary{background:var(--brown);color:#fff}.simple-layout-preview-row--background-accent .simple-layout-preview-button--primary{border:1px solid #fff;background:#fff;color:var(--brown)}.simple-layout-preview-button--secondary{border:1px solid currentColor;background:transparent;color:var(--brown)}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-button--secondary{color:#fff}.simple-layout-preview-button--text{padding-inline:0;color:var(--brown)}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-button--text{color:#fff}.simple-layout-preview-divider{width:100%;height:1px;margin:3px 0;border:0;background:currentColor;opacity:.24}.simple-layout-preview-spacer{display:grid;width:100%;place-items:center;border-radius:5px;background:repeating-linear-gradient(135deg,rgba(156,69,0,.035),rgba(156,69,0,.035) 6px,rgba(156,69,0,.08) 6px,rgba(156,69,0,.08) 12px)}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-spacer{background:repeating-linear-gradient(135deg,rgba(255,255,255,.05),rgba(255,255,255,.05) 6px,rgba(255,255,255,.13) 6px,rgba(255,255,255,.13) 12px)}.simple-layout-preview-spacer--small{height:20px}.simple-layout-preview-spacer--medium{height:38px}.simple-layout-preview-spacer--large{height:64px}.simple-layout-preview-placeholder{display:grid;width:100%;min-height:60px;place-items:center;padding:10px;color:var(--muted);font-size:9px;text-align:center}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-placeholder{color:#fff}.simple-layout-preview-media-placeholder{display:grid;width:100%;aspect-ratio:16/9;place-items:center;align-content:center;gap:7px;border-radius:9px;background:rgba(109,106,103,.1);color:var(--muted);font-size:9px;text-align:center}.simple-layout-preview-row:is(.simple-layout-preview-row--background-accent,.simple-layout-preview-row--background-dark) .simple-layout-preview-media-placeholder{background:rgba(255,255,255,.1);color:#fff}.simple-layout-preview-media-placeholder i{color:var(--orange);font-size:23px}.simple-layout-preview-row--background-accent .simple-layout-preview-media-placeholder i{color:#fff}
    .simple-preview[data-viewport=tablet] .simple-layout-preview-row--quarter .simple-layout-preview-columns{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-preview[data-viewport=tablet] .simple-layout-preview-row--width-standard{width:90%}.simple-preview[data-viewport=mobile] .simple-layout-preview-columns{grid-template-columns:1fr!important}.simple-preview[data-viewport=mobile] .simple-layout-preview-row__inner{width:100%}.simple-preview[data-viewport=mobile] .simple-layout-preview-row--spacing-generous .simple-layout-preview-row__inner{padding-top:38px;padding-bottom:38px}.simple-preview[data-viewport=mobile] .simple-layout-preview-row--spacing-standard .simple-layout-preview-row__inner{padding-top:26px;padding-bottom:26px}@container (max-width:960px){.simple-layout-preview-row--quarter .simple-layout-preview-columns{grid-template-columns:repeat(2,minmax(0,1fr))}}@container (max-width:520px){.simple-layout-preview-columns{grid-template-columns:1fr!important}.simple-layout-preview-row__inner{width:100%}.simple-layout-preview-row--spacing-generous .simple-layout-preview-row__inner{padding-top:38px;padding-bottom:38px}.simple-layout-preview-row--spacing-standard .simple-layout-preview-row__inner{padding-top:26px;padding-bottom:26px}.simple-layout-row-settings{grid-template-columns:1fr}.simple-layout-row-settings .simple-field:first-child{grid-column:auto}.simple-layout-add{grid-template-columns:1fr}.simple-layout-add .simple-btn{width:100%}}
    @container (max-width:760px){.simple-preview-block :is(.simple-preview-stats,.simple-preview-cards,.simple-preview-events,.simple-preview-team,.simple-preview-gallery),.simple-preview-partners ul{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-preview-media,.simple-preview-media--reverse{grid-template-columns:1fr;grid-template-areas:'media' 'copy'}.simple-preview-newsletter{grid-template-columns:1fr}.simple-preview-block--spacing-spacious{padding-top:64px;padding-bottom:64px}}
    @container (max-width:520px){.simple-preview-block :is(.simple-preview-stats,.simple-preview-cards,.simple-preview-events,.simple-preview-team,.simple-preview-gallery),.simple-preview-partners ul{grid-template-columns:1fr}.simple-preview-block{padding-right:18px;padding-left:18px}.simple-preview-block--spacing-compact{padding-top:28px;padding-bottom:28px}.simple-preview-block--spacing-standard{padding-top:42px;padding-bottom:42px}.simple-preview-block--spacing-spacious{padding-top:58px;padding-bottom:58px}.simple-preview-newsletter{padding:24px 18px}.simple-preview-newsletter__form{grid-template-columns:1fr}.simple-preview-newsletter__form>.simple-preview-button,.simple-preview-newsletter__consent{grid-column:1}.simple-preview-timeline>ol{padding-left:58px}.simple-preview-timeline>ol::before{left:22px}.simple-preview-timeline li>span{left:-58px;width:46px;height:46px}.simple-managed-option{grid-template-columns:auto minmax(0,1fr)}.simple-managed-move{grid-column:2}.simple-repeat-move{flex-wrap:wrap}}
    .simple-preview-block--layout{padding:0!important}
    .simple-layout-element-header{flex-direction:column;align-items:stretch}.simple-layout-element-actions{justify-content:flex-end}
    .simple-layout-select-fallback{margin-top:9px}.simple-layout-select-fallback>summary{color:var(--brown);font-size:10px;font-weight:800;cursor:pointer}.simple-layout-select-fallback .simple-field{margin-top:8px}
    .simple-editor-mode{display:flex;flex:0 0 auto;gap:3px;padding:3px;border-radius:9px;background:#efedeb}.simple-editor-mode button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:7px;padding:0 12px;border:0;border-radius:7px;background:transparent;color:#6c6763;font-size:12px;font-weight:850;white-space:nowrap;cursor:pointer}.simple-editor-mode button:hover{background:rgba(255,255,255,.7);color:var(--brown)}.simple-editor-mode button.is-active{background:#fff;color:var(--brown);box-shadow:0 1px 5px rgba(25,28,29,.12)}.simple-editor-mode button:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:2px}
    .simple-sections{overflow-x:hidden}.simple-sections__body{min-width:0}.simple-section-list{min-width:0;max-width:100%;grid-template-columns:minmax(0,1fr)}.simple-section-item{display:block;width:100%;min-width:0;max-width:100%;grid-template-columns:none}.simple-section-row{display:grid;width:100%;min-width:0;max-width:100%;grid-template-columns:44px 32px minmax(0,1fr);grid-template-areas:'drag toggle select' 'actions actions actions';align-items:center;gap:7px}.simple-section-row>.simple-drag,.simple-section-row>.simple-drag-placeholder{grid-area:drag}.simple-section-row>.simple-navigator-toggle,.simple-section-row>.simple-navigator-toggle-placeholder{grid-area:toggle}.simple-section-row>.simple-select{grid-area:select;width:100%}.simple-section-row>.simple-order{grid-area:actions;justify-self:end}.simple-navigator-toggle,.simple-navigator-toggle-placeholder{display:grid;width:32px;height:44px;place-items:center}.simple-navigator-toggle{border:0;border-radius:7px;background:transparent;color:#766f69;cursor:pointer}.simple-navigator-toggle:hover{background:#f5eee8;color:var(--brown)}.simple-navigator-toggle:focus-visible{outline:3px solid rgba(255,117,0,.25);outline-offset:1px}.simple-navigator-toggle i{transition:transform .18s ease}.simple-navigator-toggle[aria-expanded=true] i{transform:rotate(90deg)}.simple-section-item.is-selected>.simple-section-row .simple-select strong{color:var(--brown)}
    .simple-navigator-tree{display:grid;min-width:0;gap:3px;margin:7px 0 0 8px;padding:0 0 0 8px;border-left:1px solid #eadfd7;list-style:none}.simple-navigator-tree .simple-navigator-tree{margin-top:3px;margin-left:8px}.simple-navigator-tree li{min-width:0;list-style:none}.simple-navigator-node{display:grid;width:100%;min-width:0;min-height:42px;grid-template-columns:23px minmax(0,1fr) auto;align-items:center;gap:6px;padding:6px 7px;border:1px solid transparent;border-radius:7px;background:transparent;color:#514c48;text-align:left;cursor:pointer}.simple-navigator-node i{display:grid;width:23px;height:23px;place-items:center;border-radius:6px;background:#f5f1ed;color:#8a5a35;font-size:10px}.simple-navigator-node span{min-width:0;overflow:hidden;font-size:10px;font-weight:750;text-overflow:ellipsis;white-space:nowrap}.simple-navigator-node small{color:var(--muted);font-size:8px;white-space:nowrap}.simple-navigator-node:hover{border-color:#ead6c5;background:#fff9f4}.simple-navigator-node.is-selected{border-color:var(--orange);background:#fff2e7;color:var(--brown);box-shadow:0 0 0 2px rgba(255,117,0,.1)}.simple-navigator-node.is-selected i{background:var(--orange);color:#fff}.simple-navigator-node:focus-visible{outline:3px solid rgba(255,117,0,.28);outline-offset:1px}
    .simple-breadcrumb{display:flex;width:min(100%,1050px);min-height:42px;align-items:center;gap:6px;margin:0 auto 10px;padding:6px 9px;overflow-x:auto;border:1px solid var(--line);border-radius:9px;background:rgba(255,255,255,.96);box-shadow:0 4px 14px rgba(25,28,29,.05);scrollbar-width:thin}.simple-breadcrumb:empty{display:none}.simple-breadcrumb>span,.simple-breadcrumb>button{display:inline-flex;min-height:32px;flex:0 0 auto;align-items:center;gap:5px;padding:5px 7px;border:0;border-radius:6px;background:transparent;color:#625c57;font-size:10px;font-weight:800;white-space:nowrap}.simple-breadcrumb>button{cursor:pointer}.simple-breadcrumb>button:hover{background:#fff2e7;color:var(--brown)}.simple-breadcrumb>button[aria-current=page]{background:#fff0e4;color:var(--brown)}.simple-breadcrumb>button:focus-visible{outline:3px solid rgba(255,117,0,.26);outline-offset:1px}.simple-breadcrumb>i{flex:0 0 auto;color:#b2aaa3;font-size:8px}
    .simple-layout-content-overview,.simple-layout-context-list{display:grid;gap:8px}.simple-layout-context-button{display:grid;width:100%;min-width:0;min-height:58px;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:10px;padding:9px 10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);text-align:left;cursor:pointer}.simple-layout-context-button>i{display:grid;width:38px;height:38px;place-items:center;border-radius:9px;background:#fff0e4;color:var(--brown)}.simple-layout-context-button span,.simple-layout-context-button strong,.simple-layout-context-button small{display:block;min-width:0}.simple-layout-context-button strong{overflow:hidden;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.simple-layout-context-button small{margin-top:3px;color:var(--muted);font-size:9px}.simple-layout-context-button:hover{border-color:#e6b993;background:#fff9f4}.simple-layout-context-button.is-selected{border-color:var(--orange);background:#fff2e7;box-shadow:0 0 0 2px rgba(255,117,0,.1)}.simple-layout-context-button:focus-visible{outline:3px solid rgba(255,117,0,.27);outline-offset:1px}.simple-layout-selected-summary{margin:0 0 13px;padding:11px 12px;border-left:3px solid var(--orange);border-radius:7px;background:#fff7ef;color:#665348;font-size:10px;line-height:1.55}.simple-layout-selected-summary strong{color:var(--brown);font-size:12px}.simple-layout-context-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin:0 0 15px}.simple-layout-context-actions .simple-btn{width:100%;padding-inline:8px}
    .simple-layout-preset-field{min-width:0;margin:0 0 16px;padding:0;border:0}.simple-layout-preset-field>legend{margin-bottom:9px;color:#514c48;font-size:11px;font-weight:850;letter-spacing:.03em;text-transform:uppercase}.simple-layout-presets{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.simple-layout-preset{display:grid;min-width:0;min-height:72px;align-content:center;gap:8px;padding:10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:#625c57;font-size:9px;font-weight:800;cursor:pointer}.simple-layout-preset:hover{border-color:#e6b993;background:#fff9f4}.simple-layout-preset[aria-pressed=true]{border-color:var(--orange);background:#fff2e7;color:var(--brown);box-shadow:0 0 0 2px rgba(255,117,0,.1)}.simple-layout-preset:focus-visible{outline:3px solid rgba(255,117,0,.27);outline-offset:1px}.simple-layout-preset__bars{display:flex;width:100%;height:26px;align-items:stretch;gap:3px}.simple-layout-preset__bars>span{display:block;min-width:5px;border:1px solid #dfc5b0;border-radius:4px;background:#fff}.simple-layout-preset[aria-pressed=true] .simple-layout-preset__bars>span{border-color:#e99855;background:#ffd9ba}
    .simple-layout-preview-inserter{display:inline-flex;width:100%;min-height:44px;align-items:center;justify-content:center;gap:6px;padding:8px 11px;border:1px dashed #cf9f78;border-radius:8px;background:rgba(255,255,255,.72);color:var(--brown);font-size:10px;font-weight:850;cursor:pointer}.simple-layout-preview-inserter:hover{border-style:solid;border-color:var(--orange);background:#fff2e7}.simple-layout-preview-inserter:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:2px}.simple-layout-preview-row-inserter{width:calc(100% - 24px);margin:0 auto}.simple-layout-preview-row,.simple-layout-preview-column,.simple-layout-preview-element{scroll-margin:90px}.simple-layout-preview-element{position:relative;width:100%;min-width:0;border-radius:9px;outline:2px solid transparent;outline-offset:3px;transition:outline-color .15s ease,box-shadow .15s ease}.simple-layout-preview-element:hover{outline-color:rgba(255,117,0,.52)}[data-editor-mode=layout] .simple-layout-preview-row:hover{box-shadow:inset 0 0 0 2px rgba(255,117,0,.25)}[data-editor-mode=layout] .simple-layout-preview-column:hover{border-color:rgba(255,117,0,.7)}.simple-layout-preview-row.is-layout-selected{z-index:2;box-shadow:inset 0 0 0 3px var(--orange)}.simple-layout-preview-column.is-layout-selected{position:relative;border-color:var(--orange)!important;border-style:solid;box-shadow:0 0 0 3px rgba(255,117,0,.18)}.simple-layout-preview-element.is-layout-selected{z-index:3;outline-color:var(--orange);box-shadow:0 0 0 5px rgba(255,117,0,.12)}.simple-layout-preview-row.is-layout-selected::after,.simple-layout-preview-column.is-layout-selected::after,.simple-layout-preview-element.is-layout-selected::after{position:absolute;z-index:8;top:5px;right:6px;padding:3px 6px;border-radius:5px;background:var(--orange);color:#fff;content:attr(data-node-label);font-size:8px;font-weight:900;letter-spacing:.02em;line-height:1.2;pointer-events:none}.simple-layout-preview-element.is-layout-selected::after{top:-14px}.simple-layout-preview-row:focus-visible,.simple-layout-preview-column:focus-visible,.simple-layout-preview-element:focus-visible{outline:3px solid var(--orange);outline-offset:3px}
    @media(max-width:1180px){.simple-grid{grid-template-columns:235px minmax(420px,1fr) 315px}.simple-editor-mode button span{display:none}}
    @media(max-width:880px){.simple-grid{display:flex}.simple-editor-mode button span{display:inline}.simple-navigator-structure,.simple-navigator-content-tree{margin-left:35px}.simple-breadcrumb{position:sticky;z-index:4;top:76px}.simple-section-row{grid-template-columns:36px 36px minmax(0,1fr)}}
    @media(max-width:520px){.simple-editor-mode{order:4;width:100%;justify-content:center}.simple-editor-mode button{flex:1;justify-content:center}.simple-breadcrumb{top:132px}.simple-section-list{grid-template-columns:1fr}}
    .simple-element-picker__intro{margin:0;padding:16px 22px;border-bottom:1px solid var(--line);background:#fffaf5;color:#625750;font-size:12px;line-height:1.55}.simple-element-picker{display:grid;gap:24px;padding:22px}.simple-element-category{display:grid;gap:11px}.simple-element-category__head{display:grid;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:10px}.simple-element-category__head>i{display:grid;width:38px;height:38px;place-items:center;border-radius:9px;background:#fff0e4;color:var(--brown)}.simple-element-category__head h3{margin:0;font:700 17px/1.2 'Literata',Georgia,serif}.simple-element-category__head p{margin:3px 0 0;color:var(--muted);font-size:11px}.simple-element-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.simple-element-card{display:grid;grid-template-columns:42px minmax(0,1fr);align-items:start;gap:11px;min-height:108px;padding:14px;border:1px solid var(--line);border-radius:11px;background:#fff;color:var(--ink);text-align:left;cursor:pointer}.simple-element-card:hover,.simple-element-card:focus-visible{border-color:var(--orange);background:#fff9f4;outline:3px solid rgba(255,117,0,.15);outline-offset:1px}.simple-element-card:disabled{cursor:not-allowed;opacity:.48}.simple-element-card>i{display:grid;width:42px;height:42px;place-items:center;border-radius:10px;background:#fff0e4;color:var(--orange);font-size:18px}.simple-element-card strong,.simple-element-card small{display:block}.simple-element-card strong{margin:2px 0 5px;font-size:12px}.simple-element-card small{color:var(--muted);font-size:10px;line-height:1.45}.simple-element-card em{display:block;margin-top:7px;color:var(--brown);font-size:9px;font-style:normal;font-weight:850}.simple-element-picker-fallback{margin:0 22px 22px;padding:13px;border:1px solid var(--line);border-radius:9px}.simple-element-picker-fallback>summary{color:var(--brown);font-size:11px;font-weight:850;cursor:pointer}.simple-element-picker-fallback__row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin-top:10px}.simple-element-picker-fallback select{min-height:44px;padding:8px;border:1px solid #d9d2cc;border-radius:7px;background:#fff}.simple-layout-add{display:block}.simple-layout-add>[data-layout-open-picker]{width:100%}
    .simple-layout-media-field__preview{display:flex;min-width:0;align-items:center;gap:8px;margin-top:7px;padding:8px;border:1px solid var(--line);border-radius:7px;background:#faf9f8}.simple-layout-media-field__preview img{width:52px;height:42px;flex:0 0 auto;border-radius:5px;object-fit:cover}.simple-layout-media-field__preview i{display:grid;width:42px;height:42px;flex:0 0 auto;place-items:center;border-radius:7px;background:#fff0e4;color:var(--brown)}.simple-layout-media-field__preview span{min-width:0;overflow:hidden;color:var(--muted);font-size:10px;text-overflow:ellipsis;white-space:nowrap}.simple-document-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:20px}.simple-document-option{display:grid;grid-template-columns:44px minmax(0,1fr);align-items:center;gap:12px;min-height:78px;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fff;text-align:left;cursor:pointer}.simple-document-option:hover,.simple-document-option:focus-visible{border-color:var(--orange);outline:3px solid rgba(255,117,0,.14)}.simple-document-option>i{display:grid;width:44px;height:44px;place-items:center;border-radius:10px;background:#fff0e4;color:var(--brown);font-size:19px}.simple-document-option strong,.simple-document-option small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.simple-document-option strong{font-size:12px}.simple-document-option small{margin-top:4px;color:var(--muted);font-size:9px}
    .simple-layout-repeater{display:grid;gap:9px;margin-bottom:14px}.simple-layout-repeater-item{border:1px solid var(--line);border-radius:9px;background:#fff}.simple-layout-repeater-item>summary{display:flex;min-height:50px;align-items:center;gap:8px;padding:9px 10px;color:var(--ink);font-size:11px;font-weight:850;cursor:pointer;list-style:none}.simple-layout-repeater-item>summary::-webkit-details-marker{display:none}.simple-layout-repeater-item>summary::after{margin-left:auto;color:var(--brown);content:'+';font-size:17px}.simple-layout-repeater-item[open]>summary::after{content:'−'}.simple-layout-repeater-item__body{padding:11px;border-top:1px solid var(--line)}.simple-layout-repeater-actions{display:grid;grid-template-columns:repeat(3,44px);justify-content:end;gap:5px;margin-bottom:11px}.simple-layout-repeater-actions button{display:grid;width:44px;height:44px;place-items:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--brown);cursor:pointer}.simple-layout-repeater-actions button:last-child{color:#a52c24}.simple-layout-repeater-actions button:disabled{opacity:.35}.simple-layout-add-item{width:100%}.simple-progressive{margin:4px 0 14px;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.simple-progressive>summary{min-height:44px;padding:13px 2px;color:var(--brown);font-size:11px;font-weight:850;cursor:pointer}.simple-progressive>div{padding-top:5px}
    .simple-validation-error{margin:0 0 14px;padding:12px 13px;border:1px solid #e6a6a0;border-left:4px solid #b42318;border-radius:8px;background:#fff4f2;color:#7a211a;font-size:11px;line-height:1.5}.simple-validation-error strong{display:block;margin-bottom:3px;font-size:12px}.simple-field.is-invalid input,.simple-field.is-invalid textarea,.simple-field.is-invalid select{border-color:#b42318;box-shadow:0 0 0 2px rgba(180,35,24,.1)}
    .simple-help-content{display:grid;gap:22px;padding:22px}.simple-help-quickstart{margin:0;padding:18px 18px 18px 42px;border-radius:11px;background:#fff7ef}.simple-help-quickstart li{margin:0 0 10px;padding-left:4px;line-height:1.5}.simple-help-glossary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.simple-help-term{padding:15px;border:1px solid var(--line);border-radius:10px;background:#fff}.simple-help-term i{color:var(--orange);font-size:20px}.simple-help-term h3{margin:9px 0 5px;font:700 15px 'Literata',Georgia,serif}.simple-help-term p{margin:0;color:var(--muted);font-size:11px;line-height:1.5}.simple-help-modes{display:grid;grid-template-columns:1fr 1fr;gap:11px}.simple-help-mode{padding:16px;border-radius:10px;background:#f7f4f1}.simple-help-mode strong{display:block;margin-bottom:6px}.simple-help-mode p{margin:0;color:var(--muted);font-size:11px;line-height:1.5}
    .simple-layout-preview-icon{display:grid;place-items:center;color:var(--orange)}.simple-layout-preview-icon--small{width:38px;height:38px;font-size:20px}.simple-layout-preview-icon--medium{width:50px;height:50px;font-size:27px}.simple-layout-preview-icon--large{width:66px;height:66px;font-size:36px}.simple-layout-preview-icon--soft,.simple-layout-preview-icon--circle{background:#fff0e4}.simple-layout-preview-icon--soft{border-radius:13px}.simple-layout-preview-icon--circle{border-radius:999px}.simple-layout-preview-file{display:flex;width:100%;align-items:center;gap:12px;padding:13px;border:1px solid var(--line);border-radius:10px;background:#fff}.simple-layout-preview-file>i{display:grid;width:42px;height:42px;flex:0 0 auto;place-items:center;border-radius:9px;background:#fff0e4;color:var(--brown)}.simple-layout-preview-file span{display:grid;gap:3px}.simple-layout-preview-file strong{font-size:12px}.simple-layout-preview-file small{color:var(--muted);font-size:10px}.simple-layout-preview-card{width:100%;overflow:hidden;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);box-shadow:0 6px 18px rgba(25,28,29,.07)}.simple-layout-preview-card--soft{background:#fff8f2}.simple-layout-preview-card--contrast{border-color:#24211f;background:#24211f;color:#fff}.simple-layout-preview-card>img{width:100%;aspect-ratio:16/9;object-fit:cover}.simple-layout-preview-card__icon{display:grid;width:46px;height:46px;margin:16px 16px 0;place-items:center;border-radius:11px;background:#fff0e4;color:var(--orange);font-size:20px}.simple-layout-preview-card__copy{display:grid;gap:7px;padding:16px}.simple-layout-preview-card__copy small{color:var(--brown);font-size:9px;font-weight:850;text-transform:uppercase}.simple-layout-preview-card__copy strong{font:700 18px 'Literata',serif}.simple-layout-preview-card__copy p{margin:0;font-size:11px}.simple-layout-preview-card__copy em{color:var(--brown);font-size:10px;font-style:normal;font-weight:850}.simple-layout-preview-stat{display:grid;width:100%;min-height:150px;place-items:center;align-content:center;gap:8px;padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff;text-align:center}.simple-layout-preview-stat--accent{border-color:#ffd0aa;background:#fff0e4}.simple-layout-preview-stat--contrast{border-color:#24211f;background:#24211f;color:#fff}.simple-layout-preview-stat i{color:var(--orange);font-size:24px}.simple-layout-preview-stat strong{font:700 clamp(30px,5cqw,49px)/1 'Literata',serif}.simple-layout-preview-stat span{font-size:11px}.simple-layout-preview-quote{position:relative;width:100%;margin:0;padding:22px;border-left:4px solid var(--orange);border-radius:0 12px 12px 0;background:#fff8f2}.simple-layout-preview-quote--featured{border-left:0;background:linear-gradient(135deg,#ff7500,#d95400);color:#fff;text-align:center}.simple-layout-preview-quote--compact{padding:16px}.simple-layout-preview-quote img{width:58px;height:58px;border-radius:50%;object-fit:cover}.simple-layout-preview-quote blockquote{margin:10px 0;font:600 17px/1.5 'Literata',serif}.simple-layout-preview-quote figcaption{display:grid;gap:2px;font-size:10px}.simple-layout-preview-quote figcaption span{opacity:.74}.simple-layout-preview-gallery{display:grid;width:100%;gap:7px}.simple-layout-preview-gallery--2{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-layout-preview-gallery--3{grid-template-columns:repeat(3,minmax(0,1fr))}.simple-layout-preview-gallery--4{grid-template-columns:repeat(4,minmax(0,1fr))}.simple-layout-preview-gallery figure{min-width:0;margin:0}.simple-layout-preview-gallery img,.simple-layout-preview-gallery__empty{display:grid;width:100%;aspect-ratio:4/3;place-items:center;border-radius:8px;background:#eee;object-fit:cover}.simple-layout-preview-gallery figcaption{margin-top:3px;font-size:8px}.simple-layout-preview-accordion{width:100%;border-top:1px solid var(--line)}.simple-layout-preview-accordion details{border-bottom:1px solid var(--line)}.simple-layout-preview-accordion summary{padding:12px 28px 12px 2px;font-size:11px;font-weight:850;cursor:pointer}.simple-layout-preview-accordion__answer{padding:0 2px 12px;font-size:10px}.simple-layout-preview-timeline{display:grid;width:100%;gap:10px;margin:0;padding:0;list-style:none}.simple-layout-preview-timeline li{display:grid;grid-template-columns:38px minmax(0,1fr);gap:9px}.simple-layout-preview-timeline__marker{display:grid;width:36px;height:36px;place-items:center;border-radius:50%;background:var(--orange);color:#fff;font-size:10px}.simple-layout-preview-timeline li small{color:var(--brown);font-size:8px;font-weight:850;text-transform:uppercase}.simple-layout-preview-timeline h4{margin:2px 0 4px!important;font-size:14px!important}.simple-layout-preview-timeline p{margin:0!important;font-size:10px}.simple-layout-preview-callout{display:grid;width:100%;grid-template-columns:auto minmax(0,1fr);gap:12px;padding:18px;border:1px solid #ffd0aa;border-radius:12px;background:#fff0e4;color:var(--ink)}.simple-layout-preview-callout--neutral{border-color:var(--line);background:#fff}.simple-layout-preview-callout--success{border-color:#b8dec1;background:#eff9f1}.simple-layout-preview-callout--warning{border-color:#f0ce79;background:#fff8df}.simple-layout-preview-callout__icon{display:grid;width:42px;height:42px;place-items:center;border-radius:10px;background:#fff;color:var(--orange)}.simple-layout-preview-callout small{color:var(--brown);font-size:8px;font-weight:850;text-transform:uppercase}.simple-layout-preview-callout h4{margin:4px 0 7px!important}.simple-layout-preview-callout p{margin:0!important;font-size:10px}.simple-layout-preview-callout em{display:block;margin-top:9px;color:var(--brown);font-size:9px;font-style:normal;font-weight:850}
    @media(max-width:880px){.simple-element-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.simple-element-cards,.simple-document-grid,.simple-help-glossary,.simple-help-modes{grid-template-columns:1fr}.simple-element-picker{padding:14px}.simple-element-picker-fallback__row{grid-template-columns:1fr}.simple-layout-preview-gallery--3,.simple-layout-preview-gallery--4{grid-template-columns:repeat(2,minmax(0,1fr))}}
    .simple-image-actions{display:flex;flex-wrap:wrap;gap:7px}
</style>

<main class="simple-editor" id="simple-editor">
    <header class="simple-topbar">
        <div class="simple-topbar__title">
            <a class="simple-back btn igf-btn igf-btn-secondary" href="{{ route('page.index') }}" aria-label="Back to Content Hub"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div><h1 id="simple-page-heading">{{ $page->name }}</h1><p>Simple Editor &middot; {{ strtoupper($page->language) }} &middot; Live: <a class="simple-live-address" href="{{ $publicUrl }}" target="_blank" rel="noopener">{{ $publicPath }}</a></p></div>
        </div>
        <div class="simple-viewport" aria-label="Preview size">
            <button type="button" class="is-active" data-viewport="desktop" aria-label="Desktop preview" aria-pressed="true"><i class="fa fa-desktop"></i></button>
            <button type="button" data-viewport="tablet" aria-label="Tablet preview" aria-pressed="false"><i class="fa fa-tablet"></i></button>
            <button type="button" data-viewport="mobile" aria-label="Mobile preview" aria-pressed="false"><i class="fa fa-mobile"></i></button>
        </div>
        <div class="simple-editor-mode" role="group" aria-label="Editing mode">
            <button type="button" class="is-active" data-editor-mode="content" aria-pressed="true"><i class="fa fa-pencil" aria-hidden="true"></i><span>Edit content</span></button>
            <button type="button" data-editor-mode="layout" aria-pressed="false"><i class="fa fa-columns" aria-hidden="true"></i><span>Customize layout</span></button>
        </div>
        <div class="simple-history" aria-label="Editing history">
            <button class="simple-btn" id="simple-undo" type="button" aria-label="Undo last unsaved change" title="Undo" disabled><i class="fa fa-undo" aria-hidden="true"></i></button>
            <button class="simple-btn" id="simple-redo" type="button" aria-label="Redo last unsaved change" title="Redo" disabled><i class="fa fa-repeat" aria-hidden="true"></i></button>
        </div>
        <div class="simple-actions">
            <span class="simple-save-state" id="simple-save-state">{{ $canEditBuilder ? 'All changes saved' : 'Read only' }}</span>
            <details class="simple-more">
                <summary class="simple-btn" aria-label="Open page tools"><i class="fa fa-ellipsis-h" aria-hidden="true"></i> Page tools</summary>
                <div class="simple-more__menu">
                    <a class="simple-btn" href="{{ route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> Preview draft</a>
                    <a class="simple-btn" href="{{ $publicUrl }}" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> View live page</a>
                    @if($canEditSeo)<a class="simple-btn" href="{{ route('seo.content.edit', ['type' => 'page', 'id' => $page->getKey(), 'locale' => $page->language]) }}"><i class="fa fa-search" aria-hidden="true"></i> Search &amp; Sharing</a>@endif
                    @if($canEditBuilder)<a class="simple-btn" href="{{ route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => $page->language, 'mode' => 'advanced']) }}"><i class="fa fa-sliders" aria-hidden="true"></i> Advanced editor</a>@endif
                </div>
            </details>
            @if($canEditBuilder)<button class="simple-btn simple-btn--primary" type="button" data-save-changes disabled>Save changes</button>@endif
        </div>
    </header>

    @unless($canEditBuilder)
        <div class="simple-read-only" role="status"><strong>Read-only preview.</strong> You can review this page, but your role cannot edit content or publishing settings.</div>
    @endunless

    <div class="simple-grid">
        <aside class="simple-sections" aria-label="Page structure">
            <div class="simple-panel-head"><h2>Page Structure</h2><p>{{ $canEditBuilder ? 'Choose anything here or in the preview. Use Customize layout when you need rows and columns. Use the arrow buttons or drag handle to change the visitor order.' : 'Choose an item to review its content.' }}</p></div>
            <div class="simple-sections__body">
                @if($canCreateBuilder)<button class="simple-btn simple-add-section" type="button" id="open-add-section"><i class="fa fa-plus" aria-hidden="true"></i> Add section</button>@endif
                @if($legacyContentNeedsConversion)
                    <p class="simple-banner-guidance" id="simple-legacy-conversion-note" role="note"><strong>Your existing article is protected.</strong> When you add or show the first section, the editor will ask before copying the article into an editable Text section. Nothing will be discarded.</p>
                @endif
                @if($canEditBuilder)
                    @if($reusableBlocks->isNotEmpty())
                        <div class="simple-reusable-launch">
                            <button class="simple-btn" type="button" id="open-reusable-library"><i class="fa fa-link" aria-hidden="true"></i> Use a saved section</button>
                            <small id="simple-reusable-library-help">Add an approved shared section without rebuilding it.</small>
                        </div>
                    @else
                        <p id="simple-reusable-library-help" class="simple-autosave-note">Reusable sections will appear here after a section is saved to the shared library.</p>
                    @endif
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
                        <label class="simple-field"><span>Status</span><select id="simple-page-status" @disabled(!$canEditBuilder || !$canManagePublication || ($isRequiredSystemPage && $page->publication_status === 'published'))>
                            @if($isRequiredSystemPage)
                                <option value="published" selected>Published — required website page</option>
                            @else
                                @foreach(['draft'=>'Draft','pending_review'=>'Needs review','published'=>'Published'] as $value => $label)
                                    <option value="{{ $value }}" @selected($page->publication_status === $value)>{{ $label }}</option>
                                @endforeach
                            @endif
                            @if(in_array($page->publication_status, ['scheduled','private'], true))
                                <option value="{{ $page->publication_status }}" selected>{{ ucfirst($page->publication_status) }} (manage in Advanced mode)</option>
                            @endif
                        </select><small>@if($isRequiredSystemPage){{ $requiredSystemPageLabel }} stays published so this essential visitor route cannot be accidentally taken offline. Its content remains fully editable.@elseif($canManagePublication)Choose whether visitors can see this page.@else Only a publisher can change status. You can still edit and save the page content safely.@endif</small></label>
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
            <div class="simple-canvas-tip" id="simple-canvas-tip"><i class="fa {{ $canEditBuilder ? 'fa-pencil' : 'fa-eye' }}" aria-hidden="true"></i> <span>{{ $canEditBuilder ? 'Click any outlined text to edit it directly' : 'Previewing the page in read-only mode' }}</span></div>
            <nav class="simple-breadcrumb" id="simple-selection-breadcrumb" aria-label="Selected page item"></nav>
            <div class="simple-preview" id="simple-preview" data-viewport="desktop" aria-live="polite"></div>
        </section>

        <aside class="simple-inspector" aria-label="Selected section editor">
            <div class="simple-panel-head"><div class="simple-inspector__head-row"><div><h2 class="simple-inspector-title-focus" id="simple-inspector-title" tabindex="-1">Edit section</h2><span class="simple-type-badge" id="simple-inspector-type">Section</span></div><button type="button" class="simple-btn" id="simple-help"><i class="fa fa-question-circle" aria-hidden="true"></i> Help</button></div></div>
            <div class="simple-inspector__body" id="simple-inspector-body"></div>
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
            <div class="simple-reusable-actions"><button class="simple-btn btn igf-btn igf-btn-secondary" type="button" data-close-promote-reusable><i class="fa fa-times" aria-hidden="true"></i> Cancel</button><button class="simple-btn simple-btn--primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save as reusable</button></div>
        </form>
    </div>
</div>
@endif

@if($canDeleteBuilder)
<div class="simple-modal" id="simple-delete-modal" role="dialog" aria-modal="true" aria-labelledby="simple-delete-title" aria-describedby="simple-delete-description" hidden>
    <div class="simple-modal__dialog simple-modal__dialog--compact">
        <header class="simple-modal__head">
            <div><h2 id="simple-delete-title">Move section to trash?</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px">You can restore it later from a page revision.</p></div>
            <button class="simple-close btn igf-btn igf-btn-tertiary" type="button" data-cancel-section-delete aria-label="Keep section and close">&times;</button>
        </header>
        <div class="simple-delete-confirmation">
            <p id="simple-delete-description">This section will disappear from the page after you confirm.</p>
            <p class="simple-reusable-warning" role="note"><strong>Recovery:</strong> A page revision will be kept so an administrator can restore it.</p>
            <p class="simple-delete-confirmation__status" id="simple-delete-status" role="status" aria-live="polite" tabindex="-1"></p>
            <div class="simple-reusable-actions">
                <button class="simple-btn btn igf-btn igf-btn-secondary" type="button" data-cancel-section-delete><i class="fa fa-times" aria-hidden="true"></i> Keep section</button>
                <button class="simple-btn simple-btn--danger" type="button" id="simple-confirm-delete"><i class="fa fa-trash" aria-hidden="true"></i> <span>Move to trash</span></button>
            </div>
        </div>
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
<div class="simple-modal" id="video-media-modal" role="dialog" aria-modal="true" aria-labelledby="video-media-modal-title" hidden>
    <div class="simple-modal__dialog"><header class="simple-modal__head"><h2 id="video-media-modal-title">Choose an uploaded video</h2><button class="simple-close" type="button" data-close-video-media aria-label="Close">&times;</button></header>@if($canCreateBuilder)<div class="simple-upload"><span style="font-size:12px;color:var(--muted)">Upload MP4 or WebM (maximum 20 MB)</span><label class="simple-btn simple-btn--primary">Upload video<input id="simple-video-upload" type="file" accept="video/mp4,video/webm" hidden></label></div>@endif<div class="simple-media-grid" id="simple-video-grid">
        @forelse(($videoAssets ?? collect()) as $asset)
            <button class="simple-media-option" type="button" data-video-media-url="{{ $asset->url }}" title="{{ $asset->original_name }}" aria-label="Choose {{ $asset->original_name }}"><video src="{{ $asset->url }}" muted preload="metadata" aria-hidden="true"></video></button>
        @empty
            <p class="simple-empty">No uploaded videos are available yet.</p>
        @endforelse
    </div></div>
</div>
@if($canEditBuilder)
<div class="simple-modal" id="element-picker-modal" role="dialog" aria-modal="true" aria-labelledby="element-picker-title" aria-describedby="element-picker-description" hidden>
    <div class="simple-modal__dialog">
        <header class="simple-modal__head"><div><h2 id="element-picker-title">Add content</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px" id="element-picker-location">Choose what belongs in this column.</p></div><button class="simple-close" type="button" data-close-element-picker aria-label="Close content picker">&times;</button></header>
        <p class="simple-element-picker__intro" id="element-picker-description"><strong>Choose one content type.</strong> Safe starter content is added immediately, and its easy settings open on the right.</p>
        <div class="simple-element-picker" id="simple-element-picker"></div>
        <details class="simple-element-picker-fallback"><summary>Prefer a simple list?</summary><div class="simple-element-picker-fallback__row"><label class="simple-layout-sr" for="simple-element-picker-select">Content type</label><select id="simple-element-picker-select"></select><button class="simple-btn simple-btn--primary" type="button" id="simple-element-picker-add"><i class="fa fa-plus" aria-hidden="true"></i> Add selected content</button></div></details>
    </div>
</div>
@endif
<div class="simple-modal" id="document-media-modal" role="dialog" aria-modal="true" aria-labelledby="document-media-modal-title" hidden>
    <div class="simple-modal__dialog">
        <header class="simple-modal__head"><div><h2 id="document-media-modal-title">Choose a public file</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px">PDF, Word, and Excel files from the Media Library.</p></div><button class="simple-close" type="button" data-close-document-media aria-label="Close file chooser">&times;</button></header>
        @if($canCreateBuilder)<div class="simple-upload"><span style="font-size:12px;color:var(--muted)">Upload PDF, DOC, DOCX, XLS or XLSX (maximum 20 MB)</span><label class="simple-btn simple-btn--primary">Upload file<input id="simple-document-upload" type="file" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.pdf,.doc,.docx,.xls,.xlsx" hidden></label></div>@endif
        <div class="simple-document-grid" id="simple-document-grid">
            @forelse(($documentAssets ?? collect()) as $asset)
                <button class="simple-document-option" type="button" data-document-media-url="{{ $asset->url }}" aria-label="Choose {{ $asset->original_name }}"><i class="fa fa-file-text-o" aria-hidden="true"></i><span><strong>{{ $asset->original_name }}</strong><small>{{ $asset->mime_type }}</small></span></button>
            @empty
                <p class="simple-empty">No public documents are available yet.</p>
            @endforelse
        </div>
    </div>
</div>
<div class="simple-modal" id="simple-help-modal" role="dialog" aria-modal="true" aria-labelledby="simple-help-title" aria-describedby="simple-help-intro" hidden>
    <div class="simple-modal__dialog">
        <header class="simple-modal__head"><div><h2 id="simple-help-title">Build a page without code</h2><p style="margin:4px 0 0;color:var(--muted);font-size:12px" id="simple-help-intro">Start with content. Open layout controls only when the page structure needs to change.</p></div><button class="simple-close" type="button" data-close-help aria-label="Close help">&times;</button></header>
        <div class="simple-help-content">
            <section><h3 style="margin:0 0 10px;font:700 18px 'Literata',serif">Quick start</h3><ol class="simple-help-quickstart"><li>Choose a <strong>Section</strong> in Page Structure or click it in the preview.</li><li>Stay in <strong>Edit content</strong> to change words, images, buttons, cards, files, and other visitor content.</li><li>Choose <strong>Customize layout</strong> only to arrange rows and columns.</li><li>Use the orange outline and breadcrumb to confirm exactly what you are editing.</li><li>Check desktop, tablet, and mobile previews, then choose <strong>Save changes</strong>.</li></ol></section>
            <section><h3 style="margin:0 0 10px;font:700 18px 'Literata',serif">What each part means</h3><div class="simple-help-glossary">
                <article class="simple-help-term"><i class="fa fa-object-group" aria-hidden="true"></i><h3>Sections</h3><p>Ready-made page patterns, such as a Hero, Story, Gallery, or Visual layout. Sections make common pages quick and consistent.</p></article>
                <article class="simple-help-term"><i class="fa fa-bars" aria-hidden="true"></i><h3>Rows</h3><p>Horizontal bands inside a Visual layout. Each row controls page width, background, spacing, and its column arrangement.</p></article>
                <article class="simple-help-term"><i class="fa fa-columns" aria-hidden="true"></i><h3>Columns</h3><p>Side-by-side spaces inside a row. On small screens, columns safely stack so the page remains readable.</p></article>
                <article class="simple-help-term"><i class="fa fa-pencil" aria-hidden="true"></i><h3>Content</h3><p>The visitor-facing items inside a column: headings, text, images, buttons, cards, downloads, questions, and more.</p></article>
            </div></section>
            <section><h3 style="margin:0 0 10px;font:700 18px 'Literata',serif">Two safe ways to work</h3><div class="simple-help-modes"><article class="simple-help-mode"><strong><i class="fa fa-pencil" aria-hidden="true"></i> Edit content</strong><p>The everyday mode. It hides row and column controls so you can focus on what visitors read and see.</p></article><article class="simple-help-mode"><strong><i class="fa fa-columns" aria-hidden="true"></i> Customize layout</strong><p>Use this when you need another row, a different column split, background, spacing, or content position.</p></article></div></section>
        </div>
    </div>
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
    const reusableSections = @json($simpleReusableSections);
    let legacyContentNeedsConversion = @json($legacyContentNeedsConversion);
    const routes = {
        createPage: @json($canCreatePage ? route('page.create') : null),
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
        layoutOpenElement: null,
        editorMode: 'content',
        selectedLayout: {kind:'section',rowId:null,columnId:null,elementId:null},
        expandedSections: new Set(),
        testimonialIndexes: {},
        cardIndexes: {},
        mediaTarget: null,
        elementPickerTarget: null,
        validationError: null,
        modalReturn: null,
        undo: [],
        redo: [],
        autosaveTimer: null,
        leaving: false,
        pendingDeleteUuid: null,
    };
    if (state.selected) state.expandedSections.add(state.selected);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const list = document.getElementById('simple-section-list');
    const preview = document.getElementById('simple-preview');
    const inspector = document.getElementById('simple-inspector-body');
    const saveState = document.getElementById('simple-save-state');
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const plainText = value => String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    function confirmLegacyContentConversion(action='add this section') {
        if (!legacyContentNeedsConversion) return true;
        return confirm(`Keep your existing page article while you ${action}?\n\nChoose OK to copy it into the first editable Text section. The original version will also remain available in page history.`);
    }
    function acceptConvertedLegacyBlock(payload) {
        const converted = payload?.converted_legacy_block;
        if (!converted) return;
        if (!state.blocks.some(block => block.uuid === converted.uuid)) state.blocks.unshift(converted);
        legacyContentNeedsConversion = false;
        document.getElementById('simple-legacy-conversion-note')?.remove();
    }
    const safeImage = value => /^(?:https?:\/\/|\/(?!\/))/i.test(String(value || '')) ? String(value) : '';
    const youtubeEmbedUrl = value => {
        let candidate = String(value || '').trim();
        if (!candidate) return '';
        if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(candidate)) candidate = `https://${candidate}`;
        try {
            const url = new URL(candidate);
            const host = url.hostname.toLowerCase().replace(/\.$/, '');
            if (url.protocol !== 'https:' || url.username || url.password || url.port) return '';
            let id = '';
            if (host === 'youtu.be') {
                id = url.pathname.match(/^\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
            } else if (['youtube.com','www.youtube.com','m.youtube.com','music.youtube.com'].includes(host)) {
                if (url.pathname.replace(/\/+$/, '') === '/watch') id = url.searchParams.get('v') || '';
                else id = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
            } else if (['youtube-nocookie.com','www.youtube-nocookie.com'].includes(host)) {
                id = url.pathname.match(/^\/embed\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
            }
            return /^[A-Za-z0-9_-]{11}$/.test(id) ? `https://www.youtube-nocookie.com/embed/${id}` : '';
        } catch (error) {
            return '';
        }
    };
    const videoEmbedUrl = value => {
        const youtube = youtubeEmbedUrl(value);
        if (youtube) return youtube;
        let candidate = String(value || '').trim();
        if (!candidate) return '';
        if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(candidate)) candidate = `https://${candidate}`;
        try {
            const url = new URL(candidate);
            const host = url.hostname.toLowerCase().replace(/\.$/, '');
            if (url.protocol !== 'https:' || url.username || url.password || url.port) return '';
            let id = '';
            if (['vimeo.com','www.vimeo.com'].includes(host)) id = url.pathname.match(/^\/(?:video\/)?(\d+)\/?$/)?.[1] || '';
            if (['player.vimeo.com'].includes(host)) id = url.pathname.match(/^\/video\/(\d+)\/?$/)?.[1] || '';
            return id ? `https://player.vimeo.com/video/${id}` : '';
        } catch (error) {
            return '';
        }
    };
    const explicitHttpsYoutubeEmbedUrl = value => {
        const candidate = String(value || '').trim();
        if (!/^https:\/\//i.test(candidate)) return '';
        return youtubeEmbedUrl(candidate);
    };
    function safeRichHtml(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        const allowed = new Set(['A','B','BLOCKQUOTE','BR','EM','H3','H4','LI','OL','P','STRONG','U','UL']);
        [...template.content.querySelectorAll('*')].forEach(element => {
            if (!allowed.has(element.tagName)) {
                element.replaceWith(...element.childNodes);
                return;
            }
            const href = element.tagName === 'A' ? element.getAttribute('href') || '' : '';
            [...element.attributes].forEach(attribute => element.removeAttribute(attribute.name));
            if (element.tagName !== 'A') return;
            if (/^(?:https?:\/\/|\/(?!\/)|mailto:|tel:)/i.test(href)) {
                element.setAttribute('href', href);
                if (/^https?:\/\//i.test(href)) {
                    element.setAttribute('target', '_blank');
                    element.setAttribute('rel', 'noopener noreferrer');
                }
            }
        });
        return template.innerHTML;
    }
    function safeLayoutRichHtml(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        const allowed = new Set(['A','B','BLOCKQUOTE','BR','EM','H3','H4','LI','OL','P','STRONG','U','UL']);
        [...template.content.querySelectorAll('*')].forEach(element => {
            if (!allowed.has(element.tagName)) {
                element.replaceWith(...element.childNodes);
                return;
            }
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
    const current = () => state.blocks.find(block => block.uuid === state.selected);
    const endpoint = (template, uuid) => template.replace('__BLOCK__', uuid);
    const hasDirty = () => state.dirtyBlocks.size > 0 || state.dirtyPage || state.dirtyOrder;
    const hasSavableDirty = () => state.dirtyPage || state.dirtyOrder || [...state.dirtyBlocks].some(uuid=>{
        const block=state.blocks.find(item=>item.uuid===uuid);
        return block && !layoutIsBlocked(block);
    });
    const draftKey = `ignite-simple-editor:${pageUuid}:${locale}`;
    const clone = value => JSON.parse(JSON.stringify(value));
    const formatDateTimeLocal = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';

    function notify(message) {
        document.querySelector('.simple-notice')?.remove();
        const notice = document.createElement('div');
        notice.className = 'simple-notice'; notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite'); notice.textContent = message;
        document.body.appendChild(notice); setTimeout(() => notice.remove(), 3800);
    }
    function updateSaveState() {
        const dirty = hasDirty();
        const savable = hasSavableDirty();
        saveState.textContent = permissions.edit ? (dirty ? (savable?'Unsaved changes':'Repair needed before this section can be saved') : 'All changes saved') : 'Read only';
        saveState.classList.toggle('is-dirty', dirty);
        document.querySelectorAll('[data-save-changes]').forEach(button => { button.disabled = !permissions.edit || !savable || state.busy; });
        document.getElementById('simple-undo').disabled = !permissions.edit || state.undo.length === 0;
        document.getElementById('simple-redo').disabled = !permissions.edit || state.redo.length === 0;
        document.querySelectorAll('[data-delete-section],#simple-delete').forEach(button => { button.disabled = state.busy; });
    }
    function markDirty(scope) {
        if (!permissions.edit) return;
        if (scope === 'page') state.dirtyPage = true;
        else if (scope === 'order') state.dirtyOrder = true;
        else if (state.selected) {
            const block=current();
            if (layoutIsBlocked(block)) return notify('This visual layout is locked so its saved content stays safe. Ask a website administrator to repair it.');
            state.dirtyBlocks.add(state.selected);
        }
        updateSaveState();
        scheduleDraft();
    }
    function snapshot() {
        return {
            blocks: clone(state.blocks), selected: state.selected, heroSlide: state.heroSlide, cardIndexes: clone(state.cardIndexes),
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
        state.blocks = clone(saved.blocks || []); state.selected = saved.selected || state.blocks[0]?.uuid || null; state.heroSlide = Number(saved.heroSlide || 0); state.cardIndexes = clone(saved.cardIndexes || {});
        state.layoutOpenElement = null; state.selectedLayout = {kind:'section',rowId:null,columnId:null,elementId:null};
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
            try { sessionStorage.setItem(draftKey, JSON.stringify({version:3,baseEditorVersion:editorVersion,savedAt:Date.now(),...snapshot()})); updateSaveState(); }
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
            if (saved.version !== 3 || Number(saved.baseEditorVersion) !== editorVersion) {
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
        if (!response.ok) {
            const validationErrors = payload.errors && typeof payload.errors === 'object' ? payload.errors : {};
            const firstValidationMessage = Object.values(validationErrors).flat().find(message => typeof message === 'string' && message.trim());
            const error = new Error(firstValidationMessage || payload.message || 'The request could not be completed.');
            error.validationErrors = validationErrors;
            error.status = response.status;
            error.payload = payload;
            throw error;
        }
        if (Number.isInteger(Number(payload.editor_version))) editorVersion = Number(payload.editor_version);
        return payload;
    }
    function firstValidationEntry(error) {
        const entries=Object.entries(error?.validationErrors||{});
        for(const [path,messages] of entries){
            const message=(Array.isArray(messages)?messages:[messages]).find(value=>typeof value==='string'&&value.trim());
            if(message)return {path,message};
        }
        return null;
    }
    function focusLayoutValidationError(error) {
        const entries=Object.entries(error?.validationErrors||{});
        for(const [path,messages] of entries){
            const match=String(path).match(/^(?:blocks\.([^.]+)\.content|block\.content|content)\.rows\.(\d+)(?:\.columns\.(\d+))?(?:\.elements\.(\d+))?(?:\.(.+))?$/);
            if(!match)continue;
            const token=match[1],rowIndex=Number(match[2]),columnIndex=match[3]===undefined?-1:Number(match[3]),elementIndex=match[4]===undefined?-1:Number(match[4]),fieldPath=String(match[5]||'');
            const block=state.blocks.find(item=>item.uuid===token) || (/^\d+$/.test(String(token||''))?state.blocks[Number(token)]:null) || current();
            if(!block||block.type!=='layout')continue;
            const message=(Array.isArray(messages)?messages:[messages]).find(value=>typeof value==='string'&&value.trim()) || error.message;
            state.selected=block.uuid;state.expandedSections.add(block.uuid);
            if(elementIndex>=0)assignLayoutSelection(block,'element',rowIndex,columnIndex,elementIndex);
            else if(columnIndex>=0)assignLayoutSelection(block,'column',rowIndex,columnIndex);
            else assignLayoutSelection(block,'row',rowIndex);
            if(elementIndex<0)state.editorMode='layout';
            state.validationError={blockUuid:block.uuid,path:String(path),message,rowIndex,columnIndex,elementIndex,fieldPath};
            renderAll();
            requestAnimationFrame(()=>{
                let control=null;
                const itemMatch=fieldPath.match(/^items\.(\d+)\.([A-Za-z0-9_]+)/);
                if(elementIndex>=0&&itemMatch)control=inspector.querySelector(`[data-layout-item="${Number(itemMatch[1])}"][data-layout-item-field="${itemMatch[2]}"]`);
                else if(elementIndex>=0&&fieldPath){const field=fieldPath.split('.')[0];control=inspector.querySelector(`[data-layout-element-field="${field}"],[data-layout-rich-field="${field}"]`)}
                else if(elementIndex<0&&fieldPath){const field=fieldPath.split('.')[0];control=inspector.querySelector(`[data-layout-row-field="${field}"]`)}
                control?.closest('details')?.setAttribute('open','');
                control?.closest('.simple-field')?.classList.add('is-invalid');
                (control||document.getElementById('simple-validation-error')||document.getElementById('simple-inspector-title'))?.focus?.();
            });
            return true;
        }
        return false;
    }
    function layoutRowIdentity(row, rowIndex) {
        return validLayoutId(row?.id) ? row.id : `row-${rowIndex}`;
    }
    function layoutColumnIdentity(row, column, columnIndex, rowIndex) {
        return validLayoutId(column?.id) ? column.id : `${layoutRowIdentity(row,rowIndex)}:column-${columnIndex}`;
    }
    function layoutElementIdentity(row, column, element, elementIndex, rowIndex, columnIndex) {
        return validLayoutId(element?.id) ? element.id : `${layoutColumnIdentity(row,column,columnIndex,rowIndex)}:element-${elementIndex}`;
    }
    function resolveLayoutSelection(block = current()) {
        const sectionSelection = {kind:'section',row:null,column:null,element:null,rowIndex:-1,columnIndex:-1,elementIndex:-1};
        if (!block || block.type !== 'layout' || state.selectedLayout?.kind === 'section') return sectionSelection;
        const rows = layoutRows(block);
        const rowIndex = rows.findIndex((row,index) => layoutRowIdentity(row,index) === state.selectedLayout.rowId);
        if (rowIndex < 0) return sectionSelection;
        const row = rows[rowIndex];
        if (state.selectedLayout.kind === 'row') return {...sectionSelection,kind:'row',row,rowIndex};
        const columnCount = layoutPresetColumnCount(row.layout);
        const columns = row.columns.slice(0,columnCount);
        const columnIndex = columns.findIndex((column,index) => layoutColumnIdentity(row,column,index,rowIndex) === state.selectedLayout.columnId);
        if (columnIndex < 0) return {...sectionSelection,kind:'row',row,rowIndex};
        const column = columns[columnIndex];
        if (state.selectedLayout.kind === 'column') return {...sectionSelection,kind:'column',row,column,rowIndex,columnIndex};
        const elements = Array.isArray(column.elements) ? column.elements : [];
        const elementIndex = elements.findIndex((element,index) => layoutElementIdentity(row,column,element,index,rowIndex,columnIndex) === state.selectedLayout.elementId);
        if (elementIndex < 0) return {...sectionSelection,kind:'column',row,column,rowIndex,columnIndex};
        return {...sectionSelection,kind:'element',row,column,element:elements[elementIndex],rowIndex,columnIndex,elementIndex};
    }
    function assignLayoutSelection(block, kind = 'section', rowIndex = -1, columnIndex = -1, elementIndex = -1) {
        if (!block || block.type !== 'layout' || kind === 'section') {
            state.selectedLayout = {kind:'section',rowId:null,columnId:null,elementId:null};
            state.layoutOpenElement = null;
            return;
        }
        const rows = layoutRows(block);
        const row = rows[rowIndex];
        if (!row) return assignLayoutSelection(block);
        const rowId = layoutRowIdentity(row,rowIndex);
        if (kind === 'row') {
            state.selectedLayout = {kind,rowId,columnId:null,elementId:null};
            state.layoutOpenElement = null;
            return;
        }
        const column = row.columns?.[columnIndex];
        if (!column) return assignLayoutSelection(block,'row',rowIndex);
        const columnId = layoutColumnIdentity(row,column,columnIndex,rowIndex);
        if (kind === 'column') {
            state.selectedLayout = {kind,rowId,columnId,elementId:null};
            state.layoutOpenElement = null;
            return;
        }
        const element = column.elements?.[elementIndex];
        if (!element) return assignLayoutSelection(block,'column',rowIndex,columnIndex);
        state.selectedLayout = {kind:'element',rowId,columnId,elementId:layoutElementIdentity(row,column,element,elementIndex,rowIndex,columnIndex)};
        state.layoutOpenElement = `${block.uuid}:${rowIndex}:${columnIndex}:${elementIndex}`;
    }
    function currentSelectionKey() {
        if (!state.selected) return '';
        const selection = resolveLayoutSelection();
        return [state.selected,selection.kind,state.selectedLayout?.rowId||'',state.selectedLayout?.columnId||'',state.selectedLayout?.elementId||''].join('|');
    }
    function restoreSelectionFocus(destination) {
        if (!destination) return;
        const key = currentSelectionKey();
        requestAnimationFrame(() => {
            if (destination === 'inspector') {
                document.getElementById('simple-inspector-title')?.focus();
                document.querySelector('.simple-inspector')?.scrollTo({top:0,behavior:'smooth'});
                return;
            }
            if (destination === 'preview') {
                (preview.querySelector('.is-layout-selected') || preview.querySelector(`[data-preview-block="${state.selected}"]`))?.focus?.();
                return;
            }
            [...list.querySelectorAll('[data-navigator-key]')].find(button => button.dataset.navigatorKey === key)?.focus();
        });
    }
    function selectSection(uuid, options = {}) {
        if (!uuid) return;
        const sameSection = uuid === state.selected && state.selectedLayout?.kind === 'section';
        state.selected = uuid; state.heroSlide = 0; assignLayoutSelection(state.blocks.find(block=>block.uuid===uuid));
        state.expandedSections.add(uuid);
        updateSaveState();
        if (!sameSection || options.force) renderAll(); else { renderList(); renderInspector(); renderBreadcrumb(); syncPreviewSelection(); }
        restoreSelectionFocus(options.focus);
    }
    function selectLayoutNode(block, kind, rowIndex, columnIndex = -1, elementIndex = -1, options = {}) {
        if (!block || block.type !== 'layout') return;
        state.selected = block.uuid;
        state.heroSlide = 0;
        state.expandedSections.add(block.uuid);
        assignLayoutSelection(block,kind,rowIndex,columnIndex,elementIndex);
        updateSaveState();
        if (options.preservePreview) {
            renderList(); renderInspector(); renderBreadcrumb(); syncModeUi(); syncPreviewSelection();
        } else renderAll();
        restoreSelectionFocus(options.focus);
    }

    function selectedCardIndex(block, items = []) {
        if (!items.length) return 0;
        const requested = Number(state.cardIndexes[block.uuid] ?? 0);
        return Number.isInteger(requested) ? Math.min(Math.max(requested, 0), items.length - 1) : 0;
    }
    function syncPreviewSelection() {
        const block = current();
        const layoutSelection = resolveLayoutSelection(block);
        preview.querySelectorAll('[data-preview-block]').forEach(section => {
            const isCurrent = section.dataset.previewBlock === state.selected;
            section.classList.toggle('is-selected', isCurrent && (section.dataset.previewType !== 'layout' || layoutSelection.kind === 'section'));
        });
        preview.querySelectorAll('[data-layout-node]').forEach(node => {
            const nodeKind = node.dataset.layoutNode;
            const selected = block?.type === 'layout' && node.closest('[data-preview-block]')?.dataset.previewBlock === state.selected
                && nodeKind === layoutSelection.kind
                && (nodeKind === 'row' ? node.dataset.layoutRowId === state.selectedLayout.rowId
                    : nodeKind === 'column' ? node.dataset.layoutColumnId === state.selectedLayout.columnId
                    : node.dataset.layoutElementId === state.selectedLayout.elementId);
            node.classList.toggle('is-layout-selected', selected);
            if (node.hasAttribute('aria-pressed')) node.setAttribute('aria-pressed',String(selected));
        });
        preview.querySelectorAll('[data-preview-card-index]').forEach(card => {
            const selected = card.closest('[data-preview-block]')?.dataset.previewBlock === state.selected
                && Number(card.dataset.previewCardIndex) === Number(state.cardIndexes[state.selected] ?? 0);
            card.classList.toggle('is-card-selected', selected);
            card.querySelector('[data-preview-card-select]')?.setAttribute('aria-pressed', String(selected));
        });
    }
    function revealSelectedCard(index) {
        requestAnimationFrame(() => {
            const editor = inspector.querySelector(`[data-card-editor-index="${index}"]`);
            if (!editor) return;
            editor.open = true;
            editor.scrollIntoView({
                block: 'nearest',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        });
    }
    function selectPreviewCard(uuid, requestedIndex) {
        const block = state.blocks.find(item => item.uuid === uuid);
        const items = Array.isArray(block?.content?.items) ? block.content.items : [];
        if (!block || block.type !== 'cards' || contentSource(block) !== 'manual' || !items.length) return;
        const index = Math.min(Math.max(Number(requestedIndex) || 0, 0), items.length - 1);
        state.selected = uuid;
        state.cardIndexes[uuid] = index;
        state.heroSlide = 0;
        assignLayoutSelection(block);
        updateSaveState();
        renderList();
        renderInspector();
        renderBreadcrumb();
        syncPreviewSelection();
        revealSelectedCard(index);
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
    const heroSlideKeys = ['eyebrow','heading','body','primary_label','primary_url','secondary_label','secondary_url','report_label','report_url','image','overlay_opacity'];
    function syncHeroFirstSlide(block) {
        if (block?.type !== 'hero') return;
        const first = heroSlides(block)[0];
        heroSlideKeys.forEach(key => {
            block.content[key] = first[key] ?? (key === 'overlay_opacity' ? 64 : '');
        });
    }

    const textField = (key, label, value, options = {}) => {
        const binding = options.slide ? `data-slide-key="${key}"` : `data-content-key="${key}"`;
        const limits = `${options.max ? ` maxlength="${options.max}"` : ''}${options.min !== undefined ? ` min="${options.min}"` : ''}${options.maxValue !== undefined ? ` max="${options.maxValue}"` : ''}${options.step !== undefined ? ` step="${options.step}"` : ''}`;
        return `<label class="simple-field"><span>${escapeHtml(label)}</span>${options.textarea ? `<textarea ${binding}${limits}>${escapeHtml(value)}</textarea>` : `<input ${binding} type="${options.type || 'text'}" value="${escapeHtml(value)}"${limits}>`}${options.help ? `<small>${escapeHtml(options.help)}</small>` : ''}</label>`;
    };
    const selectField = (key, label, value, choices) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-content-key="${key}">${Object.entries(choices).map(([optionValue, optionLabel]) => `<option value="${escapeHtml(optionValue)}" ${String(value) === optionValue ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></label>`;
    function renderSectionPresentationField(block) {
        const value = normalizedSectionPresentation(block.content?.section_presentation);
        const select = selectField('section_presentation', 'Section presentation', value, sectionPresentationChoices).replace('<select ', '<select data-auto-rerender ');
        return `${select}<p class="simple-section-presentation-help">Changes the section’s surrounding surface while keeping its content layout.</p>`;
    }
    const fallbackDesignChoices = Object.freeze({
        spacing: {compact:'Compact',standard:'Standard',spacious:'Spacious'},
        alignment: {left:'Left',center:'Centered'},
        columns: {auto:'Automatic',2:'Two columns',3:'Three columns',4:'Four columns'},
    });
    const configuredDesignChoices = contentOptions.design || contentOptions.design_options || {};
    const designChoices = {
        spacing: configuredDesignChoices.section_spacing || contentOptions.section_spacing_options || fallbackDesignChoices.spacing,
        alignment: configuredDesignChoices.content_alignment || contentOptions.content_alignment_options || fallbackDesignChoices.alignment,
        columns: configuredDesignChoices.column_count || contentOptions.column_count_options || fallbackDesignChoices.columns,
    };
    const layoutGlobalDesignChoices = Object.freeze({
        section_presentation: sectionPresentationChoices,
        section_spacing: designChoices.spacing,
        content_alignment: designChoices.alignment,
        column_count: designChoices.columns,
    });
    const designDefaults = configuredDesignChoices.defaults || {section_spacing:'standard',content_alignment:'left',column_count:'auto'};
    const normalizedDesignValue = (value, choices, fallback) => Object.prototype.hasOwnProperty.call(choices, String(value || fallback)) ? String(value || fallback) : fallback;
    const columnSectionTypes = new Set(Array.isArray(configuredDesignChoices.column_count_types) ? configuredDesignChoices.column_count_types : ['stats','cards','ways_to_give','causes','events','team','partners','gallery']);
    function renderSectionDesignFields(block) {
        const content = block.content || (block.content = {});
        content.section_spacing = normalizedDesignValue(content.section_spacing, designChoices.spacing, designDefaults.section_spacing || 'standard');
        content.content_alignment = normalizedDesignValue(content.content_alignment, designChoices.alignment, designDefaults.content_alignment || 'left');
        content.column_count = normalizedDesignValue(content.column_count, designChoices.columns, designDefaults.column_count || 'auto');
        const columns = columnSectionTypes.has(block.type)
            ? selectField('column_count','Columns',content.column_count,designChoices.columns).replace('<select ','<select data-auto-rerender ')
            : '';
        return `<details class="simple-design-options"><summary>Section design</summary><div style="padding-top:14px">${selectField('section_spacing','Section spacing',content.section_spacing,designChoices.spacing).replace('<select ','<select data-auto-rerender ')}${selectField('content_alignment','Content alignment',content.content_alignment,designChoices.alignment).replace('<select ','<select data-auto-rerender ')}${columns}<p class="simple-section-presentation-help">Safe layout choices automatically adapt for tablets and phones.</p></div></details>`;
    }
    function designClasses(block) {
        const content = block.content || {};
        const spacing = normalizedDesignValue(content.section_spacing, designChoices.spacing, designDefaults.section_spacing || 'standard');
        const alignment = normalizedDesignValue(content.content_alignment, designChoices.alignment, designDefaults.content_alignment || 'left');
        const columns = normalizedDesignValue(content.column_count, designChoices.columns, designDefaults.column_count || 'auto');
        return ` simple-preview-block--${String(block.type).replaceAll('_','-')} simple-preview-block--spacing-${spacing} simple-preview-block--align-${alignment} simple-preview-block--columns-${columns}`;
    }

    const fallbackLayoutPresets = Object.freeze({
        full: {label:'One column',columns:1,widths:[12]},
        halves: {label:'Two equal columns',columns:2,widths:[6,6]},
        thirds: {label:'Three equal columns',columns:3,widths:[4,4,4]},
        quarter: {label:'Four equal columns',columns:4,widths:[3,3,3,3]},
        third_two_thirds: {label:'One third + two thirds',columns:2,widths:[4,8]},
        two_thirds_third: {label:'Two thirds + one third',columns:2,widths:[8,4]},
    });
    const configuredLayoutOptions = contentOptions.layout || {};
    const layoutChoiceMap = (configured, fallback) => Object.fromEntries(Object.entries(fallback).map(([token, fallbackLabel]) => {
        const configuredValue = configured?.[token];
        const label = typeof configuredValue === 'string' ? configuredValue : configuredValue?.label;
        return [token, typeof label === 'string' && label.trim() ? label : fallbackLabel];
    }));
    const layoutPresetDefinitions = Object.fromEntries(Object.entries(fallbackLayoutPresets).map(([token, fallback]) => {
        const configured = configuredLayoutOptions.presets?.[token];
        const configuredColumns = Number(typeof configured === 'object' ? configured.columns : 0);
        return [token, {
            label: typeof configured === 'string' ? configured : (configured?.label || fallback.label),
            columns: Number.isInteger(configuredColumns) && configuredColumns >= 1 && configuredColumns <= 4 ? configuredColumns : fallback.columns,
            widths: Array.isArray(configured?.widths) ? configured.widths : fallback.widths,
        }];
    }));
    const layoutPresetChoices = Object.fromEntries(Object.entries(layoutPresetDefinitions).map(([token, definition]) => [token, definition.label]));
    const layoutWidthChoices = layoutChoiceMap(configuredLayoutOptions.widths, {standard:'Standard page width',wide:'Wide',full:'Full width'});
    const layoutBackgroundChoices = layoutChoiceMap(configuredLayoutOptions.backgrounds, {default:'Default',soft:'Soft neutral',accent:'Accent',dark:'Dark'});
    const layoutSpacingChoices = layoutChoiceMap(configuredLayoutOptions.spacings, {compact:'Compact',standard:'Standard',generous:'Generous'});
    const fallbackLayoutElementCatalog = Object.freeze({
        heading:{type:'heading',label:'Heading',description:'Add a clear heading to organize this part of the page.',category:'text_layout',icon:'fa-header',mode:'static',defaults:{type:'heading',text:'New heading',level:'h2'},fields:{text:{kind:'plain_text',bounds:{max_length:500}},level:{kind:'choice',options:{h2:'Large',h3:'Medium',h4:'Small'}}}},
        rich_text:{type:'rich_text',label:'Formatted text',description:'Add paragraphs, links, emphasis, or a short list.',category:'text_layout',icon:'fa-align-left',mode:'static',defaults:{type:'rich_text',body:'<p>Add your text here.</p>'},fields:{body:{kind:'rich_text',bounds:{max_length:20000}}}},
        button:{type:'button',label:'Button',description:'Help visitors move to another page or approved web address.',category:'text_layout',icon:'fa-link',mode:'static',defaults:{type:'button',label:'Learn more',url:'',style:'primary'},fields:{label:{kind:'plain_text',bounds:{max_length:120}},url:{kind:'safe_link'},style:{kind:'choice',options:{primary:'Primary',secondary:'Secondary',text:'Text link'}}}},
        icon:{type:'icon',label:'Icon',description:'Add a simple approved symbol without uploading an image.',category:'text_layout',icon:'fa-heart-o',mode:'static',defaults:{type:'icon',icon:'heart',accessible_label:'',decorative:true,size:'medium',style:'soft'},fields:{}},
        divider:{type:'divider',label:'Divider',description:'Separate nearby content with a subtle horizontal line.',category:'text_layout',icon:'fa-minus',mode:'static',defaults:{type:'divider'},fields:{}},
        spacer:{type:'spacer',label:'Space',description:'Add controlled breathing room between nearby items.',category:'text_layout',icon:'fa-arrows-v',mode:'static',defaults:{type:'spacer',size:'medium'},fields:{}},
        callout:{type:'callout',label:'Highlighted message',description:'Draw attention to a short message and optional action.',category:'text_layout',icon:'fa-info-circle',mode:'static',defaults:{type:'callout',eyebrow:'',heading:'Important information',body:'',icon:'',tone:'accent',link_label:'',url:''},fields:{}},
        image:{type:'image',label:'Image',description:'Choose an approved image from the Media Library.',category:'media_files',icon:'fa-picture-o',mode:'static',defaults:{type:'image',path:'',alt:'',caption:''},fields:{}},
        video:{type:'video',label:'Video',description:'Choose an uploaded video or add a secure YouTube link.',category:'media_files',icon:'fa-play-circle',mode:'static',defaults:{type:'video',source_type:'upload',source:'',title:''},fields:{}},
        file:{type:'file',label:'File download',description:'Offer an approved public document from the Media Library.',category:'media_files',icon:'fa-file-o',mode:'static',defaults:{type:'file',path:'',label:'Download file',description:'',open_in_new_tab:false},fields:{}},
        gallery:{type:'gallery',label:'Photo gallery',description:'Show a small collection of approved images.',category:'media_files',icon:'fa-th',mode:'static',defaults:{type:'gallery',items:[],columns:'3',lightbox:true},fields:{}},
        card:{type:'card',label:'Card',description:'Highlight one topic with an image or icon and an optional link.',category:'highlights',icon:'fa-id-card-o',mode:'static',defaults:{type:'card',eyebrow:'',heading:'New card',body:'',image:'',image_alt:'',icon:'',link_label:'Learn more',url:'',style:'standard'},fields:{}},
        stat:{type:'stat',label:'Impact number',description:'Emphasize one important number and explain what it means.',category:'highlights',icon:'fa-bar-chart',mode:'static',defaults:{type:'stat',value:'0',label:'People reached',icon:'',emphasis:'standard'},fields:{}},
        quote:{type:'quote',label:'Quotation',description:'Highlight a quotation with an optional name and photograph.',category:'highlights',icon:'fa-quote-left',mode:'static',defaults:{type:'quote',quote:'Add a meaningful quotation.',attribution:'',role:'',image:'',image_alt:'',style:'standard'},fields:{}},
        accordion:{type:'accordion',label:'Questions and answers',description:'Add expandable answers to common questions.',category:'highlights',icon:'fa-question-circle',mode:'static',defaults:{type:'accordion',items:[{id:null,question:'Add a question',answer:'<p>Add the answer here.</p>'}],allow_one_open:false},fields:{}},
        timeline:{type:'timeline',label:'Timeline or steps',description:'Explain milestones, history, or a step-by-step process.',category:'highlights',icon:'fa-list-ol',mode:'static',defaults:{type:'timeline',items:[{id:null,date_label:'Step 1',heading:'First step',body:'',icon:''}],style:'timeline'},fields:{}},
    });
    const configuredElementCatalogGroups = Object.entries(configuredLayoutOptions.element_catalog || {}).map(([category,group]) => ({
        category,
        label:String(group?.label || category),
        description:String(group?.description || ''),
        icon:String(group?.icon || 'fa-square-o'),
        elements:(Array.isArray(group?.elements) ? group.elements : []).filter(element => element?.mode === 'static'),
    })).filter(group => group.elements.length);
    const fallbackElementGroups = [
        {category:'text_layout',label:'Text and layout',description:'Words, actions, icons, separators, and spacing.',icon:'fa-align-left'},
        {category:'media_files',label:'Media and files',description:'Images, video, galleries, and approved downloads.',icon:'fa-picture-o'},
        {category:'highlights',label:'Highlights',description:'Cards, important numbers, quotations, answers, and steps.',icon:'fa-star'},
    ].map(group => ({...group,elements:Object.values(fallbackLayoutElementCatalog).filter(element=>element.category===group.category)}));
    const layoutElementCatalogGroups = configuredElementCatalogGroups.length ? configuredElementCatalogGroups : fallbackElementGroups;
    const layoutElementCatalog = Object.freeze(Object.fromEntries(layoutElementCatalogGroups.flatMap(group=>group.elements).filter(element=>element?.mode==='static').map(element=>[String(element.type),element])));
    const isStaticLayoutElementType = type => Object.prototype.hasOwnProperty.call(layoutElementCatalog,String(type||''));
    const layoutElementChoices = Object.freeze(Object.fromEntries(Object.entries(layoutElementCatalog).map(([type,definition])=>[type,String(definition.label||type)])));
    const configuredLayoutLimit = (key,fallback) => Math.max(1,Math.floor(Number(configuredLayoutOptions.limits?.[key])||fallback));
    const layoutStorageLimits = Object.freeze({
        payloadBytes:configuredLayoutLimit('payload_bytes',524288),
        rows:configuredLayoutLimit('rows',12),
        columns:configuredLayoutLimit('columns',4),
        elementsPerColumn:configuredLayoutLimit('elements_per_column',12),
    });
    const layoutManifestChoices = (type,field,fallback) => {
        const choices = layoutElementCatalog[type]?.fields?.[field]?.options;
        return choices && typeof choices === 'object' && !Array.isArray(choices) ? choices : fallback;
    };
    const layoutManifestRepeaterChoices = (type,repeater,field,fallback) => {
        const choices = layoutElementCatalog[type]?.fields?.[repeater]?.item_fields?.[field]?.options;
        return choices && typeof choices === 'object' && !Array.isArray(choices) ? choices : fallback;
    };
    const optionalLayoutIconFallback = Object.freeze({'':'No icon',people:'People',map:'Location',heart:'Care and support',school:'Education',health:'Health',water:'Water',leaf:'Environment',relief:'Emergency relief',child:'Children',report:'Report',financials:'Finance',security:'Safeguarding',policy:'Policy'});
    const requiredLayoutIconFallback = Object.freeze(Object.fromEntries(Object.entries(optionalLayoutIconFallback).filter(([value])=>value!=='')));
    const layoutHeadingChoices = layoutManifestChoices('heading','level',{h2:'Large',h3:'Medium',h4:'Small'});
    const layoutVideoSourceChoices = layoutManifestChoices('video','source_type',{upload:'Uploaded video',youtube:'YouTube'});
    const layoutButtonStyleChoices = layoutManifestChoices('button','style',{primary:'Primary',secondary:'Secondary',text:'Text link'});
    const layoutSpacerSizeChoices = layoutManifestChoices('spacer','size',{small:'Small',medium:'Medium',large:'Large'});
    const layoutIconChoices = layoutManifestChoices('icon','icon',requiredLayoutIconFallback);
    const layoutCardIconChoices = layoutManifestChoices('card','icon',optionalLayoutIconFallback);
    const layoutStatIconChoices = layoutManifestChoices('stat','icon',optionalLayoutIconFallback);
    const layoutCalloutIconChoices = layoutManifestChoices('callout','icon',optionalLayoutIconFallback);
    const layoutTimelineIconChoices = layoutManifestRepeaterChoices('timeline','items','icon',optionalLayoutIconFallback);
    const layoutIconSizeChoices = layoutManifestChoices('icon','size',{small:'Small',medium:'Medium',large:'Large'});
    const layoutIconStyleChoices = layoutManifestChoices('icon','style',{plain:'Plain',soft:'Soft background',circle:'Circle'});
    const layoutCalloutToneChoices = layoutManifestChoices('callout','tone',{neutral:'Neutral',accent:'Accent',success:'Positive',warning:'Important'});
    const layoutCardStyleChoices = layoutManifestChoices('card','style',{standard:'Standard',soft:'Soft',contrast:'Dark contrast'});
    const layoutStatEmphasisChoices = layoutManifestChoices('stat','emphasis',{standard:'Standard',accent:'Accent',contrast:'Dark contrast'});
    const layoutQuoteStyleChoices = layoutManifestChoices('quote','style',{standard:'Standard',featured:'Featured',compact:'Compact'});
    const layoutGalleryColumnChoices = layoutManifestChoices('gallery','columns',{'2':'Two','3':'Three','4':'Four'});
    const layoutTimelineStyleChoices = layoutManifestChoices('timeline','style',{timeline:'Timeline',steps:'Numbered steps',compact:'Compact list'});
    const layoutElementIcons = Object.freeze(Object.fromEntries(Object.entries(layoutElementCatalog).map(([type,definition])=>[type,String(definition.icon||'fa-square-o')])));
    const layoutChoice = (value, choices, fallback) => Object.prototype.hasOwnProperty.call(choices, String(value || '')) ? String(value) : fallback;
    const layoutPresetColumnCount = preset => layoutPresetDefinitions[layoutChoice(preset, layoutPresetChoices, 'full')].columns;
    const validLayoutId = value => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
    const layoutLoadGuards = new WeakMap();
    const isLayoutObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);
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
    const layoutElementType = element => isStaticLayoutElementType(element?.type) ? String(element.type) : 'heading';
    const layoutElementName = element => {
        const type = layoutElementType(element);
        const firstItem = Array.isArray(element?.items) ? element.items[0] : null;
        const copy = {
            heading:element?.text,
            rich_text:plainText(element?.body),
            button:element?.label,
            icon:element?.accessible_label || layoutIconChoices[element?.icon],
            image:element?.caption || element?.alt,
            video:element?.title,
            file:element?.label,
            card:element?.heading,
            stat:[element?.value,element?.label].filter(Boolean).join(' '),
            quote:element?.quote,
            gallery:Array.isArray(element?.items) ? `${element.items.length} ${element.items.length===1?'photo':'photos'}` : '',
            accordion:firstItem?.question,
            timeline:firstItem?.heading || firstItem?.date_label,
            callout:element?.heading,
        }[type] || '';
        return copy ? `${layoutElementChoices[type]} · ${String(copy).slice(0, 34)}` : layoutElementChoices[type];
    };
    const layoutRepeaterDefinitions = type => Object.entries(layoutElementCatalog[type]?.fields || {}).filter(([,field])=>field?.kind==='repeater');
    function layoutStoredFieldShapeIssue(value, definition, location) {
        if ((value === null || value === undefined) && !definition?.required) return null;
        const kind = String(definition?.kind || 'plain_text');
        if (kind === 'repeater') return Array.isArray(value) ? null : `${location} has a damaged saved list.`;
        if (kind === 'boolean') return typeof value === 'boolean' ? null : `${location} has a damaged saved value.`;
        if (kind === 'integer') return Number.isInteger(value) ? null : `${location} has a damaged saved number.`;
        if (kind === 'uuid') return validLayoutId(value) ? null : `${location} has a damaged stable identity.`;
        if (typeof value !== 'string') return `${location} has a damaged saved value.`;
        if (['choice','approved_icon'].includes(kind) && !Object.prototype.hasOwnProperty.call(definition?.options || {},value)) {
            return `${location} uses a choice this editor does not support.`;
        }
        return null;
    }
    function layoutSerializedByteLength(value) {
        let serialized;
        try {
            serialized = JSON.stringify(value);
            if (typeof serialized !== 'string') return Number.POSITIVE_INFINITY;
            serialized = serialized.replace(/\u2028/g,'\\u2028').replace(/\u2029/g,'\\u2029');
            if (typeof TextEncoder === 'function') return new TextEncoder().encode(serialized).length;
            return encodeURIComponent(serialized).replace(/%[0-9a-f]{2}|./gi,'x').length;
        } catch (error) {
            return Number.POSITIVE_INFINITY;
        }
    }
    function layoutStoredBoundsIssue(value) {
        if (!isLayoutObject(value)) return null;
        if (layoutSerializedByteLength(value)>layoutStorageLimits.payloadBytes) return 'This visual layout is too large for this editor to save safely.';
        if (!Array.isArray(value.rows)) return null;
        if (value.rows.length>layoutStorageLimits.rows) return `This visual layout contains more than ${layoutStorageLimits.rows} rows.`;
        for (let rowIndex=0; rowIndex<value.rows.length; rowIndex+=1) {
            const columns=value.rows[rowIndex]?.columns;
            if (!Array.isArray(columns)) continue;
            if (columns.length>layoutStorageLimits.columns) return `Row ${rowIndex+1} contains more than ${layoutStorageLimits.columns} columns.`;
            for (let columnIndex=0; columnIndex<columns.length; columnIndex+=1) {
                const elements=columns[columnIndex]?.elements;
                if (!Array.isArray(elements)) continue;
                const location=`Row ${rowIndex+1}, column ${columnIndex+1}`;
                if (elements.length>layoutStorageLimits.elementsPerColumn) return `${location} contains more content items than this editor can safely handle.`;
                const typeCounts={};
                for (let elementIndex=0; elementIndex<elements.length; elementIndex+=1) {
                    const element=elements[elementIndex];
                    if (!isLayoutObject(element) || !isStaticLayoutElementType(element.type)) continue;
                    const definition=layoutElementCatalog[element.type];
                    const type=String(element.type);
                    typeCounts[type]=(typeCounts[type]||0)+1;
                    const instanceLimit=Math.max(1,Math.min(layoutStorageLimits.elementsPerColumn,Math.floor(Number(definition?.safe_bounds?.max_instances_per_column)||layoutStorageLimits.elementsPerColumn)));
                    if (typeCounts[type]>instanceLimit) return `${location} contains more than ${instanceLimit} ${String(definition?.label||type)} items.`;
                    const byteLimit=Math.max(1,Math.floor(Number(definition?.safe_bounds?.max_serialized_bytes)||65536));
                    if (layoutSerializedByteLength(element)>byteLimit) return `${location}, content item ${elementIndex+1} is too large for this editor to save safely.`;
                }
            }
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
    function inspectStoredLayout(content) {
        const blocked = detail => ({blocked:true,detail,missingIdentities:[],usedIds:{},legacy:false});
        if (!isLayoutObject(content)) return blocked('The saved visual layout is not in a usable format.');
        const hasVersion = Object.prototype.hasOwnProperty.call(content,'schema_version');
        const version = hasVersion ? content.schema_version : null;
        if (hasVersion && version !== 1 && version !== 2) return blocked('This visual layout was created by an unsupported editor version.');
        const legacy = !hasVersion || version === 1;
        if (!Array.isArray(content.rows)) return blocked('The saved rows list is damaged or missing.');
        const boundsIssue=layoutStoredBoundsIssue(content);
        if (boundsIssue) return blocked(boundsIssue);
        const allowedContentKeys = new Set(['schema_version','rows','section_presentation','section_spacing','content_alignment','column_count']);
        if (Object.keys(content).some(key=>!allowedContentKeys.has(key))) return blocked('The visual layout contains a setting this editor does not support.');
        const designIssue=layoutStoredGlobalDesignIssue(content);
        if (designIssue) return blocked(designIssue);
        const usedIds = {row:new Set(),column:new Set(),element:new Set(),repeater:new Set()};
        const missingIdentities = [];
        const identityIssue = (owner,key,location,kind) => {
            const value = owner[key];
            if (value === undefined || value === null || value === '') {
                if (!legacy) return `${location} lost its stable identity.`;
                missingIdentities.push({owner,key,kind});
                return null;
            }
            if (!validLayoutId(value)) return `${location} has a damaged stable identity.`;
            const normalized = String(value).toLowerCase();
            if (usedIds[kind].has(normalized)) return `${location} repeats an identity already used by another ${kind==='repeater'?'list item':kind}.`;
            usedIds[kind].add(normalized);
            return null;
        };
        for (let rowIndex=0; rowIndex<content.rows.length; rowIndex+=1) {
            const row = content.rows[rowIndex];
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
            if (row.columns.length !== layoutPresetColumnCount(row.layout)) return blocked(`${rowLocation} does not have the number of columns required by its saved design.`);
            for (let columnIndex=0; columnIndex<row.columns.length; columnIndex+=1) {
                const column = row.columns[columnIndex];
                const columnLocation = `${rowLocation}, column ${columnIndex+1}`;
                if (!isLayoutObject(column)) return blocked(`${columnLocation} is not stored in a usable format.`);
                if (Object.keys(column).some(key=>!['id','elements'].includes(key))) return blocked(`${columnLocation} contains a setting this editor does not support.`);
                const columnIdIssue = identityIssue(column,'id',columnLocation,'column');
                if (columnIdIssue) return blocked(columnIdIssue);
                if (!Array.isArray(column.elements)) return blocked(`${columnLocation} has a damaged or missing content list.`);
                if (column.elements.length>12) return blocked(`${columnLocation} contains more content items than this editor can safely handle.`);
                for (let elementIndex=0; elementIndex<column.elements.length; elementIndex+=1) {
                    const element = column.elements[elementIndex];
                    const elementLocation = `${columnLocation}, content item ${elementIndex+1}`;
                    if (!isLayoutObject(element)) return blocked(`${elementLocation} is not stored in a usable format.`);
                    if (!isStaticLayoutElementType(element.type)) return blocked(`${elementLocation} uses a content type this editor does not support.`);
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
                        const identity = String(fieldDefinition?.item_identity || 'id');
                        for (let itemIndex=0; itemIndex<items.length; itemIndex+=1) {
                            const item = items[itemIndex];
                            const itemLocation = `${fieldLocation}, item ${itemIndex+1}`;
                            if (!isLayoutObject(item)) return blocked(`${itemLocation} is not stored in a usable format.`);
                            if (Object.keys(item).some(key=>!allowedItemFields.has(key))) return blocked(`${itemLocation} contains a setting this editor does not support.`);
                            const itemIdIssue = identityIssue(item,identity,itemLocation,'repeater');
                            if (itemIdIssue) return blocked(itemIdIssue);
                            for (const [itemField,itemDefinition] of Object.entries(itemFields)) {
                                if (itemField===identity && legacy && !Object.prototype.hasOwnProperty.call(item,itemField)) continue;
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
    function layoutLoadGuard(block) {
        if (!block || block.type!=='layout') return {blocked:false,detail:'',legacy:false};
        const content = block.content;
        if (isLayoutObject(content) && layoutLoadGuards.has(content)) return layoutLoadGuards.get(content);
        const guard = inspectStoredLayout(content);
        if (!guard.blocked && guard.legacy) {
            guard.missingIdentities.forEach(({owner,key,kind})=>{
                let identity = newLayoutId();
                while (guard.usedIds[kind].has(identity.toLowerCase())) identity = newLayoutId();
                owner[key]=identity;
                guard.usedIds[kind].add(identity.toLowerCase());
            });
            content.schema_version=2;
            guard.upgraded=true;
        }
        if (isLayoutObject(content)) layoutLoadGuards.set(content,guard);
        return guard;
    }
    const layoutIsBlocked = block => block?.type==='layout' && layoutLoadGuard(block).blocked;
    function layoutRepairNotice(guard, compact=false) {
        return `<div class="simple-validation-error simple-layout-load-guard" data-layout-load-guard role="alert"><strong>This visual layout is locked to protect its content</strong><span>${escapeHtml(guard?.detail||'Its saved structure needs repair.')}</span><p>Nothing was changed. Ask a website administrator to repair this section, then reload this page. ${compact?'You can safely work on other sections.':'Editing and saving this section stay unavailable until it is repaired.'}</p></div>`;
    }
    function ensureLayoutElementIds(element, regenerate = false) {
        if (!element || !isStaticLayoutElementType(element.type)) return element;
        if (regenerate || !validLayoutId(element.id)) element.id = newLayoutId();
        let repeaters = layoutRepeaterDefinitions(element.type);
        if (!repeaters.length && ['gallery','accordion','timeline'].includes(element.type)) repeaters = [['items',{item_identity:'id'}]];
        repeaters.forEach(([field,definition]) => {
            // Never replace malformed saved content merely because the editor rendered it.
            // The inspector below explains the repair instead, and server validation keeps
            // an unrelated save from silently discarding the original value.
            if (!Array.isArray(element[field])) return;
            const identity = String(definition?.item_identity || 'id');
            element[field].forEach(item => {
                if (!item || typeof item !== 'object' || Array.isArray(item)) return;
                if (regenerate || !validLayoutId(item[identity])) item[identity] = newLayoutId();
            });
        });
        return element;
    }
    function layoutRows(block) {
        const guard = layoutLoadGuard(block);
        return guard.blocked ? [] : block.content.rows;
    }
    const newLayoutRow = () => ({id:newLayoutId(),layout:'full',width:'standard',background:'default',spacing:'standard',columns:[{id:newLayoutId(),elements:[]} ]});
    function newLayoutElement(type) {
        const normalized = isStaticLayoutElementType(type) ? String(type) : 'heading';
        const defaults = clone(layoutElementCatalog[normalized]?.defaults || fallbackLayoutElementCatalog[normalized]?.defaults || {type:normalized});
        defaults.type = normalized;
        defaults.id = newLayoutId();
        return ensureLayoutElementIds(defaults);
    }
    function newLayoutRepeaterItem(element, field = 'items') {
        const definition = layoutElementCatalog[element?.type]?.fields?.[field];
        const itemFields = definition?.item_fields || {};
        const item = Object.fromEntries(Object.entries(itemFields).map(([name,itemDefinition])=>[name,clone(itemDefinition?.default ?? '')]));
        if (!Object.keys(item).length) {
            if (element?.type === 'gallery') Object.assign(item,{path:'',alt:'',caption:''});
            else if (element?.type === 'accordion') Object.assign(item,{question:'Add a question',answer:'<p>Add the answer here.</p>'});
            else Object.assign(item,{date_label:`Step ${(element?.items?.length||0)+1}`,heading:'New milestone',body:'',icon:''});
        }
        item[String(definition?.item_identity || 'id')] = newLayoutId();
        return item;
    }
    function duplicateLayoutRow(row) {
        const copy = clone(row);
        copy.id = newLayoutId();
        copy.columns.forEach(column => { column.id = newLayoutId(); column.elements.forEach(element => ensureLayoutElementIds(element,true)); });
        return copy;
    }
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
    function contentManagementLinks(block, source = contentSource(block)) {
        const map = contentOptions.manage_urls || {};
        const entry = map[block.type] ?? map[source];
        const fallbackLabel = `Manage ${typeLabels[block.type] || 'content'}`;
        const links = [];
        if (typeof entry === 'string') links.push({url:entry,label:fallbackLabel,icon:'fa-pencil'});
        else if (entry && typeof entry === 'object') {
            const manageUrl = entry.manage_url || entry.url;
            const addUrl = entry.add_url || entry.create_url;
            if (manageUrl) links.push({url:manageUrl,label:entry.manage_label || entry.label || fallbackLabel,icon:'fa-pencil'});
            if (addUrl) links.push({url:addUrl,label:entry.add_label || `Add ${entry.item_label || 'content'}`,icon:'fa-plus'});
        }
        const guidedCreate = guidedPageCreateLink(block, source);
        if (guidedCreate) links.push(guidedCreate);
        const safeLinks = links.filter(link => safeManageUrl(link.url));
        return safeLinks.length ? `<nav class="simple-manage-links" aria-label="Manage section content">${safeLinks.map(link=>`<a class="simple-manage-link" href="${escapeHtml(link.url)}"><i class="fa ${link.icon}" aria-hidden="true"></i> ${escapeHtml(link.label)}</a>`).join('')}</nav>` : '';
    }
    const cardSelectField = (index, key, label, value, choices) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-card-index="${index}" data-card-key="${key}">${Object.entries(choices).map(([optionValue, optionLabel]) => `<option value="${escapeHtml(optionValue)}" ${String(value || '') === optionValue ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></label>`;
    const imageField = (key, label, value, slide = false) => {
        const id = `simple-image-${slide ? 'slide-' : ''}${key}`;
        return `<div class="simple-field"><label for="${escapeHtml(id)}">${escapeHtml(label)}</label><div class="simple-image-row"><input id="${escapeHtml(id)}" ${slide ? `data-slide-key="${key}"` : `data-content-key="${key}"`} value="${escapeHtml(value)}" placeholder="Choose an image">${permissions.edit ? `<button class="simple-btn" type="button" data-choose-image="${key}" ${slide ? 'data-slide-image="true"' : ''}>Choose</button>` : ''}</div></div>`;
    };
    const videoField = (key, label, value) => {
        const id = `simple-video-${key}`;
        return `<div class="simple-field"><label for="${escapeHtml(id)}">${escapeHtml(label)}</label><div class="simple-image-row"><input id="${escapeHtml(id)}" data-content-key="${key}" maxlength="2048" value="${escapeHtml(value)}" placeholder="Choose an uploaded MP4 or WebM video">${permissions.edit ? `<button class="simple-btn" type="button" data-choose-video="${key}">Choose</button>` : ''}</div></div>`;
    };
    const richField = (key, label, value) => {
        const labelId = `simple-rich-${key}-label`;
        return `<div class="simple-field"><span id="${escapeHtml(labelId)}">${escapeHtml(label)}</span><div class="simple-rich-toolbar" role="toolbar" aria-label="Format ${escapeHtml(label)}"><button type="button" data-format="bold" aria-label="Bold">B</button><button type="button" data-format="italic" aria-label="Italic"><em>I</em></button><button type="button" data-format="insertUnorderedList" aria-label="Bulleted list">•</button><button type="button" data-format="createLink" aria-label="Add link">↗</button></div><div class="simple-rich" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="${escapeHtml(labelId)}" data-rich-key="${key}">${value || ''}</div></div>`;
    };
    function linkField(key, label, value, options = {}) {
        const id = `simple-link-${options.cardIndex ?? 'content'}-${options.slide ? 'slide-' : ''}${key}`;
        const attributes = options.cardIndex !== undefined
            ? `data-card-index="${options.cardIndex}" data-card-key="${key}"`
            : (options.slide ? `data-slide-key="${key}"` : `data-content-key="${key}"`);
        const known = linkTargets.some(target => target.url === value);
        return `<div class="simple-field"><label for="${escapeHtml(id)}">${escapeHtml(label)}</label><div class="simple-link-row"><select data-link-picker="${escapeHtml(id)}" aria-label="Choose ${escapeHtml(label)} from existing pages"><option value="" ${value ? '' : 'selected'}>Choose a page...</option>${linkTargets.map(target=>`<option value="${escapeHtml(target.url)}" ${target.url===value?'selected':''}>${escapeHtml(target.label)}</option>`).join('')}<option value="__custom" ${value&&!known?'selected':''}>Custom web address...</option></select><input id="${escapeHtml(id)}" ${attributes} value="${escapeHtml(value || '')}" placeholder="Or enter /page-name or https://..."></div></div>`;
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
        if (source === 'category') {
            const categorySlug = String(block.content?.category_slug ?? 'our-causes').trim();
            items = items.filter(item => item.category === categorySlug);
        }
        return items;
    }
    function managedPagePreviewItems(block) {
        const content = block.content || {};
        const candidates = availableManagedItems(block, contentSource(block));
        const limit = Math.min(12, Math.max(1, Number(content.limit || 3)));
        if (content.selection_mode === 'manual') {
            const known = new Map(candidates.map(item => [String(item.value), item]));
            return (content.selected_items || [])
                .map(value => known.get(String(value)))
                .filter(Boolean)
                .slice(0, limit);
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
    function testimonialPreview(block) {
        const items = managedPagePreviewItems(block);
        const requested = Math.trunc(Number(state.testimonialIndexes[block.uuid] || 0));
        const index = items.length
            ? Math.min(items.length - 1, Math.max(0, Number.isFinite(requested) ? requested : 0))
            : 0;
        state.testimonialIndexes[block.uuid] = index;
        return { items, index, story: items[index] || null };
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
        const candidatesByValue = new Map(candidates.map(item => [String(item.value),item]));
        const selectedRows = content.selected_items.map((token,index) => {
            const value = String(token);
            const item = candidatesByValue.get(value) || {value,label:'Unavailable item',unavailable:true};
            const id = `managed-selected-${block.uuid}-${index}`;
            return `<div class="simple-managed-option${item.unavailable?' is-unavailable':''}"><input id="${escapeHtml(id)}" type="checkbox" data-managed-toggle value="${escapeHtml(value)}" checked><label for="${escapeHtml(id)}"><strong>${escapeHtml(item.label || 'Managed item')}</strong><small>${escapeHtml(item.unavailable?'This item is no longer published. Remove it or publish it again.':item.body || item.designation || '')}</small></label><span class="simple-managed-move"><button type="button" data-managed-move="up" data-managed-index="${index}" aria-label="Move ${escapeHtml(item.label || 'item')} up" ${index===0?'disabled':''}>↑</button><button type="button" data-managed-move="down" data-managed-index="${index}" aria-label="Move ${escapeHtml(item.label || 'item')} down" ${index===content.selected_items.length-1?'disabled':''}>↓</button></span></div>`;
        }).join('');
        const availableRows = candidates.filter(item => !selected.has(String(item.value))).map((item,index) => {
            const id = `managed-available-${block.uuid}-${index}`;
            return `<div class="simple-managed-option"><input id="${escapeHtml(id)}" type="checkbox" data-managed-toggle value="${escapeHtml(item.value)}"><label for="${escapeHtml(id)}"><strong>${escapeHtml(item.label || 'Managed item')}</strong><small>${escapeHtml(item.body || item.designation || '')}</small></label><span></span></div>`;
        }).join('');
        const selection = content.selection_mode === 'manual'
            ? `<div class="simple-managed-list" role="group" aria-label="Managed items in website order">${selectedRows}${availableRows || (!selectedRows?'<p class="simple-banner-guidance">No published items are available to choose.</p>':'')}</div><p style="color:var(--muted);font-size:11px">Checked items appear first in this exact order. Use the arrow buttons to reorder them.</p>`
            : '';
        const sourceSpecific = source === 'category'
            ? selectField('category_slug','Which category?',content.category_slug || 'our-causes',categories).replace('<select ','<select data-auto-rerender ')
            : source === 'projects'
                ? selectField('tag_slug','Which project group?',content.tag_slug || '',{'':'All published projects',...tags}).replace('<select ','<select data-auto-rerender ')
                : '';
        const linkFields = ['cards','causes','events'].includes(block.type)
            ? textField('item_link_label','Item link text',content.item_link_label || '')
            : '';
        const viewAllFields = ['cards','causes','events','gallery'].includes(block.type)
            ? `${textField('view_all_label','“View all” link text',content.view_all_label || '')}${linkField('view_all_url','“View all” destination',content.view_all_url || '')}`
            : '';
        const presentationField = block.type === 'causes'
            ? `${selectField('presentation','Content layout',content.presentation || 'card_grid',contentOptions.presentations?.causes || {card_grid:'Standard image cards',focus_areas:'Animated focus areas'}).replace('<select ','<select data-auto-rerender ')}<p style="color:var(--muted);font-size:11px">Animated focus areas places this heading in the first tile, reveals each tile with a short stagger, and adds a left-to-right hover effect. Five items fill two complete desktop rows.</p>`
            : '';

        return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true})}${presentationField}${sourceChoiceField(block)}${sourceSpecific}${contentManagementLinks(block,source)}${selectField('sort','Item order',content.sort,contentOptions.sorts || {})}<label class="simple-field"><span>Maximum number of items</span><input data-content-key="limit" type="number" min="1" max="12" value="${Math.min(12,Math.max(1,Number(content.limit || 3)))}"></label>${selectField('selection_mode','How should items be chosen?',content.selection_mode,{automatic:'Keep this section updated automatically',manual:'Choose specific managed items'}).replace('<select ','<select data-auto-rerender ')}${selection}${linkFields}${viewAllFields}${textField('empty_state','Message when there are no published items',content.empty_state || '',{textarea:true,max:300})}<p style="color:var(--muted);font-size:11px">Only published content appears to visitors. Update the individual records in the relevant Content manager.</p>`;
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

        return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true,max:1200})}${selectField('layout','Giving layout',content.layout,{single_cta:'Single CTA',card_grid:'Card grid',banner:'Banner'}).replace('<select ','<select data-giving-rerender ')}${selectField('selection_mode','Giving options',content.selection_mode,{automatic:'All active giving options',manual:'Choose specific options and order'}).replace('<select ','<select data-giving-rerender ')}${chooser}${projectField}${textField('link_label','Button text for managed causes',content.link_label || 'Give now',{max:80})}${textField('empty_state','Message when no option is available',content.empty_state || '',{textarea:true,max:300})}<div class="simple-giving-preview"><strong>Destination preview:</strong> ${escapeHtml(behavior)}</div>${contentManagementLinks(block,'ways_to_give')}<p style="color:var(--muted);font-size:11px">Names, descriptions, images, and destinations come from Donation Causes, the Zakat page, and the Sponsor-a-Child page. No web addresses are entered here.</p>`;
    }
    const canEditBlockContent = block => permissions.edit && (!block?.is_reusable || permissions.editReusable);
    const canEditLayoutBlock = block => canEditBlockContent(block) && block?.type === 'layout' && !layoutIsBlocked(block);
    const blockForEditorRender = block => block?.is_reusable && !permissions.editReusable
        ? {...block, content: JSON.parse(JSON.stringify(block.content || {}))}
        : block;
    const inlineElement = (tag, value, path, label, options = {}) => `<${tag} ${options.className ? `class="${options.className}"` : ''} ${permissions.edit ? `contenteditable="true" role="textbox" aria-label="Edit ${escapeHtml(label)}" spellcheck="true" data-inline-path="${escapeHtml(path)}" data-placeholder="${escapeHtml(options.placeholder || label)}" ${options.single ? 'data-inline-single="true"' : ''}` : ''}>${escapeHtml(value || '')}</${tag}>`;

    function renderHeroEditor(block) {
        const slides = heroSlides(block); state.heroSlide = Math.min(state.heroSlide, slides.length - 1); const slide = slides[state.heroSlide];
        const reorder = slides.length > 1 ? `<div class="simple-hero-reorder" role="group" aria-label="Reorder hero slides"><span class="simple-hero-reorder__copy"><strong>Slide order</strong><small>Change where this slide appears to visitors.</small></span><span class="simple-hero-reorder__actions"><button class="simple-btn" type="button" data-hero-move="earlier" aria-label="Move slide ${state.heroSlide + 1} earlier" ${state.heroSlide === 0 ? 'disabled' : ''}><i class="fa fa-arrow-left" aria-hidden="true"></i> Move earlier</button><button class="simple-btn" type="button" data-hero-move="later" aria-label="Move slide ${state.heroSlide + 1} later" ${state.heroSlide === slides.length - 1 ? 'disabled' : ''}>Move later <i class="fa fa-arrow-right" aria-hidden="true"></i></button></span></div>` : '';
        return `<div class="simple-hero-nav"><strong>Slide ${state.heroSlide + 1} of ${slides.length}</strong><span class="simple-hero-nav__buttons"><button class="simple-btn" type="button" data-hero-nav="previous" aria-label="View previous slide" title="Previous slide" ${state.heroSlide === 0 ? 'disabled' : ''}>←</button><button class="simple-btn" type="button" data-hero-nav="next" aria-label="View next slide" title="Next slide" ${state.heroSlide === slides.length - 1 ? 'disabled' : ''}>→</button></span></div>${reorder}
            ${textField('eyebrow','Small heading',slide.eyebrow,{max:120,slide:true})}${textField('heading','Main heading',slide.heading,{max:180,slide:true})}${textField('body','Description',slide.body,{textarea:true,max:1200,slide:true})}${imageField('image','Background image',slide.image,true)}
            ${textField('primary_label','Main button text',slide.primary_label,{max:80,slide:true})}${linkField('primary_url','Main button destination',slide.primary_url,{slide:true})}${textField('secondary_label','Second button text',slide.secondary_label,{max:80,slide:true})}${linkField('secondary_url','Second button destination',slide.secondary_url,{slide:true})}
            ${textField('report_label','Report link text',slide.report_label || '',{max:120,slide:true})}${linkField('report_url','Report link destination',slide.report_url || '',{slide:true})}${textField('overlay_opacity','Overlay darkness (0–100)',Number(slide.overlay_opacity ?? 64),{type:'number',min:0,maxValue:100,step:1,slide:true,help:'Higher values make light text easier to read over bright images.'})}
            <section class="simple-animation-panel"><h3>Carousel behavior</h3><p>These settings apply when this Hero has more than one slide.</p><label class="simple-check"><input data-content-key="autoplay" type="checkbox" ${block.content?.autoplay !== false?'checked':''}> Rotate slides automatically</label>${selectField('interval','Time on each slide',String(block.content?.interval || 6000),{4000:'4 seconds',6000:'6 seconds',8000:'8 seconds',12000:'12 seconds'})}<label class="simple-check"><input data-content-key="pause_on_hover" type="checkbox" ${block.content?.pause_on_hover !== false?'checked':''}> Pause while a visitor points at the Hero</label></section>
            <div style="display:flex;gap:8px;margin-bottom:14px"><button class="simple-btn" type="button" data-hero-action="add"><i class="fa fa-plus"></i> Add slide</button>${slides.length > 1 ? '<button class="simple-btn simple-btn--danger" type="button" data-hero-action="remove">Remove slide</button>' : ''}</div>`;
    }
    const simpleIconChoices = {'':'No icon',people:'People',map:'Location',heart:'Care and support',school:'Education',health:'Health',water:'Water',leaf:'Environment',relief:'Emergency relief',child:'Children',report:'Report',financials:'Finance',security:'Safeguarding',policy:'Policy'};
    function cardUsesFullRow(block, item, index, items) {
        const explicitlySet = item && Object.prototype.hasOwnProperty.call(item, 'full_width') && item.full_width !== null;
        if (explicitlySet) return item.full_width === true;

        return block.type === 'cards'
            && block.content?.variant === 'about-pillars'
            && index === items.length - 1
            && items.length % 2 === 1;
    }
    function renderStatsEditor(block) {
        const items = Array.isArray(block.content?.items) ? block.content.items : [];
        const animationEnabled = block.content?.animation_enabled !== false;
        const animationType = ['count_up','fade_up','pop'].includes(block.content?.animation_type) ? block.content.animation_type : 'count_up';
        const animationDuration = Math.min(5000,Math.max(300,Number(block.content?.animation_duration || 1600)));
        const animationDelay = Math.min(1000,Math.max(0,Number(block.content?.animation_delay ?? 120)));
        Object.assign(block.content, {animation_enabled:animationEnabled,animation_type:animationType,animation_duration:animationDuration,animation_delay:animationDelay});
        return `${textField('eyebrow','Small heading',block.content?.eyebrow || '')}${textField('heading','Section heading',block.content?.heading || '')}<section class="simple-animation-panel"><h3>Number animation</h3><p>Choose how these figures appear when a visitor reaches this section.</p><label class="simple-check"><input id="simple-stat-animation-enabled" data-content-key="animation_enabled" type="checkbox" ${animationEnabled?'checked':''}> Animate statistics</label><fieldset id="simple-stat-animation-options" ${animationEnabled?'':'disabled'}>${selectField('animation_type','Animation style',animationType,{count_up:'Count up from zero',fade_up:'Fade upward',pop:'Gentle pop'})}<label class="simple-field"><span>Animation speed</span><select data-content-key="animation_duration"><option value="800" ${animationDuration===800?'selected':''}>Fast</option><option value="1600" ${animationDuration===1600?'selected':''}>Normal</option><option value="2600" ${animationDuration===2600?'selected':''}>Slow</option></select></label><label class="simple-field"><span>Delay between each number</span><select data-content-key="animation_delay"><option value="0" ${animationDelay===0?'selected':''}>Together</option><option value="120" ${animationDelay===120?'selected':''}>Short stagger</option><option value="250" ${animationDelay===250?'selected':''}>Long stagger</option></select></label><p>Visitors who prefer reduced motion automatically see the final numbers without animation.</p></fieldset></section><div class="simple-repeat">${items.map((item,index)=>`<div class="simple-repeat-item"><div class="simple-repeat-head"><strong>Statistic ${index+1}</strong><span class="simple-repeat-move"><button type="button" data-stat-move="up" data-stat-index="${index}" aria-label="Move statistic ${index+1} up" ${index===0?'disabled':''}>↑</button><button type="button" data-stat-move="down" data-stat-index="${index}" aria-label="Move statistic ${index+1} down" ${index===items.length-1?'disabled':''}>↓</button><button type="button" data-remove-stat="${index}">Remove</button></span></div><label class="simple-field"><span>Number or value</span><input data-stat-index="${index}" data-stat-key="value" value="${escapeHtml(item.value || '')}"></label><label class="simple-field"><span>Label</span><input data-stat-index="${index}" data-stat-key="label" value="${escapeHtml(item.label || '')}"></label>${cardSelectField(index,'icon','Icon',item.icon || '',simpleIconChoices).replaceAll('data-card-index','data-stat-index').replaceAll('data-card-key','data-stat-key')}</div>`).join('')}</div><button class="simple-btn" type="button" id="add-stat"><i class="fa fa-plus"></i> Add statistic</button>`;
    }
    function renderCardsEditor(block) {
        const items = Array.isArray(block.content?.items) ? block.content.items : [];
        const isContributionList = block.type === 'cards' && block.content?.variant === 'contributions';
        const settings = {
            cards:{item:'card',heading:'Card heading',body:isContributionList?'Checklist (one item per line)':'Description',image:'Image',imageAlt:'Describe the image for screen readers',icon:true,linkLabel:block.content?.variant !== 'initiatives',url:'Destination',fullWidth:true},
            partners:{item:'partner',heading:'Partner name',body:null,image:'Logo',imageAlt:'Logo description for screen readers',url:'Partner website'},
            faq:{item:'question',heading:'Question',body:'Answer',image:null,url:null},
            timeline:{item:'milestone',heading:'Milestone heading',itemEyebrow:'Step label or year',body:'Description',image:null,url:null},
            gallery:{item:'photo',heading:'Photo caption',body:null,image:'Photo',imageAlt:'Describe the photo for screen readers',url:'Optional destination'},
        }[block.type] || {item:'item',heading:'Heading',body:'Description',image:'Image',url:'Destination'};
        const activeCardIndex = selectedCardIndex(block, items);
        return `${textField('eyebrow','Small heading',block.content?.eyebrow || '')}${textField('heading','Section heading',block.content?.heading || '')}${textField('body','Introduction',block.content?.body || '',{textarea:true})}<div class="simple-repeat">${items.map((item,index)=>`<details class="simple-repeat-item${index===activeCardIndex?' is-current-card':''}" data-card-editor-index="${index}" ${index===activeCardIndex?'open':''}><summary><strong>${escapeHtml(item.heading || `${settings.item} ${index+1}`)}</strong>${index===activeCardIndex?'<span class="simple-repeat-current">Editing</span>':''}</summary><div style="padding-top:11px"><div class="simple-repeat-head"><span></span><span class="simple-repeat-move"><button type="button" data-card-move="up" data-card-index="${index}" aria-label="Move ${settings.item} ${index+1} up" ${index===0?'disabled':''}>↑</button><button type="button" data-card-move="down" data-card-index="${index}" aria-label="Move ${settings.item} ${index+1} down" ${index===items.length-1?'disabled':''}>↓</button><button type="button" data-remove-card="${index}">Remove ${settings.item}</button></span></div>${settings.itemEyebrow?`<label class="simple-field"><span>${settings.itemEyebrow}</span><input data-card-index="${index}" data-card-key="eyebrow" value="${escapeHtml(item.eyebrow || '')}"></label>`:''}<label class="simple-field"><span>${settings.heading}</span><input data-card-index="${index}" data-card-key="heading" value="${escapeHtml(item.heading || '')}"></label>${settings.body?`<label class="simple-field"><span>${settings.body}</span><textarea data-card-index="${index}" data-card-key="body">${escapeHtml(item.body || '')}</textarea>${isContributionList?'<small>Press Enter after each checklist item. No JSON or special formatting is needed.</small>':''}</label>`:''}${settings.image ? imageField(`card-image-${index}`,settings.image,item.image || '').replace(`data-content-key="card-image-${index}"`,`data-card-index="${index}" data-card-key="image"`).replace(`data-choose-image="card-image-${index}"`,`data-choose-card-image="${index}"`) : ''}${settings.imageAlt ? `<label class="simple-field"><span>${settings.imageAlt}</span><input data-card-index="${index}" data-card-key="image_alt" maxlength="255" value="${escapeHtml(item.image_alt || item.heading || '')}"><small>Describe useful visual information; do not write “image of”.</small></label>` : ''}${settings.icon ? cardSelectField(index,'icon','Icon shown when there is no image',item.icon || '',simpleIconChoices) : ''}${settings.fullWidth ? `<div class="simple-field"><span>Card width</span><label class="simple-check"><input type="checkbox" data-card-index="${index}" data-card-key="full_width" data-card-boolean ${cardUsesFullRow(block,item,index,items)?'checked':''}> Use the full row</label><small>Stretch this card across every column. The preview updates immediately.</small></div>` : ''}${settings.linkLabel ? `<label class="simple-field"><span>Link text</span><input data-card-index="${index}" data-card-key="link_label" maxlength="120" value="${escapeHtml(item.link_label || '')}" placeholder="Learn more"></label>` : ''}${settings.url ? linkField('url',settings.url,item.url || '',{cardIndex:index}) : ''}</div></details>`).join('')}</div><button class="simple-btn" type="button" id="add-card"><i class="fa fa-plus"></i> Add ${settings.item}</button>`;
    }
    function renderMediaTextEditor(block) {
        const content = block.content || (block.content = {});
        const mediaType = ['image','video','youtube'].includes(content.media_type) ? content.media_type : 'image';
        const typeField = selectField('media_type','Media type',mediaType,{image:'Image',video:'Uploaded video',youtube:'YouTube video'}).replace('<select ','<select data-media-type-rerender ');
        let mediaFields = '';
        if (mediaType === 'image') {
            mediaFields = `${imageField('image','Image',content.image || '')}${textField('image_alt','Describe the image',content.image_alt || '',{max:255})}`;
        } else if (mediaType === 'video') {
            mediaFields = `${videoField('video_url','Uploaded video',content.video_url || '')}${imageField('poster','Poster image',content.poster || '')}${textField('caption','Video caption',content.caption || '',{max:2000})}`;
        } else {
            mediaFields = `${textField('youtube_url','YouTube video link',content.youtube_url || '',{type:'url',max:2048})}${textField('caption','Video caption',content.caption || '',{max:2000})}`;
        }
        return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${richField('body','Body text',content.body || '')}${typeField}${mediaFields}${selectField('image_position','Media position',content.image_position || 'left',{left:'Left',right:'Right'})}${textField('link_label','Link text',content.link_label || '')}${linkField('link_url','Link destination',content.link_url || '')}`;
    }
    const layoutOptionsMarkup = (choices, selected) => Object.entries(choices).map(([value,label]) => `<option value="${escapeHtml(value)}" ${String(selected)===value?'selected':''}>${escapeHtml(label)}</option>`).join('');
    const layoutRowSelect = (rowIndex, field, label, value, choices, help = '') => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-layout-row="${rowIndex}" data-layout-row-field="${escapeHtml(field)}">${layoutOptionsMarkup(choices,value)}</select>${help?`<small>${escapeHtml(help)}</small>`:''}</label>`;
    const layoutElementInput = (rowIndex, columnIndex, elementIndex, field, label, value, options = {}) => {
        const attributes = `data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="${escapeHtml(field)}"`;
        const limit = options.max ? ` maxlength="${options.max}"` : '';
        return `<label class="simple-field"><span>${escapeHtml(label)}</span>${options.textarea?`<textarea ${attributes}${limit}>${escapeHtml(value||'')}</textarea>`:`<input ${attributes} type="${options.type||'text'}" value="${escapeHtml(value||'')}"${limit}>`}${options.help?`<small>${escapeHtml(options.help)}</small>`:''}</label>`;
    };
    const layoutElementSelect = (rowIndex, columnIndex, elementIndex, field, label, value, choices, rerender = false) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="${escapeHtml(field)}" ${rerender?'data-layout-rerender':''}>${layoutOptionsMarkup(choices,value)}</select></label>`;
    const layoutElementCheckbox = (rowIndex,columnIndex,elementIndex,field,label,value,rerender=false) => `<label class="simple-check"><input type="checkbox" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="${escapeHtml(field)}" ${rerender?'data-layout-rerender':''} ${value===true?'checked':''}> ${escapeHtml(label)}</label>`;
    const layoutFieldMax = (type,field,fallback) => Math.max(1,Number(layoutElementCatalog[type]?.fields?.[field]?.bounds?.max_length)||fallback);
    function layoutElementLinkField(rowIndex, columnIndex, elementIndex, value, label = 'Destination') {
        const id = `simple-layout-link-${rowIndex}-${columnIndex}-${elementIndex}`;
        const known = linkTargets.some(target => target.url === value);
        return `<div class="simple-field"><label for="${escapeHtml(id)}">${escapeHtml(label)}</label><div class="simple-link-row"><select data-link-picker="${escapeHtml(id)}" aria-label="Choose ${escapeHtml(label)} from existing pages"><option value="" ${value?'':'selected'}>Choose a page...</option>${linkTargets.map(target=>`<option value="${escapeHtml(target.url)}" ${target.url===value?'selected':''}>${escapeHtml(target.label)}</option>`).join('')}<option value="__custom" ${value&&!known?'selected':''}>Custom web address...</option></select><input id="${escapeHtml(id)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="url" value="${escapeHtml(value||'')}" maxlength="2048" placeholder="Or enter /page-name or https://..."></div><small>Choose an existing page or enter a secure public address.</small></div>`;
    }
    function layoutElementRichField(rowIndex,columnIndex,elementIndex,field,label,value,max=20000) {
        const editorId = `simple-layout-rich-${field}-${rowIndex}-${columnIndex}-${elementIndex}`;
        return `<div class="simple-field"><span id="${editorId}-label">${escapeHtml(label)}</span><div class="simple-rich-toolbar" role="toolbar" aria-label="Format ${escapeHtml(label)}"><button type="button" data-layout-format="bold" data-layout-rich-editor="${editorId}" aria-label="Bold">B</button><button type="button" data-layout-format="italic" data-layout-rich-editor="${editorId}" aria-label="Italic"><em>I</em></button><button type="button" data-layout-format="insertUnorderedList" data-layout-rich-editor="${editorId}" aria-label="Bulleted list">•</button><button type="button" data-layout-format="createLink" data-layout-rich-editor="${editorId}" aria-label="Add link">↗</button></div><div id="${editorId}" class="simple-rich" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="${editorId}-label" data-layout-rich data-layout-rich-field="${escapeHtml(field)}" data-layout-rich-max="${max}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}">${safeLayoutRichHtml(value||'')}</div></div>`;
    }
    function layoutElementImageField(rowIndex,columnIndex,elementIndex,field,label,value,options={}) {
        const id = `simple-layout-image-${field}-${rowIndex}-${columnIndex}-${elementIndex}`;
        const image = safeImage(value);
        const help = options.help || 'Choose an approved image from the Media Library.';
        const populated = String(value || '').trim() !== '';
        const actions = permissions.edit ? `<span class="simple-image-actions"><button class="simple-btn" type="button" data-layout-choose-image data-layout-image-field="${escapeHtml(field)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}">Choose</button>${options.clearable&&populated?`<button class="simple-btn simple-btn--danger" type="button" data-layout-clear-image data-layout-image-field="${escapeHtml(field)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}"><i class="fa fa-times" aria-hidden="true"></i> Remove image</button>`:''}</span>` : '';
        return `<div class="simple-field"><label for="${id}">${escapeHtml(label)}</label><div class="simple-image-row"><input id="${id}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="${escapeHtml(field)}" maxlength="2048" value="${escapeHtml(value||'')}" placeholder="Choose an image" readonly>${actions}</div>${image?`<div class="simple-layout-media-field__preview"><img src="${escapeHtml(image)}" alt=""><span>${escapeHtml(value)}</span></div>`:''}<small>${escapeHtml(help)}</small></div>`;
    }
    function layoutElementFileField(rowIndex,columnIndex,elementIndex,value) {
        const id = `simple-layout-file-${rowIndex}-${columnIndex}-${elementIndex}`;
        return `<div class="simple-field"><label for="${id}">Public file</label><div class="simple-image-row"><input id="${id}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="path" maxlength="2048" value="${escapeHtml(value||'')}" placeholder="Choose a PDF, Word, or Excel file" readonly>${permissions.edit?`<button class="simple-btn" type="button" data-layout-choose-file data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}">Choose</button>`:''}</div>${value?`<div class="simple-layout-media-field__preview"><i class="fa fa-file-text-o" aria-hidden="true"></i><span>${escapeHtml(value)}</span></div>`:''}<small>Only approved public documents from the Media Library can be used.</small></div>`;
    }
    const layoutItemAttributes = (rowIndex,columnIndex,elementIndex,itemIndex,field) => `data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}" data-layout-item-field="${escapeHtml(field)}"`;
    const layoutItemInput = (rowIndex,columnIndex,elementIndex,itemIndex,field,label,value,options={}) => `<label class="simple-field"><span>${escapeHtml(label)}</span>${options.textarea?`<textarea ${layoutItemAttributes(rowIndex,columnIndex,elementIndex,itemIndex,field)} maxlength="${options.max||2000}">${escapeHtml(value||'')}</textarea>`:`<input ${layoutItemAttributes(rowIndex,columnIndex,elementIndex,itemIndex,field)} value="${escapeHtml(value||'')}" maxlength="${options.max||500}">`}${options.help?`<small>${escapeHtml(options.help)}</small>`:''}</label>`;
    const layoutItemSelect = (rowIndex,columnIndex,elementIndex,itemIndex,field,label,value,choices) => `<label class="simple-field"><span>${escapeHtml(label)}</span><select ${layoutItemAttributes(rowIndex,columnIndex,elementIndex,itemIndex,field)}>${layoutOptionsMarkup(choices,value)}</select></label>`;
    function layoutItemRichField(rowIndex,columnIndex,elementIndex,itemIndex,field,label,value) {
        const editorId = `simple-layout-item-rich-${rowIndex}-${columnIndex}-${elementIndex}-${itemIndex}-${field}`;
        return `<div class="simple-field"><span id="${editorId}-label">${escapeHtml(label)}</span><div class="simple-rich-toolbar" role="toolbar" aria-label="Format ${escapeHtml(label)}"><button type="button" data-layout-format="bold" data-layout-rich-editor="${editorId}" aria-label="Bold">B</button><button type="button" data-layout-format="italic" data-layout-rich-editor="${editorId}" aria-label="Italic"><em>I</em></button><button type="button" data-layout-format="insertUnorderedList" data-layout-rich-editor="${editorId}" aria-label="Bulleted list">•</button></div><div id="${editorId}" class="simple-rich" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="${editorId}-label" data-layout-item-rich data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}" data-layout-item-field="${escapeHtml(field)}">${safeLayoutRichHtml(value||'')}</div></div>`;
    }
    function layoutRepeaterActions(rowIndex,columnIndex,elementIndex,itemIndex,itemCount,itemName) {
        return `<div class="simple-layout-repeater-actions" role="group" aria-label="Arrange ${escapeHtml(itemName)} ${itemIndex+1}"><button type="button" data-layout-item-action="up" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}" aria-label="Move ${escapeHtml(itemName)} ${itemIndex+1} up" ${itemIndex===0?'disabled':''}>↑</button><button type="button" data-layout-item-action="down" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}" aria-label="Move ${escapeHtml(itemName)} ${itemIndex+1} down" ${itemIndex===itemCount-1?'disabled':''}>↓</button><button type="button" data-layout-item-action="remove" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}" aria-label="Remove ${escapeHtml(itemName)} ${itemIndex+1}"><i class="fa fa-trash" aria-hidden="true"></i></button></div>`;
    }
    function renderLayoutGalleryFields(element,rowIndex,columnIndex,elementIndex) {
        if (!Array.isArray(element.items)) return '<div class="simple-validation-error" role="alert"><strong>This saved photo list needs repair.</strong> It has been left unchanged. Ask an administrator to repair it before saving this section.</div>';
        const items = element.items;
        const cards = items.map((item,itemIndex)=>{
            const image = safeImage(item.path);
            const inputId = `simple-layout-gallery-${rowIndex}-${columnIndex}-${elementIndex}-${itemIndex}`;
            return `<details class="simple-layout-repeater-item" ${itemIndex===0?'open':''}><summary>Photo ${itemIndex+1}${item.caption?` · ${escapeHtml(String(item.caption).slice(0,28))}`:''}</summary><div class="simple-layout-repeater-item__body">${layoutRepeaterActions(rowIndex,columnIndex,elementIndex,itemIndex,items.length,'photo')}<div class="simple-field"><label for="${inputId}">Image</label><div class="simple-image-row"><input id="${inputId}" ${layoutItemAttributes(rowIndex,columnIndex,elementIndex,itemIndex,'path')} value="${escapeHtml(item.path||'')}" readonly placeholder="Choose an image">${permissions.edit?`<button class="simple-btn" type="button" data-layout-item-choose-image data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-item="${itemIndex}">Choose</button>`:''}</div>${image?`<div class="simple-layout-media-field__preview"><img src="${escapeHtml(image)}" alt=""><span>${escapeHtml(item.path)}</span></div>`:''}</div>${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'alt','Describe the image',item.alt||'',{max:255,help:'Describe useful visual information; leave empty only for a decorative image.'})}${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'caption','Caption (optional)',item.caption||'',{max:500})}</div></details>`;
        }).join('');
        return `<div class="simple-layout-repeater">${cards||'<p class="simple-layout-empty">No photos yet. Add the first photo below.</p>'}</div><button class="simple-btn simple-layout-add-item" type="button" data-layout-item-add="items" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" ${items.length>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add photo</button><p class="simple-layout-limit">${items.length} of 12 photos used</p><details class="simple-progressive"><summary>Gallery display options</summary><div>${layoutElementSelect(rowIndex,columnIndex,elementIndex,'columns','Photos per row',layoutChoice(element.columns,layoutGalleryColumnChoices,'3'),layoutGalleryColumnChoices)}${layoutElementCheckbox(rowIndex,columnIndex,elementIndex,'lightbox','Open larger photos when visitors select them',element.lightbox!==false)}</div></details>`;
    }
    function renderLayoutAccordionFields(element,rowIndex,columnIndex,elementIndex) {
        if (!Array.isArray(element.items)) return '<div class="simple-validation-error" role="alert"><strong>This saved question list needs repair.</strong> It has been left unchanged. Ask an administrator to repair it before saving this section.</div>';
        const items = element.items;
        const cards = items.map((item,itemIndex)=>`<details class="simple-layout-repeater-item" ${itemIndex===0?'open':''}><summary>Question ${itemIndex+1}${item.question?` · ${escapeHtml(String(item.question).slice(0,28))}`:''}</summary><div class="simple-layout-repeater-item__body">${layoutRepeaterActions(rowIndex,columnIndex,elementIndex,itemIndex,items.length,'question')}${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'question','Question',item.question||'',{max:500})}${layoutItemRichField(rowIndex,columnIndex,elementIndex,itemIndex,'answer','Answer',item.answer||'')}</div></details>`).join('');
        return `<div class="simple-layout-repeater">${cards||'<p class="simple-layout-empty">No questions yet. Add the first question below.</p>'}</div><button class="simple-btn simple-layout-add-item" type="button" data-layout-item-add="items" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" ${items.length>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add question</button><p class="simple-layout-limit">${items.length} of 12 questions used</p>${layoutElementCheckbox(rowIndex,columnIndex,elementIndex,'allow_one_open','Keep only one answer open at a time',element.allow_one_open===true)}`;
    }
    function renderLayoutTimelineFields(element,rowIndex,columnIndex,elementIndex) {
        if (!Array.isArray(element.items)) return '<div class="simple-validation-error" role="alert"><strong>This saved timeline needs repair.</strong> It has been left unchanged. Ask an administrator to repair it before saving this section.</div>';
        const items = element.items;
        const cards = items.map((item,itemIndex)=>`<details class="simple-layout-repeater-item" ${itemIndex===0?'open':''}><summary>${element.style==='steps'?'Step':'Milestone'} ${itemIndex+1}${item.heading?` · ${escapeHtml(String(item.heading).slice(0,28))}`:''}</summary><div class="simple-layout-repeater-item__body">${layoutRepeaterActions(rowIndex,columnIndex,elementIndex,itemIndex,items.length,element.style==='steps'?'step':'milestone')}${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'date_label','Date or step number',item.date_label||'',{max:120})}${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'heading','Heading',item.heading||'',{max:300})}${layoutItemInput(rowIndex,columnIndex,elementIndex,itemIndex,'body','Description',item.body||'',{textarea:true,max:2000})}${layoutItemSelect(rowIndex,columnIndex,elementIndex,itemIndex,'icon','Icon (optional)',item.icon||'',layoutTimelineIconChoices)}</div></details>`).join('');
        return `${layoutElementSelect(rowIndex,columnIndex,elementIndex,'style','Display as',layoutChoice(element.style,layoutTimelineStyleChoices,'timeline'),layoutTimelineStyleChoices,true)}<div class="simple-layout-repeater">${cards||'<p class="simple-layout-empty">No milestones yet. Add the first one below.</p>'}</div><button class="simple-btn simple-layout-add-item" type="button" data-layout-item-add="items" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" ${items.length>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add ${element.style==='steps'?'step':'milestone'}</button><p class="simple-layout-limit">${items.length} of 12 items used</p>`;
    }
    function renderLayoutElementFields(element, rowIndex, columnIndex, elementIndex) {
        const type = layoutElementType(element);
        if (type === 'heading') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'text','Heading text',element.text||'',{max:layoutFieldMax(type,'text',500)})}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'level','Heading size',layoutChoice(element.level,layoutHeadingChoices,'h2'),layoutHeadingChoices)}`;
        }
        if (type === 'rich_text') {
            return layoutElementRichField(rowIndex,columnIndex,elementIndex,'body','Formatted text',element.body||'',layoutFieldMax(type,'body',20000));
        }
        if (type === 'image') {
            return `${layoutElementImageField(rowIndex,columnIndex,elementIndex,'path','Image',element.path||'')}${layoutElementInput(rowIndex,columnIndex,elementIndex,'alt','Describe the image',element.alt||'',{max:255,help:'Describe useful visual information; do not write “image of”.'})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'caption','Caption (optional)',element.caption||'',{max:1000})}`;
        }
        if (type === 'video') {
            const sourceType = layoutChoice(element.source_type,layoutVideoSourceChoices,'upload');
            const sourceId = `simple-layout-video-${rowIndex}-${columnIndex}-${elementIndex}`;
            const sourceLabel = sourceType === 'upload' ? 'Uploaded video' : 'YouTube video link';
            const picker = sourceType === 'upload' && permissions.edit ? `<button class="simple-btn" type="button" data-layout-choose-video data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}">Choose</button>` : '';
            return `${layoutElementSelect(rowIndex,columnIndex,elementIndex,'source_type','Video source',sourceType,layoutVideoSourceChoices,true)}<div class="simple-field"><label for="${sourceId}">${sourceLabel}</label><div class="simple-image-row"><input id="${sourceId}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-field="source" maxlength="2048" value="${escapeHtml(element.source||'')}" placeholder="${sourceType==='upload'?'Choose an uploaded MP4 or WebM video':'https://www.youtube.com/watch?v=...'}" ${sourceType==='upload'?'readonly':''}>${picker}</div>${sourceType==='upload'?'<small>Choose an MP4 or WebM video from the Media Library.</small>':''}</div>${layoutElementInput(rowIndex,columnIndex,elementIndex,'title','Video title',element.title||'',{max:255,help:'A short, useful title for visitors using assistive technology.'})}`;
        }
        if (type === 'button') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'label','Button text',element.label||'',{max:120})}${layoutElementLinkField(rowIndex,columnIndex,elementIndex,element.url||'','Button destination')}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'style','Button style',layoutChoice(element.style,layoutButtonStyleChoices,'primary'),layoutButtonStyleChoices)}`;
        }
        if (type === 'icon') {
            return `${layoutElementSelect(rowIndex,columnIndex,elementIndex,'icon','Symbol',layoutChoice(element.icon,layoutIconChoices,'heart'),layoutIconChoices)}${layoutElementCheckbox(rowIndex,columnIndex,elementIndex,'decorative','This icon is decorative',element.decorative!==false,true)}${element.decorative===false?layoutElementInput(rowIndex,columnIndex,elementIndex,'accessible_label','Meaning for screen readers',element.accessible_label||'',{max:120,help:'Briefly explain what this icon means.'}):''}<details class="simple-progressive"><summary>Icon appearance</summary><div>${layoutElementSelect(rowIndex,columnIndex,elementIndex,'size','Icon size',layoutChoice(element.size,layoutIconSizeChoices,'medium'),layoutIconSizeChoices)}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'style','Icon appearance',layoutChoice(element.style,layoutIconStyleChoices,'soft'),layoutIconStyleChoices)}</div></details>`;
        }
        if (type === 'file') {
            return `${layoutElementFileField(rowIndex,columnIndex,elementIndex,element.path||'')}${layoutElementInput(rowIndex,columnIndex,elementIndex,'label','Download link text',element.label||'',{max:160})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'description','Short description (optional)',element.description||'',{textarea:true,max:500})}${layoutElementCheckbox(rowIndex,columnIndex,elementIndex,'open_in_new_tab','Open the file in a new tab',element.open_in_new_tab===true)}`;
        }
        if (type === 'card') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'heading','Card heading',element.heading||'',{max:300})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'body','Description',element.body||'',{textarea:true,max:2000})}<details class="simple-progressive" open><summary>Image or icon</summary><div>${layoutElementImageField(rowIndex,columnIndex,elementIndex,'image','Card image (optional)',element.image||'',{clearable:true})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'image_alt','Describe the card image',element.image_alt||'',{max:255})}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'icon','Icon when there is no image',layoutChoice(element.icon,layoutCardIconChoices,''),layoutCardIconChoices)}</div></details><details class="simple-progressive"><summary>Optional label, link, and appearance</summary><div>${layoutElementInput(rowIndex,columnIndex,elementIndex,'eyebrow','Small heading',element.eyebrow||'',{max:120})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'link_label','Link text',element.link_label||'',{max:120})}${layoutElementLinkField(rowIndex,columnIndex,elementIndex,element.url||'','Card destination')}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'style','Card appearance',layoutChoice(element.style,layoutCardStyleChoices,'standard'),layoutCardStyleChoices)}</div></details>`;
        }
        if (type === 'stat') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'value','Number or value',element.value||'',{max:40})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'label','What this number means',element.label||'',{max:200})}<details class="simple-progressive"><summary>Icon and appearance</summary><div>${layoutElementSelect(rowIndex,columnIndex,elementIndex,'icon','Icon (optional)',layoutChoice(element.icon,layoutStatIconChoices,''),layoutStatIconChoices)}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'emphasis','Appearance',layoutChoice(element.emphasis,layoutStatEmphasisChoices,'standard'),layoutStatEmphasisChoices)}</div></details>`;
        }
        if (type === 'quote') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'quote','Quotation',element.quote||'',{textarea:true,max:2000})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'attribution','Name (optional)',element.attribution||'',{max:200})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'role','Role or organization (optional)',element.role||'',{max:300})}<details class="simple-progressive"><summary>Photograph and appearance</summary><div>${layoutElementImageField(rowIndex,columnIndex,elementIndex,'image','Photograph (optional)',element.image||'',{clearable:true})}${layoutElementInput(rowIndex,columnIndex,elementIndex,'image_alt','Describe the photograph',element.image_alt||'',{max:255})}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'style','Quotation appearance',layoutChoice(element.style,layoutQuoteStyleChoices,'standard'),layoutQuoteStyleChoices)}</div></details>`;
        }
        if (type === 'gallery') return renderLayoutGalleryFields(element,rowIndex,columnIndex,elementIndex);
        if (type === 'accordion') return renderLayoutAccordionFields(element,rowIndex,columnIndex,elementIndex);
        if (type === 'timeline') return renderLayoutTimelineFields(element,rowIndex,columnIndex,elementIndex);
        if (type === 'callout') {
            return `${layoutElementInput(rowIndex,columnIndex,elementIndex,'heading','Heading',element.heading||'',{max:300})}${layoutElementRichField(rowIndex,columnIndex,elementIndex,'body','Message',element.body||'',5000)}<details class="simple-progressive"><summary>Optional label, icon, link, and appearance</summary><div>${layoutElementInput(rowIndex,columnIndex,elementIndex,'eyebrow','Small heading',element.eyebrow||'',{max:120})}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'icon','Icon (optional)',layoutChoice(element.icon,layoutCalloutIconChoices,''),layoutCalloutIconChoices)}${layoutElementInput(rowIndex,columnIndex,elementIndex,'link_label','Link text',element.link_label||'',{max:120})}${layoutElementLinkField(rowIndex,columnIndex,elementIndex,element.url||'','Link destination')}${layoutElementSelect(rowIndex,columnIndex,elementIndex,'tone','Appearance',layoutChoice(element.tone,layoutCalloutToneChoices,'accent'),layoutCalloutToneChoices)}</div></details>`;
        }
        if (type === 'spacer') {
            return `${layoutElementSelect(rowIndex,columnIndex,elementIndex,'size','Amount of space',layoutChoice(element.size,layoutSpacerSizeChoices,'medium'),layoutSpacerSizeChoices)}<p class="simple-layout-element-note">Space automatically becomes smaller on phones.</p>`;
        }
        return '<p class="simple-layout-element-note">A divider adds a subtle horizontal line between nearby elements.</p>';
    }
    const layoutInspectorSelectAttributes = (kind,rowIndex,columnIndex=-1,elementIndex=-1) => `data-layout-inspector-select="${kind}" data-layout-row="${rowIndex}"${columnIndex>=0?` data-layout-column="${columnIndex}"`:''}${elementIndex>=0?` data-layout-element="${elementIndex}"`:''}`;
    function layoutContextButton(kind, rowIndex, columnIndex, elementIndex, icon, title, detail, selected = false) {
        return `<button type="button" class="simple-layout-context-button${selected?' is-selected':''}" ${layoutInspectorSelectAttributes(kind,rowIndex,columnIndex,elementIndex)}><i class="fa ${icon}" aria-hidden="true"></i><span><strong>${escapeHtml(title)}</strong><small>${escapeHtml(detail)}</small></span></button>`;
    }
    function renderLayoutPresetChooser(rowIndex, selectedPreset) {
        return `<fieldset class="simple-layout-preset-field"><legend>Choose the column layout</legend><div class="simple-layout-presets">${Object.entries(layoutPresetDefinitions).map(([preset,definition])=>{
            const widths = Array.isArray(definition.widths) && definition.widths.length ? definition.widths : Array(definition.columns).fill(1);
            const bars = widths.slice(0,definition.columns).map(width=>`<span style="flex:${Math.max(1,Math.min(12,Number(width)||1))}" aria-hidden="true"></span>`).join('');
            return `<button type="button" class="simple-layout-preset" data-layout-preset="${escapeHtml(preset)}" data-layout-row="${rowIndex}" aria-label="Use ${escapeHtml(definition.label)}" aria-pressed="${preset===selectedPreset}"><span class="simple-layout-preset__bars">${bars}</span><span>${escapeHtml(definition.label)}</span></button>`;
        }).join('')}</div><p class="simple-layout-element-note" style="margin-top:8px">Columns stack automatically on smaller screens.</p><details class="simple-layout-select-fallback"><summary>Choose from a list instead</summary>${layoutRowSelect(rowIndex,'layout','Column layout',selectedPreset,layoutPresetChoices,'Changing the layout keeps every content item whenever space allows.')}</details></fieldset>`;
    }
    function renderLayoutElementEditor(block, element, rowIndex, columnIndex, elementIndex, columnCount, columnElements, options = {}) {
        const type = layoutElementType(element);
        const label = layoutElementName(element);
        const path = `${block.uuid}:${rowIndex}:${columnIndex}:${elementIndex}`;
        const open = options.forceOpen || state.layoutOpenElement === path || (state.layoutOpenElement === null && rowIndex === 0 && columnIndex === 0 && elementIndex === 0);
        const targetLeft = columnIndex > 0 ? block.content.rows[rowIndex].columns[columnIndex-1] : null;
        const targetRight = columnIndex < columnCount-1 ? block.content.rows[rowIndex].columns[columnIndex+1] : null;
        const targetLeftBlocked = !targetLeft || !layoutColumnCanAccept(targetLeft,type);
        const targetRightBlocked = !targetRight || !layoutColumnCanAccept(targetRight,type);
        const duplicateDisabled = !layoutColumnCanAccept({elements:columnElements},type);
        const actions = options.contentOnly ? '' : `<span class="simple-layout-element-actions" role="group" aria-label="Arrange ${escapeHtml(layoutElementChoices[type])}">
                <button type="button" data-layout-element-action="up" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(layoutElementChoices[type])} up" title="Move up" ${elementIndex===0?'disabled':''}>↑</button>
                <button type="button" data-layout-element-action="down" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(layoutElementChoices[type])} down" title="Move down" ${elementIndex===columnElements.length-1?'disabled':''}>↓</button>
                <button type="button" data-layout-element-action="left" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(layoutElementChoices[type])} to the previous column" title="Move to previous column" ${targetLeftBlocked?'disabled':''}>←</button>
                <button type="button" data-layout-element-action="right" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Move ${escapeHtml(layoutElementChoices[type])} to the next column" title="Move to next column" ${targetRightBlocked?'disabled':''}>→</button>
                <button type="button" data-layout-element-action="duplicate" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Duplicate ${escapeHtml(layoutElementChoices[type])}" title="Duplicate" ${duplicateDisabled?'disabled':''}><i class="fa fa-copy" aria-hidden="true"></i></button>
                <button type="button" data-layout-element-action="remove" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-label="Delete ${escapeHtml(layoutElementChoices[type])}" title="Delete"><i class="fa fa-trash" aria-hidden="true"></i></button>
            </span>`;
        return `<article class="simple-layout-element-editor" aria-label="${escapeHtml(label)}">
            <header class="simple-layout-element-header"><span class="simple-layout-element-title"><i class="fa ${layoutElementIcons[type]||'fa-square-o'}" aria-hidden="true"></i><span>${escapeHtml(label)}</span></span>${actions}</header>
            <details class="simple-layout-element-details" data-layout-element-details="${path}" ${open?'open':''}><summary>Edit content</summary><div class="simple-layout-element-body">${renderLayoutElementFields(element,rowIndex,columnIndex,elementIndex)}</div></details>
        </article>`;
    }
    function renderLayoutRowEditor(block, row, rowIndex, rows) {
        const preset = layoutChoice(row.layout, layoutPresetChoices, 'full');
        const columnCount = layoutPresetColumnCount(preset);
        const columns = row.columns.slice(0,columnCount);
        return `<section class="simple-layout-row-editor" aria-labelledby="simple-layout-row-${rowIndex}">
            <header class="simple-layout-row-header"><span class="simple-layout-row-title"><strong id="simple-layout-row-${rowIndex}">Row ${rowIndex+1}</strong><small>${escapeHtml(layoutPresetChoices[preset])} · ${columnCount} ${columnCount===1?'column':'columns'}</small></span><span class="simple-layout-row-actions" role="group" aria-label="Arrange row ${rowIndex+1}">
                <button type="button" data-layout-row-action="up" data-layout-row="${rowIndex}" aria-label="Move row ${rowIndex+1} up" title="Move row up" ${rowIndex===0?'disabled':''}>↑</button>
                <button type="button" data-layout-row-action="down" data-layout-row="${rowIndex}" aria-label="Move row ${rowIndex+1} down" title="Move row down" ${rowIndex===rows.length-1?'disabled':''}>↓</button>
                <button type="button" data-layout-row-action="duplicate" data-layout-row="${rowIndex}" aria-label="Duplicate row ${rowIndex+1}" title="Duplicate row" ${rows.length>=12?'disabled':''}><i class="fa fa-copy" aria-hidden="true"></i></button>
                <button type="button" data-layout-row-action="remove" data-layout-row="${rowIndex}" aria-label="Delete row ${rowIndex+1}" title="Delete row"><i class="fa fa-trash" aria-hidden="true"></i></button>
            </span></header>
            <div class="simple-layout-row-body">${renderLayoutPresetChooser(rowIndex,preset)}<div class="simple-layout-row-settings">${layoutRowSelect(rowIndex,'width','Content width',layoutChoice(row.width,layoutWidthChoices,'standard'),layoutWidthChoices)}${layoutRowSelect(rowIndex,'background','Background',layoutChoice(row.background,layoutBackgroundChoices,'default'),layoutBackgroundChoices)}${layoutRowSelect(rowIndex,'spacing','Space inside row',layoutChoice(row.spacing,layoutSpacingChoices,'standard'),layoutSpacingChoices)}</div>
                <div class="simple-layout-columns-editor">${columns.map((column,columnIndex)=>{
                    const elements = Array.isArray(column.elements) ? column.elements : (column.elements=[]);
                    return `<section class="simple-layout-column-editor" aria-labelledby="simple-layout-column-${rowIndex}-${columnIndex}"><header class="simple-layout-column-head"><strong id="simple-layout-column-${rowIndex}-${columnIndex}">Column ${columnIndex+1}</strong><small>${elements.length} of 12 content items</small></header>${elements.length?elements.map((element,elementIndex)=>renderLayoutElementEditor(block,element,rowIndex,columnIndex,elementIndex,columnCount,elements)).join(''):'<p class="simple-layout-empty">This column is empty. Add its first content item below.</p>'}${renderLayoutAddToColumn(rowIndex,columnIndex,elements.length)}</section>`;
                }).join('')}</div>
            </div>
        </section>`;
    }
    function renderLayoutAddToColumn(rowIndex, columnIndex, elementCount) {
        return `<div class="simple-layout-add"><button class="simple-btn" type="button" data-layout-open-picker data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" aria-label="Choose content for row ${rowIndex+1}, column ${columnIndex+1}" ${elementCount>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add content</button></div>`;
    }
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
    function elementPickerContext() {
        const target = state.elementPickerTarget;
        const block = target ? state.blocks.find(item=>item.uuid===target.blockUuid) : null;
        const rows = block ? layoutRows(block) : [];
        const column = rows[target?.row]?.columns?.[target?.column];
        return {target,block,rows,column};
    }
    function renderElementPicker() {
        const picker = document.getElementById('simple-element-picker');
        const fallback = document.getElementById('simple-element-picker-select');
        const location = document.getElementById('element-picker-location');
        if (!picker || !fallback) return;
        const {target,column} = elementPickerContext();
        const elements = Array.isArray(column?.elements) ? column.elements : [];
        if (location && target) location.textContent = `Adding to Row ${target.row+1}, Column ${target.column+1}`;
        picker.innerHTML = layoutElementCatalogGroups.map(group=>{
            const cards = group.elements.filter(definition=>definition?.mode==='static' && isStaticLayoutElementType(definition.type)).map(definition=>{
                const limit = layoutElementInstanceLimit(definition.type);
                const issue = layoutColumnCapacityIssue(column,definition.type);
                const disabled = issue!==null;
                return `<button type="button" class="simple-element-card" data-layout-pick-element="${escapeHtml(definition.type)}" ${disabled?'disabled':''}><i class="fa ${escapeHtml(definition.icon||'fa-square-o')}" aria-hidden="true"></i><span><strong>${escapeHtml(definition.label||definition.type)}</strong><small>${escapeHtml(definition.description||'Add this content to the page.')}</small>${disabled?`<em>${elements.length>=12?'This column is full':`Limit of ${limit} reached`}</em>`:''}</span></button>`;
            }).join('');
            return cards ? `<section class="simple-element-category" aria-labelledby="simple-element-category-${escapeHtml(group.category)}"><header class="simple-element-category__head"><i class="fa ${escapeHtml(group.icon||'fa-square-o')}" aria-hidden="true"></i><div><h3 id="simple-element-category-${escapeHtml(group.category)}">${escapeHtml(group.label)}</h3>${group.description?`<p>${escapeHtml(group.description)}</p>`:''}</div></header><div class="simple-element-cards">${cards}</div></section>` : '';
        }).join('');
        fallback.innerHTML = layoutElementCatalogGroups.flatMap(group=>group.elements).filter(definition=>definition?.mode==='static'&&isStaticLayoutElementType(definition.type)).map(definition=>{
            const disabled = !layoutColumnCanAccept(column,definition.type);
            return `<option value="${escapeHtml(definition.type)}" ${disabled?'disabled':''}>${escapeHtml(groupedElementLabel(definition))}</option>`;
        }).join('');
        document.getElementById('simple-element-picker-add').disabled = !fallback.options.length || fallback.selectedOptions[0]?.disabled;
    }
    const groupedElementLabel = definition => `${definition.label || definition.type} — ${definition.category === 'media_files' ? 'Media and files' : definition.category === 'highlights' ? 'Highlights' : 'Text and layout'}`;
    function openElementPicker(block,rowIndex,columnIndex) {
        if (!canEditLayoutBlock(block)) return;
        const column = layoutRows(block)[rowIndex]?.columns?.[columnIndex];
        if (!column) return;
        if (column.elements.length>=12) return notify('This column already contains twelve content items.');
        state.selected=block.uuid;
        state.expandedSections.add(block.uuid);
        assignLayoutSelection(block,'column',rowIndex,columnIndex);
        state.elementPickerTarget={blockUuid:block.uuid,row:rowIndex,column:columnIndex};
        renderAll();
        renderElementPicker();
        const modal=document.getElementById('element-picker-modal');
        state.modalReturn=document.querySelector(`[data-layout-open-picker][data-layout-row="${rowIndex}"][data-layout-column="${columnIndex}"]`) || document.getElementById('simple-inspector-title');
        modal.hidden=false;
        modal.querySelector('[data-layout-pick-element]:not([disabled]),.simple-close')?.focus();
    }
    function addLayoutElementFromPicker(type) {
        const {target,block,column} = elementPickerContext();
        if (!target || !canEditLayoutBlock(block) || !column || !isStaticLayoutElementType(type)) return;
        const limit = layoutElementInstanceLimit(type);
        const issue = layoutColumnCapacityIssue(column,type);
        if (issue==='total') return notify('This column already contains twelve content items.');
        if (issue==='type') return notify(`This column can contain up to ${limit} ${layoutElementChoices[type]} ${limit===1?'item':'items'}.`);
        recordHistory();
        column.elements.push(newLayoutElement(type));
        assignLayoutSelection(block,'element',target.row,target.column,column.elements.length-1);
        state.validationError=null;
        markDirty('block');
        document.getElementById('element-picker-modal').hidden=true;
        state.elementPickerTarget=null;
        state.modalReturn=null;
        renderAll();
        restoreSelectionFocus('inspector');
    }
    function renderLayoutContentOverview(block, rows) {
        const items = [];
        rows.forEach((row,rowIndex)=>row.columns.slice(0,layoutPresetColumnCount(row.layout)).forEach((column,columnIndex)=>(column.elements||[]).forEach((element,elementIndex)=>{
            const type = layoutElementType(element);
            items.push(layoutContextButton('element',rowIndex,columnIndex,elementIndex,layoutElementIcons[type]||'fa-square-o',layoutElementName(element),`Row ${rowIndex+1} · Column ${columnIndex+1}`));
        })));
        return `<div class="simple-layout-guide"><strong>Edit page content</strong>Choose an item below, in Page Structure, or directly in the preview. Layout controls stay hidden until you choose Customize layout.</div><div class="simple-layout-content-overview">${items.length?items.join(''):'<p class="simple-layout-empty">This section has no content yet. Choose Customize layout to add its first row and column.</p>'}</div>`;
    }
    function renderLayoutStructureOverview(block, rows) {
        return `<div class="simple-layout-guide"><strong>Customize rows and columns</strong>Select a row to change its column design, or use the plus buttons in the preview to insert a row exactly where you need it.</div><div class="simple-layout-context-list">${rows.map((row,rowIndex)=>layoutContextButton('row',rowIndex,-1,-1,'fa-columns',`Row ${rowIndex+1}`,`${layoutPresetChoices[row.layout]} · ${layoutPresetColumnCount(row.layout)} ${layoutPresetColumnCount(row.layout)===1?'column':'columns'}`)).join('')}</div>${rows.length?'':'<p class="simple-layout-empty">This visual layout has no rows yet.</p>'}<button class="simple-btn simple-layout-add-row" type="button" id="simple-layout-add-row" ${rows.length>=12?'disabled':''}><i class="fa fa-plus" aria-hidden="true"></i> Add row</button><p class="simple-layout-limit">${rows.length} of 12 rows used</p>`;
    }
    function renderLayoutRowContext(block, selection, rows) {
        const {row,rowIndex} = selection;
        const preset = layoutChoice(row.layout,layoutPresetChoices,'full');
        const count = layoutPresetColumnCount(preset);
        return `<p class="simple-layout-selected-summary"><strong>Editing Row ${rowIndex+1}</strong><br>${escapeHtml(layoutPresetChoices[preset])}. Choose a visual layout or select a column to work with its content.</p><div class="simple-layout-context-actions" role="group" aria-label="Arrange row ${rowIndex+1}"><button class="simple-btn" type="button" data-layout-row-action="up" data-layout-row="${rowIndex}" ${rowIndex===0?'disabled':''}>↑ Move up</button><button class="simple-btn" type="button" data-layout-row-action="down" data-layout-row="${rowIndex}" ${rowIndex===rows.length-1?'disabled':''}>↓ Move down</button><button class="simple-btn" type="button" data-layout-row-action="duplicate" data-layout-row="${rowIndex}" ${rows.length>=12?'disabled':''}><i class="fa fa-copy" aria-hidden="true"></i> Duplicate</button><button class="simple-btn simple-btn--danger" type="button" data-layout-row-action="remove" data-layout-row="${rowIndex}"><i class="fa fa-trash" aria-hidden="true"></i> Delete</button></div>${renderLayoutPresetChooser(rowIndex,preset)}<div class="simple-layout-row-settings">${layoutRowSelect(rowIndex,'width','Content width',layoutChoice(row.width,layoutWidthChoices,'standard'),layoutWidthChoices)}${layoutRowSelect(rowIndex,'background','Background',layoutChoice(row.background,layoutBackgroundChoices,'default'),layoutBackgroundChoices)}${layoutRowSelect(rowIndex,'spacing','Space inside row',layoutChoice(row.spacing,layoutSpacingChoices,'standard'),layoutSpacingChoices)}</div><h3 style="font:700 14px 'Literata',serif">Columns in this row</h3><div class="simple-layout-context-list">${row.columns.slice(0,count).map((column,columnIndex)=>layoutContextButton('column',rowIndex,columnIndex,-1,'fa-columns',`Column ${columnIndex+1}`,`${(column.elements||[]).length} ${(column.elements||[]).length===1?'item':'items'}`)).join('')}</div>`;
    }
    function renderLayoutColumnContext(block, selection) {
        const {row,column,rowIndex,columnIndex} = selection;
        const elements = Array.isArray(column.elements) ? column.elements : (column.elements=[]);
        return `<p class="simple-layout-selected-summary"><strong>Editing Column ${columnIndex+1}</strong><br>Row ${rowIndex+1} · ${elements.length} of 12 content items</p><div class="simple-layout-context-list">${elements.map((element,elementIndex)=>{const type=layoutElementType(element);return layoutContextButton('element',rowIndex,columnIndex,elementIndex,layoutElementIcons[type]||'fa-square-o',layoutElementName(element),layoutElementChoices[type]);}).join('')}</div>${elements.length?'':'<p class="simple-layout-empty">This column is empty. Add its first content item below.</p>'}${renderLayoutAddToColumn(rowIndex,columnIndex,elements.length)}`;
    }
    function renderLayoutElementContext(block, selection) {
        const {row,column,element,rowIndex,columnIndex,elementIndex} = selection;
        const elements = Array.isArray(column.elements) ? column.elements : [];
        return `<p class="simple-layout-selected-summary"><strong>Editing ${escapeHtml(layoutElementChoices[layoutElementType(element)])}</strong><br>Row ${rowIndex+1} · Column ${columnIndex+1}. Changes appear in the preview immediately.</p>${renderLayoutElementEditor(block,element,rowIndex,columnIndex,elementIndex,layoutPresetColumnCount(row.layout),elements,{contentOnly:state.editorMode==='content',forceOpen:true})}`;
    }
    function renderLayoutEditor(block) {
        const guard=layoutLoadGuard(block);
        if (guard.blocked) return layoutRepairNotice(guard);
        const rows = layoutRows(block);
        const selection = resolveLayoutSelection(block);
        if (state.editorMode === 'content') {
            if (selection.kind === 'element') return renderLayoutElementContext(block,selection);
            if (selection.kind === 'column') return `<div class="simple-layout-guide"><strong>Add content</strong>Choose what you want to place in Row ${selection.rowIndex+1}, Column ${selection.columnIndex+1}. You can keep the safe column layout unchanged.</div>${renderLayoutColumnContext(block,selection)}`;
            return renderLayoutContentOverview(block,rows);
        }
        if (selection.kind === 'row') return renderLayoutRowContext(block,selection,rows);
        if (selection.kind === 'column') return renderLayoutColumnContext(block,selection);
        if (selection.kind === 'element') return renderLayoutElementContext(block,selection);
        return renderLayoutStructureOverview(block,rows);
    }
    function renderEssentialFields(block) {
        const content = block.content || (block.content = {});
        if (block.type === 'layout') return renderLayoutEditor(block);
        if (block.type === 'hero') return renderHeroEditor(block);
        if (block.type === 'stats') return renderStatsEditor(block);
        if (block.type === 'ways_to_give') return renderWaysToGiveEditor(block);
        if (contentOptions.sources?.[block.type]) {
            const source = contentSource(block);
            if (source !== 'manual') return renderAutomaticEditor(block);
            if (['cards','gallery'].includes(block.type)) return `${sourceChoiceField(block)}${contentManagementLinks(block,source)}${renderCardsEditor(block)}`;
        }
        if (['cards','partners','faq','timeline','gallery'].includes(block.type)) return `${contentManagementLinks(block)}${renderCardsEditor(block)}`;
        if (block.type === 'rich_text') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}<p class="simple-editor-hint"><i class="fa fa-info-circle" aria-hidden="true"></i> Edit formatted body copy here; the preview keeps formatting read-only to protect it.</p>${richField('body','Body text',content.body || '')}`;
        if (block.type === 'media_text') return `${contentManagementLinks(block,'media')}${renderMediaTextEditor(block)}`;
        if (block.type === 'video') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Section heading',content.heading || '')}${textField('body','Introduction',content.body || '',{textarea:true})}${videoField('video_url','YouTube, Vimeo, or uploaded video URL',content.video_url || '')}${imageField('poster','Poster image (uploaded videos)',content.poster || '')}${textField('caption','Video caption',content.caption || '',{max:2000})}${contentManagementLinks(block,'media')}`;
        if (block.type === 'cta') return `${textField('eyebrow','Small heading',content.eyebrow || '')}${textField('heading','Main message',content.heading || '')}${textField('body','Description',content.body || '',{textarea:true})}${textField('primary_label','Main button text',content.primary_label || '')}${linkField('primary_url','Main button destination',content.primary_url || '')}${textField('secondary_label','Second button text',content.secondary_label || '')}${linkField('secondary_url','Second button destination',content.secondary_url || '')}`;
        if (block.type === 'newsletter') return `${textField('heading','Section heading',content.heading || '')}${textField('body','Description',content.body || '',{textarea:true})}${textField('email_label','Email field label',content.email_label || '')}${textField('email_placeholder','Email field placeholder',content.email_placeholder || '')}${textField('button_label','Button text',content.button_label || '')}${textField('consent_text','Consent text',content.consent_text || '',{textarea:true})}${textField('privacy_label','Privacy link text',content.privacy_label || '')}${linkField('privacy_url','Privacy link destination',content.privacy_url || '')}`;
        return `<div class="simple-shared">This specialized section is available in Advanced mode. Its content is preserved.</div>`;
    }
    function renderInspector() {
        const block = current();
        if (!block) { document.getElementById('simple-inspector-title').textContent='Edit section'; document.getElementById('simple-inspector-type').textContent='No section selected'; inspector.innerHTML='<div class="simple-empty" style="padding:50px 10px">Add a section or select one from the page preview.</div>'; return; }
        const layoutBlocked = layoutIsBlocked(block);
        const sharedContentReadOnly = block.is_reusable && !permissions.editReusable;
        const editorBlock = blockForEditorRender(block);
        const layoutSelection = layoutBlocked ? {kind:'section',row:null,column:null,element:null,rowIndex:-1,columnIndex:-1,elementIndex:-1} : resolveLayoutSelection(block);
        let inspectorTitle = block.label || typeLabels[block.type] || 'Edit section';
        let inspectorType = typeLabels[block.type] || block.type;
        if (block.type === 'layout' && layoutSelection.kind === 'row') {
            inspectorTitle = `Row ${layoutSelection.rowIndex+1}`;
            inspectorType = `${layoutPresetColumnCount(layoutSelection.row.layout)} ${layoutPresetColumnCount(layoutSelection.row.layout)===1?'column':'columns'}`;
        } else if (block.type === 'layout' && layoutSelection.kind === 'column') {
            inspectorTitle = `Column ${layoutSelection.columnIndex+1}`;
            inspectorType = `Row ${layoutSelection.rowIndex+1}`;
        } else if (block.type === 'layout' && layoutSelection.kind === 'element') {
            inspectorTitle = layoutElementName(layoutSelection.element);
            inspectorType = layoutElementChoices[layoutElementType(layoutSelection.element)];
        }
        document.getElementById('simple-inspector-title').textContent = inspectorTitle;
        document.getElementById('simple-inspector-type').textContent = inspectorType;
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
        const reusableAction = !layoutBlocked && layoutSelection.kind === 'section' && !block.is_reusable && permissions.create
            ? '<div class="simple-shared"><strong>Reuse this section on other pages.</strong> Save it to the shared library with a clear name. <button class="simple-btn" type="button" id="simple-promote-reusable"><i class="fa fa-share-alt" aria-hidden="true"></i> Save as reusable</button></div>'
            : '';
        const validationNotice = state.validationError?.blockUuid === block.uuid
            ? `<div class="simple-validation-error" id="simple-validation-error" role="alert" tabindex="-1"><strong>Please fix this content</strong>${escapeHtml(state.validationError.message)}</div>`
            : '';
        const sectionDesignControls = block.type === 'layout' ? '' : `${renderSectionPresentationField(editorBlock)}${renderSectionDesignFields(editorBlock)}`;
        const sectionOptions = !layoutBlocked && (block.type !== 'layout' || layoutSelection.kind === 'section') ? `<details class="simple-options"><summary>Section options</summary><div style="padding-top:14px"><label class="simple-field"><span>Editor label</span><input id="simple-block-label" value="${escapeHtml(block.label || typeLabels[block.type] || '')}"></label><label class="simple-check"><input id="simple-block-enabled" type="checkbox" ${block.is_enabled ? 'checked' : ''}> Show this section on the website</label><fieldset class="simple-placement-options"><legend>Device visibility and schedule</legend><label class="simple-check"><input id="simple-block-desktop" type="checkbox" ${block.show_on_desktop !== false ? 'checked' : ''}> Show on desktop</label><label class="simple-check"><input id="simple-block-mobile" type="checkbox" ${block.show_on_mobile !== false ? 'checked' : ''}> Show on mobile</label><label class="simple-field"><span>Show from (optional)</span><input id="simple-block-available-from" type="datetime-local" value="${escapeHtml(formatDateTimeLocal(block.available_from))}"></label><label class="simple-field"><span>Show until (optional)</span><input id="simple-block-available-until" type="datetime-local" value="${escapeHtml(formatDateTimeLocal(block.available_until))}"><small>Leave both dates empty to keep the section available whenever the page is published.</small></label></fieldset>${actionGroup}${editNote}</div></details>` : '';
        inspector.innerHTML = `${validationNotice}${sharedNotice}${reusableAction}${sectionDesignControls}${renderEssentialFields(editorBlock)}${sectionOptions}`;
        if (!permissions.edit || sharedContentReadOnly || layoutBlocked) {
            inspector.querySelectorAll('input,textarea,select,button,[contenteditable="true"]').forEach(control => {
                const pageOnlyControl = sharedContentReadOnly && control.matches('#simple-block-enabled,#simple-block-desktop,#simple-block-mobile,#simple-block-available-from,#simple-block-available-until,#simple-duplicate,#simple-delete,#simple-detach-reusable,[data-hero-nav],[data-layout-inspector-select]');
                const permittedReadOnlyAction = !permissions.edit && control.matches('#simple-duplicate,#simple-delete,#simple-promote-reusable,[data-hero-nav],[data-layout-inspector-select]');
                if (!layoutBlocked && (pageOnlyControl || permittedReadOnlyAction)) return;
                if (control.hasAttribute('contenteditable')) control.removeAttribute('contenteditable');
                else control.disabled = true;
            });
        }
        wireInspector(block);
    }
    function reshapeLayoutRow(row, nextPreset) {
        const preset = layoutChoice(nextPreset, layoutPresetChoices, 'full');
        const desiredCount = layoutPresetColumnCount(preset);
        const currentColumns = Array.isArray(row.columns) ? row.columns : [];
        if (currentColumns.length > desiredCount) {
            const plannedColumns = planLayoutColumnCollapse(currentColumns,desiredCount);
            if (!plannedColumns) return false;
            row.columns = plannedColumns;
        } else {
            const expandedColumns = currentColumns.map(column=>({...column,elements:[...layoutColumnElements(column)]}));
            while (expandedColumns.length < desiredCount) expandedColumns.push({id:newLayoutId(),elements:[]});
            row.columns = expandedColumns;
        }
        row.layout = preset;
        return true;
    }
    function layoutElementFromControl(block, control) {
        const rowIndex = Number(control.dataset.layoutRow);
        const columnIndex = Number(control.dataset.layoutColumn);
        const elementIndex = Number(control.dataset.layoutElement);
        const rows = layoutRows(block);
        return {rowIndex,columnIndex,elementIndex,row:rows[rowIndex],column:rows[rowIndex]?.columns?.[columnIndex],element:rows[rowIndex]?.columns?.[columnIndex]?.elements?.[elementIndex]};
    }
    function wireLayoutEditor(block) {
        if (layoutIsBlocked(block)) return;
        const rows = layoutRows(block);
        inspector.querySelectorAll('[data-layout-element-details]').forEach(details => details.addEventListener('toggle', () => {
            if (details.open) state.layoutOpenElement = details.dataset.layoutElementDetails;
            else if (state.layoutOpenElement === details.dataset.layoutElementDetails) state.layoutOpenElement = '';
        }));
        inspector.querySelectorAll('[data-layout-row-field]').forEach(control => control.addEventListener('change', () => {
            const row = rows[Number(control.dataset.layoutRow)];
            if (!row) return;
            const field = control.dataset.layoutRowField;
            if (field === 'layout') {
                const oldPreset = row.layout;
                if (!reshapeLayoutRow(row,control.value)) {
                    control.value = oldPreset;
                    return notify('This layout has no room for every element. Move or remove elements before reducing its columns.');
                }
                state.layoutOpenElement = null;
                markDirty('block'); renderAll(); return;
            }
            const choices = field === 'width' ? layoutWidthChoices : field === 'background' ? layoutBackgroundChoices : layoutSpacingChoices;
            row[field] = layoutChoice(control.value,choices,field === 'spacing'?'standard':field === 'background'?'default':'standard');
            markDirty('block'); renderPreview();
        }));
        inspector.querySelectorAll('[data-layout-preset]').forEach(button => button.addEventListener('click', () => {
            const rowIndex = Number(button.dataset.layoutRow);
            const row = rows[rowIndex];
            if (!row || button.dataset.layoutPreset === row.layout) return;
            const oldPreset = row.layout;
            recordHistory();
            if (!reshapeLayoutRow(row,button.dataset.layoutPreset)) {
                state.undo.pop();
                row.layout = oldPreset;
                updateSaveState();
                return notify('This design has no room for every content item. Move some items before reducing its columns.');
            }
            assignLayoutSelection(block,'row',rowIndex);
            markDirty('block'); renderAll(); restoreSelectionFocus('inspector');
        }));
        inspector.querySelector('#simple-layout-add-row')?.addEventListener('click', () => {
            if (rows.length >= 12) return notify('A visual layout can contain up to twelve rows.');
            recordHistory(); rows.push(newLayoutRow()); assignLayoutSelection(block,'row',rows.length-1); markDirty('block'); renderAll(); restoreSelectionFocus('inspector');
        });
        inspector.querySelectorAll('[data-layout-row-action]').forEach(button => button.addEventListener('click', () => {
            const index = Number(button.dataset.layoutRow);
            const action = button.dataset.layoutRowAction;
            if (!rows[index]) return;
            if (action === 'up' || action === 'down') {
                const target = action === 'up' ? index-1 : index+1;
                if (target < 0 || target >= rows.length) return;
                recordHistory(); [rows[index],rows[target]] = [rows[target],rows[index]]; state.layoutOpenElement = null; markDirty('block'); renderAll(); return;
            }
            if (action === 'duplicate') {
                if (rows.length >= 12) return notify('A visual layout can contain up to twelve rows.');
                recordHistory(); rows.splice(index+1,0,duplicateLayoutRow(rows[index])); assignLayoutSelection(block,'row',index+1); markDirty('block'); renderAll(); return;
            }
            if (action === 'remove') {
                recordHistory(); rows.splice(index,1); assignLayoutSelection(block); markDirty('block'); renderAll(); notify('Row removed. Use Undo if you need it back.');
            }
        }));
        inspector.querySelectorAll('[data-layout-open-picker]').forEach(button => button.addEventListener('click', () => openElementPicker(block,Number(button.dataset.layoutRow),Number(button.dataset.layoutColumn))));
        inspector.querySelectorAll('[data-layout-element-action]').forEach(button => button.addEventListener('click', () => {
            const {rowIndex,columnIndex,elementIndex,row,column,element} = layoutElementFromControl(block,button);
            if (!row || !column || !element) return;
            const action = button.dataset.layoutElementAction;
            if (action === 'up' || action === 'down') {
                const target = action === 'up' ? elementIndex-1 : elementIndex+1;
                if (target < 0 || target >= column.elements.length) return;
                recordHistory(); [column.elements[elementIndex],column.elements[target]] = [column.elements[target],column.elements[elementIndex]];
                assignLayoutSelection(block,'element',rowIndex,columnIndex,target); markDirty('block'); renderAll(); return;
            }
            if (action === 'left' || action === 'right') {
                const targetColumnIndex = action === 'left' ? columnIndex-1 : columnIndex+1;
                const targetColumn = row.columns[targetColumnIndex];
                const type = layoutElementType(element);
                const issue = targetColumn ? layoutColumnCapacityIssue(targetColumn,type) : 'total';
                if (issue==='total') return notify('The adjacent column is full.');
                if (issue==='type') return notify(`The adjacent column can contain up to ${layoutElementInstanceLimit(type)} ${layoutElementChoices[type]} ${layoutElementInstanceLimit(type)===1?'item':'items'}.`);
                recordHistory(); column.elements.splice(elementIndex,1); targetColumn.elements.push(element);
                assignLayoutSelection(block,'element',rowIndex,targetColumnIndex,targetColumn.elements.length-1); markDirty('block'); renderAll(); return;
            }
            if (action === 'duplicate') {
                const type=layoutElementType(element),limit=layoutElementInstanceLimit(type),issue=layoutColumnCapacityIssue(column,type);
                if (issue==='total') return notify('A column can contain up to twelve content items.');
                if (issue==='type') return notify(`This column can contain up to ${limit} ${layoutElementChoices[type]} ${limit===1?'item':'items'}.`);
                recordHistory(); const copy = clone(element); copy.id = newLayoutId(); ensureLayoutElementIds(copy,true); column.elements.splice(elementIndex+1,0,copy);
                assignLayoutSelection(block,'element',rowIndex,columnIndex,elementIndex+1); markDirty('block'); renderAll(); return;
            }
            if (action === 'remove') {
                recordHistory(); column.elements.splice(elementIndex,1); assignLayoutSelection(block,'column',rowIndex,columnIndex); markDirty('block'); renderAll(); notify('Content item removed. Use Undo if you need it back.');
            }
        }));
        inspector.querySelectorAll('[data-layout-element-field]').forEach(control => control.addEventListener('input', () => {
            const {element} = layoutElementFromControl(block,control);
            if (!element) return;
            element[control.dataset.layoutElementField] = control.type==='checkbox' ? control.checked : control.value;
            state.validationError=null;
            markDirty('block'); renderPreview();
        }));
        inspector.querySelectorAll('[data-layout-rerender]').forEach(control => control.addEventListener('change', () => { renderInspector(); renderPreview(); }));
        inspector.querySelectorAll('[data-layout-rich]').forEach(editor => editor.addEventListener('input', () => {
            const {element} = layoutElementFromControl(block,editor);
            if (!element) return;
            element[editor.dataset.layoutRichField||'body'] = editor.innerHTML; state.validationError=null; markDirty('block'); renderPreview();
        }));
        inspector.querySelectorAll('[data-layout-item-field]').forEach(control => control.addEventListener('input', () => {
            const {element}=layoutElementFromControl(block,control),item=element?.items?.[Number(control.dataset.layoutItem)];
            if(!item)return;
            item[control.dataset.layoutItemField]=control.type==='checkbox'?control.checked:control.value;
            state.validationError=null;markDirty('block');renderPreview();
        }));
        inspector.querySelectorAll('[data-layout-item-rich]').forEach(editor => editor.addEventListener('input', () => {
            const {element}=layoutElementFromControl(block,editor),item=element?.items?.[Number(editor.dataset.layoutItem)];
            if(!item)return;
            item[editor.dataset.layoutItemField]=editor.innerHTML;state.validationError=null;markDirty('block');renderPreview();
        }));
        inspector.querySelectorAll('[data-layout-item-add]').forEach(button=>button.addEventListener('click',()=>{
            const {element}=layoutElementFromControl(block,button),field=button.dataset.layoutItemAdd||'items';
            if(!element)return;
            if(!Array.isArray(element[field]))element[field]=[];
            const max=Math.max(1,Number(layoutElementCatalog[element.type]?.fields?.[field]?.bounds?.max_items)||12);
            if(element[field].length>=max)return notify(`This list can contain up to ${max} items.`);
            recordHistory();element[field].push(newLayoutRepeaterItem(element,field));state.validationError=null;markDirty('block');renderAll();
        }));
        inspector.querySelectorAll('[data-layout-item-action]').forEach(button=>button.addEventListener('click',()=>{
            const {element}=layoutElementFromControl(block,button),items=element?.items,itemIndex=Number(button.dataset.layoutItem),action=button.dataset.layoutItemAction;
            if(!Array.isArray(items)||!items[itemIndex])return;
            if(action==='up'||action==='down'){
                const target=action==='up'?itemIndex-1:itemIndex+1;if(target<0||target>=items.length)return;
                recordHistory();[items[itemIndex],items[target]]=[items[target],items[itemIndex]];
            }else if(action==='remove'){recordHistory();items.splice(itemIndex,1);}
            state.validationError=null;markDirty('block');renderAll();
        }));
        inspector.querySelectorAll('[data-layout-format]').forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            const editor = document.getElementById(button.dataset.layoutRichEditor);
            editor?.focus();
            if (button.dataset.layoutFormat === 'createLink') {
                const url = prompt('Enter the link address:','https://');
                if (url) document.execCommand('createLink',false,url);
            } else document.execCommand(button.dataset.layoutFormat,false,null);
            editor?.dispatchEvent(new Event('input',{bubbles:true}));
        }));
        inspector.querySelectorAll('[data-layout-clear-image]').forEach(button => button.addEventListener('click', () => {
            const {element} = layoutElementFromControl(block,button);
            const field = button.dataset.layoutImageField || 'image';
            if (!element || !['card','quote'].includes(layoutElementType(element)) || field !== 'image' || !String(element[field] || '').trim()) return;
            recordHistory();
            element[field] = '';
            state.validationError = null;
            markDirty('block');
            renderInspector();
            renderPreview();
            notify('Image removed. Use Undo if you need it back.');
        }));
        inspector.querySelectorAll('[data-layout-choose-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:'layout',row:Number(button.dataset.layoutRow),column:Number(button.dataset.layoutColumn),element:Number(button.dataset.layoutElement),key:button.dataset.layoutImageField||'path'})));
        inspector.querySelectorAll('[data-layout-item-choose-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:'layout-item',row:Number(button.dataset.layoutRow),column:Number(button.dataset.layoutColumn),element:Number(button.dataset.layoutElement),item:Number(button.dataset.layoutItem),key:'path'})));
        inspector.querySelectorAll('[data-layout-choose-video]').forEach(button => button.addEventListener('click', () => openVideoMedia({kind:'layout',row:Number(button.dataset.layoutRow),column:Number(button.dataset.layoutColumn),element:Number(button.dataset.layoutElement),key:'source'})));
        inspector.querySelectorAll('[data-layout-choose-file]').forEach(button => button.addEventListener('click', () => openDocumentMedia({kind:'layout',row:Number(button.dataset.layoutRow),column:Number(button.dataset.layoutColumn),element:Number(button.dataset.layoutElement),key:'path'})));
    }
    function wireInspector(block) {
        inspector.querySelectorAll('[data-hero-nav]').forEach(button => button.addEventListener('click', () => { state.heroSlide += button.dataset.heroNav === 'next' ? 1 : -1; renderInspector(); renderPreview(); }));
        inspector.querySelectorAll('[data-layout-inspector-select]').forEach(button => button.addEventListener('click', () => selectLayoutNode(block,button.dataset.layoutInspectorSelect,Number(button.dataset.layoutRow),Number(button.dataset.layoutColumn ?? -1),Number(button.dataset.layoutElement ?? -1),{focus:'inspector'})));
        if (permissions.edit) {
            const enabled = inspector.querySelector('#simple-block-enabled');
            if (enabled) {
                captureOnFocus(enabled);
                enabled.addEventListener('change',event=>{block.is_enabled=event.target.checked;markDirty('block');renderList();renderPreview()});
            }
            const placementControls = [
                ['#simple-block-desktop','show_on_desktop','checkbox'],
                ['#simple-block-mobile','show_on_mobile','checkbox'],
                ['#simple-block-available-from','available_from','datetime'],
                ['#simple-block-available-until','available_until','datetime'],
            ];
            placementControls.forEach(([selector,key,type]) => {
                const control = inspector.querySelector(selector);
                if (!control) return;
                captureOnFocus(control);
                control.addEventListener('change', () => {
                    block[key] = type === 'checkbox' ? control.checked : (control.value || null);
                    markDirty('block'); renderPreview();
                });
            });
        }
        if (canEditBlockContent(block)) {
            inspector.querySelectorAll('input,textarea,select,[contenteditable="true"]').forEach(control => {
                if (control.id !== 'simple-block-enabled') captureOnFocus(control);
            });
            inspector.querySelectorAll('[data-content-key]').forEach(input => input.addEventListener('input', () => { block.content[input.dataset.contentKey] = input.type === 'checkbox' ? input.checked : input.type === 'number' || ['animation_duration','animation_delay','interval'].includes(input.dataset.contentKey) ? Number(input.value) : input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelector('[data-media-type-rerender]')?.addEventListener('change', event => { block.content.media_type=event.target.value;markDirty('block');renderInspector();renderPreview(); });
            inspector.querySelectorAll('[data-content-array-key]').forEach(input => input.addEventListener('change', () => { block.content[input.dataset.contentArrayKey] = [...input.selectedOptions].map(option => option.value); markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-auto-rerender]').forEach(input => input.addEventListener('change', () => { block.content[input.dataset.contentKey] = input.value; if (input.dataset.contentKey === 'content_source') block.content.selected_items = []; markDirty('block'); renderInspector(); renderPreview(); }));
            inspector.querySelectorAll('[data-managed-toggle]').forEach(input => input.addEventListener('change', () => {
                recordHistory();
                const selected = Array.isArray(block.content.selected_items) ? block.content.selected_items.map(String) : [];
                if (input.checked && selected.length >= 12) {
                    input.checked = false;
                    return notify('Choose no more than twelve managed items.');
                }
                block.content.selected_items = input.checked ? [...selected,input.value] : selected.filter(token => token !== input.value);
                markDirty('block'); renderAll();
            }));
            inspector.querySelectorAll('[data-managed-move]').forEach(button => button.addEventListener('click', () => {
                const index = Number(button.dataset.managedIndex);
                const target = button.dataset.managedMove === 'up' ? index - 1 : index + 1;
                if (target < 0 || target >= block.content.selected_items.length) return;
                recordHistory();
                [block.content.selected_items[index],block.content.selected_items[target]] = [block.content.selected_items[target],block.content.selected_items[index]];
                markDirty('block'); renderAll();
            }));
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
            inspector.querySelectorAll('[data-slide-key]').forEach(input => input.addEventListener('input', () => { const key=input.dataset.slideKey;heroSlides(block)[state.heroSlide][key] = key==='overlay_opacity'?Number(input.value):input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-stat-index]').forEach(input => input.addEventListener('input', () => { block.content.items[Number(input.dataset.statIndex)][input.dataset.statKey] = input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-card-index]').forEach(input => input.addEventListener('input', () => { block.content.items[Number(input.dataset.cardIndex)][input.dataset.cardKey] = input.type === 'checkbox' ? input.checked : input.value; markDirty('block'); renderPreview(); }));
            inspector.querySelectorAll('[data-link-picker]').forEach(picker => picker.addEventListener('change', () => { if(picker.dataset.historyReady!=='true')recordHistory(); if (picker.value === '__custom') return document.getElementById(picker.dataset.linkPicker)?.focus(); const input=document.getElementById(picker.dataset.linkPicker); if(!input)return; input.value=picker.value; input.dispatchEvent(new Event('input',{bubbles:true})); }));
            inspector.querySelectorAll('[data-format]').forEach(button => button.addEventListener('click', () => { const editor=inspector.querySelector('[contenteditable]'); editor?.focus(); if(button.dataset.format==='createLink'){const url=prompt('Enter the link address:','https://');if(url)document.execCommand('createLink',false,url)}else document.execCommand(button.dataset.format,false,null); editor?.dispatchEvent(new Event('input',{bubbles:true})); }));
            inspector.querySelectorAll('[data-choose-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:button.dataset.slideImage?'slide':'content',key:button.dataset.chooseImage})));
            inspector.querySelectorAll('[data-choose-video]').forEach(button => button.addEventListener('click', () => openVideoMedia({kind:'content',key:button.dataset.chooseVideo})));
            inspector.querySelectorAll('[data-choose-card-image]').forEach(button => button.addEventListener('click', () => openMedia({kind:'card',index:Number(button.dataset.chooseCardImage),key:'image'})));
            if (block.type === 'layout') wireLayoutEditor(block);
        }
        if (canEditBlockContent(block)) {
            inspector.querySelectorAll('[data-hero-move]').forEach(button => button.addEventListener('click', () => {
                const slides = heroSlides(block);
                const target = button.dataset.heroMove === 'earlier' ? state.heroSlide - 1 : state.heroSlide + 1;
                if (target < 0 || target >= slides.length) return;
                recordHistory();
                [slides[state.heroSlide],slides[target]] = [slides[target],slides[state.heroSlide]];
                state.heroSlide = target;
                markDirty('block'); renderAll();
            }));
            inspector.querySelector('[data-hero-action="add"]')?.addEventListener('click', () => { if(heroSlides(block).length>=8)return notify('A hero can contain up to eight slides.'); recordHistory(); heroSlides(block).push({eyebrow:'',heading:'New slide',body:'',primary_label:'Learn more',primary_url:'#',secondary_label:'',secondary_url:'',report_label:'',report_url:'',image:'',overlay_opacity:64}); state.heroSlide=heroSlides(block).length-1; markDirty('block'); renderAll(); });
            inspector.querySelector('[data-hero-action="remove"]')?.addEventListener('click', () => { if(!confirm('Remove this slide?'))return; recordHistory(); heroSlides(block).splice(state.heroSlide,1); state.heroSlide=Math.max(0,state.heroSlide-1); markDirty('block'); renderAll(); });
            inspector.querySelector('#add-stat')?.addEventListener('click',()=>{const items=block.content.items||(block.content.items=[]);if(items.length>=60)return notify('A section can contain up to sixty statistics.');recordHistory();items.push({value:'0',label:'New statistic',icon:''});markDirty('block');renderAll()});
            inspector.querySelector('#simple-stat-animation-enabled')?.addEventListener('change',event=>{const options=inspector.querySelector('#simple-stat-animation-options');if(options)options.disabled=!event.target.checked});
            inspector.querySelectorAll('[data-stat-move]').forEach(button=>button.addEventListener('click',()=>{const index=Number(button.dataset.statIndex);const target=button.dataset.statMove==='up'?index-1:index+1;if(target<0||target>=block.content.items.length)return;recordHistory();[block.content.items[index],block.content.items[target]]=[block.content.items[target],block.content.items[index]];markDirty('block');renderAll()}));
            inspector.querySelectorAll('[data-remove-stat]').forEach(button=>button.addEventListener('click',()=>{recordHistory();block.content.items.splice(Number(button.dataset.removeStat),1);markDirty('block');renderAll()}));
            inspector.querySelector('#add-card')?.addEventListener('click',()=>{const items=block.content.items||(block.content.items=[]);if(items.length>=60)return notify('A section can contain up to sixty items.');recordHistory();const labels={partners:'New partner',faq:'New question',timeline:'New milestone',gallery:'New photo'};const heading=labels[block.type]||'New card';items.push({eyebrow:'',heading,body:'',image:'',image_alt:['cards','partners','gallery'].includes(block.type)?heading:'',icon:'',url:'',link_label:'Learn more'});state.cardIndexes[block.uuid]=items.length-1;markDirty('block');renderAll();revealSelectedCard(items.length-1)});
            inspector.querySelectorAll('[data-card-move]').forEach(button=>button.addEventListener('click',()=>{const index=Number(button.dataset.cardIndex);const target=button.dataset.cardMove==='up'?index-1:index+1;if(target<0||target>=block.content.items.length)return;recordHistory();const selected=selectedCardIndex(block,block.content.items);[block.content.items[index],block.content.items[target]]=[block.content.items[target],block.content.items[index]];if(selected===index)state.cardIndexes[block.uuid]=target;else if(selected===target)state.cardIndexes[block.uuid]=index;markDirty('block');renderAll();revealSelectedCard(selectedCardIndex(block,block.content.items))}));
            inspector.querySelectorAll('[data-remove-card]').forEach(button=>button.addEventListener('click',()=>{const index=Number(button.dataset.removeCard);recordHistory();const selected=selectedCardIndex(block,block.content.items);block.content.items.splice(index,1);state.cardIndexes[block.uuid]=index<selected?selected-1:Math.min(selected,Math.max(block.content.items.length-1,0));markDirty('block');renderAll();if(block.content.items.length)revealSelectedCard(selectedCardIndex(block,block.content.items))}));
            inspector.querySelector('#simple-block-label')?.addEventListener('input',event=>{block.label=event.target.value;markDirty('block');renderList();renderPreview()});
        }
        inspector.querySelector('#simple-duplicate')?.addEventListener('click',duplicateSection);
        inspector.querySelector('#simple-delete')?.addEventListener('click', event => deleteSection(undefined, event.currentTarget));
        inspector.querySelector('#simple-promote-reusable')?.addEventListener('click',openPromoteReusable);
        inspector.querySelector('#simple-detach-reusable')?.addEventListener('click',detachReusableSection);
    }

    const navigatorKey = (block,kind='section',rowId='',columnId='',elementId='') => [block.uuid,kind,rowId,columnId,elementId].join('|');
    function renderNavigatorElement(block,row,column,element,rowIndex,columnIndex,elementIndex,selection) {
        const type = layoutElementType(element);
        const rowId = layoutRowIdentity(row,rowIndex);
        const columnId = layoutColumnIdentity(row,column,columnIndex,rowIndex);
        const elementId = layoutElementIdentity(row,column,element,elementIndex,rowIndex,columnIndex);
        const key = navigatorKey(block,'element',rowId,columnId,elementId);
        const selected = block.uuid === state.selected && selection.kind === 'element' && state.selectedLayout.elementId === elementId;
        return `<li><button type="button" class="simple-navigator-node${selected?' is-selected':''}" data-navigator-kind="element" data-navigator-key="${escapeHtml(key)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" aria-current="${selected?'true':'false'}"><i class="fa ${layoutElementIcons[type]||'fa-square-o'}" aria-hidden="true"></i><span>${escapeHtml(layoutElementName(element))}</span></button></li>`;
    }
    function renderLayoutNavigator(block) {
        if (!state.expandedSections.has(block.uuid)) return '';
        const guard=layoutLoadGuard(block);
        if (guard.blocked) return `<div class="simple-navigator-tree simple-layout-load-guard" data-layout-load-guard><strong>Layout locked</strong><small>${escapeHtml(guard.detail)} Nothing was changed.</small></div>`;
        const rows = layoutRows(block);
        const selection = block.uuid === state.selected ? resolveLayoutSelection(block) : {kind:'section'};
        if (state.editorMode === 'content') {
            const elements = rows.flatMap((row,rowIndex)=>row.columns.slice(0,layoutPresetColumnCount(row.layout)).flatMap((column,columnIndex)=>(column.elements||[]).map((element,elementIndex)=>renderNavigatorElement(block,row,column,element,rowIndex,columnIndex,elementIndex,selection))));
            return `<ul class="simple-navigator-tree simple-navigator-content-tree" id="simple-navigator-${escapeHtml(block.uuid)}">${elements.length?elements.join(''):'<li><span class="simple-layout-element-note">No editable content in this section yet.</span></li>'}</ul>`;
        }
        return `<ul class="simple-navigator-tree simple-navigator-structure" id="simple-navigator-${escapeHtml(block.uuid)}">${rows.map((row,rowIndex)=>{
            const rowId = layoutRowIdentity(row,rowIndex);
            const rowSelected = block.uuid===state.selected && selection.kind==='row' && state.selectedLayout.rowId===rowId;
            const rowKey = navigatorKey(block,'row',rowId);
            const count = layoutPresetColumnCount(row.layout);
            return `<li><button type="button" class="simple-navigator-node${rowSelected?' is-selected':''}" data-navigator-kind="row" data-navigator-key="${escapeHtml(rowKey)}" data-layout-row="${rowIndex}" aria-current="${rowSelected?'true':'false'}"><i class="fa fa-columns" aria-hidden="true"></i><span>Row ${rowIndex+1}</span><small>${count} ${count===1?'column':'columns'}</small></button><ul class="simple-navigator-tree">${row.columns.slice(0,count).map((column,columnIndex)=>{
                const columnId = layoutColumnIdentity(row,column,columnIndex,rowIndex);
                const columnSelected = block.uuid===state.selected && selection.kind==='column' && state.selectedLayout.columnId===columnId;
                const columnKey = navigatorKey(block,'column',rowId,columnId);
                return `<li><button type="button" class="simple-navigator-node${columnSelected?' is-selected':''}" data-navigator-kind="column" data-navigator-key="${escapeHtml(columnKey)}" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" aria-current="${columnSelected?'true':'false'}"><i class="fa fa-square-o" aria-hidden="true"></i><span>Column ${columnIndex+1}</span><small>${(column.elements||[]).length}</small></button><ul class="simple-navigator-tree">${(column.elements||[]).map((element,elementIndex)=>renderNavigatorElement(block,row,column,element,rowIndex,columnIndex,elementIndex,selection)).join('')}</ul></li>`;
            }).join('')}</ul></li>`;
        }).join('')}</ul>`;
    }
    function renderList() {
        list.innerHTML = state.blocks.length ? state.blocks.map((block,index) => {
            const label = block.label || typeLabels[block.type] || block.type;
            const dragHandle = permissions.edit ? `<button class="simple-drag" type="button" aria-label="Drag ${escapeHtml(label)} to reorder">⋮⋮</button>` : '<span class="simple-drag-placeholder" aria-hidden="true"></span>';
            const orderActions = permissions.edit ? `<button type="button" data-move="up" data-index="${index}" aria-label="Move ${escapeHtml(label)} up" ${index===0?'disabled':''}>↑</button><button type="button" data-move="down" data-index="${index}" aria-label="Move ${escapeHtml(label)} down" ${index===state.blocks.length-1?'disabled':''}>↓</button>` : '';
            const deleteAction = permissions.delete ? `<button type="button" data-delete-section="${block.uuid}" aria-label="Move ${escapeHtml(label)} to trash" title="Move to trash"><i class="fa fa-trash" aria-hidden="true"></i></button>` : '';
            const actions = orderActions || deleteAction ? `<span class="simple-order">${orderActions}${deleteAction}</span>` : '<span class="simple-order" aria-hidden="true"></span>';
            const hasChildren = block.type === 'layout';
            const expanded = hasChildren && state.expandedSections.has(block.uuid);
            const toggle = hasChildren ? `<button type="button" class="simple-navigator-toggle" data-expand-section="${block.uuid}" aria-expanded="${expanded}" aria-controls="simple-navigator-${block.uuid}" aria-label="${expanded?'Collapse':'Expand'} ${escapeHtml(label)}"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>` : '<span class="simple-navigator-toggle-placeholder" aria-hidden="true"></span>';
            const sectionSelected = block.uuid===state.selected && resolveLayoutSelection(block).kind==='section';
            return `<li class="simple-section-item ${block.uuid===state.selected?'is-selected':''}" data-section="${block.uuid}"><div class="simple-section-row">${dragHandle}${toggle}<button class="simple-select" type="button" data-select="${block.uuid}" data-navigator-key="${escapeHtml(navigatorKey(block))}" aria-pressed="${sectionSelected}" title="Select ${escapeHtml(block.label || typeLabels[block.type])}"><strong>${escapeHtml(label)}</strong><small>${escapeHtml(typeLabels[block.type] || block.type)}${block.is_enabled?'':' · Hidden'}</small></button>${actions}</div>${hasChildren&&expanded?renderLayoutNavigator(block):''}</li>`;
        }).join('') : '<li class="simple-empty" style="padding:25px 5px">This page has no sections yet.</li>';
        wireOrdering();
    }
    const previewIconClasses = Object.freeze({people:'fa-users',map:'fa-map-marker',heart:'fa-heart',school:'fa-graduation-cap',health:'fa-medkit',water:'fa-tint',leaf:'fa-leaf',relief:'fa-life-ring',child:'fa-child',report:'fa-file-text-o',financials:'fa-line-chart',security:'fa-shield',policy:'fa-book'});
    const previewIcon = (token, fallback = 'fa-circle-o') => `<i class="fa ${previewIconClasses[token] || fallback}" aria-hidden="true"></i>`;
    const previewItemName = item => String(item?.heading || item?.label || item?.name || '').trim();
    const previewItemImage = item => safeImage(item?.image || item?.photo || item?.thumbnail || '');
    const previewEmpty = (content, fallback) => `<p class="simple-banner-guidance">${escapeHtml(content.empty_state || fallback)}</p>`;
    function previewSectionMeta(block, extraClasses = '') {
        const content = block.content || {};
        const from = block.available_from ? Date.parse(String(block.available_from).replace(' ', 'T')) : NaN;
        const until = block.available_until ? Date.parse(String(block.available_until).replace(' ', 'T')) : NaN;
        const outsideSchedule = (Number.isFinite(from) && from > Date.now()) || (Number.isFinite(until) && until < Date.now());
        const sectionIsSelected = block.uuid===state.selected && (block.type!=='layout' || resolveLayoutSelection(block).kind==='section');
        const classes = `simple-preview-block${designClasses(block)} simple-preview-block--presentation-${normalizedSectionPresentation(content.section_presentation)}${extraClasses}${sectionIsSelected?' is-selected':''}${block.is_enabled?'':' is-hidden'}${outsideSchedule?' is-outside-schedule':''}`;
        return `class="${classes}" data-preview-block="${escapeHtml(block.uuid)}" data-preview-type="${escapeHtml(block.type)}" data-label="${escapeHtml(block.label || typeLabels[block.type] || block.type)}" data-hide-desktop="${block.show_on_desktop === false}" data-hide-mobile="${block.show_on_mobile === false}" data-outside-schedule="${outsideSchedule}" tabindex="0"`;
    }
    function previewHeading(block, options = {}) {
        const content = block.content || {};
        const body = plainText(content.body || '');
        return `<header class="simple-preview-heading">${inlineElement('div',content.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',content.heading||block.label,'heading','section heading',{single:true})}${body?inlineElement('p',body,'body','introduction'):''}${options.viewAll && content.view_all_label?`<span class="simple-preview-view-all">${inlineElement('span',content.view_all_label,'view_all_label','view-all link text',{single:true})} <span aria-hidden="true">→</span></span>`:''}</header>`;
    }
    function previewEventDate(item) {
        const raw = item?.event_date || item?.start_date || item?.date || item?.published_at;
        let date = null;
        if (typeof raw === 'number' || /^\d{9,13}$/.test(String(raw || ''))) {
            const number = Number(raw);
            date = new Date(number < 100000000000 ? number * 1000 : number);
        } else if (raw) date = new Date(raw);
        if (!date || Number.isNaN(date.getTime())) return {day:'—',month:'Date'};
        const language = locale === 'bn' ? 'bn-BD' : 'en-US';
        return {day:new Intl.DateTimeFormat(language,{day:'2-digit'}).format(date),month:new Intl.DateTimeFormat(language,{month:'short'}).format(date)};
    }
    function previewLayoutElement(block, row, column, element, rowIndex, columnIndex, elementIndex) {
        const type = layoutElementType(element);
        const path = `rows.${rowIndex}.columns.${columnIndex}.elements.${elementIndex}`;
        const elementId = layoutElementIdentity(row,column,element,elementIndex,rowIndex,columnIndex);
        const selected = block.uuid===state.selected && state.selectedLayout?.kind==='element' && state.selectedLayout.elementId===elementId;
        let content = '';
        if (type === 'heading') {
            const level = layoutChoice(element.level,layoutHeadingChoices,'h2');
            content = inlineElement(level,element.text||'New heading',`${path}.text`,'heading text',{single:true});
        }
        else if (type === 'rich_text') content = `<div class="simple-layout-preview-rich">${safeLayoutRichHtml(element.body||'')}</div>`;
        else if (type === 'image') {
            const image = safeImage(element.path);
            content = image ? `<figure class="simple-layout-preview-media"><img src="${escapeHtml(image)}" alt="${escapeHtml(element.alt||'')}">${element.caption?`<figcaption>${escapeHtml(element.caption)}</figcaption>`:''}</figure>` : `<div class="simple-layout-preview-media-placeholder"><i class="fa fa-picture-o" aria-hidden="true"></i><span>Choose an image for this content</span></div>`;
        }
        else if (type === 'video') {
            const sourceType = element.source_type === 'youtube' ? 'youtube' : 'upload';
            const source = sourceType === 'youtube' ? explicitHttpsYoutubeEmbedUrl(element.source) : safeImage(element.source);
            const title = element.title || 'Video';
            const media = sourceType === 'youtube' && source
                ? `<iframe src="${escapeHtml(source)}" title="${escapeHtml(title)}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`
                : source ? `<video src="${escapeHtml(source)}" aria-label="${escapeHtml(title)}" controls preload="metadata"></video>` : '';
            content = media ? `<figure class="simple-layout-preview-media">${media}</figure>` : `<div class="simple-layout-preview-media-placeholder"><i class="fa fa-play-circle" aria-hidden="true"></i><span>Choose a video for this content</span></div>`;
        }
        else if (type === 'button') {
            const style = layoutChoice(element.style,layoutButtonStyleChoices,'primary');
            content = inlineElement('span',element.label||'Button',`${path}.label`,'button text',{className:`simple-layout-preview-button simple-layout-preview-button--${style}`,single:true});
        }
        else if (type === 'icon') {
            const size=layoutChoice(element.size,layoutIconSizeChoices,'medium'),style=layoutChoice(element.style,layoutIconStyleChoices,'soft'),accessible=element.decorative===false?String(element.accessible_label||'').trim():'';
            content=`<span class="simple-layout-preview-icon simple-layout-preview-icon--${size} simple-layout-preview-icon--${style}" ${accessible?`role="img" aria-label="${escapeHtml(accessible)}"`:'aria-hidden="true"'}>${previewIcon(element.icon,'fa-heart')}</span>`;
        }
        else if (type === 'file') {
            content = element.path ? `<span class="simple-layout-preview-file"><i class="fa fa-file-text-o" aria-hidden="true"></i><span><strong>${escapeHtml(element.label||'Download file')}</strong>${element.description?`<small>${escapeHtml(element.description)}</small>`:''}</span><i class="fa fa-download" aria-hidden="true"></i></span>` : `<div class="simple-layout-preview-media-placeholder"><i class="fa fa-file-o" aria-hidden="true"></i><span>Choose a public file</span></div>`;
        }
        else if (type === 'card') {
            const image=safeImage(element.image),style=layoutChoice(element.style,layoutCardStyleChoices,'standard');
            content=`<article class="simple-layout-preview-card simple-layout-preview-card--${style}">${image?`<img src="${escapeHtml(image)}" alt="${escapeHtml(element.image_alt||'')}">`:element.icon?`<span class="simple-layout-preview-card__icon">${previewIcon(element.icon,'fa-star')}</span>`:''}<div class="simple-layout-preview-card__copy">${element.eyebrow?`<small>${escapeHtml(element.eyebrow)}</small>`:''}<strong>${escapeHtml(element.heading||'New card')}</strong>${element.body?`<p>${escapeHtml(element.body)}</p>`:''}${element.url&&element.link_label?`<em>${escapeHtml(element.link_label)} <span aria-hidden="true">→</span></em>`:''}</div></article>`;
        }
        else if (type === 'stat') {
            const emphasis=layoutChoice(element.emphasis,layoutStatEmphasisChoices,'standard');
            content=`<div class="simple-layout-preview-stat simple-layout-preview-stat--${emphasis}">${element.icon?previewIcon(element.icon,'fa-bar-chart'):''}<strong>${escapeHtml(element.value||'0')}</strong><span>${escapeHtml(element.label||'People reached')}</span></div>`;
        }
        else if (type === 'quote') {
            const image=safeImage(element.image),style=layoutChoice(element.style,layoutQuoteStyleChoices,'standard');
            content=`<figure class="simple-layout-preview-quote simple-layout-preview-quote--${style}">${image?`<img src="${escapeHtml(image)}" alt="${escapeHtml(element.image_alt||'')}">`:''}<blockquote>${escapeHtml(element.quote||'Add a meaningful quotation.')}</blockquote>${element.attribution||element.role?`<figcaption>${element.attribution?`<strong>${escapeHtml(element.attribution)}</strong>`:''}${element.role?`<span>${escapeHtml(element.role)}</span>`:''}</figcaption>`:''}</figure>`;
        }
        else if (type === 'gallery') {
            const items=(Array.isArray(element.items)?element.items:[]).filter(item=>safeImage(item?.path)).slice(0,12),columns=layoutChoice(element.columns,layoutGalleryColumnChoices,'3');
            content=items.length?`<div class="simple-layout-preview-gallery simple-layout-preview-gallery--${columns}">${items.map(item=>`<figure><img src="${escapeHtml(safeImage(item.path))}" alt="${escapeHtml(item.alt||'')}">${item.caption?`<figcaption>${escapeHtml(item.caption)}</figcaption>`:''}</figure>`).join('')}</div>`:`<div class="simple-layout-preview-media-placeholder"><i class="fa fa-th" aria-hidden="true"></i><span>Add photos to this gallery</span></div>`;
        }
        else if (type === 'accordion') {
            const items=(Array.isArray(element.items)?element.items:[]).filter(item=>String(item?.question||'').trim()).slice(0,12);
            content=items.length?`<div class="simple-layout-preview-accordion">${items.map((item,itemIndex)=>`<details ${itemIndex===0?'open':''} data-layout-preview-interactive><summary data-layout-preview-interactive>${escapeHtml(item.question)}</summary>${item.answer?`<div class="simple-layout-preview-accordion__answer">${safeLayoutRichHtml(item.answer)}</div>`:''}</details>`).join('')}</div>`:`<div class="simple-layout-preview-placeholder">Add a question and answer</div>`;
        }
        else if (type === 'timeline') {
            const items=(Array.isArray(element.items)?element.items:[]).filter(item=>item&&(item.date_label||item.heading||item.body)).slice(0,12),style=layoutChoice(element.style,layoutTimelineStyleChoices,'timeline');
            content=items.length?`<ol class="simple-layout-preview-timeline simple-layout-preview-timeline--${style}">${items.map((item,itemIndex)=>`<li><span class="simple-layout-preview-timeline__marker">${item.icon?previewIcon(item.icon,'fa-circle-o'):`${itemIndex+1}`}</span><div>${item.date_label?`<small>${escapeHtml(item.date_label)}</small>`:''}${item.heading?`<h4>${escapeHtml(item.heading)}</h4>`:''}${item.body?`<p>${escapeHtml(item.body)}</p>`:''}</div></li>`).join('')}</ol>`:`<div class="simple-layout-preview-placeholder">Add a milestone or step</div>`;
        }
        else if (type === 'callout') {
            const tone=layoutChoice(element.tone,layoutCalloutToneChoices,'accent');
            content=`<aside class="simple-layout-preview-callout simple-layout-preview-callout--${tone}">${element.icon?`<span class="simple-layout-preview-callout__icon">${previewIcon(element.icon,'fa-info-circle')}</span>`:''}<div>${element.eyebrow?`<small>${escapeHtml(element.eyebrow)}</small>`:''}<h4>${escapeHtml(element.heading||'Important information')}</h4>${element.body?`<div>${safeLayoutRichHtml(element.body)}</div>`:''}${element.url&&element.link_label?`<em>${escapeHtml(element.link_label)} <span aria-hidden="true">→</span></em>`:''}</div></aside>`;
        }
        else if (type === 'divider') content = '<hr class="simple-layout-preview-divider">';
        else if (type === 'spacer') {
            const size = layoutChoice(element.size,layoutSpacerSizeChoices,'medium');
            content = `<div class="simple-layout-preview-spacer simple-layout-preview-spacer--${size}" role="img" aria-label="${escapeHtml(layoutSpacerSizeChoices[size])} spacer"><span class="simple-layout-sr">${escapeHtml(layoutSpacerSizeChoices[size])} spacer</span></div>`;
        }
        return `<div class="simple-layout-preview-element${selected?' is-layout-selected':''}" data-layout-node="element" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-element="${elementIndex}" data-layout-element-id="${escapeHtml(elementId)}" data-node-label="${escapeHtml(layoutElementChoices[type])}" tabindex="0" role="group" aria-label="Edit ${escapeHtml(layoutElementName(element))}">${content}</div>`;
    }
    function previewLayoutRow(block, row, rowIndex) {
        const preset = layoutChoice(row.layout,layoutPresetChoices,'full');
        const width = layoutChoice(row.width,layoutWidthChoices,'standard');
        const background = layoutChoice(row.background,layoutBackgroundChoices,'default');
        const spacing = layoutChoice(row.spacing,layoutSpacingChoices,'standard');
        const columnCount = layoutPresetColumnCount(preset);
        const columns = row.columns.slice(0,columnCount);
        const rowId = layoutRowIdentity(row,rowIndex);
        const rowSelected = block.uuid===state.selected && state.selectedLayout?.kind==='row' && state.selectedLayout.rowId===rowId;
        return `<section class="simple-layout-preview-row simple-layout-preview-row--${preset.replaceAll('_','-')} simple-layout-preview-row--background-${background} simple-layout-preview-row--spacing-${spacing}${rowSelected?' is-layout-selected':''}" data-layout-node="row" data-layout-row="${rowIndex}" data-layout-row-id="${escapeHtml(rowId)}" data-node-label="Row ${rowIndex+1}" tabindex="${state.editorMode==='layout'?'0':'-1'}" aria-label="Layout row ${rowIndex+1}, ${escapeHtml(layoutPresetChoices[preset])}"><div class="simple-layout-preview-row__inner simple-layout-preview-row--width-${width}"><div class="simple-layout-preview-columns">${columns.map((column,columnIndex)=>{
            const elements = Array.isArray(column.elements) ? column.elements : [];
            const columnId = layoutColumnIdentity(row,column,columnIndex,rowIndex);
            const columnSelected = block.uuid===state.selected && state.selectedLayout?.kind==='column' && state.selectedLayout.columnId===columnId;
            const addButton = canEditLayoutBlock(block) && elements.length<12 ? `<button type="button" class="simple-layout-preview-inserter" data-preview-layout-action="add-content" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}"><i class="fa fa-plus" aria-hidden="true"></i> Add content</button>` : '';
            return `<div class="simple-layout-preview-column${columnSelected?' is-layout-selected':''}" data-layout-node="column" data-layout-row="${rowIndex}" data-layout-column="${columnIndex}" data-layout-column-id="${escapeHtml(columnId)}" data-node-label="Column ${columnIndex+1}" tabindex="${state.editorMode==='layout'?'0':'-1'}" role="group" aria-label="Row ${rowIndex+1}, column ${columnIndex+1}">${elements.length?elements.map((element,elementIndex)=>previewLayoutElement(block,row,column,element,rowIndex,columnIndex,elementIndex)).join(''):`<div class="simple-layout-preview-placeholder">Column ${columnIndex+1}<br>No content yet</div>`}${addButton}</div>`;
        }).join('')}</div></div></section>`;
    }
    function previewBlock(block) {
        // Every root receives data-preview-type="${block.type}" from previewSectionMeta.
        const c = block.content || {};

        if (block.type === 'layout') {
            const guard=layoutLoadGuard(block);
            if (guard.blocked) return `<section ${previewSectionMeta(block)}><div class="simple-layout-preview">${layoutRepairNotice(guard,true)}</div></section>`;
            const rows = layoutRows(block);
            const rowInserter = index => canEditLayoutBlock(block) ? `<button type="button" class="simple-layout-preview-inserter simple-layout-preview-row-inserter" data-preview-layout-action="add-row" data-layout-row-insert="${index}"><i class="fa fa-plus" aria-hidden="true"></i> Add row here</button>` : '';
            const rowsMarkup = rows.length ? `${rows.map((row,rowIndex)=>`${rowInserter(rowIndex)}${previewLayoutRow(block,row,rowIndex)}`).join('')}${rowInserter(rows.length)}` : `${rowInserter(0)}<div class="simple-layout-preview-placeholder">Add a row to begin this visual layout.</div>`;
            return `<section ${previewSectionMeta(block)}><div class="simple-layout-preview" aria-label="Visual layout preview">${rowsMarkup}</div></section>`;
        }

        if (block.type === 'hero') {
            const slides = heroSlides(block);
            const slide = slides[state.heroSlide] || slides[0];
            const image = safeImage(slide.image);
            const opacity = Math.min(100,Math.max(0,Number(slide.overlay_opacity ?? 64))) / 100;
            const actions = `${slide.primary_label?inlineElement('span',slide.primary_label,'slide.primary_label','main button text',{className:'simple-preview-button',single:true}):''}${slide.secondary_label?inlineElement('span',slide.secondary_label,'slide.secondary_label','second button text',{className:'simple-preview-button simple-preview-button--outline',single:true}):''}`;
            return `<section ${previewSectionMeta(block,' simple-preview-block--hero')} style="--simple-hero-overlay:${opacity}">${image?`<img class="simple-preview-hero-image" src="${escapeHtml(image)}" alt="">`:''}<div class="simple-preview-hero-copy">${inlineElement('div',slide.eyebrow,'slide.eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',slide.heading,'slide.heading','main heading',{single:true})}${inlineElement('p',slide.body,'slide.body','description')}<div class="simple-preview-actions">${actions}</div>${slide.report_label?`<span class="simple-preview-hero-report">${previewIcon('report','fa-file-text-o')} ${inlineElement('span',slide.report_label,'slide.report_label','report link text',{single:true})} <span aria-hidden="true">→</span></span>`:''}${slides.length>1?`<small class="simple-preview-carousel-status">Slide ${state.heroSlide+1} of ${slides.length}${c.autoplay===false?' · Manual':' · Auto'}</small>`:''}</div></section>`;
        }

        if (block.type === 'stats') {
            const items = Array.isArray(c.items) ? c.items : [];
            const animated = c.animation_enabled !== false;
            const animationType = ['count_up','fade_up','pop'].includes(c.animation_type) ? c.animation_type : 'count_up';
            const duration = Math.min(5000,Math.max(300,Number(c.animation_duration || 1600)));
            const delay = Math.min(1000,Math.max(0,Number(c.animation_delay ?? 120)));
            return `<section ${previewSectionMeta(block)}>${previewHeading(block)}<div class="simple-preview-stats">${items.map((item,index)=>`<article class="simple-preview-stat${animated?` is-animation-${animationType.replace('_','-')}`:''}" style="--preview-animation-duration:${duration}ms;--preview-animation-delay:${delay*index}ms"><span class="simple-preview-stat__icon">${previewIcon(item.icon,'fa-line-chart')}</span>${inlineElement('strong',item.value,`items.${index}.value`,'statistic value',{single:true})}${inlineElement('span',item.label,`items.${index}.label`,'statistic label',{single:true})}</article>`).join('')}</div>${items.length?'':previewEmpty(c,'Add a statistic to show it here.')}</section>`;
        }

        if(block.type==='rich_text') {
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-rich-text">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}<div class="simple-preview-rich-copy">${safeRichHtml(c.body || '')}</div><p class="simple-preview-edit-hint simple-rich-edit-hint"><i class="fa fa-info-circle" aria-hidden="true"></i> Formatted body copy is read-only here. Use the editor panel to preserve links and formatting.</p></div></section>`;
        }

        if(block.type==='media_text') {
            const mediaType = ['image','video','youtube'].includes(c.media_type) ? c.media_type : 'image';
            let media = '<div class="simple-preview-media-placeholder"><i class="fa fa-picture-o" aria-hidden="true"></i><span>Choose media in the editor panel</span></div>';
            if (mediaType === 'video' && safeImage(c.video_url)) media = `<video src="${escapeHtml(safeImage(c.video_url))}" ${safeImage(c.poster)?`poster="${escapeHtml(safeImage(c.poster))}"`:''} controls preload="metadata"></video>`;
            if (mediaType === 'youtube' && youtubeEmbedUrl(c.youtube_url)) media = `<iframe src="${escapeHtml(youtubeEmbedUrl(c.youtube_url))}" title="${escapeHtml(c.heading||'YouTube video')}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`;
            if (mediaType === 'image' && safeImage(c.image)) media = `<img src="${escapeHtml(safeImage(c.image))}" alt="${escapeHtml(c.image_alt||'')}">`;
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-media${c.image_position==='right'?' simple-preview-media--reverse':''}"><figure>${media}${c.caption?`<figcaption>${escapeHtml(c.caption)}</figcaption>`:''}</figure><div class="simple-preview-media__copy">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}<div class="simple-preview-rich-copy">${safeRichHtml(c.body || '')}</div><p class="simple-preview-edit-hint"><i class="fa fa-info-circle" aria-hidden="true"></i> Formatted body copy is read-only here. Use the editor panel to preserve links and formatting.</p>${c.link_label?inlineElement('span',c.link_label,'link_label','link text',{className:'simple-preview-button',single:true}):''}</div></div></section>`;
        }

        if (block.type === 'ways_to_give') {
            const available = contentOptions.ways_to_give?.items || [];
            const known = new Map([...(contentOptions.ways_to_give?.known_items||[]),...available].map(option=>[String(option.value),option]));
            let items = c.selection_mode === 'manual' ? (c.selected_items||[]).map(token=>known.get(String(token))).filter(option=>option?.active!==false) : available;
            if (c.layout === 'single_cta') items = items.slice(0,1);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block)}<div class="simple-preview-cards simple-preview-ways simple-preview-ways--${escapeHtml(c.layout||'card_grid')}">${items.map(item=>`<article class="simple-preview-card simple-preview-way"><span class="simple-preview-card__icon">${previewIcon('heart','fa-gift')}</span><div><h3>${escapeHtml(item.label||'Giving option')}</h3><p>${escapeHtml(item.destination||item.body||'Managed giving destination')}</p><span class="simple-preview-button">${escapeHtml(c.link_label||'Give now')} →</span></div></article>`).join('')}</div>${items.length?'':previewEmpty(c,'No giving options selected.')}</section>`;
        }

        if (block.type === 'causes' && c.presentation === 'focus_areas') {
            const items = managedPagePreviewItems(block);
            const body = plainText(c.body || '');
            return `<section ${previewSectionMeta(block,' simple-preview-block--focus')}><div class="simple-focus-grid"><header class="simple-focus-tile simple-focus-heading" style="--simple-focus-delay:0ms">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${body?inlineElement('p',body,'body','introduction'):''}<span class="simple-focus-view-all">${inlineElement('span',c.view_all_label||'View all programs','view_all_label','view-all link text',{single:true})} <span aria-hidden="true">→</span></span></header>${items.map((item,index)=>`<article class="simple-focus-tile simple-focus-card" style="--simple-focus-delay:${Math.min(index+1,5)*100}ms"><span class="simple-focus-card__visual">${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:previewIcon(item.icon,'fa-compass')}</span><div class="simple-focus-card__copy"><h3>${escapeHtml(previewItemName(item)||'Published item')}</h3>${item.body?`<p>${escapeHtml(plainText(item.body))}</p>`:''}<span class="simple-focus-card__link">${escapeHtml(c.item_link_label||'Learn more')} <span aria-hidden="true">→</span></span></div></article>`).join('')}</div>${items.length?'':previewEmpty(c,'No published items are available for this selection.')}</section>`;
        }

        if(block.type==='cards') {
            const manual = contentSource(block) === 'manual';
            const items = manual ? (Array.isArray(c.items)?c.items:[]) : managedPagePreviewItems(block);
            const activeCardIndex = selectedCardIndex(block, items);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block,{viewAll:true})}<div class="simple-preview-cards">${items.map((item,index)=>`<article class="simple-preview-card${cardUsesFullRow(block,item,index,items)?' simple-preview-card--full-width':''}${manual&&index===activeCardIndex&&block.uuid===state.selected?' is-card-selected':''}" ${manual?`data-preview-card-index="${index}"`:''}>${manual?`<button type="button" class="simple-preview-card__edit" data-preview-card-select aria-label="Edit card ${index+1}: ${escapeHtml(previewItemName(item)||'Untitled card')}" aria-pressed="${index===activeCardIndex&&block.uuid===state.selected?'true':'false'}"><i class="fa fa-pencil" aria-hidden="true"></i> Edit card</button>`:''}${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:`<span class="simple-preview-card__placeholder">${previewIcon(item.icon,'fa-compass')}</span>`}<div>${item.location?`<small>${previewIcon('map','fa-map-marker')} ${escapeHtml(item.location)}</small>`:item.eyebrow?`<small>${escapeHtml(item.eyebrow)}</small>`:''}${manual?inlineElement('h3',previewItemName(item)||'Item',`items.${index}.heading`,'item heading',{single:true}):`<h3>${escapeHtml(previewItemName(item)||'Published item')}</h3>`}${item.body?manual?inlineElement('p',plainText(item.body),`items.${index}.body`,'item description'):`<p>${escapeHtml(plainText(item.body))}</p>`:''}${item.status?`<span class="simple-preview-card__status">${escapeHtml(item.status)}</span>`:''}${safeManageUrl(item.url)?`<span class="simple-preview-card__link">${escapeHtml(item.link_label||c.item_link_label||'Learn more')} →</span>`:''}</div></article>`).join('')}</div>${items.length?'':previewEmpty(c,'Add cards here or select managed content in the editor panel.')}</section>`;
        }

        if(block.type==='causes') {
            const items = managedPagePreviewItems(block);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block,{viewAll:true})}<div class="simple-preview-cards">${items.map(item=>`<article class="simple-preview-card">${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:`<span class="simple-preview-card__placeholder">${previewIcon(item.icon,'fa-compass')}</span>`}<div><h3>${escapeHtml(previewItemName(item)||'Published program')}</h3>${item.body?`<p>${escapeHtml(plainText(item.body))}</p>`:''}<span class="simple-preview-card__link">${escapeHtml(item.link_label||c.item_link_label||'Learn more')} →</span></div></article>`).join('')}</div>${items.length?'':previewEmpty(c,'No published programs are available for this selection.')}</section>`;
        }

        if (block.type === 'events') {
            const items = managedPagePreviewItems(block);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block,{viewAll:true})}<div class="simple-preview-events">${items.map(item=>{const date=previewEventDate(item);return `<article class="simple-preview-event">${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:''}<span class="simple-preview-event__date"><strong>${escapeHtml(date.day)}</strong><small>${escapeHtml(date.month)}</small></span><div><h3>${escapeHtml(previewItemName(item)||'Published event')}</h3>${item.body?`<p>${escapeHtml(plainText(item.body))}</p>`:''}<b>${escapeHtml(item.link_label||c.item_link_label||'Read more')} →</b></div></article>`}).join('')}</div>${items.length?'':previewEmpty(c,'Upcoming events and field updates will appear here automatically.')}</section>`;
        }

        if(block.type==='cta'&&!['campaign','campus-actions'].includes(c.variant)) {
            // Stable root class contract: class="simple-preview-block simple-preview-block--cta…"
            const primary = c.primary_label?inlineElement('span',c.primary_label,'primary_label','main button text',{className:'simple-preview-button',single:true}):'';
            const secondary = c.secondary_label?inlineElement('span',c.secondary_label,'secondary_label','second button text',{className:'simple-preview-button simple-preview-button--outline',single:true}):'';
            const hasActions = Boolean(primary || secondary);
            return `<section ${previewSectionMeta(block)}><div class="simple-cta-panel" data-actions="${hasActions}"><span class="simple-cta-signal" aria-hidden="true">${previewIcon('heart','fa-heart')}</span><div class="simple-cta-content">${inlineElement('div',c.eyebrow||'','eyebrow','small heading',{className:'simple-preview-eyebrow',single:true})}${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body||''),'body','description')}</div>${hasActions?`<div class="simple-cta-actions">${primary}${secondary}</div>`:''}</div></section>`;
        }

        if(block.type==='testimonials') {
            const testimonial = testimonialPreview(block);
            const story = testimonial.story;
            const photo = previewItemImage(story);
            const navigation = testimonial.items.length > 1 ? `<nav class="simple-testimonial-nav" aria-label="Community story preview navigation"><button type="button" data-testimonial-step="-1" aria-label="Previous community story">${previewIcon('', 'fa-arrow-left')}</button>${testimonial.items.map((item,index)=>`<button type="button" class="simple-testimonial-dot" data-testimonial-index="${index}" aria-label="Show community story ${index+1} of ${testimonial.items.length}" ${index===testimonial.index?'aria-current="true"':''}><span></span></button>`).join('')}<button type="button" data-testimonial-step="1" aria-label="Next community story">${previewIcon('', 'fa-arrow-right')}</button></nav>` : '';
            const card = story ? `<div class="simple-testimonial-card" aria-live="polite">${previewIcon('', 'fa-quote-left')}<blockquote>${escapeHtml(story.quote||story.body||'')}</blockquote><div class="simple-testimonial-person">${photo?`<img src="${escapeHtml(photo)}" alt="${escapeHtml(previewItemName(story))}">`:''}<span><strong>${escapeHtml(previewItemName(story)||'Community member')}</strong>${story.designation?`<small>${escapeHtml(story.designation)}</small>`:''}</span></div>${navigation}</div>` : previewEmpty(c,'Approved community stories will appear here automatically.');
            return `<section ${previewSectionMeta(block)}>${previewHeading(block)}${card}</section>`;
        }

        if (block.type === 'team') {
            const items = managedPagePreviewItems(block);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block)}<div class="simple-preview-team">${items.map(item=>{const name=previewItemName(item)||'Team member';const image=previewItemImage(item);const initials=name.split(/\s+/).slice(0,2).map(part=>part[0]||'').join('').toUpperCase();const groupName=item.group_name||item.group?.name||'';return `<article class="simple-preview-team-card"><div class="simple-preview-team-card__media">${image?`<img src="${escapeHtml(image)}" alt="${escapeHtml(item.image_alt||name)}">`:`<span aria-hidden="true">${escapeHtml(initials||'IGF')}</span>`}</div><h3>${escapeHtml(name)}</h3>${item.designation||item.position||item.title?`<p>${escapeHtml(item.designation||item.position||item.title)}</p>`:''}${groupName?`<small>${escapeHtml(groupName)}</small>`:''}</article>`}).join('')}</div>${items.length?'':previewEmpty(c,'Published board and team members will appear here automatically.')}</section>`;
        }

        if (block.type === 'partners') {
            const items = Array.isArray(c.items) ? c.items : [];
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-partners">${previewHeading(block)}<ul>${items.map((item,index)=>`<li>${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:previewIcon('', 'fa-handshake-o')}${inlineElement('strong',previewItemName(item)||'Partner',`items.${index}.heading`,'partner name',{single:true})}</li>`).join('')}</ul>${items.length?'':previewEmpty(c,'Add partner logos in the editor panel.')}</div></section>`;
        }

        if (block.type === 'faq') {
            const items = Array.isArray(c.items) ? c.items : [];
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-faq">${previewHeading(block)}<div class="simple-preview-faq__items">${items.map((item,index)=>`<details ${index===0?'open':''}><summary>${inlineElement('span',item.heading||'Question',`items.${index}.heading`,'question',{single:true})}${previewIcon('', 'fa-plus')}</summary><div class="simple-preview-rich-copy">${safeRichHtml(item.body||'')}</div></details>`).join('')}</div>${items.length?'':previewEmpty(c,'Add a question in the editor panel.')}</div></section>`;
        }

        if (block.type === 'timeline') {
            const items = Array.isArray(c.items) ? c.items : [];
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-timeline">${previewHeading(block)}<ol>${items.map((item,index)=>`<li><span>${inlineElement('b',item.eyebrow||String(index+1).padStart(2,'0'),`items.${index}.eyebrow`,'step label or year',{single:true})}</span><div>${inlineElement('h3',item.heading||'Milestone',`items.${index}.heading`,'milestone heading',{single:true})}<div class="simple-preview-rich-copy">${safeRichHtml(item.body||'')}</div></div></li>`).join('')}</ol>${items.length?'':previewEmpty(c,'Add a milestone in the editor panel.')}</div></section>`;
        }

        if (block.type === 'gallery') {
            const items = contentSource(block) === 'manual' ? (Array.isArray(c.items)?c.items:[]) : managedPagePreviewItems(block);
            return `<section ${previewSectionMeta(block)}>${previewHeading(block,{viewAll:true})}<div class="simple-preview-gallery">${items.map((item,index)=>`<figure>${previewItemImage(item)?`<img src="${escapeHtml(previewItemImage(item))}" alt="${escapeHtml(item.image_alt||previewItemName(item))}">`:`<span class="simple-preview-gallery__placeholder">${previewIcon('', 'fa-picture-o')}</span>`}${previewItemName(item)?`<figcaption>${contentSource(block)==='manual'?inlineElement('span',previewItemName(item),`items.${index}.heading`,'photo caption',{single:true}):escapeHtml(previewItemName(item))}</figcaption>`:''}</figure>`).join('')}</div>${items.length?'':previewEmpty(c,'Published gallery photos will appear here automatically.')}</section>`;
        }

        if (block.type === 'video') {
            const source = safeImage(c.video_url);
            const embed = videoEmbedUrl(c.video_url);
            const frame = embed ? `<iframe src="${escapeHtml(embed)}" title="${escapeHtml(c.heading||'Video')}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>` : source ? `<video src="${escapeHtml(source)}" ${safeImage(c.poster)?`poster="${escapeHtml(safeImage(c.poster))}"`:''} controls preload="metadata"></video>` : `<div class="simple-preview-video__placeholder">${previewIcon('', 'fa-play-circle')}<span>Choose an uploaded video or enter a trusted YouTube or Vimeo link</span></div>`;
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-video">${previewHeading(block)}<div class="simple-preview-video__frame">${frame}</div>${c.caption?`<p class="simple-preview-video__caption">${escapeHtml(c.caption)}</p>`:''}</div></section>`;
        }

        if(block.type==='cta') {
            return `<section ${previewSectionMeta(block)}>${previewHeading(block)}<p class="simple-banner-guidance">This campaign-style call to action is preserved. Use Advanced editor for its specialized form preview.</p></section>`;
        }

        if (block.type === 'newsletter') {
            return `<section ${previewSectionMeta(block)}><div class="simple-preview-newsletter"><div class="simple-preview-newsletter__copy">${inlineElement('h2',c.heading||block.label,'heading','section heading',{single:true})}${inlineElement('p',plainText(c.body||''),'body','description')}</div><div class="simple-preview-newsletter__form" role="group" aria-label="Newsletter form preview"><label><span>${escapeHtml(c.email_label||'Email address')}</span><input type="email" value="" placeholder="${escapeHtml(c.email_placeholder||'you@example.com')}" readonly tabindex="-1"></label><span class="simple-preview-button">${inlineElement('span',c.button_label||'Subscribe','button_label','button text',{single:true})}</span><p class="simple-preview-newsletter__consent"><span class="simple-preview-checkbox" aria-hidden="true"></span>${escapeHtml(c.consent_text||'I agree to receive updates.')} ${c.privacy_label?`<u>${escapeHtml(c.privacy_label)}</u>`:''}</p></div></div></section>`;
        }

        return `<section ${previewSectionMeta(block)}>${previewHeading(block)}<p class="simple-banner-guidance">This section’s specialized content is preserved. Open Advanced editor for its complete preview.</p></section>`;
    }
    function setInlineValue(block, path, value) {
        if (path.startsWith('slide.')) heroSlides(block)[state.heroSlide][path.split('.')[1]] = value;
        else if (path.startsWith('rows.')) {
            const [,rowIndex,,columnIndex,,elementIndex,key] = path.split('.');
            const element = block.content?.rows?.[Number(rowIndex)]?.columns?.[Number(columnIndex)]?.elements?.[Number(elementIndex)];
            if (element && key) element[key] = value;
        }
        else if (path.startsWith('items.')) { const [,index,key]=path.split('.'); block.content.items[Number(index)][key]=value; }
        else block.content[path] = value;
    }
    function wireInlinePreview() {
        preview.querySelectorAll('[data-inline-path]').forEach(element => {
            element.addEventListener('click', event => event.stopPropagation());
            element.addEventListener('focus', () => {
                const section=element.closest('[data-preview-block]'); const uuid=section?.dataset.previewBlock;
                const card=element.closest('[data-preview-card-index]');
                const layoutNode=element.closest('[data-layout-node="element"]');
                const layoutBlock=state.blocks.find(item=>item.uuid===uuid);
                if(uuid&&card&&(uuid!==state.selected||Number(card.dataset.previewCardIndex)!==Number(state.cardIndexes[uuid]??0)))selectPreviewCard(uuid,Number(card.dataset.previewCardIndex));
                else if(layoutNode&&layoutBlock?.type==='layout')selectLayoutNode(layoutBlock,'element',Number(layoutNode.dataset.layoutRow),Number(layoutNode.dataset.layoutColumn),Number(layoutNode.dataset.layoutElement),{preservePreview:true});
                else if(uuid && uuid!==state.selected){state.selected=uuid;state.heroSlide=0;assignLayoutSelection(layoutBlock);renderList();renderInspector();renderBreadcrumb();syncPreviewSelection();}
                recordHistory();
            });
            element.addEventListener('input', () => { const block=state.blocks.find(item=>item.uuid===element.closest('[data-preview-block]')?.dataset.previewBlock); if(!block)return; setInlineValue(block,(element.dataset.inlinePath || ''),element.textContent.trim()); markDirty('block'); });
            element.addEventListener('blur', () => renderInspector());
            element.addEventListener('keydown', event => { if(element.dataset.inlineSingle==='true' && event.key==='Enter'){event.preventDefault();element.blur();} });
        });
    }
    function wireCardPreviewSelection() {
        preview.querySelectorAll('[data-preview-card-index]').forEach(card => {
            card.addEventListener('click', event => {
                if (event.target.closest('[data-inline-path]')) return;
                if (event.target.closest('[data-preview-card-select]')) event.preventDefault();
                event.stopPropagation();
                const uuid = card.closest('[data-preview-block]')?.dataset.previewBlock;
                selectPreviewCard(uuid, Number(card.dataset.previewCardIndex));
            });
        });
    }
    function wireLayoutPreviewSelection() {
        preview.querySelectorAll('[data-layout-node]').forEach(node => {
            const choose = event => {
                if (event.target.closest('[data-preview-layout-action],[data-inline-path]')) return;
                const kind = node.dataset.layoutNode;
                const interactive = event.target.closest('[data-layout-preview-interactive]');
                if (!interactive) event.preventDefault();
                event.stopPropagation();
                const uuid = node.closest('[data-preview-block]')?.dataset.previewBlock;
                const block = state.blocks.find(item=>item.uuid===uuid);
                if (kind !== 'element' && state.editorMode !== 'layout') {
                    selectSection(uuid,{force:true,focus:'inspector'});
                    return;
                }
                selectLayoutNode(block,kind,Number(node.dataset.layoutRow),Number(node.dataset.layoutColumn ?? -1),Number(node.dataset.layoutElement ?? -1),{focus:'inspector'});
            };
            node.addEventListener('click',choose);
            node.addEventListener('keydown',event=>{if(event.target===node&&['Enter',' '].includes(event.key))choose(event)});
        });
        preview.querySelectorAll('[data-preview-layout-action]').forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const uuid = button.closest('[data-preview-block]')?.dataset.previewBlock;
            const block = state.blocks.find(item=>item.uuid===uuid);
            if (!block || block.type!=='layout') return;
            if (button.dataset.previewLayoutAction === 'add-content') {
                openElementPicker(block,Number(button.dataset.layoutRow),Number(button.dataset.layoutColumn));
                return;
            }
            if (!canEditLayoutBlock(block)) return;
            const rows = layoutRows(block);
            if (rows.length >= 12) return notify('A visual layout can contain up to twelve rows.');
            const insertAt = Math.min(Math.max(Number(button.dataset.layoutRowInsert)||0,0),rows.length);
            state.selected=block.uuid; state.expandedSections.add(block.uuid); recordHistory(); rows.splice(insertAt,0,newLayoutRow()); assignLayoutSelection(block,'row',insertAt); markDirty('block'); renderAll(); restoreSelectionFocus('inspector');
        }));
    }
    function wireTestimonialPreview() {
        preview.querySelectorAll('[data-testimonial-index],[data-testimonial-step]').forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                const uuid = button.closest('[data-preview-block]')?.dataset.previewBlock;
                const block = state.blocks.find(item => item.uuid === uuid);
                if (!block) return;
                const testimonial = testimonialPreview(block);
                if (!testimonial.items.length) return;
                const requestedIndex = button.hasAttribute('data-testimonial-index')
                    ? Number(button.dataset.testimonialIndex)
                    : testimonial.index + Number(button.dataset.testimonialStep || 0);
                state.testimonialIndexes[uuid] = (requestedIndex + testimonial.items.length) % testimonial.items.length;
                renderPreview();
            });
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
        preview.querySelectorAll('[data-preview-block]').forEach(section=>{
            const choose=event=>{if(!event.target.closest('[data-inline-path],[data-layout-node],[data-preview-layout-action]'))selectSection(section.dataset.previewBlock,{force:true,focus:'inspector'})};
            section.addEventListener('click',choose);
            section.addEventListener('keydown',event=>{if(event.target===section&&['Enter',' '].includes(event.key)){event.preventDefault();selectSection(section.dataset.previewBlock,{force:true,focus:'inspector'})}});
        });
        wireLayoutPreviewSelection();
        wireCardPreviewSelection();
        wireTestimonialPreview();
        if(permissions.edit)wireInlinePreview();
    }
    function renderBreadcrumb() {
        const breadcrumb = document.getElementById('simple-selection-breadcrumb');
        const block = current();
        if (!breadcrumb) return;
        if (!block) { breadcrumb.innerHTML='<span>Page</span><i class="fa fa-chevron-right" aria-hidden="true"></i><span>Nothing selected</span>'; return; }
        const selection = resolveLayoutSelection(block);
        const crumbs = [{kind:'section',label:block.label||typeLabels[block.type]||'Section'}];
        if (block.type==='layout' && selection.row) crumbs.push({kind:'row',label:`Row ${selection.rowIndex+1}`,row:selection.rowIndex});
        if (block.type==='layout' && selection.column) crumbs.push({kind:'column',label:`Column ${selection.columnIndex+1}`,row:selection.rowIndex,column:selection.columnIndex});
        if (block.type==='layout' && selection.element) crumbs.push({kind:'element',label:layoutElementChoices[layoutElementType(selection.element)],row:selection.rowIndex,column:selection.columnIndex,element:selection.elementIndex});
        breadcrumb.innerHTML = `<span><i class="fa fa-file-o" aria-hidden="true"></i> Page</span>${crumbs.map((crumb,index)=>`<i class="fa fa-chevron-right" aria-hidden="true"></i><button type="button" data-breadcrumb-kind="${crumb.kind}" ${Number.isInteger(crumb.row)?`data-layout-row="${crumb.row}"`:''} ${Number.isInteger(crumb.column)?`data-layout-column="${crumb.column}"`:''} ${Number.isInteger(crumb.element)?`data-layout-element="${crumb.element}"`:''} aria-current="${index===crumbs.length-1?'page':'false'}">${escapeHtml(crumb.label)}</button>`).join('')}`;
    }
    function syncModeUi() {
        const editor = document.getElementById('simple-editor');
        editor.dataset.editorMode = state.editorMode;
        document.querySelectorAll('[data-editor-mode]').forEach(button=>{const active=button.dataset.editorMode===state.editorMode;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active))});
        const tip = document.getElementById('simple-canvas-tip');
        if (tip) {
            tip.dataset.mode = state.editorMode;
            const text = tip.querySelector('span');
            if (text) text.textContent = permissions.edit ? (state.editorMode==='content'?'Click text or another content item to edit it':'Click a row, column, or content item to customize the layout') : 'Previewing the page in read-only mode';
        }
    }
    function setEditorMode(mode) {
        const next = mode === 'layout' ? 'layout' : 'content';
        if (next === state.editorMode) return;
        state.editorMode = next;
        const block = current();
        const selection = resolveLayoutSelection(block);
        if (next === 'content' && ['row'].includes(selection.kind)) assignLayoutSelection(block);
        renderAll();
        document.querySelector(`[data-editor-mode="${next}"]`)?.focus();
    }
    function renderAll(){syncModeUi();renderList();renderPreview();renderInspector();renderBreadcrumb();syncPreviewSelection()}

    async function saveChanges(){
        if(!permissions.edit||state.busy)return;
        if(!hasDirty())return notify('Everything is already saved.');
        if(!hasSavableDirty())return notify('This visual layout is locked so its saved content stays safe. You can still edit and save another section.');
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
            const savableDirtyBlocks=state.blocks.filter(block=>state.dirtyBlocks.has(block.uuid)&&!layoutIsBlocked(block));
            if(savableDirtyBlocks.length)body.blocks=savableDirtyBlocks.map(block=>{
                syncHeroFirstSlide(block);
                return {uuid:block.uuid,label:block.label,content:block.content||{},is_enabled:!!block.is_enabled,show_on_desktop:block.show_on_desktop!==false,show_on_mobile:block.show_on_mobile!==false,available_from:block.available_from||null,available_until:block.available_until||null,expected_reusable_version:block.reusable_version};
            });
            if(state.dirtyOrder)body.order=state.blocks.map(item=>item.uuid);
            if(legacyContentNeedsConversion&&(body.blocks||[]).some(block=>block.is_enabled)){
                if(!confirmLegacyContentConversion('show this first section'))return;
                body.convert_legacy_content=true;
            }
            const payload=await request(routes.simpleSave,'PUT',body);
            state.validationError=null;
            acceptConvertedLegacyBlock(payload);
            (payload.blocks||[]).forEach(saved=>{const block=state.blocks.find(item=>item.uuid===saved.uuid);if(block)Object.assign(block,saved)});
            if(payload.page){document.getElementById('simple-page-heading').textContent=payload.page.name;document.getElementById('simple-page-name').value=payload.page.name;document.getElementById('simple-page-zakat-eligible').checked=!!payload.page.is_zakat_eligible;document.getElementById('simple-page-funding-project').checked=!!payload.page.is_funding_project;syncFundingProjectControls()}
            const submittedBlockUuids=new Set(savableDirtyBlocks.map(block=>block.uuid));
            state.dirtyBlocks=new Set([...state.dirtyBlocks].filter(uuid=>!submittedBlockUuids.has(uuid)));
            if(body.page)state.dirtyPage=false;
            if(body.order)state.dirtyOrder=false;
            if(!hasDirty()){state.undo=[];state.redo=[];clearDraft()}else scheduleDraft();
            updateSaveState();renderAll();notify(payload.message);
        }catch(error){if(!focusLayoutValidationError(error))notify(error.message)}finally{state.busy=false;updateSaveState()}
    }
    async function addSection(type){if(!permissions.create)return;const convert=legacyContentNeedsConversion;if(convert&&!confirmLegacyContentConversion('add a new section'))return;try{const payload=await request(routes.storeBlock,'POST',{locale,type,is_enabled:false,convert_legacy_content:convert});acceptConvertedLegacyBlock(payload);payload.block.is_enabled=true;state.blocks.push(payload.block);state.selected=payload.block.uuid;state.heroSlide=0;state.dirtyBlocks.add(payload.block.uuid);updateSaveState();scheduleDraft();closeModal(document.getElementById('add-section-modal'));renderAll();notify(convert?payload.message:'Section added as a draft. Add your content, then choose Save changes to show it on the website.')}catch(error){notify(error.message)}}
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
        const convert=legacyContentNeedsConversion;
        if(convert&&!confirmLegacyContentConversion('add this saved section'))return;
        try{
            const payload=await request(routes.attachReusable,'POST',{locale,reusable_uuid:reusableUuid,convert_legacy_content:convert});
            acceptConvertedLegacyBlock(payload);
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
    function openDeleteConfirmation(blockUuid = state.selected, returnFocus = document.activeElement) {
        if (!permissions.delete || state.busy || state.pendingDeleteUuid) return;
        const block = state.blocks.find(item => item.uuid === blockUuid);
        const modal = document.getElementById('simple-delete-modal');
        if (!block || !modal) return;
        state.pendingDeleteUuid = block.uuid;
        state.modalReturn = returnFocus;
        const unsavedWarning = state.dirtyBlocks.has(block.uuid)
            ? ' Unsaved edits in this section will be discarded and are not included in the restorable revision.'
            : '';
        const otherUnsavedChanges = state.dirtyPage
            || state.dirtyOrder
            || [...state.dirtyBlocks].some(uuid => uuid !== block.uuid);
        const otherChangesNote = otherUnsavedChanges
            ? ' Your other unsaved page changes will remain in this editor.'
            : '';
        document.getElementById('simple-delete-title').textContent = `Move “${block.label || typeLabels[block.type] || 'this section'}” to trash?`;
        document.getElementById('simple-delete-description').textContent = `This section will disappear from the page after you confirm.${unsavedWarning}${otherChangesNote}`;
        const status = document.getElementById('simple-delete-status');
        status.textContent = '';
        status.classList.remove('is-error');
        modal.hidden = false;
        modal.querySelector('.simple-reusable-actions [data-cancel-section-delete]').focus();
    }
    function closeDeleteConfirmation({restoreFocus = true} = {}) {
        if (state.busy) return;
        const modal = document.getElementById('simple-delete-modal');
        if (!modal) return;
        modal.hidden = true;
        state.pendingDeleteUuid = null;
        const returnFocus = state.modalReturn;
        state.modalReturn = null;
        if (restoreFocus && returnFocus?.isConnected) returnFocus.focus();
    }
    async function confirmDeleteSection() {
        if (!permissions.delete || state.busy || !state.pendingDeleteUuid) return;
        const block = state.blocks.find(item => item.uuid === state.pendingDeleteUuid);
        if (!block) return closeDeleteConfirmation();
        const modal = document.getElementById('simple-delete-modal');
        const confirmButton = document.getElementById('simple-confirm-delete');
        const status = document.getElementById('simple-delete-status');
        state.busy = true;
        modal.setAttribute('aria-busy', 'true');
        status.textContent = `Moving ${block.label || 'section'} to trash…`;
        status.classList.remove('is-error');
        status.focus();
        modal.querySelectorAll('button').forEach(button => { button.disabled = true; });
        confirmButton.querySelector('span').textContent = 'Moving to trash…';
        updateSaveState();
        let deleted = false;
        try {
            const payload = await request(endpoint(routes.destroy, block.uuid), 'DELETE', {locale});
            const wasSelected = block.uuid === state.selected;
            const deletedIndex = state.blocks.findIndex(item => item.uuid === block.uuid);
            const nextSelection = state.blocks[deletedIndex + 1]?.uuid || state.blocks[deletedIndex - 1]?.uuid || null;
            state.dirtyBlocks.delete(block.uuid);
            state.blocks = state.blocks.filter(item => item.uuid !== block.uuid);
            if (wasSelected) state.selected = nextSelection;
            state.undo = [];
            state.redo = [];
            state.pendingDeleteUuid = null;
            state.modalReturn = null;
            modal.hidden = true;
            deleted = true;
            scheduleDraft();
            renderAll();
            notify(payload.message);
            requestAnimationFrame(() => {
                const nextControl = state.selected
                    ? list.querySelector(`[data-select="${state.selected}"]`)
                    : document.getElementById('open-add-section');
                nextControl?.focus();
            });
        } catch (error) {
            status.textContent = error.message;
            status.classList.add('is-error');
            notify(error.message);
        } finally {
            state.busy = false;
            modal.removeAttribute('aria-busy');
            if (!deleted) {
                modal.querySelectorAll('button').forEach(button => { button.disabled = false; });
                confirmButton.querySelector('span').textContent = 'Move to trash';
                confirmButton.focus();
            }
            updateSaveState();
        }
    }
    function deleteSection(blockUuid = state.selected, returnFocus = document.activeElement) {
        openDeleteConfirmation(blockUuid, returnFocus);
    }
    function wireOrdering() {
        let dragState = null;
        list.querySelectorAll('[data-select]').forEach(button => button.addEventListener('click', () => selectSection(button.dataset.select,{force:true,focus:'navigator'})));
        list.querySelectorAll('[data-expand-section]').forEach(button => button.addEventListener('click', () => {
            const uuid = button.dataset.expandSection;
            if (state.expandedSections.has(uuid)) state.expandedSections.delete(uuid); else state.expandedSections.add(uuid);
            renderList();
            requestAnimationFrame(()=>list.querySelector(`[data-expand-section="${uuid}"]`)?.focus());
        }));
        list.querySelectorAll('[data-navigator-kind]').forEach(button => button.addEventListener('click', () => {
            const block = state.blocks.find(item=>item.uuid===button.closest('[data-section]')?.dataset.section);
            selectLayoutNode(block,button.dataset.navigatorKind,Number(button.dataset.layoutRow),Number(button.dataset.layoutColumn ?? -1),Number(button.dataset.layoutElement ?? -1),{focus:'navigator'});
        }));
        list.querySelectorAll('[data-delete-section]').forEach(button => button.addEventListener('click', event => {
            event.stopPropagation();
            deleteSection(button.dataset.deleteSection, event.currentTarget);
        }));
        if (!permissions.edit) return;
        list.querySelectorAll('[data-move]').forEach(button => button.addEventListener('click', () => {
            const index = Number(button.dataset.index);
            const target = button.dataset.move === 'up' ? index - 1 : index + 1;
            if (target < 0 || target >= state.blocks.length) return;
            recordHistory();
            [state.blocks[index], state.blocks[target]] = [state.blocks[target], state.blocks[index]];
            markDirty('order');
            renderAll();
        }));
        list.querySelectorAll('[data-section]').forEach(item => {
            const handle = item.querySelector('.simple-drag');
            handle?.addEventListener('pointerdown', event => {
                if (event.button !== 0) return;
                dragState = {item, pointerId:event.pointerId, startX:event.clientX, startY:event.clientY, moved:false};
                handle.setPointerCapture?.(event.pointerId);
            });
            handle?.addEventListener('pointermove', event => {
                if (!dragState || dragState.item !== item || dragState.pointerId !== event.pointerId) return;
                if (!dragState.moved && Math.hypot(event.clientX - dragState.startX, event.clientY - dragState.startY) < 6) return;
                if (!dragState.moved) {
                    dragState.moved = true;
                    item.classList.add('is-dragging');
                }
                event.preventDefault();
                const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-section]');
                if (target && target !== item) {
                    const rect = target.getBoundingClientRect();
                    list.insertBefore(item, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
                }
                const sidebar = list.closest('.simple-sections');
                const sidebarRect = sidebar?.getBoundingClientRect();
                if (sidebar && sidebarRect) {
                    if (event.clientY < sidebarRect.top + 48) sidebar.scrollTop -= 12;
                    else if (event.clientY > sidebarRect.bottom - 48) sidebar.scrollTop += 12;
                }
            });
            const finishDrag = event => {
                if (!dragState || dragState.item !== item || dragState.pointerId !== event.pointerId) return;
                const moved = dragState.moved;
                dragState = null;
                if (handle.hasPointerCapture?.(event.pointerId)) handle.releasePointerCapture(event.pointerId);
                item.classList.remove('is-dragging');
                if (!moved) return;
                const reordered = [...list.querySelectorAll('[data-section]')]
                    .map(node => state.blocks.find(block => block.uuid === node.dataset.section));
                if (reordered.every((block, index) => block?.uuid === state.blocks[index]?.uuid)) return;
                recordHistory();
                state.blocks = reordered;
                markDirty('order');
                renderAll();
            };
            handle?.addEventListener('pointerup', finishDrag);
            handle?.addEventListener('pointercancel', finishDrag);
        });
    }
    function openMedia(target){if(!permissions.edit)return;state.mediaTarget={...target,modalId:'media-modal'};state.modalReturn=document.activeElement;document.getElementById('media-modal').hidden=false;document.querySelector('#media-modal .simple-close').focus()}
    function openVideoMedia(target){if(!permissions.edit)return;state.mediaTarget={...target,modalId:'video-media-modal'};state.modalReturn=document.activeElement;document.getElementById('video-media-modal').hidden=false;document.querySelector('#video-media-modal .simple-close').focus()}
    function openDocumentMedia(target){if(!permissions.edit)return;state.mediaTarget={...target,modalId:'document-media-modal'};state.modalReturn=document.activeElement;document.getElementById('document-media-modal').hidden=false;document.querySelector('#document-media-modal .simple-close').focus()}
    function closeModal(modal){if(!modal)return;modal.hidden=true;if(modal.id==='element-picker-modal')state.elementPickerTarget=null;if(['media-modal','video-media-modal','document-media-modal'].includes(modal.id))state.mediaTarget=null;state.modalReturn?.focus();state.modalReturn=null}
    function chooseMedia(url){if(!permissions.edit)return;const block=current();if(!block||!state.mediaTarget)return;recordHistory();const target=state.mediaTarget;if(target.kind==='slide')heroSlides(block)[state.heroSlide][target.key]=url;else if(target.kind==='card')block.content.items[target.index][target.key]=url;else if(target.kind==='layout'){const element=block.content?.rows?.[target.row]?.columns?.[target.column]?.elements?.[target.element];if(!element)return;element[target.key]=url}else if(target.kind==='layout-item'){const item=block.content?.rows?.[target.row]?.columns?.[target.column]?.elements?.[target.element]?.items?.[target.item];if(!item)return;item[target.key]=url}else block.content[target.key]=url;state.validationError=null;markDirty('block');const modal=document.getElementById(target.modalId||'media-modal');closeModal(modal);renderAll()}
    document.getElementById('simple-media-grid').addEventListener('click',event=>{const option=event.target.closest('[data-media-url]');if(option)chooseMedia(option.dataset.mediaUrl)});
    document.getElementById('simple-video-grid').addEventListener('click',event=>{const option=event.target.closest('[data-video-media-url]');if(option)chooseMedia(option.dataset.videoMediaUrl)});
    document.getElementById('simple-document-grid').addEventListener('click',event=>{const option=event.target.closest('[data-document-media-url]');if(option)chooseMedia(option.dataset.documentMediaUrl)});
    document.getElementById('simple-media-upload')?.addEventListener('change',async event=>{if(!permissions.create||!permissions.edit)return;const file=event.target.files[0];if(!file)return;const form=new FormData();form.append('locale',locale);form.append('file',file);try{const payload=await request(routes.media,'POST',form,true);const grid=document.getElementById('simple-media-grid');grid.insertAdjacentHTML('afterbegin',`<button class="simple-media-option" type="button" data-media-url="${escapeHtml(payload.asset.url)}"><img src="${escapeHtml(payload.asset.url)}" alt=""></button>`);chooseMedia(payload.asset.url);notify(payload.message)}catch(error){notify(error.message)}finally{event.target.value=''}});
    document.getElementById('simple-video-upload')?.addEventListener('change',async event=>{if(!permissions.create||!permissions.edit)return;const file=event.target.files[0];if(!file)return;if(!/^video\/(mp4|webm)$/.test(file.type)){notify('Choose a supported MP4 or WebM video.');event.target.value='';return}const form=new FormData();form.append('locale',locale);form.append('file',file);form.append('media_kind','video');try{const payload=await request(routes.media,'POST',form,true);const grid=document.getElementById('simple-video-grid');grid.querySelector('.simple-empty')?.remove();grid.insertAdjacentHTML('afterbegin',`<button class="simple-media-option" type="button" data-video-media-url="${escapeHtml(payload.asset.url)}" aria-label="Choose ${escapeHtml(payload.asset.original_name)}"><video src="${escapeHtml(payload.asset.url)}" muted preload="metadata" aria-hidden="true"></video></button>`);chooseMedia(payload.asset.url);notify(payload.message)}catch(error){notify(error.message)}finally{event.target.value=''}});
    document.getElementById('simple-document-upload')?.addEventListener('change',async event=>{if(!permissions.create||!permissions.edit)return;const file=event.target.files[0];if(!file)return;const supported=/\.(?:pdf|docx?|xlsx?)$/i.test(file.name)&&(!file.type||['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(file.type));if(!supported){notify('Choose a PDF, Word, or Excel file.');event.target.value='';return}const form=new FormData();form.append('locale',locale);form.append('file',file);form.append('media_kind','document');try{const payload=await request(routes.media,'POST',form,true);const grid=document.getElementById('simple-document-grid');grid.querySelector('.simple-empty')?.remove();grid.insertAdjacentHTML('afterbegin',`<button class="simple-document-option" type="button" data-document-media-url="${escapeHtml(payload.asset.url)}" aria-label="Choose ${escapeHtml(payload.asset.original_name)}"><i class="fa fa-file-text-o" aria-hidden="true"></i><span><strong>${escapeHtml(payload.asset.original_name)}</strong><small>${escapeHtml(payload.asset.mime_type||'Public document')}</small></span></button>`);chooseMedia(payload.asset.url);notify(payload.message)}catch(error){notify(error.message)}finally{event.target.value=''}});

    document.querySelectorAll('[data-save-changes]').forEach(button=>button.addEventListener('click',saveChanges));
    function refreshSimplePageThumbnailPreview(){const select=document.getElementById('simple-page-thumbnail'),image=document.getElementById('simple-page-thumbnail-preview'),url=select.selectedOptions[0]?.dataset.url||'';image.src=url;image.hidden=url===''}
    function syncFundingProjectControls(clearZakat = false){const funding=document.getElementById('simple-page-funding-project'),zakat=document.getElementById('simple-page-zakat-eligible');if(!funding||!zakat)return;if(clearZakat&&!funding.checked)zakat.checked=false;zakat.disabled=!permissions.edit||!canManageFundingEligibility||!funding.checked}
    const pageSettingInputs=document.querySelectorAll('#simple-page-name,#simple-page-status,#simple-page-thumbnail,#simple-page-category,#simple-page-banner,#simple-page-zakat-eligible,#simple-page-funding-project,.simple-page-tag');if(permissions.edit){pageSettingInputs.forEach(input=>{captureOnFocus(input);input.addEventListener(input.matches('select,input[type="checkbox"]')?'change':'input',()=>{if(input.id==='simple-page-funding-project')syncFundingProjectControls(true);markDirty('page');if(input.id==='simple-page-thumbnail')refreshSimplePageThumbnailPreview()})})}syncFundingProjectControls();
    document.getElementById('simple-undo').addEventListener('click',()=>{if(permissions.edit)undoChange()});document.getElementById('simple-redo').addEventListener('click',()=>{if(permissions.edit)redoChange()});
    document.getElementById('open-add-section')?.addEventListener('click',()=>{if(!permissions.create)return;state.modalReturn=document.activeElement;const modal=document.getElementById('add-section-modal');modal.hidden=false;modal.querySelector('[data-add-section]')?.focus()});
    document.getElementById('open-reusable-library')?.addEventListener('click',()=>{if(!permissions.edit||!reusableSections.length)return;state.modalReturn=document.activeElement;const modal=document.getElementById('reusable-library-modal');modal.hidden=false;modal.querySelector('[data-attach-reusable]')?.focus()});
    document.getElementById('simple-promote-reusable-form')?.addEventListener('submit',promoteReusableSection);
    document.querySelectorAll('[data-add-section]').forEach(button=>button.addEventListener('click',()=>addSection(button.dataset.addSection)));
    document.getElementById('simple-element-picker')?.addEventListener('click',event=>{const button=event.target.closest('[data-layout-pick-element]');if(button&&!button.disabled)addLayoutElementFromPicker(button.dataset.layoutPickElement)});
    document.getElementById('simple-element-picker-select')?.addEventListener('change',event=>{const button=document.getElementById('simple-element-picker-add');button.disabled=!event.target.value||event.target.selectedOptions[0]?.disabled});
    document.getElementById('simple-element-picker-add')?.addEventListener('click',()=>addLayoutElementFromPicker(document.getElementById('simple-element-picker-select').value));
    document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('add-section-modal'))));document.querySelectorAll('[data-close-media]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('media-modal'))));document.querySelectorAll('[data-close-video-media]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('video-media-modal'))));
    document.querySelectorAll('[data-close-document-media]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('document-media-modal'))));
    document.querySelectorAll('[data-close-element-picker]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('element-picker-modal'))));
    document.querySelectorAll('[data-close-help]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('simple-help-modal'))));
    document.querySelectorAll('[data-close-reusable-library]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('reusable-library-modal'))));
    document.querySelectorAll('[data-close-promote-reusable]').forEach(button=>button.addEventListener('click',()=>closeModal(document.getElementById('promote-reusable-modal'))));
    document.querySelectorAll('[data-cancel-section-delete]').forEach(button=>button.addEventListener('click',()=>closeDeleteConfirmation()));
    document.getElementById('simple-confirm-delete')?.addEventListener('click',confirmDeleteSection);
    document.querySelectorAll('.simple-modal').forEach(modal=>modal.addEventListener('click',event=>{if(event.target!==modal)return;if(modal.id==='simple-delete-modal')closeDeleteConfirmation();else closeModal(modal)}));
    document.addEventListener('keydown',event=>{const modal=document.querySelector('.simple-modal:not([hidden])');if(!modal)return;if(event.key==='Escape'){if(modal.id==='simple-delete-modal')closeDeleteConfirmation();else closeModal(modal);return}if(event.key!=='Tab')return;const focusable=[...modal.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href]')].filter(item=>item.getClientRects().length);if(!focusable.length)return;const first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}});
    function setPreviewViewport(viewport){document.querySelectorAll('.simple-viewport [data-viewport]').forEach(item=>{const active=item.dataset.viewport===viewport;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active))});preview.dataset.viewport=viewport}
    document.querySelectorAll('.simple-viewport [data-viewport]').forEach(button=>button.addEventListener('click',()=>setPreviewViewport(button.dataset.viewport)));
    document.querySelectorAll('[data-editor-mode]').forEach(button=>button.addEventListener('click',()=>setEditorMode(button.dataset.editorMode)));
    document.getElementById('simple-selection-breadcrumb')?.addEventListener('click',event=>{
        const button=event.target.closest('[data-breadcrumb-kind]');
        if(!button)return;
        const block=current();
        if(!block)return;
        if(button.dataset.breadcrumbKind==='section')selectSection(block.uuid,{force:true,focus:'inspector'});
        else selectLayoutNode(block,button.dataset.breadcrumbKind,Number(button.dataset.layoutRow),Number(button.dataset.layoutColumn??-1),Number(button.dataset.layoutElement??-1),{focus:'inspector'});
    });
    if(window.matchMedia('(max-width:520px)').matches)setPreviewViewport('mobile');else if(window.matchMedia('(max-width:880px)').matches)setPreviewViewport('tablet');
    document.getElementById('simple-help').addEventListener('click',()=>{state.modalReturn=document.activeElement;const modal=document.getElementById('simple-help-modal');modal.hidden=false;modal.querySelector('.simple-close')?.focus()});
    document.addEventListener('keydown',event=>{if(!permissions.edit||!(event.ctrlKey||event.metaKey)||event.altKey||!['z','y'].includes(event.key.toLowerCase())||event.target.matches('input,textarea,select,[contenteditable="true"]'))return;event.preventDefault();if(event.key.toLowerCase()==='y'||event.shiftKey)redoChange();else undoChange()});
    window.addEventListener('beforeunload',event=>{if(!hasDirty()||state.leaving)return;event.preventDefault();event.returnValue='' });
    document.addEventListener('click',event=>{const link=event.target.closest('a[href]');if(!link||!hasDirty()||link.target==='_blank')return;if(!confirm('Leave this page and discard your unsaved changes?'))event.preventDefault();else{state.leaving=true;clearDraft()}},true);
    renderReusableLibrary();
    if(!permissions.edit){clearDraft();updateSaveState();renderAll()}else if(!restoreDraft()){updateSaveState();renderAll()}
})();
</script>
@endsection
