@extends('admin.layouts.master')

@section('content')
<style>
    .igf-chat-admin{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#6d6965;--line:#e4ded8;max-width:1320px;margin:28px auto;padding:0 22px;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-chat-admin *{box-sizing:border-box}.igf-chat-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:20px}.igf-chat-head h1{margin:0;font:700 40px/1.08 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-chat-head p{max-width:760px;margin:8px 0 0;color:var(--muted);line-height:1.6}.igf-chat-tabs{display:flex;gap:8px;margin:0 0 18px;padding:5px;border:1px solid var(--line);border-radius:11px;background:#fff;width:max-content}.igf-chat-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 15px;border-radius:8px;color:#57514d;font-weight:800;text-decoration:none}.igf-chat-tab.is-active{background:#fff1e5;color:var(--brown)}.igf-chat-tab:hover{color:var(--brown)}.igf-chat-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.igf-chat-metric{padding:17px 18px;border:1px solid var(--line);border-radius:12px;background:#fff}.igf-chat-metric strong{display:block;font:700 27px 'Literata',Georgia,serif}.igf-chat-metric span{color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase}.igf-chat-card{border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 10px 28px rgba(25,28,29,.045)}.igf-chat-card__head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:18px 20px;border-bottom:1px solid var(--line)}.igf-chat-card__head h2{margin:0;font:650 23px 'Literata',Georgia,serif}.igf-chat-card__body{padding:20px}.igf-chat-filter{display:grid;grid-template-columns:minmax(220px,1fr) 180px auto;gap:10px}.igf-chat-field{display:grid;gap:6px}.igf-chat-field>span,.igf-chat-field>label{font-size:11px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.igf-chat-field input,.igf-chat-field select,.igf-chat-field textarea{width:100%;min-height:43px;padding:9px 11px;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:var(--ink)}.igf-chat-field textarea{min-height:90px;resize:vertical}.igf-chat-field input:focus,.igf-chat-field select:focus,.igf-chat-field textarea:focus{outline:3px solid rgba(255,117,0,.18);border-color:var(--orange)}.igf-chat-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:7px;padding:8px 14px;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:#45413e;font-weight:850;cursor:pointer;text-decoration:none}.igf-chat-button:hover{border-color:var(--orange);color:var(--brown)}.igf-chat-button--primary{border-color:var(--orange);background:var(--orange);color:#fff}.igf-chat-button--primary:hover{background:var(--brown);color:#fff}.igf-chat-button--danger{color:#9d2d25}.igf-chat-alert{margin-bottom:16px;padding:12px 14px;border-radius:9px;background:#eaf6ed;color:#24633a;font-weight:750}.igf-chat-alert--error{background:#fff0ee;color:#922d25}.igf-chat-table-wrap{overflow-x:auto}.igf-chat-table{width:100%;border-collapse:collapse}.igf-chat-table th{padding:11px 12px;border-bottom:1px solid var(--line);color:#6f6964;font-size:10px;letter-spacing:.06em;text-align:left;text-transform:uppercase}.igf-chat-table td{padding:14px 12px;border-bottom:1px solid #eee9e5;vertical-align:top}.igf-chat-table tr:last-child td{border-bottom:0}.igf-chat-person strong{display:block}.igf-chat-person small,.igf-chat-preview small{display:block;margin-top:3px;color:var(--muted)}.igf-chat-preview{max-width:460px;white-space:normal}.igf-chat-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#fff2e6;color:#8b480e;font-size:10px;font-weight:900;text-transform:uppercase}.igf-chat-status--answered{background:#e8f2ff;color:#245d91}.igf-chat-status--resolved{background:#e7f5eb;color:#246b3c}.igf-chat-status--closed{background:#edeae8;color:#5f5a56}.igf-chat-unread{display:inline-block;width:8px;height:8px;margin-right:6px;border-radius:50%;background:var(--orange)}.igf-chat-empty{padding:50px 20px;text-align:center;color:var(--muted)}.igf-chat-empty i{display:block;margin-bottom:12px;color:var(--orange);font-size:34px}.igf-chat-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;gap:18px}.igf-chat-stack{display:grid;gap:18px}.igf-chat-form{display:grid;gap:13px}.igf-chat-row{display:grid;grid-template-columns:1fr 130px;gap:10px}.igf-chat-check{display:flex;align-items:center;gap:9px;font-weight:750}.igf-chat-check input{width:18px;height:18px;accent-color:var(--orange)}.igf-chat-faq{border:1px solid var(--line);border-radius:10px;background:#fff}.igf-chat-faq summary{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 15px;cursor:pointer;list-style:none}.igf-chat-faq summary::-webkit-details-marker{display:none}.igf-chat-faq summary strong{display:block}.igf-chat-faq summary small{color:var(--muted)}.igf-chat-faq__body{padding:0 15px 15px;border-top:1px solid var(--line)}.igf-chat-readonly{padding:12px;border-radius:8px;background:#f7f4f1;color:#6a6561;font-size:12px;line-height:1.5}.igf-chat-note{margin:0;color:var(--muted);font-size:12px;line-height:1.6}.pagination{margin:18px 0 0}.igf-chat-visually-hidden{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(1px,1px,1px,1px)!important;white-space:nowrap!important}
    .igf-chat-button--primary{border-color:#9b3f00;background:#9b3f00;color:#fff}.igf-chat-button--primary:hover{border-color:#7b3200;background:#7b3200;color:#fff}
    .igf-chat-filter-stack{display:grid;gap:10px}.igf-chat-filter--search{grid-template-columns:minmax(220px,1fr) auto}.igf-chat-filter--status{grid-template-columns:180px auto;justify-content:start}.igf-chat-clear-form{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:8px;background:#f7f4f1}.igf-chat-clear-form p{margin:0;color:var(--muted);font-size:13px}
    @media(max-width:900px){.igf-chat-grid{grid-template-columns:1fr}.igf-chat-filter{grid-template-columns:1fr 170px}.igf-chat-filter .igf-chat-button{grid-column:1/-1}.igf-chat-head{align-items:flex-start;flex-direction:column}}
    @media(max-width:650px){.igf-chat-admin{padding:0 12px}.igf-chat-head h1{font-size:32px}.igf-chat-metrics{grid-template-columns:1fr}.igf-chat-filter,.igf-chat-row{grid-template-columns:1fr}.igf-chat-tabs{width:100%}.igf-chat-tab{flex:1;justify-content:center}.igf-chat-table th:nth-child(3),.igf-chat-table td:nth-child(3){display:none}}
</style>

<main class="igf-chat-admin">
    <header class="igf-chat-head">
        <div>
            <h1>Website Chat</h1>
            <p>{{ $tab === 'inbox' ? 'Review submitted visitor enquiries and reply safely.' : 'Control the predefined questions, approved answers, and public chat presentation without opening visitor conversations.' }}</p>
        </div>
        <a class="igf-chat-button" href="{{ route('frontend.home') }}" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Open website</a>
    </header>

    @if(session('success'))<div class="igf-chat-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="igf-chat-alert igf-chat-alert--error" role="alert"><strong>Please check the form:</strong> {{ $errors->first() }}</div>@endif

    <nav class="igf-chat-tabs" aria-label="Chat administration">
        @if($can['inbox'] ?? false)<a class="igf-chat-tab {{ $tab === 'inbox' ? 'is-active' : '' }}" href="{{ route('chat.index') }}"><i class="fa fa-inbox" aria-hidden="true"></i> Inbox</a>@endif
        @if($can['faq_view'] ?? false)<a class="igf-chat-tab {{ $tab === 'questions' ? 'is-active' : '' }}" href="{{ route('chat.faq.index') }}"><i class="fa fa-comments-o" aria-hidden="true"></i> Questions &amp; answers</a>@endif
    </nav>

    @if($tab === 'inbox')
        <div class="igf-chat-metrics" aria-label="Chat summary">
            <div class="igf-chat-metric"><strong>{{ $counts['waiting'] }}</strong><span>Waiting for staff</span></div>
            <div class="igf-chat-metric"><strong>{{ $counts['unread'] }}</strong><span>Unread activity</span></div>
            <div class="igf-chat-metric"><strong>{{ $counts['all'] }}</strong><span>Total conversations</span></div>
        </div>

        <section class="igf-chat-card">
            <div class="igf-chat-card__head"><h2>Conversation inbox</h2></div>
            <div class="igf-chat-card__body">
                <div class="igf-chat-filter-stack">
                <form class="igf-chat-filter igf-chat-filter--search" method="post" action="{{ route('chat.search') }}">@csrf
                    <label class="igf-chat-field"><span>Search name, contact or question</span><input type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" placeholder="Search conversations" required></label>
                    <button class="igf-chat-button igf-chat-button--primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                <form class="igf-chat-filter igf-chat-filter--status" method="get" action="{{ route('chat.index') }}">
                    <label class="igf-chat-field"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach(['waiting' => 'Waiting', 'answered' => 'Answered', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
                    <button class="igf-chat-button" type="submit">Apply status</button>
                </form>
                @if($search !== '')
                <form class="igf-chat-clear-form" method="post" action="{{ route('chat.search.clear') }}">@csrf
                    <p>Search is active. It will expire automatically after a short period.</p>
                    <button class="igf-chat-button" type="submit"><i class="fa fa-times" aria-hidden="true"></i> Clear search</button>
                </form>
                @endif
                </div>
            </div>
            <div class="igf-chat-table-wrap">
                <table class="igf-chat-table">
                    <thead><tr><th>Visitor</th><th>Latest message</th><th>Activity</th><th>Status</th><th><span class="igf-chat-visually-hidden">Action</span></th></tr></thead>
                    <tbody>
                    @forelse($conversations as $conversation)
                        @php($unread = $conversation->last_message_at && (!$conversation->admin_read_at || $conversation->admin_read_at->lt($conversation->last_message_at)))
                        <tr>
                            <td class="igf-chat-person">
                                <strong>@if($conversation->user){{ $conversation->user->name ?: 'Member #'.$conversation->user->id }}@else{{ $conversation->guest_name ?: 'Guest' }}@endif</strong>
                                @if($conversation->user)<small>Verified member #{{ $conversation->user->id }} · {{ $conversation->user->email ?: $conversation->user->phone_no }}</small>@else<small>Guest details are unverified · {{ $conversation->guest_email ?: $conversation->guest_phone }}</small>@endif
                            </td>
                            <td class="igf-chat-preview">@if($unread)<span class="igf-chat-unread" aria-label="Unread"></span>@endif{{ \Illuminate\Support\Str::limit($conversation->latestMessage?->body ?? 'No message', 110) }}<small>{{ $conversation->messages_count }} messages · {{ $conversation->locale === 'bn' ? 'Bangla' : 'English' }}</small></td>
                            <td>{{ optional($conversation->last_message_at)->diffForHumans() }}<small style="display:block;color:#777">{{ $conversation->page_url }}</small></td>
                            <td><span class="igf-chat-status igf-chat-status--{{ $conversation->status }}">{{ $conversation->status }}</span></td>
                            <td>@if($can['view'])<a class="igf-chat-button" href="{{ route('chat.show', $conversation) }}">Open</a>@else<span class="igf-chat-note">No access</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="igf-chat-empty"><i class="fa fa-comments-o" aria-hidden="true"></i><strong>No conversations found</strong><p>Submitted visitor questions will appear here.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($conversations->hasPages())<div class="igf-chat-card__body">{{ $conversations->links('vendor.pagination.bootstrap-4') }}</div>@endif
        </section>
    @else
        <div class="igf-chat-grid">
            <div class="igf-chat-stack">
                @foreach(['en' => 'English widget', 'bn' => 'Bangla widget'] as $locale => $label)
                    @php($setting = $settings->get($locale))
                    <section class="igf-chat-card">
                        <div class="igf-chat-card__head"><h2>{{ $label }}</h2><span class="igf-chat-status {{ $setting?->enabled ? 'igf-chat-status--resolved' : 'igf-chat-status--closed' }}">{{ $setting?->enabled ? 'Enabled' : 'Hidden' }}</span></div>
                        <div class="igf-chat-card__body">
                            @if($can['settings'])
                            <form class="igf-chat-form" method="post" action="{{ route('chat.settings.update', $locale) }}">@csrf @method('PUT')
                                <input type="hidden" name="enabled" value="0"><label class="igf-chat-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting?->enabled))> Show this language's chat window</label>
                                <label class="igf-chat-field"><span>Chat title</span><input name="title" required maxlength="120" value="{{ old('title', $setting?->title) }}"></label>
                                <label class="igf-chat-field"><span>Welcome message</span><textarea name="welcome_message" required maxlength="1000">{{ old('welcome_message', $setting?->welcome_message) }}</textarea></label>
                                <label class="igf-chat-field"><span>Optional short privacy note</span><textarea name="privacy_message" maxlength="500" placeholder="Leave blank to show no note">{{ old('privacy_message', $setting?->privacy_message) }}</textarea></label>
                                <button class="igf-chat-button igf-chat-button--primary" type="submit">Save {{ $locale === 'bn' ? 'Bangla' : 'English' }} settings</button>
                            </form>
                            @else<div class="igf-chat-readonly">You can review chat settings, but your administrator role cannot change them.</div>@endif
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="igf-chat-stack">
                @if($can['faq_store'])
                <section class="igf-chat-card">
                    <div class="igf-chat-card__head"><h2>Add a suggested question</h2></div>
                    <div class="igf-chat-card__body">
                        <form class="igf-chat-form" method="post" action="{{ route('chat.faq.store') }}">@csrf
                            <div class="igf-chat-row"><label class="igf-chat-field"><span>Language</span><select name="locale"><option value="en">English</option><option value="bn">Bangla</option></select></label><label class="igf-chat-field"><span>Display order</span><input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 10) }}" required></label></div>
                            <label class="igf-chat-field"><span>Question visitors will see</span><input name="question" maxlength="500" required value="{{ old('question') }}"></label>
                            <label class="igf-chat-field"><span>Approved answer</span><textarea name="answer" maxlength="4000" required>{{ old('answer') }}</textarea></label>
                            <input type="hidden" name="is_active" value="0"><label class="igf-chat-check"><input type="checkbox" name="is_active" value="1" checked> Show this question publicly</label>
                            <button class="igf-chat-button igf-chat-button--primary" type="submit">Add question &amp; answer</button>
                        </form>
                    </div>
                </section>
                @endif

                <section class="igf-chat-card">
                    <div class="igf-chat-card__head"><div><h2>Saved questions</h2><p class="igf-chat-note">Visitors see active questions in this order. Clicks are anonymous aggregate totals.</p></div><span class="igf-chat-status">{{ $faqs->count() }} questions · {{ number_format($faqs->sum('click_count')) }} {{ \Illuminate\Support\Str::plural('click', $faqs->sum('click_count')) }}</span></div>
                    <div class="igf-chat-card__body igf-chat-stack">
                        @forelse($faqs as $faq)
                        <details class="igf-chat-faq">
                            <summary><span><strong>{{ $faq->question }}</strong><small>{{ strtoupper($faq->locale) }} · order {{ $faq->sort_order }} · {{ number_format($faq->click_count) }} {{ \Illuminate\Support\Str::plural('click', $faq->click_count) }}</small></span><span class="igf-chat-status {{ $faq->is_active ? 'igf-chat-status--resolved' : 'igf-chat-status--closed' }}">{{ $faq->is_active ? 'Visible' : 'Hidden' }}</span></summary>
                            <div class="igf-chat-faq__body">
                                @if($can['faq_update'])
                                <form class="igf-chat-form" method="post" action="{{ route('chat.faq.update', $faq) }}">@csrf @method('PUT')
                                    <div class="igf-chat-row"><label class="igf-chat-field"><span>Language</span><select name="locale"><option value="en" @selected($faq->locale === 'en')>English</option><option value="bn" @selected($faq->locale === 'bn')>Bangla</option></select></label><label class="igf-chat-field"><span>Display order</span><input type="number" name="sort_order" min="0" max="9999" value="{{ $faq->sort_order }}" required></label></div>
                                    <label class="igf-chat-field"><span>Question</span><input name="question" maxlength="500" required value="{{ $faq->question }}"></label>
                                    <label class="igf-chat-field"><span>Answer</span><textarea name="answer" maxlength="4000" required>{{ $faq->answer }}</textarea></label>
                                    <input type="hidden" name="is_active" value="0"><label class="igf-chat-check"><input type="checkbox" name="is_active" value="1" @checked($faq->is_active)> Show publicly</label>
                                    <button class="igf-chat-button igf-chat-button--primary" type="submit">Save question</button>
                                </form>
                                @else<p style="white-space:pre-wrap">{{ $faq->answer }}</p>@endif
                                @if($can['faq_destroy'])<form method="post" action="{{ route('chat.faq.destroy', $faq) }}" style="margin-top:12px" onsubmit="return confirm('Remove this question from the public chat? Existing conversation history will stay intact.')">@csrf @method('DELETE')<button class="igf-chat-button igf-chat-button--danger" type="submit"><i class="fa fa-trash" aria-hidden="true"></i> Remove question</button></form>@endif
                            </div>
                        </details>
                        @empty<div class="igf-chat-empty">No questions have been added yet.</div>@endforelse
                    </div>
                </section>
            </div>
        </div>
    @endif
</main>
@endsection
