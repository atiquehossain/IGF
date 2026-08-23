@extends('admin.layouts.master')

@section('content')
<style>
    .igf-thread{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#6d6965;--line:#e4ded8;max-width:1180px;margin:28px auto;padding:0 22px;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-thread *{box-sizing:border-box}.igf-thread-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}.igf-thread-head h1{margin:8px 0 3px;font:700 34px/1.12 'Literata',Georgia,serif}.igf-thread-head p{margin:0;color:var(--muted)}.igf-thread-back{display:inline-flex;align-items:center;gap:7px;color:var(--brown);font-weight:850;text-decoration:none}.igf-thread-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;align-items:start;gap:18px}.igf-thread-card{border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 10px 28px rgba(25,28,29,.045)}.igf-thread-card__head{padding:17px 19px;border-bottom:1px solid var(--line)}.igf-thread-card__head h2{margin:0;font:650 22px 'Literata',Georgia,serif}.igf-thread-card__body{padding:19px}.igf-thread-messages{display:grid;gap:14px;max-height:590px;overflow:auto;padding:19px;background:#f8f6f4}.igf-thread-message{max-width:78%;padding:12px 14px;border:1px solid #ddd6d0;border-radius:13px 13px 13px 3px;background:#fff}.igf-thread-message--visitor{margin-left:auto;border-color:#ffb475;border-radius:13px 13px 3px 13px;background:#fff4ea}.igf-thread-message--admin{border-color:#bfd7f0;background:#edf6ff}.igf-thread-message p{margin:0;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}.igf-thread-message small{display:block;margin-top:7px;color:#77716c;font-size:10px;font-weight:800;text-transform:uppercase}.igf-thread-form{display:grid;gap:10px}.igf-thread-form textarea,.igf-thread-form select{width:100%;min-height:44px;padding:10px 11px;border:1px solid #d8d1ca;border-radius:8px}.igf-thread-form textarea{min-height:105px;resize:vertical}.igf-thread-form textarea:focus,.igf-thread-form select:focus{outline:3px solid rgba(255,117,0,.18);border-color:var(--orange)}.igf-thread-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:7px;padding:8px 14px;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:#45413e;font-weight:850;cursor:pointer;text-decoration:none}.igf-thread-button--primary{border-color:var(--orange);background:var(--orange);color:#fff}.igf-thread-meta{display:grid;gap:13px;margin:0}.igf-thread-meta div{padding-bottom:12px;border-bottom:1px solid #eee9e5}.igf-thread-meta div:last-child{border:0;padding-bottom:0}.igf-thread-meta dt{color:#77716c;font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.igf-thread-meta dd{margin:4px 0 0;overflow-wrap:anywhere}.igf-thread-alert{margin-bottom:15px;padding:12px 14px;border-radius:9px;background:#eaf6ed;color:#24633a;font-weight:750}.igf-thread-alert--error{background:#fff0ee;color:#922d25}.igf-thread-safety{padding:13px;border-radius:9px;background:#fff8e9;color:#725017;font-size:12px;line-height:1.6}.igf-thread-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#fff2e6;color:#8b480e;font-size:10px;font-weight:900;text-transform:uppercase}
    .igf-thread-button--primary{border-color:#9b3f00;background:#9b3f00;color:#fff}.igf-thread-button--primary:hover{border-color:#7b3200;background:#7b3200;color:#fff}
    @media(max-width:840px){.igf-thread-layout{grid-template-columns:1fr}.igf-thread-head{flex-direction:column}.igf-thread-message{max-width:90%}}
    @media(max-width:600px){.igf-thread{padding:0 12px}.igf-thread-head h1{font-size:29px}}
</style>

<main class="igf-thread">
    <header class="igf-thread-head">
        <div><a class="igf-thread-back btn igf-btn igf-btn-tertiary" href="{{ route('chat.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to chat inbox</a><h1>@if($conversation->user){{ $conversation->user->name ?: 'Member #'.$conversation->user->id }}@else{{ $conversation->guest_name ?: 'Guest visitor' }}@endif</h1><p>Conversation {{ substr($conversation->uuid, 0, 8) }}</p></div>
        <span class="igf-thread-status">{{ $conversation->status }}</span>
    </header>

    @if(session('success'))<div class="igf-thread-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="igf-thread-alert igf-thread-alert--error" role="alert">{{ $errors->first() }}</div>@endif

    <div class="igf-thread-layout">
        <section class="igf-thread-card">
            <div class="igf-thread-card__head"><h2>Messages</h2></div>
            <div class="igf-thread-messages" aria-label="Conversation transcript">
                @foreach($conversation->messages as $message)
                    <article class="igf-thread-message igf-thread-message--{{ $message->sender_type }}">
                        <p>{{ $message->body }}</p>
                        <small>@if($message->sender_type === 'visitor')Visitor @elseif($message->sender_type === 'admin')Ignite staff @else Automatic answer @endif · {{ optional($message->created_at)->format('d M Y, g:i a') }}</small>
                    </article>
                @endforeach
            </div>
            @if($canReply)
            <div class="igf-thread-card__body">
                <form class="igf-thread-form" method="post" action="{{ route('chat.reply', $conversation) }}">@csrf
                    <label for="chat-reply"><strong>Reply as Ignite staff</strong></label>
                    <textarea id="chat-reply" name="body" required maxlength="2000" placeholder="Write a clear, non-sensitive reply...">{{ old('body') }}</textarea>
                    <button class="igf-thread-button igf-thread-button--primary" type="submit"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send reply</button>
                </form>
            </div>
            @endif
        </section>

        <aside style="display:grid;gap:18px">
            <section class="igf-thread-card"><div class="igf-thread-card__head"><h2>Visitor</h2></div><div class="igf-thread-card__body"><dl class="igf-thread-meta">
                <div><dt>Identity</dt><dd>@if($conversation->user)Verified member #{{ $conversation->user->id }}@else Guest — supplied details are unverified @endif</dd></div>
                <div><dt>Name</dt><dd>{{ $conversation->user?->name ?: $conversation->guest_name ?: 'Not provided' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $conversation->user?->email ?: $conversation->guest_email ?: 'Not provided' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $conversation->user?->phone_no ?: $conversation->guest_phone ?: 'Not provided' }}</dd></div>
                <div><dt>Language</dt><dd>{{ $conversation->locale === 'bn' ? 'Bangla' : 'English' }}</dd></div>
                <div><dt>Started on page</dt><dd>{{ $conversation->page_url ?: 'Unknown page' }}</dd></div>
            </dl></div></section>

            @if($canStatus)<section class="igf-thread-card"><div class="igf-thread-card__head"><h2>Conversation status</h2></div><div class="igf-thread-card__body"><form class="igf-thread-form" method="post" action="{{ route('chat.status', $conversation) }}">@csrf @method('PUT')<label for="chat-status"><strong>Status</strong></label><select id="chat-status" name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($conversation->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="igf-thread-button" type="submit">Update status</button></form></div></section>@endif

            <div class="igf-thread-safety"><strong>Privacy reminder:</strong> never request passwords, card details, NID, medical records, or safeguarding reports in chat. Move sensitive or emergency matters to the approved secure process.</div>
        </aside>
    </div>
</main>
@endsection
