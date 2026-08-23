@extends('admin.layouts.master')

@section('content')
@php
    $permission = app(\App\Http\Middleware\Permission::class);
    $mediaUrls = app(\App\Services\AdminMediaUrlResolver::class);
    $admin = auth('admin')->user();
    $canCreatePage = $permission->allows($admin, 'page.create');
    $canEditBuilder = $permission->allows($admin, 'page.builder.edit');
    $canEditSponsor = $permission->allows($admin, 'site.settings.update');
    $canBulkCopy = $permission->allows($admin, 'page.bulk.copy');
    $canDeletePage = $permission->allows($admin, 'page.destroy');
    $canUseBulk = $canBulkCopy || $canDeletePage;
    $canViewReusable = $permission->allows($admin, 'reusable-blocks.index');
    $canViewMedia = $permission->allows($admin, 'media.index');
    $canViewSeo = $permission->allows($admin, 'seo.index');
    $canViewPageTrash = $permission->allows($admin, 'page.trash.index');
    $canViewContentTrash = $permission->allows($admin, 'content.trash.index');
    $hasQuickTools = $canViewReusable || $canViewMedia || $canViewSeo || $canViewPageTrash || $canViewContentTrash;
    $isReadOnly = !$canCreatePage && !$canEditBuilder && !$canEditSponsor && !$canBulkCopy && !$canDeletePage;
    $statusLabels = ['published' => 'Published', 'draft' => 'Drafts', 'pending_review' => 'Needs review', 'scheduled' => 'Scheduled', 'private' => 'Private'];
    $activeFilterLabels = collect();
    if (filled($search)) $activeFilterLabels->push('Search: '.$search);
    if (filled($status)) $activeFilterLabels->push('Status: '.($statusLabels[$status] ?? str_replace('_', ' ', $status)));
    if (filled($language)) $activeFilterLabels->push('Language: '.strtoupper($language));
    if ($category) $activeFilterLabels->push('Type: '.($categories->firstWhere('id', $category)?->name ?? 'Selected'));
    if ($needsTranslation) $activeFilterLabels->push('Needs translation');
    $hasActiveFilters = $activeFilterLabels->isNotEmpty();
