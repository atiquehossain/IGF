@php
    $values = $editor['values'];
    $fallback = $editor['fallback'];
    $effective = $editor['effective'];
    $autoContent = (bool) old('seo_auto', $editor['auto_content']);
    $customTitleValue = (string) old('seo.title', $values['title']);
    $customDescriptionValue = (string) old('seo.description', $values['description']);
    $displayTitleValue = $autoContent ? $fallback['title'] : $customTitleValue;
    $displayDescriptionValue = $autoContent ? $fallback['description'] : $customDescriptionValue;
    $isIndexable = (bool) old('seo.robots_index', $values['robots_index']);
    $schemaValue = old('seo.schema_markup', $values['schema_markup']);
    $schemaMode = old('schema_template', $editor['schema_selected']);
    $canEditMetadata = $canEditMetadata ?? true;
    $canRestoreRevisions = $canRestoreRevisions ?? false;
    $canReviewMetadata = $canReviewMetadata ?? false;
    $canViewMedia = $canViewMedia ?? false;
    $canUploadMedia = $canUploadMedia ?? false;
    $canUseExternalCanonical = $canUseExternalCanonical ?? false;
    $canOpenPage = $canOpenPage ?? true;
    $seoRevisionDiffs = $seoRevisionDiffs ?? collect();
    $seoRevisionCanonicalPolicies = $seoRevisionCanonicalPolicies ?? collect();
    $contentAnalysis = (array) ($editor['content_analysis'] ?? ['available' => false, 'issues' => []]);
    $contentAnalysisJson = json_encode([
        'available' => (bool) ($contentAnalysis['available'] ?? false),
        'saved_focus_phrase' => (string) $values['focus_keyword'],
        'focus_in_headings' => (bool) ($contentAnalysis['focus_in_headings'] ?? false),
        'focus_in_body' => (bool) ($contentAnalysis['focus_in_body'] ?? false),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    $reviewStatusLabel = match($editor['review']['status']) {
        'pending' => 'Review requested',
        'approved' => 'SEO approved',
        'changes_requested' => 'Changes requested',
        default => 'Not submitted for review',
    };
@endphp

<section class="seo2-card seo2-editor" id="seo-editor" aria-labelledby="seo-editor-title">
    <header class="seo2-card__head">
        <div>
            <h2 id="seo-editor-title">Search &amp; Sharing editor</h2>
            <p><strong>{{ $editorTitle }}</strong> <span aria-hidden="true">·</span> {{ strtoupper($editor['locale']) }} <span aria-hidden="true">·</span> <span data-final-url-label>{{ $effective['url'] }}</span></p>
        </div>
        <div class="seo2-actions">
            @if($editor['copy_url'] && $canEditMetadata)<a class="seo2-btn seo2-btn--soft" href="{{ $editor['copy_url'] }}"><i class="fa fa-copy" aria-hidden="true"></i> Copy English</a>@endif
            @if($canOpenPage)<a class="seo2-btn" href="{{ $editor['default_url'] }}" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Open page</a>@endif
        </div>
    </header>
    <div class="seo2-card__body">
        @if($editor['copying_english'])<div class="seo2-alert seo2-alert--warning" role="status">English values are copied into this draft. Review the wording, then save to create the {{ strtoupper($editor['locale']) }} version.</div>@endif

        <form method="POST" action="{{ $editorFormAction }}" data-seo-form data-original-indexable="{{ $isIndexable ? '1' : '0' }}" data-default-url="{{ $editor['default_url'] }}">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $editor['locale'] }}">
            @if($editor['page_editor_version'] !== null)<input type="hidden" name="expected_editor_version" value="{{ old('expected_editor_version', $editor['page_editor_version']) }}">@endif
            @if($editor['seo_editor_version'] !== null)<input type="hidden" name="expected_seo_version" value="{{ old('expected_seo_version', $editor['seo_editor_version']) }}">@endif
            @if($editorRouteName)<input type="hidden" name="route_name" value="{{ $editorRouteName }}">@endif
            <input type="hidden" name="seo[robots_index]" value="{{ $isIndexable ? 1 : 0 }}" data-index-value>
            <input type="hidden" name="seo[exclude_from_sitemap]" value="{{ $isIndexable ? 0 : 1 }}" data-sitemap-exclude>
            <input type="hidden" name="seo[twitter_image]" value="{{ old('seo.twitter_image', $values['twitter_image']) }}" data-twitter-image>
            <input type="hidden" name="seo[sitemap_priority]" value="{{ old('seo.sitemap_priority', $values['sitemap_priority']) }}">
            <input type="hidden" name="seo[sitemap_change_frequency]" value="{{ old('seo.sitemap_change_frequency', $values['sitemap_change_frequency']) }}">
            <textarea name="seo[schema_markup]" data-schema-value hidden>{{ $schemaValue }}</textarea>

            <div class="seo2-editor-layout">
                <fieldset class="seo2-editor-main seo2-editor-fieldset" @disabled(!$canEditMetadata)>
                    <section class="seo2-section" data-basic-section>
                        <h3>How this page appears in Google</h3>
                        <p class="seo2-section__intro">Use clear, natural wording. The preview updates while you type; Google may still adjust the final result.</p>
                        <label class="seo2-auto">
                            <input type="hidden" name="seo_auto" value="0">
                            <input type="checkbox" name="seo_auto" value="1" data-auto-content @checked($autoContent)>
                            <span><strong>Use the current page title and summary automatically</strong><small>The inherited text is shown below and updates whenever the page content changes. Turn this off only for custom search wording.</small></span>
                        </label>
                        <div class="seo2-field">
                            <label for="seo2-title">Search title <span class="seo2-inherited">Using page title</span><span class="seo2-counter" data-title-count aria-live="polite"></span></label>
                            <input id="seo2-title" name="seo[title]" maxlength="255" value="{{ $displayTitleValue }}" data-seo-title data-fallback="{{ $fallback['title'] }}" data-custom-value="{{ $customTitleValue }}" data-inherited-value="{{ $fallback['title'] }}" autocomplete="off" @readonly($autoContent)>
                            <p class="seo2-help seo2-inherited-note">This inherited value is read-only and is not saved as a custom override. The counter measures the text shown here.</p>
                            <p class="seo2-help">Aim for 35–60 characters and put the clearest words first.</p>
                        </div>
                        <div class="seo2-field">
                            <label for="seo2-description">Search description <span class="seo2-inherited">Using page summary</span><span class="seo2-counter" data-description-count aria-live="polite"></span></label>
                            <textarea id="seo2-description" name="seo[description]" maxlength="500" data-seo-description data-fallback="{{ $fallback['description'] }}" data-custom-value="{{ $customDescriptionValue }}" data-inherited-value="{{ $fallback['description'] }}" @readonly($autoContent)>{{ $displayDescriptionValue }}</textarea>
                            <p class="seo2-help seo2-inherited-note">This inherited value is read-only and is not saved as a custom override. The counter measures the text shown here.</p>
                            <p class="seo2-help">Aim for 120–160 characters. Explain what visitors will find and why it matters.</p>
                        </div>
                        <div class="seo2-field">
                            <label for="seo2-focus">Focus phrase <span class="seo2-optional">Optional writing check</span></label>
                            <input id="seo2-focus" name="seo[focus_keyword]" maxlength="255" value="{{ old('seo.focus_keyword', $values['focus_keyword']) }}" data-focus-phrase autocomplete="off" placeholder="Example: child education Bangladesh">
                            <p class="seo2-help">This is an internal writing aid, not a meta-keywords tag and not a ranking guarantee.</p>
                            <ul class="seo2-focus-checks" data-focus-checks aria-live="polite">
                                <li data-focus-check="title">Use the phrase naturally in the search title</li>
                                <li data-focus-check="description">Use it naturally in the description</li>
                                <li data-focus-check="url">Keep the page address related and readable</li>
                                @if($contentAnalysis['available'] ?? false)<li data-focus-check="headings">Use it naturally in a saved page heading</li><li data-focus-check="body">Use it naturally in the saved page body</li>@endif
                            </ul>
                        </div>
                    </section>

                    <section class="seo2-section">
                        <h3>Social sharing image</h3>
                        <p class="seo2-section__intro">This image appears when someone shares the page on Facebook, LinkedIn, WhatsApp or X.</p>
                        <div class="seo2-media-control">
                            <div class="seo2-media-thumb" data-share-thumb>
                                @if($effective['image'])<img src="{{ \Illuminate\Support\Str::startsWith($effective['image'], ['http://','https://']) ? $effective['image'] : url($effective['image']) }}" alt="">@else<span>No image</span>@endif
                            </div>
                            <div class="seo2-media-actions">
                                <input id="seo2-share-image" name="seo[og_image]" type="url" value="{{ old('seo.og_image', $values['og_image']) }}" data-share-image data-fallback="{{ $fallback['image'] ? (\Illuminate\Support\Str::startsWith($fallback['image'], ['http://','https://']) ? $fallback['image'] : url($fallback['image'])) : '' }}" aria-label="Social sharing image URL">
                                <button class="seo2-btn seo2-btn--soft" type="button" data-media-open><i class="fa fa-picture-o" aria-hidden="true"></i> Choose from Media Library</button>
                                <button class="seo2-btn" type="button" data-media-clear>Remove</button>
                            </div>
                        </div>
                        <div class="seo2-field" style="margin-top:12px">
                            <label for="seo2-share-image-alt">Image description <span class="seo2-optional">Recommended</span></label>
                            <input id="seo2-share-image-alt" name="seo[social_image_alt]" maxlength="420" value="{{ old('seo.social_image_alt', $values['social_image_alt']) }}" data-share-image-alt placeholder="Example: Children learning together in an Ignite community classroom">
                            <p class="seo2-help">Describe the image for people who cannot see it. Choosing an image from the Media Library copies its alternative text here; review it for this page.</p>
                        </div>
                    </section>

                    <section class="seo2-section">
                        <h3>Search visibility</h3>
                        <p class="seo2-section__intro">Most public pages should be visible. Hide only private, duplicate, temporary or unfinished content.</p>
                        <div class="seo2-visibility" role="radiogroup" aria-label="Search visibility">
                            <label class="seo2-choice"><input type="radio" name="search_visibility" value="index" data-visibility @checked($isIndexable)><span><strong><i class="fa fa-search" aria-hidden="true"></i> Show in search results</strong><small>Search engines may index this page and it stays in the sitemap.</small></span></label>
                            <label class="seo2-choice"><input type="radio" name="search_visibility" value="hidden" data-visibility @checked(!$isIndexable)><span><strong><i class="fa fa-eye-slash" aria-hidden="true"></i> Hide from search results</strong><small>Adds noindex and removes this page from the sitemap automatically.</small></span></label>
                        </div>
                        <div class="seo2-warning" data-visibility-warning @if($isIndexable) hidden @endif><strong>This page will be hidden.</strong> Existing search results can take time to disappear. Visitors with the direct link may still open it.</div>
                    </section>

                    @if($editor['permalink'])
                    <section class="seo2-section">
                        <h3>Page address</h3>
                        <p class="seo2-section__intro">Keep addresses short and descriptive. If you change it, the old address will permanently redirect to the new one.</p>
                        <div class="seo2-field">
                            <label for="seo2-slug">Permalink</label>
                            <div class="seo2-permalink"><span>{{ $editor['permalink']['prefix'] }}</span><input id="seo2-slug" name="permalink_slug" value="{{ old('permalink_slug', $editor['permalink']['slug']) }}" data-permalink-slug @disabled(!$editor['permalink']['editable'])></div>
                            @if(!$editor['permalink']['editable'])<input type="hidden" name="permalink_slug" value="{{ $editor['permalink']['slug'] }}"><p class="seo2-help">{{ $editor['permalink']['restriction'] ?? 'This primary website address is protected to prevent broken navigation.' }}</p>@else<p class="seo2-permalink-preview" data-permalink-preview>{{ $editor['default_url'] }}</p>@endif
                        </div>
                    </section>
                    @endif

                    <details class="seo2-advanced">
                        <summary><span><i class="fa fa-sliders" aria-hidden="true"></i> Advanced settings</span><small>For an SEO specialist</small></summary>
                        <div class="seo2-advanced__body">
                            <div class="seo2-field">
                                <label for="seo2-canonical">Preferred (canonical) URL</label>
                                <input id="seo2-canonical" name="seo[canonical_url]" type="url" value="{{ old('seo.canonical_url', $values['canonical_url']) }}" placeholder="{{ $editor['default_url'] }}" data-canonical>
                                <p class="seo2-help">Leave blank to use this page address automatically. A normal editor can only choose another address on <strong>{{ parse_url($editor['default_url'], PHP_URL_HOST) }}</strong>.</p>
                                @if($canUseExternalCanonical)
                                    <label class="seo2-auto"><input type="checkbox" name="external_canonical_confirm" value="1" @checked(old('external_canonical_confirm'))><span><strong>I intentionally want this page to credit another website</strong><small>Required only when the preferred URL uses a different scheme, domain or port. This can remove the current page from search results, so verify the destination first.</small></span></label>
                                @else
                                    <p class="seo2-help"><i class="fa fa-lock" aria-hidden="true"></i> Crediting another website is locked to an administrator with the External canonical specialist permission.</p>
                                @endif
                            </div>
                            <input type="hidden" name="seo[robots_follow]" value="0"><label class="seo2-auto"><input type="checkbox" name="seo[robots_follow]" value="1" @checked(old('seo.robots_follow', $values['robots_follow']))><span><strong>Let search engines follow links on this page</strong><small>Recommended for almost every public page.</small></span></label>

                            <details class="seo2-advanced">
                                <summary>Social text overrides</summary>
                                <div class="seo2-advanced__body seo2-grid">
                                    <div class="seo2-field"><label for="seo2-og-title">Open Graph title</label><input id="seo2-og-title" name="seo[og_title]" value="{{ old('seo.og_title', $values['og_title']) }}" data-social-title></div>
                                    <div class="seo2-field"><label for="seo2-twitter-title">X/Twitter title</label><input id="seo2-twitter-title" name="seo[twitter_title]" value="{{ old('seo.twitter_title', $values['twitter_title']) }}"></div>
                                    <div class="seo2-field"><label for="seo2-og-description">Open Graph description</label><textarea id="seo2-og-description" name="seo[og_description]" data-social-description>{{ old('seo.og_description', $values['og_description']) }}</textarea></div>
                                    <div class="seo2-field"><label for="seo2-twitter-description">X/Twitter description</label><textarea id="seo2-twitter-description" name="seo[twitter_description]">{{ old('seo.twitter_description', $values['twitter_description']) }}</textarea></div>
                                    <div class="seo2-field"><label for="seo2-twitter-card">X/Twitter card</label><select id="seo2-twitter-card" name="seo[twitter_card]"><option value="summary_large_image" @selected(old('seo.twitter_card', $values['twitter_card']) === 'summary_large_image')>Large image</option><option value="summary" @selected(old('seo.twitter_card', $values['twitter_card']) === 'summary')>Compact summary</option></select></div>
                                </div>
                            </details>

                            <section class="seo2-section" style="margin-top:15px">
                                <h3>Structured data</h3>
                                <p class="seo2-section__intro">Choose an optional page-specific template. With no custom template, the public page uses the managed organization, website and generic page Schema automatically.</p>
                                <div class="seo2-field"><label for="seo2-schema-template">Schema template</label><select id="seo2-schema-template" name="schema_template" data-schema-template>@foreach($editor['schema_options'] as $key => $label)<option value="{{ $key }}" @selected($schemaMode === $key)>{{ $label }} @if($editor['schema_suggested'] === $key) — recommended @endif</option>@endforeach<option value="expert" @selected($schemaMode === 'expert')>Expert: custom JSON</option></select></div>
                                <div class="seo2-field" data-schema-generated-wrap @if($schemaMode === 'expert') hidden @endif><label for="seo2-schema-generated">Generated custom schema markup</label><textarea id="seo2-schema-generated" class="seo2-code" readonly data-schema-generated></textarea><p class="seo2-help">Page-specific templates use the title, description, image and final URL above. “No custom template” intentionally leaves this field empty; the automatic public fallback still applies.</p></div>
                                <div class="seo2-field" data-schema-expert-wrap @if($schemaMode !== 'expert') hidden @endif><label for="seo2-schema-expert">Expert raw JSON-LD</label><textarea id="seo2-schema-expert" class="seo2-code" data-schema-expert>{{ $schemaValue }}</textarea><p class="seo2-help">Invalid JSON will not save. Use this only when supplied by an SEO specialist.</p></div>
                            </section>
                        </div>
                    </details>
                </fieldset>

                <aside class="seo2-editor-side" aria-label="Live previews">
                    <section class="seo2-section seo2-checklist">
                        <div class="seo2-checklist__head"><div><h3>SEO setup completeness</h3><p class="seo2-section__intro">{{ $editor['publication']['label'] }} · {{ $editor['health']['score'] }}% complete</p></div><span class="seo2-chip {{ $editor['health']['status']==='Needs attention' ? 'seo2-chip--danger' : ($editor['health']['status']==='Hidden' ? 'seo2-chip--neutral' : '') }}">{{ $editor['health']['status'] }}</span></div>
                        <div class="seo2-checklist__counts"><strong>{{ $editor['health']['required_count'] }} required</strong><span>{{ $editor['health']['recommended_count'] }} recommended</span></div>
                        <ul class="seo2-checklist__items">@forelse($editor['health']['issues'] as $issue)<li class="is-{{ $issue['level'] }}"><strong>{{ ucfirst($issue['level']) }}:</strong> {{ $issue['label'] }}</li>@empty<li class="is-complete"><strong>Complete:</strong> No outstanding SEO actions.</li>@endforelse</ul>
                        <div class="seo2-review"><strong>{{ $reviewStatusLabel }}</strong>@if($editor['review']['note'])<p>{{ $editor['review']['note'] }}</p>@endif
                            @if($canEditMetadata && $editor['review']['exists'] && $editor['review']['status'] !== 'pending')<button class="seo2-btn seo2-btn--soft" type="submit" form="seo-review-request-form">Request SEO review</button>@elseif($canEditMetadata && !$editor['review']['exists'])<small>Save once before requesting review.</small>@endif
                            @if($canReviewMetadata && $editor['review']['status'] === 'pending')<div class="seo2-review__actions"><button class="seo2-btn seo2-btn--soft" type="submit" form="seo-review-approve-form">Approve</button><label class="seo2-field"><span>Reviewer note</span><textarea name="note" form="seo-review-changes-form" maxlength="2000" required placeholder="Explain what needs to change"></textarea></label><button class="seo2-btn" type="submit" form="seo-review-changes-form">Request changes</button></div>@endif
                        </div>
                    </section>
                    @if($contentAnalysis['available'] ?? false)
                    <section class="seo2-section" data-saved-content-analysis>
                        <h3>Saved page content</h3>
                        <p class="seo2-section__intro">Rule-based {{ $contentAnalysis['locale_label'] }} checks review the page body without AI or keyword-density scoring.</p>
                        <div class="seo2-checklist__counts">
                            <strong>{{ $contentAnalysis['word_count'] }} words · {{ $contentAnalysis['readability'] }}</strong>
                            <span>H1: {{ $contentAnalysis['h1_count'] }} · H2: {{ $contentAnalysis['h2_count'] }}</span>
                        </div>
                        <ul class="seo2-checklist__items">
                            <li class="{{ $contentAnalysis['image_count'] > $contentAnalysis['images_with_alt'] ? 'is-required' : 'is-complete' }}"><strong>Image text:</strong> {{ $contentAnalysis['images_with_alt'] }}/{{ $contentAnalysis['image_count'] }} content images covered</li>
                            <li class="is-information"><strong>Links:</strong> {{ $contentAnalysis['internal_link_count'] }} internal · {{ $contentAnalysis['external_link_count'] }} external</li>
                            @if(empty($contentAnalysis['issues']))<li class="is-complete"><strong>Complete:</strong> Saved content has no outstanding structure or readability actions.</li>@endif
                        </ul>
                        <p class="seo2-help">These results use the last saved page content. Save content changes, then reload this editor to refresh them.</p>
                    </section>
                    @endif
                    <section class="seo2-section">
                        <div class="seo2-actions" style="justify-content:space-between;margin-bottom:13px"><h3 style="margin:0">Google preview</h3><div class="seo2-preview-toggle" aria-label="Preview size"><button class="seo2-btn" type="button" data-preview-device="desktop" aria-pressed="true">Desktop</button><button class="seo2-btn" type="button" data-preview-device="mobile" aria-pressed="false">Mobile</button></div></div>
                        <div class="seo2-google" data-google-preview data-device="desktop"><span class="seo2-google__url" data-preview-url>{{ $effective['url'] }}</span><strong class="seo2-google__title" data-preview-title>{{ $effective['title'] }}</strong><span class="seo2-google__description" data-preview-description>{{ $effective['description'] }}</span></div>
                    </section>
                    <section class="seo2-section">
                        <h3>Social preview</h3><p class="seo2-section__intro">A close preview of a shared link card.</p>
                        <div class="seo2-social"><div class="seo2-social__image" data-social-image>@if($effective['image'])<img src="{{ \Illuminate\Support\Str::startsWith($effective['image'], ['http://','https://']) ? $effective['image'] : url($effective['image']) }}" alt="">@else<span>Choose a social image</span>@endif</div><div class="seo2-social__copy"><small>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</small><strong data-social-preview-title>{{ $effective['title'] }}</strong><span data-social-preview-description>{{ $effective['description'] }}</span></div></div>
                    </section>
                    <section class="seo2-section">
                        <h3>Recent changes</h3><p class="seo2-section__intro">SEO changes are backed up before every save.</p>
                        <div class="seo2-history">
                            @forelse($seoRevisions as $revision)
                                @php
                                    $revisionPolicy = $seoRevisionCanonicalPolicies->get((string)$revision->uuid, ['external' => false, 'canonical' => '']);
                                    $restoreFormId = 'seo-restore-' . $revision->uuid;
                                    $diff = $seoRevisionDiffs->get((string)$revision->uuid, []);
                                @endphp
                                <div class="seo2-history-item">
                                    <strong>{{ $revision->reason ?: 'SEO update' }}</strong><small>{{ $revision->created_at?->format('M j, Y g:i A') }}</small>
                                    @if(count($diff))<details class="seo2-history-diff"><summary>What changed ({{ count($diff) }})</summary><dl>@foreach($diff as $change)<div><dt>{{ $change['field'] }}</dt><dd><del>{{ $change['before'] }}</del><ins>{{ $change['after'] }}</ins></dd></div>@endforeach</dl></details>@endif
                                    @if($canRestoreRevisions)
                                        @isset($seoRevisionRestoreUrl)
                                            @if($revisionPolicy['external'])
                                                <div class="seo2-warning"><strong>This version credits another website.</strong><br>{{ $revisionPolicy['canonical'] }}
                                                    @if($canUseExternalCanonical)
                                                        <label class="seo2-auto"><input type="checkbox" name="external_canonical_confirm" value="1" form="{{ $restoreFormId }}" required><span><strong>I confirm this external canonical restoration</strong><small>Required before this version can be restored.</small></span></label>
                                                    @else
                                                        <small>Restoring this version requires the External canonical specialist permission.</small>
                                                    @endif
                                                </div>
                                            @endif
                                            @if(!$revisionPolicy['external'] || $canUseExternalCanonical)
                                                <button class="seo2-btn" type="submit" form="{{ $restoreFormId }}" onclick="return confirm('Restore this earlier version? Your current settings will be kept as an undo version.')">Restore this version</button>
                                            @endif
                                        @endisset
                                    @endif
                                </div>
                            @empty
                                <p class="seo2-help">No earlier saved version yet.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>

            @if($canEditMetadata)<div class="seo2-savebar"><span class="seo2-dirty" data-dirty-status role="status" aria-live="polite">Unsaved changes</span><button class="seo2-btn seo2-btn--primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> Save Search &amp; Sharing</button></div>@endif
        </form>
    </div>
