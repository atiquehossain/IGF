@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
@php
    $search = $report['search_console'];
    $analytics = $report['analytics'];
    $searchData = $search['data'];
    $analyticsData = $analytics['data'];
    $searchTotals = $searchData['totals'] ?? ['clicks' => 0, 'impressions' => 0, 'ctr_percent' => 0];
    $analyticsTotals = $analyticsData['totals'] ?? ['sessions' => 0, 'engaged_sessions' => 0, 'page_views' => 0];
@endphp
<style>
    .sperf-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:20px}.sperf-metric{padding:16px;border:1px solid var(--seo-line);border-radius:12px;background:#fff}.sperf-metric strong{display:block;font:700 26px 'Literata',serif}.sperf-metric span{display:block;margin-top:4px;color:var(--seo-muted);font-size:10px;font-weight:900;text-transform:uppercase}.sperf-sources{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px}.sperf-source{padding:14px 16px;border:1px solid var(--seo-line);border-radius:11px;background:#fff}.sperf-source__head{display:flex;align-items:center;justify-content:space-between;gap:12px}.sperf-source p{margin:6px 0 0;color:var(--seo-muted);font-size:12px;line-height:1.5}.sperf-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#f0eeec;color:#625d58;font-size:9px;font-weight:900;text-transform:uppercase}.sperf-status--connected{background:#e8f5ec;color:#276b3d}.sperf-status--unavailable{background:#fff0ee;color:#922d25}.sperf-tabs{display:flex;flex-wrap:wrap;gap:7px}.sperf-tabs a{display:inline-flex;min-height:40px;align-items:center;padding:8px 12px;border:1px solid var(--seo-line);border-radius:8px;background:#fff;color:var(--seo-ink);font-weight:800;text-decoration:none}.sperf-tabs a.is-active{border-color:var(--seo-orange);background:var(--seo-soft);color:var(--seo-brown)}.sperf-sections{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:18px}.sperf-stack{display:grid;gap:18px}.sperf-card{border:1px solid var(--seo-line);border-radius:12px;background:#fff;overflow:hidden}.sperf-card__head{padding:16px;border-bottom:1px solid var(--seo-line)}.sperf-card__head h2{margin:0;font-size:20px}.sperf-card__head p{margin:5px 0 0;color:var(--seo-muted);font-size:12px}.sperf-table-wrap{overflow:auto}.sperf-table{width:100%;border-collapse:collapse}.sperf-table th,.sperf-table td{padding:11px 13px;border-bottom:1px solid var(--seo-line);text-align:left;vertical-align:top}.sperf-table th{background:#f7f5f3;color:var(--seo-muted);font-size:10px;text-transform:uppercase}.sperf-table tbody tr:last-child td{border-bottom:0}.sperf-path{max-width:430px;overflow-wrap:anywhere;font:11px/1.5 ui-monospace,Consolas,monospace}.sperf-empty{padding:28px;color:var(--seo-muted);text-align:center}.sperf-setup{padding:18px}.sperf-setup ol{margin:10px 0 0;padding-left:20px}.sperf-setup li{margin:7px 0;line-height:1.45}.sperf-setup code{overflow-wrap:anywhere}.sperf-note{margin-top:18px;padding:14px;border-radius:10px;background:#f5f3f1;color:#5f5954;font-size:12px;line-height:1.55}@media(max-width:1100px){.sperf-grid{grid-template-columns:repeat(3,1fr)}.sperf-sections{grid-template-columns:1fr}}@media(max-width:720px){.sperf-grid,.sperf-sources{grid-template-columns:1fr 1fr}}@media(max-width:520px){.sperf-grid,.sperf-sources{grid-template-columns:1fr}.sperf-source__head{align-items:flex-start;flex-direction:column}}
</style>
<main class="seo2">
    <header class="seo2-head">
        <div>
            <h1>Search Performance</h1>
            <p>Use first-party Google data to see whether people discover and engage with the website. This center does not use AI, scrape search results or maintain a keyword rank tracker.</p>
        </div>
        <div class="seo2-actions">
            <a class="seo2-btn" href="{{ route('seo.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Search &amp; Sharing</a>
            <form method="POST" action="{{ route('seo.performance.refresh') }}">@csrf<input type="hidden" name="days" value="{{ $days }}"><button class="seo2-btn seo2-btn--primary" type="submit"><i class="fa fa-refresh" aria-hidden="true"></i> Refresh data</button></form>
        </div>
    </header>

    @if(session('message'))<div class="seo2-alert {{ session('alert-type') === 'warning' ? 'seo2-alert--warning' : '' }}" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>Check this report:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="seo2-filter" style="margin-bottom:16px">
        <div class="sperf-tabs" aria-label="Reporting period">
            @foreach([7 => '7 days', 28 => '28 days', 90 => '90 days'] as $period => $label)
                <a class="{{ $days === $period ? 'is-active' : '' }}" href="{{ route('seo.performance.index', ['days' => $period]) }}" @if($days === $period) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <span class="seo2-help">Cached safely for {{ config('seo-performance.cache_minutes') }} minutes · Last checked {{ $report['fetched_at']->diffForHumans() }}</span>
    </div>

    <section class="sperf-sources" aria-label="Data connections">
        @foreach([['label' => 'Google Search Console', 'source' => $search, 'property' => $configuration['search_console_site']], ['label' => 'Google Analytics 4', 'source' => $analytics, 'property' => $configuration['analytics_property']]] as $connection)
            <article class="sperf-source">
                <div class="sperf-source__head"><strong>{{ $connection['label'] }}</strong><span class="sperf-status sperf-status--{{ $connection['source']['status'] }}">{{ str_replace('_', ' ', $connection['source']['status']) }}</span></div>
                <p>{{ $connection['source']['message'] }} @if($connection['property'])<br>Property: <code>{{ $connection['property'] }}</code>@endif</p>
            </article>
        @endforeach
    </section>

    <section class="sperf-grid" aria-label="Organic performance summary">
        <article class="sperf-metric"><strong>{{ number_format($searchTotals['clicks']) }}</strong><span>Search clicks</span></article>
        <article class="sperf-metric"><strong>{{ number_format($searchTotals['impressions']) }}</strong><span>Search impressions</span></article>
        <article class="sperf-metric"><strong>{{ number_format($searchTotals['ctr_percent'], 1) }}%</strong><span>Search click rate</span></article>
        <article class="sperf-metric"><strong>{{ number_format($analyticsTotals['sessions']) }}</strong><span>Organic sessions</span></article>
        <article class="sperf-metric"><strong>{{ number_format($analyticsTotals['engaged_sessions']) }}</strong><span>Engaged organic sessions</span></article>
    </section>

    @if($search['status'] !== 'connected' && $analytics['status'] !== 'connected')
        <section class="sperf-card" aria-labelledby="performance-setup-title">
            <div class="sperf-card__head"><h2 id="performance-setup-title">Connect first-party performance data</h2><p>No paid SEO or AI subscription is required. A deployment owner completes these server settings.</p></div>
            <div class="sperf-setup">
                <ol>
                    <li>Create a Google service account and grant its email read-only access to the approved Search Console property and GA4 property.</li>
                    <li>Store the downloaded credential JSON outside the public website directory.</li>
                    <li>Set <code>SEO_PERFORMANCE_ENABLED=true</code>, <code>GOOGLE_SEARCH_CONSOLE_SITE_URL</code>, <code>ANALYTICS_VIEW_ID</code> and <code>GOOGLE_APPLICATION_CREDENTIALS</code> in the server environment.</li>
                    <li>Clear the configuration cache, then return here and choose <strong>Refresh data</strong>.</li>
                </ol>
                <p class="sperf-note"><strong>Security:</strong> credentials are never displayed or stored in the database. The integration requests read-only Search Console access and fails closed when configuration is incomplete.</p>
            </div>
        </section>
    @else
        <div class="sperf-sections">
            <div class="sperf-stack">
                <section class="sperf-card" aria-labelledby="search-pages-title">
                    <div class="sperf-card__head"><h2 id="search-pages-title">Top pages in Google Search</h2><p>Landing pages receiving the most first-party Search Console impressions.</p></div>
                    <div class="sperf-table-wrap"><table class="sperf-table"><thead><tr><th>Page</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Action</th></tr></thead><tbody>
                    @forelse($searchData['pages'] ?? [] as $row)
                        <tr><td class="sperf-path">{{ $row['path'] ?: $row['value'] }}</td><td>{{ number_format($row['clicks']) }}</td><td>{{ number_format($row['impressions']) }}</td><td>{{ number_format($row['ctr_percent'], 1) }}%</td><td>@if($row['path'])<a class="seo2-btn seo2-btn--soft" href="{{ route('seo.index', ['search' => $row['path'], 'issue' => 'all']) }}">Review SEO</a>@endif</td></tr>
                    @empty<tr><td colspan="5" class="sperf-empty">No Search Console page data is available.</td></tr>@endforelse
                    </tbody></table></div>
                </section>

                <section class="sperf-card" aria-labelledby="opportunities-title">
                    <div class="sperf-card__head"><h2 id="opportunities-title">Click-through opportunities</h2><p>Pages with useful search visibility but a low click rate. Improve their title and description before creating more content.</p></div>
                    <div class="sperf-table-wrap"><table class="sperf-table"><thead><tr><th>Page</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Action</th></tr></thead><tbody>
                    @forelse($searchData['opportunities'] ?? [] as $row)
                        <tr><td class="sperf-path">{{ $row['path'] ?: $row['value'] }}</td><td>{{ number_format($row['impressions']) }}</td><td>{{ number_format($row['clicks']) }}</td><td>{{ number_format($row['ctr_percent'], 1) }}%</td><td>@if($row['path'])<a class="seo2-btn seo2-btn--soft" href="{{ route('seo.index', ['search' => $row['path'], 'issue' => 'all']) }}">Review SEO</a>@endif</td></tr>
                    @empty<tr><td colspan="5" class="sperf-empty">No low-click opportunities were found for this period.</td></tr>@endforelse
                    </tbody></table></div>
                </section>

                <section class="sperf-card" aria-labelledby="landing-pages-title">
                    <div class="sperf-card__head"><h2 id="landing-pages-title">Organic landing pages</h2><p>Where visitors begin after finding the website in organic search.</p></div>
                    <div class="sperf-table-wrap"><table class="sperf-table"><thead><tr><th>Landing page</th><th>Sessions</th><th>Engaged</th><th>Views</th></tr></thead><tbody>
                    @forelse($analyticsData['pages'] ?? [] as $row)
                        <tr><td class="sperf-path">{{ $row['path'] }}</td><td>{{ number_format($row['sessions']) }}</td><td>{{ number_format($row['engaged_sessions']) }}</td><td>{{ number_format($row['page_views']) }}</td></tr>
                    @empty<tr><td colspan="4" class="sperf-empty">No GA4 organic landing-page data is available.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            </div>

            <div class="sperf-stack">
                <section class="sperf-card" aria-labelledby="queries-title">
                    <div class="sperf-card__head"><h2 id="queries-title">Queries people used</h2><p>First-party Search Console terms, shown without rank tracking.</p></div>
                    <div class="sperf-table-wrap"><table class="sperf-table"><thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th></tr></thead><tbody>
                    @forelse($searchData['queries'] ?? [] as $row)
                        <tr><td>{{ $row['value'] }}</td><td>{{ number_format($row['clicks']) }}</td><td>{{ number_format($row['impressions']) }}</td><td>{{ number_format($row['ctr_percent'], 1) }}%</td></tr>
                    @empty<tr><td colspan="4" class="sperf-empty">No Search Console query data is available.</td></tr>@endforelse
                    </tbody></table></div>
                </section>

                <section class="sperf-card" aria-labelledby="sitemaps-title">
                    <div class="sperf-card__head"><h2 id="sitemaps-title">Submitted sitemaps</h2><p>Warnings and errors reported by Search Console.</p></div>
                    <div class="sperf-table-wrap"><table class="sperf-table"><thead><tr><th>Sitemap</th><th>Warnings</th><th>Errors</th></tr></thead><tbody>
                    @forelse($searchData['sitemaps'] ?? [] as $row)
                        <tr><td class="sperf-path">{{ $row['path'] }}</td><td>{{ number_format($row['warnings']) }}</td><td>{{ number_format($row['errors']) }}</td></tr>
                    @empty<tr><td colspan="3" class="sperf-empty">No sitemap status is available.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            </div>
        </div>
    @endif
</main>
@endsection