@endphp
<style>
    @import url('https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=Literata:opsz,wght@7..72,500;600;700&display=swap');
    .content-hub{--primary:#9c4500;--orange:#ff7500;--ink:#191c1d;--muted:#656569;--line:#e9e4df;max-width:1370px;margin:0 auto;padding:34px 32px 64px;font-family:'Hanken Grotesk',sans-serif;color:var(--ink)}
    .hub-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:28px}.hub-head h1{margin:0 0 7px;font:700 clamp(38px,4vw,58px)/1.05 'Literata',serif;letter-spacing:-.035em}.hub-head p{max-width:660px;margin:0;color:var(--muted);font-size:15px}.hub-create{display:inline-flex;min-height:44px;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:9px;background:var(--primary);color:#fff;font-weight:800;text-decoration:none;box-shadow:0 5px 14px rgba(120,51,0,.2)}.hub-create:hover{background:#783300;color:#fff}
    .hub-grid{display:grid;grid-template-columns:240px minmax(0,1fr);gap:24px}.hub-filter-panel{min-width:0}.hub-filter-panel>summary{display:none}.hub-filter-state{margin-left:8px;padding:3px 7px;border-radius:999px;background:#fff0db;color:#80500d;font-size:11px}.hub-side{display:grid;align-content:start;gap:16px}.hub-card{border:1px solid var(--line);border-radius:11px;background:#fff;box-shadow:0 7px 24px rgba(25,28,29,.035)}.hub-card__body{padding:18px}.hub-card h2{margin:0 0 14px;font:600 18px 'Literata',serif}.hub-count{display:flex;justify-content:space-between;padding:7px 0;color:var(--muted);text-decoration:none}.hub-count strong{color:var(--primary)}.hub-count.is-active{color:var(--primary);font-weight:800}.hub-filter{display:grid;gap:10px}.hub-filter label{margin:2px 0 -4px;color:var(--muted);font-size:11px;font-weight:800;text-transform:uppercase}.hub-filter input,.hub-filter select{width:100%;padding:10px;border:1px solid #ddd8d2;border-radius:7px;background:#fff}.hub-filter__check{display:flex!important;align-items:center;gap:8px;margin:3px 0!important;font-size:12px!important;text-transform:none!important}.hub-filter__check input{width:auto}.hub-reset{color:var(--primary);font-size:12px;text-decoration:none}.hub-filter-submit{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:var(--primary);font-size:13px;font-weight:800}
    .hub-main{min-width:0}.hub-tools{display:flex;align-items:stretch;gap:10px;margin-bottom:12px}.hub-search{display:flex;flex:1}.hub-search input{width:100%;padding:11px 13px;border:1px solid #ddd8d2;border-right:0;border-radius:8px 0 0 8px}.hub-search button{min-width:44px;padding:0 16px;border:1px solid #ddd8d2;border-radius:0 8px 8px 0;background:#fff}.hub-tool-menu{position:relative}.hub-tool-menu>summary{display:flex;min-height:44px;align-items:center;padding:0 14px;border:1px solid var(--line);border-radius:8px;background:#fff;color:#504e4c;cursor:pointer;font-size:13px;font-weight:800;list-style:none}.hub-tool-menu>summary::-webkit-details-marker{display:none}.hub-tool-menu>summary::after{content:'\25BE';margin-left:9px;font-size:10px}.hub-tool-menu[open]>summary{border-color:#cdbeb2}.hub-quick{position:absolute;z-index:20;top:calc(100% + 6px);right:0;display:grid;width:220px;padding:7px;border:1px solid var(--line);border-radius:9px;background:#fff;box-shadow:0 12px 30px rgba(25,28,29,.14)}.hub-quick a{display:flex;min-height:44px;align-items:center;padding:8px 10px;border-radius:7px;color:#504e4c;text-decoration:none;font-size:13px;font-weight:700}.hub-quick a:hover{background:#f8f5f2;color:var(--primary)}.hub-active-filters{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:0 0 12px;padding:10px 12px;border:1px solid #ead8c7;border-radius:9px;background:#fffaf5;color:#5f5248;font-size:12px}.hub-active-filters>strong{margin-right:2px}.hub-filter-chip{padding:4px 8px;border-radius:999px;background:#f1e9e2;color:#634c3a;font-weight:700}.hub-active-filters .hub-reset{margin-left:auto}
    .hub-read-only{margin:-12px 0 24px;padding:12px 15px;border:1px solid #e7c785;border-radius:9px;background:#fff8df;color:#674d12;font-size:13px;line-height:1.5}.hub-read-only strong{margin-right:5px}
    .hub-bulk{display:flex;min-height:48px;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding:8px 12px;border:1px solid var(--line);border-bottom:0;border-radius:11px 11px 0 0;background:#fff}.hub-select-all{display:flex;align-items:center;gap:8px;margin:0;color:var(--muted);font-size:12px;font-weight:700}.hub-bulk__actions{display:flex;align-items:center;gap:7px}.hub-bulk__actions[hidden]{display:none!important}.hub-bulk select,.hub-bulk button{min-height:44px;padding:6px 10px;border:1px solid #ddd8d2;border-radius:7px;background:#fff;color:#504e4c;font-size:12px;font-weight:700}.hub-bulk button[data-bulk="delete"]{margin-left:6px;border-color:#e5c1bd;color:#9c2e26}.hub-list{overflow:hidden;border:1px solid var(--line);border-radius:0 0 11px 11px;background:#fff}.hub-row{display:grid;grid-template-columns:26px 50px minmax(0,1fr) 110px 125px 108px 190px;align-items:center;gap:12px;min-height:88px;margin:0;padding:13px 16px;border-bottom:1px solid #efebe7}.hub-row:last-child{border:0}.hub-select{width:17px;height:17px;accent-color:var(--orange)}.hub-thumb{display:flex;align-items:center;justify-content:center;width:48px;height:48px;overflow:hidden;border-radius:7px;background:#f1eeeb;color:#8b817a}.hub-thumb img{width:100%;height:100%;object-fit:cover}.hub-title{min-width:0}.hub-title strong{display:block;margin-bottom:3px;font:600 16px 'Literata',serif}.hub-title a{color:var(--ink);text-decoration:none}.hub-meta{color:#77716c;font-size:12px}.hub-badge{display:inline-flex;width:max-content;padding:5px 8px;border-radius:999px;background:#eee;color:#555;font-size:11px;font-weight:800;text-transform:capitalize}.hub-badge--published{background:#e6f5eb;color:#247542}.hub-badge--draft{background:#f0f0ee;color:#656565}.hub-badge--scheduled{background:#e9f0ff;color:#315fa8}.hub-badge--pending_review{background:#fff0db;color:#8f5714}.hub-badge--private{background:#fbe7e5;color:#9f3028}.hub-translation{display:block;margin-top:5px;color:#b05a18;font-size:10px;font-weight:800;text-transform:uppercase}.hub-actions{display:flex;justify-content:flex-end;gap:6px}.hub-action{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:7px 10px;border:1px solid #e2dcd6;border-radius:7px;background:#fff;color:#444;cursor:pointer;font-size:12px;font-weight:800;text-decoration:none}.hub-action:hover{border-color:var(--orange);color:var(--primary)}.hub-action--danger{border-color:#eed4d1;color:#9c2e26}.hub-empty{padding:70px;text-align:center;color:var(--muted)}.hub-pagination{display:flex;justify-content:flex-end;margin-top:18px}
    @media(max-width:1400px){.hub-row{grid-template-columns:26px 50px minmax(0,1fr) 118px 190px}.hub-category,.hub-updated{display:none}}
    @media(min-width:1101px){.hub-filter-panel>.hub-side{display:grid!important}}
    @media(max-width:1100px){.hub-grid{grid-template-columns:1fr}.hub-filter-panel>summary{display:flex;min-height:46px;align-items:center;justify-content:space-between;padding:0 14px;border:1px solid var(--line);border-radius:9px;background:#fff;cursor:pointer;font-size:13px;font-weight:800;list-style:none}.hub-filter-panel>summary::-webkit-details-marker{display:none}.hub-filter-panel>summary::after{content:'Show';color:var(--primary);font-size:12px}.hub-filter-panel[open]>summary{margin-bottom:12px}.hub-filter-panel[open]>summary::after{content:'Hide'}.hub-side{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:680px){.content-hub{padding:24px 14px}.hub-head{flex-direction:column}.hub-create{width:100%;justify-content:center}.hub-side{grid-template-columns:1fr}.hub-tools{align-items:stretch}.hub-tool-menu>summary{justify-content:center}.hub-quick{position:fixed;top:auto;right:14px;bottom:14px;left:14px;width:auto}.hub-bulk{align-items:flex-start;flex-direction:column}.hub-bulk__actions{width:100%;flex-wrap:wrap}.hub-row{grid-template-columns:24px 44px minmax(0,1fr);min-height:auto}.hub-row>div:nth-of-type(4){display:none}.hub-actions{grid-column:3;justify-content:flex-start;flex-wrap:wrap}.hub-action{min-height:44px}.hub-empty{padding:48px 20px}}
    .hub-seo{display:inline-flex;margin-top:5px;padding:4px 7px;border-radius:999px;background:#fff0db;color:#80500d;font-size:9px;font-weight:900;text-decoration:none;text-transform:uppercase}.hub-seo--ready{background:#e6f5eb;color:#247542}.hub-seo--hidden{background:#ecebea;color:#5e5955}
</style>

<main class="content-hub">
    <header class="hub-head">
        <div><h1>Content Hub</h1><p>Manage, publish, schedule, translate, and recover every story and page. Impact begins with clear communication.</p></div>
        @if($canCreatePage)<a class="hub-create" href="{{ route('page.create') }}"><span aria-hidden="true">+</span> New page</a>@endif
    </header>

    @if($isReadOnly)
        <div class="hub-read-only" role="status"><strong>Read-only access.</strong> You can search, filter, and preview content. Ask an administrator for page editing access to make changes.</div>
    @endif

    <div class="hub-grid">
        <details class="hub-filter-panel" data-active-filters="{{ $hasActiveFilters ? '1' : '0' }}" open>
        <summary>Overview and filters @if($hasActiveFilters)<span class="hub-filter-state">{{ $activeFilterLabels->count() }} active</span>@endif</summary>
        <aside class="hub-side" aria-label="Content filters">
            <section class="hub-card"><div class="hub-card__body"><h2>Overview</h2>
                <a class="hub-count {{ !$status ? 'is-active' : '' }}" href="{{ route('page.index') }}"><span>All content</span><strong>{{ $counts->sum() }}</strong></a>
                @foreach(['published' => 'Published', 'draft' => 'Drafts', 'pending_review' => 'Needs review', 'scheduled' => 'Scheduled', 'private' => 'Private'] as $key => $label)
                    <a class="hub-count {{ $status === $key ? 'is-active' : '' }}" href="{{ route('page.index', ['status' => $key]) }}"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong></a>
                @endforeach
                <a class="hub-count {{ $needsTranslation ? 'is-active' : '' }}" href="{{ route('page.index', ['needs_translation' => 1]) }}"><span>Needs translation</span><strong aria-hidden="true">→</strong></a>
            </div></section>
            <section class="hub-card"><div class="hub-card__body"><div style="display:flex;justify-content:space-between"><h2>Filters</h2><a class="hub-reset btn igf-btn igf-btn-tertiary" href="{{ route('page.index') }}"><i class="fa fa-undo" aria-hidden="true"></i> Clear</a></div><form class="hub-filter" method="GET">
                <label for="hub-category">Content type</label><select id="hub-category" name="category"><option value="">All content types</option>@foreach($categories as $filterCategory)<option value="{{ $filterCategory->id }}" @selected($category === $filterCategory->id)>{{ $filterCategory->name }}</option>@endforeach</select>
                <label for="hub-language">Language</label><select id="hub-language" name="language"><option value="">All languages</option>@foreach($languages as $filterLanguage)<option value="{{ $filterLanguage }}" @selected($language === $filterLanguage)>{{ strtoupper($filterLanguage) }}</option>@endforeach</select>
                <label for="hub-status">Status</label><select id="hub-status" name="status"><option value="">Any status</option>@foreach(['published' => 'Published', 'draft' => 'Draft', 'pending_review' => 'Pending review', 'scheduled' => 'Scheduled', 'private' => 'Private'] as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
                <label class="hub-filter__check" for="hub-needs-translation"><input id="hub-needs-translation" type="checkbox" name="needs_translation" value="1" @checked($needsTranslation)> Needs translation</label>
                <button class="hub-filter-submit" type="submit">Apply filters</button>
            </form></div></section>
        </aside>
        </details>

        <section class="hub-main">
            <div class="hub-tools">
                <form class="hub-search" method="GET" action="{{ route('page.index') }}">@if($status)<input type="hidden" name="status" value="{{ $status }}">@endif @if($language)<input type="hidden" name="language" value="{{ $language }}">@endif @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif @if($needsTranslation)<input type="hidden" name="needs_translation" value="1">@endif<input type="search" name="search" value="{{ $search }}" aria-label="Search content by title, URL, or category" placeholder="Search content by title, URL, or category"><button type="submit" aria-label="Search"><i class="fa fa-search" aria-hidden="true"></i></button></form>
                @if($hasQuickTools)
                    <details class="hub-tool-menu">
                        <summary>More tools</summary>
                        <nav class="hub-quick" aria-label="Content management tools">
                            @if($canViewReusable)<a href="{{ route('reusable-blocks.index') }}">Reusable sections</a>@endif
                            @if($canViewMedia)<a href="{{ route('media.index') }}">Media library</a>@endif
                            @if($canViewSeo)<a href="{{ route('seo.index') }}">Search &amp; Sharing</a>@endif
                            @if($canViewPageTrash)<a href="{{ route('page.trash.index') }}">Page trash</a>@endif
                            @if($canViewContentTrash)<a href="{{ route('content.trash.index') }}">Content trash</a>@endif
                        </nav>
                    </details>
                @endif
            </div>
            @if($hasActiveFilters)
                <div class="hub-active-filters" aria-label="Active content filters">
                    <strong>Showing filtered results:</strong>
                    @foreach($activeFilterLabels as $activeFilterLabel)<span class="hub-filter-chip">{{ $activeFilterLabel }}</span>@endforeach
                    <a class="hub-reset" href="{{ route('page.index') }}">Clear all</a>
                </div>
            @endif
            @if($canUseBulk)
            <div class="hub-bulk">
                <label class="hub-select-all" for="hub-select-all"><input id="hub-select-all" class="hub-select" type="checkbox"> Select all on this page <span id="hub-selected-count"></span></label>
                <div class="hub-bulk__actions" id="hub-bulk-actions" aria-label="Bulk actions" hidden>
                    @if($canBulkCopy)
                        <select id="hub-target-language" aria-label="Translation language">@foreach($languages as $filterLanguage)<option value="{{ $filterLanguage }}">Translate to {{ strtoupper($filterLanguage) }}</option>@endforeach</select>
                        <button type="button" data-bulk="translate">Translate</button>
                        <button type="button" data-bulk="duplicate">Duplicate</button>
                    @endif
                    @if($canDeletePage)<button type="button" data-bulk="delete">Delete</button>@endif
                </div>
            </div>
            @endif
            <div class="hub-list">
                @forelse($pages as $page)
                    @php
                        $state = $page->publication_status ?: ($page->status ? 'published' : 'draft');
                        $usesSponsorCustomizer = $page->slug === 'sponsor-a-child';
                        $editUrl = $usesSponsorCustomizer
                            ? route('site.settings.index', ['locale' => $page->language]).'#settings-sponsor_page'
                            : route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => $page->language]);
                        $previewUrl = $usesSponsorCustomizer
                            ? route('frontend.sponsor_child')
                            : route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language]);
                        $publicPath = $usesSponsorCustomizer ? '/sponsor-child' : '/page/'.$page->slug;
                        $canEditRow = $usesSponsorCustomizer ? $canEditSponsor : $canEditBuilder;
                        $thumbnailUrl = $mediaUrls->image($page->getRawOriginal('thumbnail'), 'page');
                    @endphp
                    <article class="hub-row" id="page-{{ $page->id }}" data-page-uuid="{{ $page->uuid }}">
                        @if($canUseBulk)<input class="hub-select hub-row-select" type="checkbox" value="{{ $page->id }}" aria-label="Select {{ $page->name }} ({{ strtoupper($page->language) }})">@else<span aria-hidden="true"></span>@endif
                        <div class="hub-thumb"><img src="{{ $thumbnailUrl }}"
                            onerror="this.onerror=null;this.src='{{ $mediaUrls->fallback() }}'"
                            alt=""></div>
                        <div class="hub-title"><strong>@if($canEditRow)<a href="{{ $editUrl }}">{{ $page->name }}</a>@else{{ $page->name }}@endif</strong><span class="hub-meta">{{ strtoupper($page->language) }} &middot; {{ $publicPath }}@if($usesSponsorCustomizer) &middot; Sponsor customizer @endif</span></div>
                        <div class="hub-category"><span class="hub-meta">{{ $page->c_name ?: 'Page' }}</span></div>
                        <div><span class="hub-badge hub-badge--{{ $state }}">{{ str_replace('_', ' ', $state) }}</span>@if((int)$page->translation_count < $languages->count())<span class="hub-translation">Needs translation</span>@endif @if($canViewSeo)<a class="hub-seo {{ $page->seo_admin_status==='Ready' ? 'hub-seo--ready' : ($page->seo_admin_status==='Hidden' ? 'hub-seo--hidden' : '') }}" href="{{ route('seo.content.edit', ['type'=>'page','id'=>$page->id,'locale'=>$page->language]) }}">{{ $page->seo_admin_status === 'Ready' ? 'SEO ready' : ($page->seo_admin_status === 'Hidden' ? 'SEO hidden' : 'Review SEO') }}</a>@endif</div>
                        <div class="hub-updated"><span class="hub-meta">Updated<br>{{ $page->updated_at?->diffForHumans() }}</span></div>
                        <div class="hub-actions">
                            <a class="hub-action" href="{{ $previewUrl }}" target="_blank" rel="noopener" aria-label="Preview {{ $page->name }}">Preview</a>
                            @if($canEditRow)<a class="hub-action" href="{{ $editUrl }}" aria-label="{{ $usesSponsorCustomizer ? 'Customize' : 'Edit' }} {{ $page->name }}">{{ $usesSponsorCustomizer ? 'Customize' : 'Edit' }}</a>@endif
                            @if($canDeletePage)<button class="hub-action hub-action--danger trash" type="button" data-url="{{ route('page.destroy', $page->uuid) }}" data-id="{{ $page->uuid }}" aria-label="Move {{ $page->name }} to trash">Trash</button>@endif
                        </div>
                    </article>
                @empty<div class="hub-empty"><h2>No content found</h2><p>{{ $canCreatePage ? 'Create a page or clear the current filters.' : 'Clear the current filters to look for other content.' }}</p></div>@endforelse
            </div>
            <div class="hub-pagination">{{ $pages->appends(['search' => $search, 'status' => $status, 'language' => $language, 'category' => $category, 'needs_translation' => $needsTranslation ? 1 : null])->links('vendor.pagination.bootstrap-4') }}</div>
        </section>
    </div>
</main>
@endsection

@section('custom-js')
<script>
const hubPermissions = @json(['copy' => $canBulkCopy, 'delete' => $canDeletePage]);
const filterPanel = document.querySelector('.hub-filter-panel');
const mobileContentHub = window.matchMedia('(max-width: 1100px)');
const syncFilterPanel = () => {
    if (!filterPanel) return;
    if (mobileContentHub.matches) filterPanel.removeAttribute('open');
    else filterPanel.setAttribute('open', '');
};
syncFilterPanel();
if (typeof mobileContentHub.addEventListener === 'function') {
    mobileContentHub.addEventListener('change', syncFilterPanel);
}

document.querySelectorAll('.hub-action.trash').forEach(button => button.addEventListener('click', async () => {
    if (!hubPermissions.delete) return;
    if (!confirm('Move this page and every language version to trash? It can be restored.')) return;
    const response = await fetch(button.dataset.url, {method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) { toastrMsg('error', payload.message || 'The page could not be moved to trash.'); return; }
    document.querySelectorAll(`[data-page-uuid="${CSS.escape(button.dataset.id)}"]`).forEach(row => row.remove());
    toastrMsg('success', payload.message || 'Page moved to trash.');
}));

const selectAll = document.getElementById('hub-select-all');
const rowSelections = [...document.querySelectorAll('.hub-row-select')];
const bulkActions = document.getElementById('hub-bulk-actions');
const selectedCount = document.getElementById('hub-selected-count');
const selectedIds = () => rowSelections.filter(input => input.checked).map(input => Number(input.value));
const updateBulkState = () => {
    const count = selectedIds().length;
    if (selectedCount) selectedCount.textContent = count ? `(${count})` : '';
    if (bulkActions) bulkActions.hidden = count === 0;
    if (selectAll) {
        selectAll.checked = rowSelections.length > 0 && count === rowSelections.length;
        selectAll.indeterminate = count > 0 && count < rowSelections.length;
    }
};
selectAll?.addEventListener('change', () => {
    rowSelections.forEach(input => { input.checked = selectAll.checked; });
    updateBulkState();
});
rowSelections.forEach(input => input.addEventListener('change', updateBulkState));

document.querySelectorAll('[data-bulk]').forEach(button => button.addEventListener('click', async () => {
    const pageIds = selectedIds();
    if (!pageIds.length) return;
    const action = button.dataset.bulk;
    if ((action === 'delete' && !hubPermissions.delete) || (action !== 'delete' && !hubPermissions.copy)) return;
    if (action === 'delete' && !confirm('Move every selected page and its language versions to trash? They can be restored.')) return;
    if (action === 'duplicate' && !confirm('Create draft copies of the selected content?')) return;

    const url = action === 'delete' ? @json(route('page.bulk.destroy')) : @json(route('page.bulk.copy'));
    const method = action === 'delete' ? 'DELETE' : 'POST';
    const body = {page_ids: pageIds};
    if (action !== 'delete') body.action = action;
    if (action === 'translate') body.target_language = document.getElementById('hub-target-language')?.value;
    button.disabled = true;
    try {
        const response = await fetch(url, {
            method,
            headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'The bulk action failed.');
        toastrMsg('success', payload.message);
        setTimeout(() => window.location.reload(), 450);
    } catch (error) {
        toastrMsg('error', error.message);
        button.disabled = false;
    }
}));
updateBulkState();
</script>
@endsection