</section>

@if($canRestoreRevisions)
    @isset($seoRevisionRestoreUrl)
        @foreach($seoRevisions as $revision)
            @php
                $revisionPolicy = $seoRevisionCanonicalPolicies->get((string)$revision->uuid, ['external' => false]);
                $restoreFormId = 'seo-restore-' . $revision->uuid;
            @endphp
            @if(!$revisionPolicy['external'] || $canUseExternalCanonical)<form id="{{ $restoreFormId }}" method="POST" action="{{ $seoRevisionRestoreUrl($revision) }}" hidden>@csrf @if($editor['page_editor_version'] !== null)<input type="hidden" name="expected_editor_version" value="{{ $editor['page_editor_version'] }}">@endif @if($editor['seo_editor_version'] !== null)<input type="hidden" name="expected_seo_version" value="{{ $editor['seo_editor_version'] }}">@endif</form>@endif
        @endforeach
    @endisset
@endif

@if($editor['review']['exists'])
    @foreach(['request'=>'seo.review.request','approve'=>'seo.review.resolve','changes'=>'seo.review.resolve'] as $reviewAction => $reviewRoute)
        <form id="seo-review-{{ $reviewAction }}-form" method="POST" action="{{ route($reviewRoute) }}" hidden>@csrf<input type="hidden" name="owner_type" value="{{ $editor['review']['owner_type'] }}"><input type="hidden" name="owner_id" value="{{ $editor['review']['owner_id'] }}"><input type="hidden" name="route_name" value="{{ $editor['review']['route_name'] }}"><input type="hidden" name="locale" value="{{ $editor['locale'] }}">@if($reviewAction !== 'request')<input type="hidden" name="decision" value="{{ $reviewAction }}"><input type="hidden" name="expected_review_hash" value="{{ $editor['review']['content_hash'] }}"><input type="hidden" name="expected_review_version" value="{{ $editor['review']['request_version'] }}">@endif</form>
    @endforeach
