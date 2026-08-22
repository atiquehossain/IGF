@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<style>
    .seo-links .seo2-head{align-items:center}.seo-links-notice{display:grid;grid-template-columns:24px 1fr;gap:10px;margin-bottom:18px;padding:14px 16px;border:1px solid #d6eadb;border-radius:11px;background:#f1faf3;color:#285c36}.seo-links-notice i{padding-top:2px;text-align:center}.seo-links-notice strong,.seo-links-notice span{display:block}.seo-links-notice span{margin-top:3px;font-size:12px;line-height:1.5}.seo-links-filter{grid-template-columns:minmax(240px,1fr) 210px auto}.seo-links-list{display:grid;gap:15px}.seo-links-target{overflow:hidden;border:1px solid var(--seo-line);border-radius:12px;background:#fff}.seo-links-target__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:17px 18px;border-bottom:1px solid var(--seo-line);background:#fffaf6}.seo-links-target__identity{min-width:0}.seo-links-target__identity h2{margin:0;font-size:20px}.seo-links-target__identity p{margin:5px 0 0;color:var(--seo-muted);font-size:11px;overflow-wrap:anywhere}.seo-links-target__status{display:flex;flex:0 0 auto;align-items:center;gap:7px}.seo-links-target__body{padding:17px 18px}.seo-links-target__summary{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:13px}.seo-links-target__summary p{margin:0;color:var(--seo-muted);font-size:12px;line-height:1.5}.seo-links-suggestions{display:grid;gap:10px}.seo-links-suggestion{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1.2fr) auto;align-items:center;gap:14px;padding:13px;border:1px solid #eee7e0;border-radius:10px}.seo-links-suggestion__source strong,.seo-links-suggestion__source small{display:block}.seo-links-suggestion__source small{margin-top:4px;color:var(--seo-muted);font-size:10px}.seo-links-suggestion__why{display:grid;gap:6px}.seo-links-suggestion__why p{margin:0;color:#514a44;font-size:11px;line-height:1.45}.seo-links-anchors{display:flex;flex-wrap:wrap;gap:5px}.seo-links-anchor{display:inline-flex;padding:5px 7px;border-radius:6px;background:#f2efec;color:#433d38;font-size:10px;font-weight:750}.seo-links-disconnected{padding:18px;border:1px dashed #d7d0c9;border-radius:10px;background:#faf8f6;color:var(--seo-muted)}.seo-links-disconnected strong{display:block;color:var(--seo-ink)}.seo-links-disconnected p{margin:5px 0 0;font-size:12px;line-height:1.5}.seo-links-method{margin-bottom:18px}.seo-links-method summary{cursor:pointer;color:var(--seo-brown);font-weight:800}.seo-links-method p{margin:10px 0 0;color:var(--seo-muted);font-size:12px;line-height:1.6}.seo-links-target__actions{display:flex;flex-wrap:wrap;gap:7px}.seo-links-readonly{display:block;max-width:190px;color:var(--seo-muted);font-size:10px;line-height:1.4}.seo-links-empty{margin-bottom:24px}
    @media(max-width:980px){.seo-links-suggestion{grid-template-columns:1fr 1fr}.seo-links-suggestion__action{grid-column:1/-1}.seo-links-suggestion__action .seo2-btn{width:100%}}
    @media(max-width:680px){.seo-links .seo2-head{align-items:flex-start}.seo-links .seo2-head .seo2-actions{width:100%}.seo-links .seo2-head .seo2-actions .seo2-btn{flex:1 1 160px}.seo-links-filter,.seo-links-suggestion{grid-template-columns:1fr}.seo-links-target__head,.seo-links-target__summary{align-items:flex-start;flex-direction:column}.seo-links-target__status{flex-wrap:wrap}.seo-links-target__actions,.seo-links-target__actions .seo2-btn{width:100%}.seo-links-suggestion__action{grid-column:auto}.seo-links-suggestion__action .seo2-btn{width:100%}}
</style>
<main class="seo2 seo-links">
    <header class="seo2-head">
        <div>
            <h1>Contextual link assistant</h1>
            <p>Find public pages with few internal links, then choose relevant source pages using your existing content, categories, projects and focus phrases.</p>
        </div>
        <div class="seo2-actions">
            <a class="seo2-btn" href="{{ route('seo.index', ['locale' => $locale]) }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Search &amp; Sharing</a>
            @if($canViewTechnicalSeo)<a class="seo2-btn seo2-btn--soft" href="{{ route('seo.technical.index', ['issue_type' => 'orphan_page', 'visibility' => 'open']) }}">Technical link checks</a>@endif
        </div>
    </header>

    <div class="seo-links-notice" role="note">
        <i class="fa fa-shield" aria-hidden="true"></i>
        <div><strong>Suggestions only—nothing is inserted or published automatically.</strong><span>The assistant uses transparent matching rules and has no AI or external API cost. Open a source page, review the context, and add the link manually.</span></div>
    </div>

    @unless($localeIsPublic)
        <div class="seo2-alert seo2-alert--warning" role="status"><strong>{{ strtoupper($locale) }} is not enabled on the public site.</strong> These recommendations are preparation-only. Public preview links are hidden because visitors currently receive the default language at those addresses.</div>
    @endunless

    <nav class="seo2-language" aria-label="Internal-link language">
        @foreach($locales as $language)
            <a class="{{ (string) $language->id === $locale ? 'is-active' : '' }}" href="{{ route('seo.internal-links.index', ['locale' => $language->id, 'search' => $filters['search'], 'status' => $filters['status']]) }}" @if((string) $language->id === $locale) aria-current="page" @endif>
                <span>{{ $language->name }}</span>
            </a>
        @endforeach
    </nav>

    <section class="seo2-metrics" aria-label="Internal-link summary">
        <article class="seo2-metric"><strong>{{ $analysis['public_page_count'] }}</strong><span>{{ $localeIsPublic ? 'Indexable public pages checked' : 'Publish-ready pages checked' }}</span></article>
        <article class="seo2-metric"><strong>{{ $analysis['orphan_target_count'] }}</strong><span>Pages with no contextual page links</span></article>
        <article class="seo2-metric"><strong>{{ $analysis['weak_target_count'] - $analysis['orphan_target_count'] }}</strong><span>Pages with only one contextual link</span></article>
        <article class="seo2-metric"><strong>{{ $analysis['suggestion_count'] }}</strong><span>Relevant source suggestions</span></article>
    </section>

    @if($analysis['is_limited'])
        <div class="seo2-alert seo2-alert--warning" role="status"><strong>Large content library:</strong> this review uses the first 250 public pages to keep the admin responsive.</div>
    @endif

    <details class="seo2-card seo-links-method">
        <summary class="seo2-card__head">How are recommendations chosen?</summary>
        <div class="seo2-card__body"><p>Only pages in {{ strtoupper($locale) }} are compared. Same-category and shared-project matches carry the most weight, followed by focus-phrase, title and content overlap. Pages that already link to the target are removed from the suggestions. Hidden, private, draft, noindex and sitemap-excluded pages are not recommended.</p></div>
    </details>

    <section class="seo2-card" style="margin-bottom:24px" aria-labelledby="seo-links-results-title">
        <header class="seo2-card__head"><div><h2 id="seo-links-results-title">Pages to strengthen</h2><p>This editorial assistant counts links inside managed page content only. Header, footer, archive-card and template-navigation links are excluded; use Technical link checks for site-wide orphan status.</p></div></header>
        <div class="seo2-card__body">
            <form class="seo2-filter seo-links-filter" method="GET" action="{{ route('seo.internal-links.index') }}">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <label class="seo2-field"><span>Find a target page</span><input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Search title, address or focus phrase"></label>
                <label class="seo2-field"><span>Contextual link coverage</span><select name="status"><option value="all" @selected($filters['status'] === 'all')>All pages to strengthen</option><option value="orphan" @selected($filters['status'] === 'orphan')>No contextual page links</option><option value="weak" @selected($filters['status'] === 'weak')>Only one contextual page link</option></select></label>
                <button class="seo2-btn seo2-btn--primary" type="submit">Apply filters</button>
            </form>

            @if($pagination->total())
                <p class="seo2-result-summary">Showing {{ $pagination->firstItem() }}–{{ $pagination->lastItem() }} of {{ $pagination->total() }} matching target {{ $pagination->total() === 1 ? 'page' : 'pages' }}.</p>
            @endif

            <div class="seo-links-list">
                @forelse($visibleTargets as $target)
                    <article class="seo-links-target">
                        <header class="seo-links-target__head">
                            <div class="seo-links-target__identity">
                                <h2>{{ $target['title'] }}</h2>
                                <p>{{ strtoupper($target['locale']) }} · {{ $localeIsPublic ? $target['public_url'] : 'Translated address not live' }}</p>
                            </div>
                            <div class="seo-links-target__status">
                                <span class="seo2-chip {{ $target['status'] === 'orphan' ? 'seo2-chip--danger' : '' }}">{{ $target['status'] === 'orphan' ? 'No contextual links' : 'One contextual link' }}</span>
                                <span class="seo2-chip seo2-chip--neutral">{{ $target['inbound_count'] }} contextual inbound</span>
                            </div>
                        </header>
                        <div class="seo-links-target__body">
                            <div class="seo-links-target__summary">
                                <p><strong>{{ $target['status_label'] }}.</strong> Review a source suggestion and add a natural link where it helps the reader.</p>
                                <div class="seo-links-target__actions">
                                    <a class="seo2-btn seo2-btn--soft" href="{{ $target['editor_url'] }}">Open target SEO</a>
                                    @if($canEditPageContent)<a class="seo2-btn" href="{{ $target['content_editor_url'] }}">Edit target content</a>@endif
                                    @if($localeIsPublic)<a class="seo2-btn" href="{{ $target['public_url'] }}" target="_blank" rel="noopener">View target <span class="seo2-sr">in a new tab</span></a>@endif
                                </div>
                            </div>

                            <div class="seo-links-suggestions">
                                @forelse($target['suggestions'] as $suggestion)
                                    <section class="seo-links-suggestion" aria-label="Suggested source: {{ $suggestion['source_title'] }}">
                                        <div class="seo-links-suggestion__source">
                                            <strong>{{ $suggestion['source_title'] }}</strong>
                                            <small>{{ strtoupper($suggestion['source_locale']) }} · relevance score {{ $suggestion['score'] }}</small>
                                        </div>
                                        <div class="seo-links-suggestion__why">
                                            <p><strong>Why it fits:</strong> {{ implode(' · ', $suggestion['reasons']) }}</p>
                                            @if($suggestion['anchor_phrases'])
                                                <div class="seo-links-anchors" aria-label="Suggested anchor phrases">
                                                    @foreach($suggestion['anchor_phrases'] as $anchor)<span class="seo-links-anchor">“{{ $anchor }}”</span>@endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="seo-links-suggestion__action">
                                            @if($canEditPageContent)
                                                <a class="seo2-btn seo2-btn--primary" href="{{ $suggestion['source_editor_url'] }}">Edit source content</a>
                                            @else
                                                <span class="seo-links-readonly">Ask a Content Hub editor to add this link.</span>
                                            @endif
                                        </div>
                                    </section>
                                @empty
                                    <div class="seo-links-disconnected"><strong>No relevant source page found in {{ strtoupper($locale) }}.</strong><p>This is a disconnected topic, not a safe automatic match. Add a category, project or focus phrase to related pages, or create a deliberate navigation path before linking.</p></div>
                                @endforelse
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="seo2-empty seo-links-empty">
                        @if($analysis['public_page_count'] === 0)
                            <h3>No {{ $localeIsPublic ? 'indexable public' : 'publish-ready' }} pages in {{ strtoupper($locale) }}</h3><p>Publish content in this language before reviewing internal links.</p>
                        @elseif($analysis['public_page_count'] === 1)
                            <h3>One public page is available</h3><p>At least two pages in the same language are needed to recommend an internal link.</p>
                        @elseif($filters['search'] !== '' || $filters['status'] !== 'all')
                            <h3>No pages match these filters</h3><p>Try another title or show all weak pages.</p><a class="seo2-btn" href="{{ route('seo.internal-links.index', ['locale' => $locale]) }}">Clear filters</a>
                        @else
                            <h3>Internal-link coverage looks healthy</h3><p>Every checked page has links from at least two other managed pages.</p>
                        @endif
                    </div>
                @endforelse
            </div>

            @if($pagination->hasPages())<nav class="seo2-pagination" aria-label="Internal-link target pages">{{ $pagination->links('vendor.pagination.bootstrap-4') }}</nav>@endif
        </div>
    </section>
</main>
@endsection
