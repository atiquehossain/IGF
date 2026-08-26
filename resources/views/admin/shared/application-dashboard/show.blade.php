<link rel="stylesheet" href="{{ asset('admin-assets/application-dashboard/dashboard.css') }}">

@php
    $pretty = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
@endphp

<main class="ad-page" aria-labelledby="ad-detail-title">
    <header class="ad-detail-header">
        <div class="ad-detail-heading">
            <a class="ad-icon-link" href="{{ route($routeNames['index'], ['listing' => $listing->uuid]) }}" aria-label="Back to {{ $isJob ? 'applications' : 'registrations' }}"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
            <div>
                <p class="ad-eyebrow">{{ $listingLabel }}</p>
                <h1 id="ad-detail-title">{{ $record->name }}</h1>
                <p><code>{{ $record->reference_number }}</code> · form version {{ $record->formVersion?->version ?? 'unknown' }}</p>
            </div>
        </div>
        <span class="ad-status ad-status-{{ $record->workflow_status }}">{{ $pretty($record->workflow_status) }}</span>
    </header>

    @if(session('message'))
        <div class="alert alert-{{ session('alert-type', 'success') }}" role="status">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>The request could not be completed.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($record->anonymized_at)
        <div class="alert alert-warning" role="status"><strong>Anonymized.</strong> Direct identifiers, submitted answers, private documents, and sensitive note content have been removed.</div>
    @endif

    <div class="ad-detail-grid">
        <div class="ad-detail-main">
            <section class="ad-card" aria-labelledby="ad-contact-title">
                <div class="ad-card-heading"><div><h2 id="ad-contact-title">Contact details</h2><p>Copy an address to contact the applicant manually outside this system.</p></div></div>
                <dl class="ad-detail-list">
                    <div><dt>Name</dt><dd>{{ $record->name }}</dd></div>
                    <div><dt>Email</dt><dd><span>{{ $record->email }}</span> <button class="ad-copy-button" type="button" data-copy-value="{{ $record->email }}"><i class="fa fa-copy" aria-hidden="true"></i> Copy email</button></dd></div>
                    <div><dt>Phone</dt><dd>@if($record->phone)<span>{{ $record->phone }}</span> <button class="ad-copy-button" type="button" data-copy-value="{{ $record->phone }}"><i class="fa fa-copy" aria-hidden="true"></i> Copy phone</button>@else Not provided @endif</dd></div>
                    <div><dt>First submitted</dt><dd><time datetime="{{ $record->first_submitted_at?->toAtomString() }}">{{ $record->first_submitted_at?->format('d M Y, g:i A') }}</time></dd></div>
                    <div><dt>Last submitted</dt><dd><time datetime="{{ $record->last_submitted_at?->toAtomString() }}">{{ $record->last_submitted_at?->format('d M Y, g:i A') }}</time></dd></div>
                    <div><dt>Submission count</dt><dd>{{ $record->submission_count }}</dd></div>
                    <div><dt>Source</dt><dd>{{ $pretty($record->source) }}</dd></div>
                    <div><dt>Assigned reviewer</dt><dd>{{ $record->assignedAdmin?->name ?: ($record->assignedAdmin?->username ?: 'Unassigned') }}</dd></div>
                </dl>
            </section>

            <section class="ad-card" aria-labelledby="ad-answers-title">
                <div class="ad-card-heading"><div><h2 id="ad-answers-title">Versioned answers</h2><p>Labels come from the immutable form version used for this submission.</p></div></div>
                @forelse($answerRows as $answer)
                    <div class="ad-answer"><h3>{{ $answer['label'] }}</h3><p>{{ $answer['value'] !== '' ? $answer['value'] : 'No answer' }}</p></div>
                @empty
                    <p class="ad-empty-copy">No custom answers are stored for this submission.</p>
                @endforelse
            </section>

            <section class="ad-card" aria-labelledby="ad-documents-title">
                <div class="ad-card-heading"><div><h2 id="ad-documents-title">Private documents</h2><p>Downloads are permission checked, audited, and served with no-store headers.</p></div></div>
                <ul class="ad-document-list">
                    @forelse($record->documents as $document)
                        <li>
                            <div><i class="fa fa-file-pdf-o" aria-hidden="true"></i> <strong>{{ $document->original_name }}</strong><span>{{ $pretty($document->document_kind) }} · {{ number_format($document->bytes / 1024, 1) }} KiB</span></div>
                            @if($canDownload)<a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['download'], [$record, $document]) }}"><i class="fa fa-download" aria-hidden="true"></i> Download</a>@else<span class="ad-help">Your role cannot download private files.</span>@endif
                        </li>
                    @empty
                        <li class="ad-empty-copy">No private documents are attached.</li>
                    @endforelse
                </ul>
            </section>

            @if($isJob)
                <section class="ad-card" aria-labelledby="ad-scorecard-title">
                    <div class="ad-card-heading"><div><h2 id="ad-scorecard-title">Job scorecard</h2><p>Each reviewer maintains one score per criterion.</p></div></div>
                    @forelse($criteria as $criterion)
                        @php
                            $myScore = $record->scores->first(fn ($score) => (int) $score->job_scorecard_criterion_id === (int) $criterion->id && (int) $score->reviewer_admin_id === (int) $actor->id);
                            $criterionScores = $record->scores->where('job_scorecard_criterion_id', $criterion->id);
                        @endphp
                        <article class="ad-scorecard-row">
                            <div><h3>{{ $criterion->label }}</h3>@if($criterion->description)<p>{{ $criterion->description }}</p>@endif<p class="ad-help">Maximum {{ number_format((float) $criterion->maximum_score, 2) }} · {{ $criterionScores->count() }} reviewer score(s)</p></div>
                            @if($canEdit)
                                <form method="post" action="{{ route($routeNames['score'], $record) }}" class="ad-score-form">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="criterion" value="{{ $criterion->uuid }}">
                                    <div class="ad-field"><label for="score-{{ $criterion->id }}">Your score</label><input id="score-{{ $criterion->id }}" name="score" type="number" min="0" max="{{ $criterion->maximum_score }}" step="0.01" value="{{ $myScore?->score }}" required></div>
                                    <div class="ad-field ad-field-grow"><label for="score-comment-{{ $criterion->id }}">Private rationale</label><textarea id="score-comment-{{ $criterion->id }}" name="comment" rows="2" maxlength="20000">{{ $myScore?->comment }}</textarea></div>
                                    <button class="btn igf-btn igf-btn-primary" type="submit">Save score</button>
                                </form>
                            @endif
                            @if($criterionScores->isNotEmpty())
                                <details><summary>View team scores</summary><ul class="ad-timeline">@foreach($criterionScores as $score)<li><strong>{{ $score->reviewerAdmin?->name ?: ($score->reviewerAdmin?->username ?: 'Former administrator') }}</strong> — {{ $score->score }}/{{ $score->maximum_score_snapshot }}@if($score->comment)<p>{{ $score->comment }}</p>@endif</li>@endforeach</ul></details>
                            @endif
                        </article>
                    @empty
                        <p class="ad-empty-copy">No scorecard criteria are configured for this job.</p>
                    @endforelse
                </section>
            @endif

            <section class="ad-card" aria-labelledby="ad-notes-title" data-cy="private-notes">
                <div class="ad-card-heading"><div><h2 id="ad-notes-title">Private notes</h2><p>Notes are append-only. Applicants never see them.</p></div></div>
                @if($canEdit)
                    <form method="post" action="{{ route($routeNames['note'], $record) }}" class="ad-note-form">
                        @csrf
                        <div class="ad-field ad-field-grow"><label for="ad-note-body">Add a note</label><textarea id="ad-note-body" name="body" rows="4" maxlength="20000" required></textarea></div>
                        <button class="btn igf-btn igf-btn-primary" type="submit">Add private note</button>
                    </form>
                @endif
                <ol class="ad-timeline">
                    @forelse($record->notes as $note)
                        <li><div><strong>{{ $note->author_name_snapshot ?: 'Former administrator' }}</strong><time datetime="{{ $note->created_at?->toAtomString() }}">{{ $note->created_at?->format('d M Y, g:i A') }}</time></div><p>{{ $note->body }}</p></li>
                    @empty
                        <li class="ad-empty-copy">No private notes yet.</li>
                    @endforelse
                </ol>
            </section>

            <section class="ad-card" aria-labelledby="ad-history-title">
                <div class="ad-card-heading"><div><h2 id="ad-history-title">Status history</h2><p>Append-only workflow events for this record.</p></div></div>
                <ol class="ad-timeline">
                    @forelse($record->statusEvents->sortByDesc('id') as $event)
                        <li><div><strong>{{ $pretty($event->to_status) }}</strong><time datetime="{{ $event->created_at?->toAtomString() }}">{{ $event->created_at?->format('d M Y, g:i A') }}</time></div><p>From {{ $event->from_status ? $pretty($event->from_status) : 'initial state' }} · {{ $event->actor_name_snapshot ?: $pretty($event->source) }}</p></li>
                    @empty
                        <li class="ad-empty-copy">No status changes have been recorded.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="ad-detail-sidebar" aria-label="Application controls">
            <section class="ad-card ad-sticky-card" aria-labelledby="ad-workflow-title">
                <div class="ad-card-heading"><div><h2 id="ad-workflow-title">Review controls</h2><p>Changes are audited.</p></div></div>
                @if($canEdit)
                    <form method="post" action="{{ route($routeNames['assign'], $record) }}" class="ad-stacked-form">
                        @csrf
                        @method('PATCH')
                        <div class="ad-field"><label for="ad-detail-assignee">Assigned reviewer</label><select id="ad-detail-assignee" name="assigned_to_admin_id"><option value="">Unassigned</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((int) $record->assigned_to_admin_id === (int) $assignee->id)>{{ $assignee->name ?: $assignee->username }}</option>@endforeach</select></div>
                        <button class="btn igf-btn igf-btn-secondary" type="submit">Save assignment</button>
                    </form>
                    <form method="post" action="{{ route($routeNames['workflow'], $record) }}" class="ad-stacked-form">
                        @csrf
                        @method('PATCH')
                        <div class="ad-field"><label for="ad-detail-status">Move from {{ $pretty($record->workflow_status) }}</label><select id="ad-detail-status" name="workflow_status" required @disabled($transitions === [])>@forelse($transitions as $status)<option value="{{ $status }}">{{ $pretty($status) }}</option>@empty<option value="">No available transitions</option>@endforelse</select></div>
                        <button class="btn igf-btn igf-btn-primary" type="submit" @disabled($transitions === [])>Update status</button>
                    </form>
                @else
                    <p class="ad-help">Your role has view-only access to this record.</p>
                @endif
            </section>

            @if($canAnonymize || $canDelete)
                <section class="ad-card ad-danger-zone" aria-labelledby="ad-privacy-title">
                    <div class="ad-card-heading"><div><h2 id="ad-privacy-title">Owner privacy controls</h2><p>These actions cannot be undone.</p></div></div>
                    @if($canAnonymize && !$record->anonymized_at)
                        <form method="post" action="{{ route($routeNames['anonymize'], $record) }}" class="ad-stacked-form">
                            @csrf
                            <p>Removes direct identifiers, answers, documents, and sensitive note/score text while retaining an anonymized workflow record.</p>
                            <div class="ad-field"><label for="ad-anonymize-confirmation">Type <code>ANONYMIZE {{ $record->reference_number }}</code></label><input id="ad-anonymize-confirmation" name="confirmation" type="text" autocomplete="off" required></div>
                            <button class="btn btn-warning" type="submit">Anonymize permanently</button>
                        </form>
                    @endif
                    @if($canDelete)
                        <form method="post" action="{{ route($routeNames['delete'], $record) }}" class="ad-stacked-form">
                            @csrf
                            @method('DELETE')
                            <p>Permanently deletes the record and its private documents. Prefer anonymization when a workflow history should remain.</p>
                            <div class="ad-field"><label for="ad-delete-confirmation">Type <code>DELETE {{ $record->reference_number }}</code></label><input id="ad-delete-confirmation" name="confirmation" type="text" autocomplete="off" required></div>
                            <button class="btn btn-danger" type="submit">Delete permanently</button>
                        </form>
                    @endif
                </section>
            @endif
        </aside>
    </div>

    <div class="ad-copy-status" data-copy-status role="status" aria-live="polite"></div>
</main>

@section('custom-js')
<script src="{{ asset('admin-assets/application-dashboard/dashboard.js') }}" defer></script>
@endsection