@endif

<script type="application/json" data-schema-library>@json($editor['generated_schemas'])</script>
<script type="application/json" data-content-analysis>{!! $contentAnalysisJson !!}</script>
<div class="seo2-modal" data-media-modal data-media-endpoint="{{ route('seo.media.index') }}" role="dialog" aria-modal="true" aria-labelledby="seo2-media-title" hidden>
    <div class="seo2-modal__dialog"><header class="seo2-modal__head"><div><h2 id="seo2-media-title">Choose a social image</h2><small>Recommended: 1200 × 630 with useful alternative text.</small></div><div class="seo2-actions">@if($canViewMedia)<a class="seo2-btn seo2-btn--soft" href="{{ route('media.index', ['type'=>'image']) }}" target="_blank" rel="noopener"><i class="fa {{ $canUploadMedia ? 'fa-upload' : 'fa-picture-o' }}" aria-hidden="true"></i> {{ $canUploadMedia ? 'Upload or manage images' : 'Open Media Library' }}</a>@endif<button class="seo2-btn" type="button" data-media-close aria-label="Close image picker"><i class="fa fa-times" aria-hidden="true"></i></button></div></header><div class="seo2-modal__search"><label class="seo2-sr" for="seo2-media-search">Search uploaded images</label><input id="seo2-media-search" type="search" placeholder="Search file name or alt text" data-media-search><span data-media-result-status aria-live="polite"></span></div><div class="seo2-media-grid" data-media-grid><div class="seo2-empty"><p>Loading images…</p></div></div><footer class="seo2-media-pages"><button class="seo2-btn" type="button" data-media-previous>Previous</button><span data-media-page-status></span><button class="seo2-btn" type="button" data-media-next>Next</button></footer></div>
</div>
