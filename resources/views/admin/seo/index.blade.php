@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<main class="seo2">
    <header class="seo2-head">
        <div><h1>Search &amp; Sharing</h1><p>Help people find every public page and make shared links look trustworthy. Start with anything marked “Needs attention”; advanced technical settings stay out of the way.</p></div>
        <details class="seo2-tools">
            <summary class="seo2-btn"><i class="fa fa-sliders" aria-hidden="true"></i> Advanced SEO tools <i class="fa fa-chevron-down seo2-tools__chevron" aria-hidden="true"></i></summary>
            <div class="seo2-tools__menu">
                <a href="{{ route('seo.bulk.index') }}"><i class="fa fa-table" aria-hidden="true"></i><span><strong>Bulk metadata editor</strong><small>Edit many pages in a spreadsheet view.</small></span></a>
                @if(Route::has('seo.performance.index'))<a href="{{ route('seo.performance.index') }}"><i class="fa fa-area-chart" aria-hidden="true"></i><span><strong>Search performance</strong><small>Review first-party clicks, impressions and organic visits.</small></span></a>@endif
                @if($technicalSeoUrl)<a href="{{ $technicalSeoUrl }}"><i class="fa fa-stethoscope" aria-hidden="true"></i><span><strong>Technical checks</strong><small>Scan for broken journeys and page-structure problems.</small></span></a>@endif
                @if(Route::has('seo.internal-links.index'))<a href="{{ route('seo.internal-links.index', ['locale' => $locale]) }}"><i class="fa fa-link" aria-hidden="true"></i><span><strong>Contextual link assistant</strong><small>Find weak pages and relevant places to link from.</small></span></a>@endif
                <a href="{{ route('seo.sitemap.index') }}" target="_blank" rel="noopener"><i class="fa fa-sitemap" aria-hidden="true"></i><span><strong>View XML sitemap</strong><small>Open the search-engine page inventory.</small></span></a>
                @if($canManageRedirects)<a href="{{ route('seo.redirects.index') }}"><i class="fa fa-random" aria-hidden="true"></i><span><strong>Manage redirects</strong><small>Keep old page addresses working.</small></span></a>@endif
            </div>
        </details>
    </header>
    @include('admin.seo._indexing-status')
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>Please fix these settings:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($canEditMetadata)<div class="seo2-alert seo2-alert--warning" role="status">@if($canRestoreRevisions)<strong>Revision restore access.</strong> You can inspect live health, previews and revision differences, and restore earlier versions. Saving other SEO changes requires SEO edit access.@else<strong>Read-only SEO access.</strong> You can inspect live health, previews and revision differences. SEO edit access includes both saving changes and restoring earlier versions.@endif</div>@endunless

    <nav class="seo2-language" aria-label="SEO language">
        @foreach($languageSummary as $language)<a class="{{ $language['id'] === $locale ? 'is-active' : '' }}" href="{{ route('seo.index', ['locale' => $language['id']]) }}" aria-label="{{ $language['name'] }}: {{ $language['ready'] }} of {{ $language['total'] }} indexable live pages ready"><span>{{ $language['name'] }}</span><small>{{ $language['ready'] }}/{{ $language['total'] }}</small></a>@endforeach
    </nav>
    <section class="seo2-metrics" aria-label="SEO completion summary">
        <article class="seo2-metric"><strong>{{ $dashboardCounts['average'] }}%</strong><span>Average for indexable live pages</span></article>
        <article class="seo2-metric"><strong>{{ $dashboardCounts['ready'] }}/{{ $dashboardCounts['indexable_live'] }}</strong><span>Indexable live pages ready</span></article>
        <article class="seo2-metric"><strong>{{ $dashboardCounts['attention'] }}/{{ $dashboardCounts['indexable_live'] }}</strong><span>Indexable live pages needing attention</span></article>
        <article class="seo2-metric"><strong>{{ $dashboardCounts['draft'] }}</strong><span>Draft, private or scheduled</span></article>
        <article class="seo2-metric"><strong>{{ $dashboardCounts['missing_translation'] }}</strong><span>Missing translations</span></article>
    </section>
    <p class="seo2-metric-note">All completion metrics use the same {{ $dashboardCounts['indexable_live'] }} indexable live pages. Intentionally hidden live pages are excluded ({{ $dashboardCounts['hidden'] }} currently hidden).</p>

    <section class="seo2-card seo2-dashboard" aria-labelledby="seo2-dashboard-title">
        <header class="seo2-card__head"><div><h2 id="seo2-dashboard-title">Website SEO checklist</h2><p>{{ strtoupper($locale) }} · {{ $dashboardCounts['total'] }} managed pages and features, including drafts that can be prepared before publishing. System endpoints are excluded.</p></div></header>
        <div class="seo2-card__body">
            <form class="seo2-filter" method="GET" action="{{ route('seo.index') }}"><input type="hidden" name="locale" value="{{ $locale }}"><label class="seo2-field"><span>Find a page</span><input type="search" name="search" value="{{ $dashboardFilters['search'] }}" placeholder="Search name or address"></label><label class="seo2-field"><span>Content type</span><select name="type">@foreach($dashboardTypes as $value => $label)<option value="{{ $value }}" @selected($dashboardFilters['type'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="seo2-field"><span>Show</span><select name="issue"><option value="needs_attention" @selected($dashboardFilters['issue'] === 'needs_attention')>Needs attention</option><option value="all" @selected($dashboardFilters['issue'] === 'all')>Everything</option><option value="missing_title" @selected($dashboardFilters['issue'] === 'missing_title')>Missing search title</option><option value="missing_description" @selected($dashboardFilters['issue'] === 'missing_description')>Missing description</option><option value="missing_image" @selected($dashboardFilters['issue'] === 'missing_image')>Missing social image</option><option value="duplicate_title" @selected($dashboardFilters['issue'] === 'duplicate_title')>Duplicate title</option><option value="duplicate_description" @selected($dashboardFilters['issue'] === 'duplicate_description')>Duplicate description</option><option value="focus_missing_title" @selected($dashboardFilters['issue'] === 'focus_missing_title')>Focus phrase missing from title</option><option value="focus_missing_description" @selected($dashboardFilters['issue'] === 'focus_missing_description')>Focus phrase missing from description</option><option value="hidden" @selected($dashboardFilters['issue'] === 'hidden')>Hidden from search</option><option value="missing_translation" @selected($dashboardFilters['issue'] === 'missing_translation')>Missing translation</option></select></label><button class="seo2-btn" type="submit">Apply filters</button></form>
            @if($dashboardPagination->total())<p class="seo2-result-summary">Showing {{ $dashboardPagination->firstItem() }}–{{ $dashboardPagination->lastItem() }} of {{ $dashboardPagination->total() }} matching {{ \Illuminate\Support\Str::plural('item', $dashboardPagination->total()) }}.</p>@endif
            <div class="seo2-content-list">
                @forelse($dashboardVisibleTargets as $target)
                @php
                    $actionableIssueCount = collect($target['issues'])->whereIn('level', ['required', 'recommended'])->count();
                    $targetActionLabel = !$canEditMetadata
                        ? 'View details'
                        : ($actionableIssueCount > 0
                            ? 'Fix ' . $actionableIssueCount . ' SEO ' . \Illuminate\Support\Str::plural('issue', $actionableIssueCount)
                            : ($target['status'] === 'Hidden' ? 'Review visibility' : 'Review settings'));
                @endphp
                <article class="seo2-target">
                    <div class="seo2-target__name"><strong>{{ $target['label'] }}</strong><small>{{ $target['type_label'] }} · {{ strtoupper($target['locale']) }} · <b>{{ $target['publication']['label'] }}</b></small><span class="seo2-target__url">{{ $target['url'] }}</span></div>
                    <div class="seo2-score" aria-label="{{ $target['score'] }} percent complete"><strong>{{ $target['score'] }}%</strong><span aria-hidden="true"><i style="width:{{ $target['score'] }}%"></i></span></div>
                    <div class="seo2-target__issues">@forelse(array_slice($target['issues'], 0, 3) as $issue)<span class="seo2-chip {{ $issue['level'] === 'required' ? 'seo2-chip--danger' : ($issue['level'] === 'information' ? 'seo2-chip--neutral' : '') }}">{{ $issue['level'] === 'required' ? 'Required: ' : ($issue['level'] === 'recommended' ? 'Recommended: ' : '') }}{{ $issue['label'] }}</span>@empty<span class="seo2-chip" style="background:#e8f5ec;color:#276b3d">Ready</span>@endforelse</div>
                    @if(!($target['is_editable'] ?? true))
                        @if($canViewTranslations)<a class="seo2-btn seo2-btn--soft" href="{{ $target['edit_url'] }}">Create translation</a>@else<span class="seo2-help">Ask a Translation Center editor to create this translation.</span>@endif
                    @else<a class="seo2-btn seo2-btn--soft" href="{{ $target['edit_url'] }}">{{ $targetActionLabel }}</a>@endif
                </article>
                @empty<div class="seo2-empty"><h3>No matching pages</h3><p>Try another filter or review everything.</p><a class="seo2-btn" href="{{ route('seo.index', ['locale' => $locale, 'issue' => 'all']) }}">Show everything</a></div>@endforelse
            </div>
            @if($dashboardPagination->hasPages())<nav class="seo2-pagination" aria-label="SEO checklist pages">{{ $dashboardPagination->links('vendor.pagination.bootstrap-4') }}</nav>@endif
        </div>
    </section>

    <section class="seo2-card" style="margin-bottom:24px">
        <header class="seo2-card__head"><div><h2>Edit a website feature</h2><p>Choose a curated public feature. Technical and private routes are never shown.</p></div></header>
        <div class="seo2-card__body"><form class="seo2-filter" method="GET" action="{{ route('seo.index') }}"><label class="seo2-field"><span>Page or feature</span><select name="route">@foreach($routeDefinitions as $name => $definition)<option value="{{ $name }}" @selected($name === $selectedName)>{{ $definition['label'] }} — {{ $definition['path'] }}</option>@endforeach</select></label><label class="seo2-field"><span>Language</span><select name="locale">@foreach($locales as $option)<option value="{{ $option->id }}" @selected($option->id === $locale)>{{ $option->name }}</option>@endforeach</select></label><button class="seo2-btn seo2-btn--primary" type="submit">Open Search &amp; Sharing</button></form></div>
    </section>

    @if($missingManagedPageTranslation)
        <div class="seo2-alert seo2-alert--warning" role="status"><strong>{{ strtoupper($locale) }} translation required.</strong> This website feature is owned by a translated Page, so route-level SEO cannot be saved for a missing translation. @if($translationCenterUrl)<a href="{{ $translationCenterUrl }}">Create this translation in Translation Center</a>.@else Ask a Translation Center editor to create it before editing search settings.@endif</div>
    @endif
    @php($editorTitle = $selectedLabel)
    @php($canEditMetadata = $editorCanEditMetadata)
    @php($canRestoreRevisions = $editorCanRestoreRevisions)
    @php($canReviewMetadata = $editorCanReviewMetadata)
    @php($canOpenPage = $editorCanOpenPage)
    <details class="seo2-route-editor" @if(request()->filled('route')) open @endif>
        <summary><span><strong>Edit Search &amp; Sharing</strong><small>{{ $selectedLabel }} · {{ strtoupper($locale) }}</small></span><i class="fa fa-chevron-down" aria-hidden="true"></i></summary>
        <div class="seo2-route-editor__body">@include('admin.seo._editor')</div>
    </details>
</main>
@endsection

@section('custom-js')
@include('admin.seo._scripts')
@endsection
