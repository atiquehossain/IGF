@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<style>
    .tseo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.tseo-metric{padding:16px;border:1px solid var(--seo-line);border-radius:12px;background:#fff}.tseo-metric strong{display:block;font:700 27px 'Literata',serif}.tseo-metric span{color:var(--seo-muted);font-size:10px;font-weight:900;text-transform:uppercase}.tseo-section{margin-top:20px}.tseo-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#f0eeec;color:#554f4a;font-size:9px;font-weight:900;text-transform:uppercase}.tseo-status--high{background:#fff0ee;color:#922d25}.tseo-status--medium{background:#fff3db;color:#79520b}.tseo-status--low{background:#edf3f7;color:#376074}.tseo-path{max-width:430px;font:11px/1.5 ui-monospace,Consolas,monospace;overflow-wrap:anywhere}.tseo-message{max-width:440px;color:#514b47;line-height:1.45}.tseo-form-inline{display:flex;flex-wrap:wrap;align-items:center;gap:7px}.tseo-form-inline select,.tseo-form-inline input{min-height:37px;padding:7px 9px;border:1px solid #d7d0c9;border-radius:7px;background:#fff}.tseo-note{margin:0;color:var(--seo-muted);font-size:12px;line-height:1.5}.tseo-404{display:grid;gap:10px}.tseo-404-card{padding:16px;border:1px solid var(--seo-line);border-radius:11px;background:#fff}.tseo-404-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.tseo-404-head h3{margin:0;font-size:17px;overflow-wrap:anywhere}.tseo-404-meta{display:flex;flex-wrap:wrap;gap:10px;margin:7px 0;color:var(--seo-muted);font-size:11px}.tseo-suggestions{display:flex;flex-wrap:wrap;gap:7px;margin-top:11px}.tseo-suggestion{display:flex;align-items:center;gap:6px;padding:7px;border:1px solid #eadfd7;border-radius:8px;background:#fffaf6}.tseo-suggestion code{font-size:10px}.tseo-pagination{display:flex;justify-content:flex-end;margin-top:14px}.tseo-pagination .pagination{display:flex;flex-wrap:wrap;gap:5px;margin:0}.tseo-pagination .page-link{border-radius:6px}.tseo-empty{padding:34px;color:var(--seo-muted);text-align:center}.tseo-privacy{margin-top:18px;padding:13px 15px;border-radius:9px;background:#f5f3f1;color:#5f5954;font-size:12px;line-height:1.55}@media(max-width:900px){.tseo-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:580px){.tseo-grid{grid-template-columns:1fr}.tseo-404-head{flex-direction:column}}
    .tseo-form-inline select,.tseo-form-inline input{min-height:44px}.tseo-monitor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:16px}.tseo-delta{padding:14px;border:1px solid var(--seo-line);border-radius:10px;background:#fff}.tseo-delta strong{display:block;font-size:24px}.tseo-delta span{color:var(--seo-muted);font-size:11px;font-weight:800}.tseo-status--ok{background:#e9f7ef;color:#17653a}.tseo-status--off{background:#f3f0ed;color:#6b625c}.tseo-status--new{background:#fff0ee;color:#922d25}.tseo-status--recurring{background:#fff3db;color:#79520b}.tseo-actions{display:flex;flex-direction:column;align-items:flex-start;gap:6px}.tseo-actions form{margin:0}.tseo-alert-list{display:grid;gap:9px}.tseo-alert-item{padding:13px 15px;border-left:4px solid #a73b32;border-radius:8px;background:#fff5f3}.tseo-alert-item.is-resolved{border-left-color:#27834d;background:#f0faf4}.tseo-alert-item p{margin:5px 0;color:#514b47}.tseo-alert-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px;color:var(--seo-muted);font-size:11px}.tseo-history-note{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:14px}@media(max-width:700px){.tseo-monitor-grid{grid-template-columns:1fr}}
</style>
<main class="seo2">
    <header class="seo2-head">
        <div>
            <h1>Technical SEO &amp; 404 Center</h1>
            <p>Find broken website journeys, page-structure problems and missing addresses without sending website content to an outside crawler.</p>
        </div>
        <div class="seo2-actions">
            @if($canViewMetadata)<a class="seo2-btn" href="{{ route('seo.index') }}">Search &amp; Sharing</a>@endif
            @if($canScan)
                <form method="POST" action="{{ route('seo.technical.scan') }}">@csrf<button class="seo2-btn seo2-btn--primary" type="submit">Run safe scan</button></form>
            @endif
        </div>
    </header>

    @if(session('message'))<div class="seo2-alert {{ session('alert-type') === 'error' ? 'seo2-alert--error' : '' }}" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>Please check this action:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="tseo-grid" aria-label="Technical SEO summary">
        <article class="tseo-metric"><strong>{{ (int)($latestCounts['high'] ?? 0) }}</strong><span>High priority</span></article>
        <article class="tseo-metric"><strong>{{ (int)($latestCounts['medium'] ?? 0) }}</strong><span>Needs improvement</span></article>
        <article class="tseo-metric"><strong>{{ $open404Count }}</strong><span>Open 404 addresses</span></article>
        <article class="tseo-metric"><strong>{{ $latestRun?->urls_checked ?? 0 }}</strong><span>URLs checked</span></article>
    </section>

    <section class="seo2-card tseo-section" aria-labelledby="monitoring-title">
        <header class="seo2-card__head">
            <div>
                <h2 id="monitoring-title">Monitoring &amp; scan history</h2>
                <p>Each completed scan is compared by stable finding fingerprint, so repaired and newly introduced problems are visible.</p>
            </div>
            <span class="tseo-status {{ $scheduleEnabled ? 'tseo-status--ok' : 'tseo-status--off' }}">
                Weekly schedule {{ $scheduleEnabled ? 'enabled' : 'off' }}
            </span>
        </header>
        <div class="seo2-card__body">
            <div class="tseo-history-note">
                <strong>{{ $scheduleEnabled ? 'Automatic scan: Monday at 03:10 (' . config('app.timezone') . ')' : 'Automatic scans are disabled; manual scans remain available.' }}</strong>
                <span class="tseo-status {{ $inAppAlertsEnabled ? 'tseo-status--ok' : 'tseo-status--off' }}">In-app alerts {{ $inAppAlertsEnabled ? 'on' : 'off' }}</span>
                <span class="tseo-status {{ $emailAlertsEnabled ? 'tseo-status--ok' : 'tseo-status--off' }}">Email alerts {{ $emailAlertsEnabled ? 'on' : 'off' }}</span>
            </div>

            @if($latestRun?->isCompletedSnapshot())
                <div class="tseo-monitor-grid" aria-label="Latest scan comparison">
                    <article class="tseo-delta"><strong>{{ $latestComparison['new'] }}</strong><span>{{ $latestComparison['has_baseline'] ? 'New since previous scan' : 'Findings in first baseline' }}</span></article>
                    <article class="tseo-delta"><strong>{{ $latestComparison['recurring'] }}</strong><span>Recurring findings</span></article>
                    <article class="tseo-delta"><strong>{{ $latestComparison['resolved'] }}</strong><span>Resolved since previous scan</span></article>
                </div>
                <p class="tseo-note">@if($latestComparison['has_baseline']) Comparing scan #{{ $latestRun->id }} with completed scan #{{ $latestComparison['previous_run_id'] }}. @else This is the baseline snapshot; the next completed scan will show resolved and recurring findings. @endif</p>
            @elseif($latestRun)
                <p class="seo2-alert seo2-alert--error">The latest scan is {{ str_replace('_', ' ', $latestRun->status) }}. A failed or running scan is never compared with a completed snapshot.</p>
            @else
                <p class="tseo-note">Run the first safe scan to establish a comparison baseline.</p>
            @endif

            <div class="seo2-table-wrap" style="margin-top:16px">
                <table class="seo2-table">
                    <thead><tr><th>Run</th><th>Started</th><th>Trigger</th><th>Status</th><th>URLs</th><th>Findings</th></tr></thead>
                    <tbody>
                    @forelse($runHistory as $run)
                        <tr>
                            <td><strong>#{{ $run->id }}</strong></td>
                            <td>{{ $run->started_at?->format('M j, Y H:i') ?? '—' }}</td>
                            <td>{{ ucfirst($run->trigger) }}</td>
                            <td><span class="tseo-status {{ $run->status === 'failed' ? 'tseo-status--high' : ($run->isCompletedSnapshot() ? 'tseo-status--ok' : '') }}">{{ str_replace('_', ' ', $run->status) }}</span></td>
                            <td>{{ $run->urls_checked }}</td>
                            <td>{{ $run->issues_found }}</td>
                        </tr>
                    @empty<tr><td colspan="6" class="tseo-empty">No scan history is available yet.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @if($auditAlerts->isNotEmpty())
    <section class="seo2-card tseo-section" aria-labelledby="monitor-alerts-title">
        <header class="seo2-card__head"><div><h2 id="monitor-alerts-title">Monitoring history</h2><p>Recent alerts are retained for audit history. A later clean scan marks earlier alerts as resolved.</p></div></header>
        <div class="seo2-card__body tseo-alert-list">
            @foreach($auditAlerts as $alert)
                @php($alertResolved = $latestRun?->isCompletedSnapshot() && $latestCounts->sum() === 0 && $latestRun->id > $alert->run_id)
                <article class="tseo-alert-item {{ $alertResolved ? 'is-resolved' : '' }}">
                    <strong>{{ $alert->title }}</strong>
                    <p>{{ $alert->message }}</p>
                    <div class="tseo-alert-meta"><span>Scan #{{ $alert->run_id }} · {{ $alert->created_at?->diffForHumans() }}@if($emailAlertsEnabled) · Email {{ $alert->email_status }}@endif</span><span class="seo2-chip {{ $alertResolved ? 'tseo-status--ok' : 'tseo-status--new' }}">{{ $alertResolved ? 'Resolved by clean scan #' . $latestRun->id : 'Needs review' }}</span></div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    <section class="seo2-card tseo-section" aria-labelledby="audit-findings-title">
        <header class="seo2-card__head">
            <div><h2 id="audit-findings-title">Latest scan findings</h2>
                <p>@if($latestRun)Last scan {{ $latestRun->completed_at?->diffForHumans() ?? 'is running' }} · {{ str_replace('_', ' ', $latestRun->status) }} · {{ $ignoredCount }} ignore rules @else No scan has been run yet. @endif</p>
            </div>
        </header>
        <div class="seo2-card__body">
            <form class="seo2-filter" method="GET" action="{{ route('seo.technical.index') }}">
                <label class="seo2-field"><span>Find a path</span><input type="search" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="/about-us"></label>
                <label class="seo2-field"><span>Finding</span><select name="issue_type"><option value="">All findings</option>@foreach(['broken_link'=>'Broken links','broken_image'=>'Broken images','http_4xx'=>'Page errors (4xx)','http_5xx'=>'Server errors (5xx)','redirect_in_link'=>'Redirecting links','orphan_page'=>'Orphan pages','canonical_conflict'=>'Canonical conflicts','duplicate_canonical'=>'Duplicate canonicals','missing_h1'=>'Missing H1','multiple_h1'=>'Multiple H1s','hreflang_mismatch'=>'Language mismatch','schema_mismatch'=>'Schema mismatch','head_mismatch'=>'Head mismatch','response_too_large'=>'Oversized response'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['issue_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="seo2-field"><span>Priority</span><select name="severity"><option value="">Every priority</option>@foreach(['high'=>'High','medium'=>'Medium','low'=>'Low'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['severity'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="seo2-field"><span>Visibility</span><select name="visibility"><option value="open" @selected($filters['visibility'] === 'open')>Open only</option><option value="ignored" @selected($filters['visibility'] === 'ignored')>Ignored only</option><option value="all" @selected($filters['visibility'] === 'all')>All</option></select></label>
                <button class="seo2-btn" type="submit">Apply filters</button>
            </form>

            <div class="seo2-table-wrap">
                <table class="seo2-table">
                    <thead><tr><th>Priority</th><th>Finding</th><th>Page and target</th><th>What it means</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($issues as $issue)
                        @php($isIgnored = isset($ignoredFingerprints[$issue->fingerprint]))
                        @php($issueState = $issueStates->get($issue->id))
                        <tr>
                            <td><span class="tseo-status tseo-status--{{ $issue->severity }}">{{ $issue->severity }}</span></td>
                            <td><strong>{{ str_replace('_', ' ', $issue->issue_type) }}</strong>@if($issueState)<br><span class="tseo-status tseo-status--{{ $issueState }}">{{ $issueState }}</span>@endif @if($isIgnored)<br><span class="tseo-status">Ignored</span>@endif @if($issue->http_status)<br><small>HTTP {{ $issue->http_status }}</small>@endif</td>
                            <td class="tseo-path"><strong>{{ $issue->source_path }}</strong>@if($issue->target_path)<br>→ {{ $issue->target_path }}@endif</td>
                            <td class="tseo-message">{{ $issue->message }}</td>
                            <td>
                                <div class="tseo-actions">
                                @foreach($issueActions->get($issue->id, []) as $action)
                                    <a class="seo2-btn {{ $action['kind'] === 'content' ? 'seo2-btn--soft' : '' }}" href="{{ $action['url'] }}" @if($action['kind'] === 'preview') target="_blank" rel="noopener" @endif>{{ $action['label'] }}</a>
                                @endforeach
                                @if($canIgnore && !$isIgnored)<form method="POST" action="{{ route('seo.technical.issues.ignore', $issue) }}">@csrf<button class="seo2-btn seo2-btn--soft" type="submit">Ignore exact finding</button></form>
                                @elseif($canIgnore && $isIgnored)
                                    @php($rule = $ignoredRules->get($issue->fingerprint))
                                    @if($rule)<form method="POST" action="{{ route('seo.technical.ignore-rules.destroy', $rule) }}">@csrf @method('DELETE')<button class="seo2-btn" type="submit">Stop ignoring</button></form>@endif
                                @elseif(($issueActions->get($issue->id, [])) === [])<span class="tseo-note">View only</span>@endif
                                </div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="5" class="tseo-empty">@if($latestRun)No findings match these filters.@else Run the first safe scan to create a technical SEO snapshot.@endif</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="tseo-pagination">{{ $issues->links('vendor.pagination.bootstrap-4') }}</div>
        </div>
    </section>

    <section class="seo2-card tseo-section" aria-labelledby="not-found-title">
        <header class="seo2-card__head"><div><h2 id="not-found-title">404 inbox</h2><p>Repeated missing visitor-facing addresses are grouped together. Queries, IP addresses, sessions and device details are never stored.</p></div></header>
        <div class="seo2-card__body">
            <form class="tseo-form-inline" method="GET" action="{{ route('seo.technical.index') }}">
                <label for="not-found-filter"><strong>Show</strong></label><select id="not-found-filter" name="not_found"><option value="open" @selected($filters['not_found'] === 'open')>Open</option><option value="resolved" @selected($filters['not_found'] === 'resolved')>Resolved</option><option value="all" @selected($filters['not_found'] === 'all')>All</option></select><button class="seo2-btn" type="submit">Apply</button>
            </form>
            <div class="tseo-404">
            @forelse($notFoundHits as $hit)
                <article class="tseo-404-card">
                    <div class="tseo-404-head"><div><h3 class="tseo-path">{{ $hit->path }}</h3><div class="tseo-404-meta"><span>{{ $hit->hits }} {{ Str::plural('visit', $hit->hits) }}</span><span>{{ strtoupper($hit->locale) }}</span><span>Last seen {{ $hit->last_seen_at?->diffForHumans() }}</span>@if($hit->referrer_path)<span>From {{ $hit->referrer_path }}</span>@endif</div></div>@if($hit->resolved_at)<span class="tseo-status">Resolved</span>@endif</div>
                    @if($liveNotFoundHits->get($hit->id, false))
                        <p class="tseo-note"><strong>Live again:</strong> this address now resolves to managed website content. Redirect creation is disabled so it cannot shadow the live page; dismiss this stale inbox record.</p>
                    @elseif($canRedirect && !$hit->resolved_at && !str_contains($hit->path, '[redacted]'))
                        <div class="tseo-suggestions" aria-label="Suggested managed destinations">
                        @forelse($suggestions[$hit->id] ?? [] as $suggestion)
                            <form class="tseo-suggestion" method="POST" action="{{ route('seo.technical.not-found.redirect', $hit) }}">@csrf<input type="hidden" name="destination" value="{{ $suggestion['path'] }}"><input type="hidden" name="status_code" value="301"><span><strong>{{ $suggestion['label'] }}</strong><br><code>{{ $suggestion['path'] }}</code></span><button class="seo2-btn seo2-btn--soft" type="submit">Create 301</button></form>
                        @empty<span class="tseo-note">No safe managed destination is available.</span>@endforelse
                        </div>
                    @elseif(str_contains($hit->path, '[redacted]'))<p class="tseo-note">Part of this address was privacy-redacted, so it cannot be turned into a redirect.</p>@endif
                    @if($canIgnore && !$hit->resolved_at)<form method="POST" action="{{ route('seo.technical.not-found.dismiss', $hit) }}" style="margin-top:10px">@csrf<button class="seo2-btn" type="submit">Dismiss for now</button></form>@endif
                </article>
            @empty<div class="tseo-empty">There are no missing addresses in this view.</div>@endforelse
            </div>
            <div class="tseo-pagination">{{ $notFoundHits->links('vendor.pagination.bootstrap-4') }}</div>
            <p class="tseo-privacy"><strong>Privacy-safe by design:</strong> only normalized public paths, an optional same-site referrer path, language, counts and timestamps are retained. Query strings and fragments are discarded; sensitive-looking path segments are replaced before hashing or storage.</p>
        </div>
    </section>
</main>
@endsection
