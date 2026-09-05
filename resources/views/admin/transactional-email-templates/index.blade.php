@extends('admin.layouts.master')

@section('content')
<style>
    .mail-templates{--orange:#f26322;--brown:#8c3d0a;--ink:#25282a;--muted:#6d6d69;max-width:1220px;margin:28px auto;padding:0 22px 60px;color:var(--ink)}.mail-templates *{box-sizing:border-box}.mail-templates__head{display:flex;align-items:end;justify-content:space-between;gap:22px;margin-bottom:18px}.mail-templates h1{margin:0;font:700 42px Georgia,serif}.mail-templates__head p{max-width:760px;margin:8px 0 0;color:var(--muted);line-height:1.55}.mail-lock{margin:0 0 20px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e;line-height:1.5}.mail-lock strong{display:block}.mail-grid{display:grid;gap:16px}.mail-card{overflow:hidden;border:1px solid #e7e2dc;border-radius:12px;background:#fff;box-shadow:0 7px 22px rgba(37,40,42,.04)}.mail-card__head{padding:18px 20px;border-bottom:1px solid #eeeae6}.mail-card__head h2{margin:0;font:650 22px Georgia,serif}.mail-card__head p{margin:6px 0 0;color:var(--muted)}.mail-variants{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.mail-variant{display:grid;grid-template-columns:1fr auto;align-items:center;gap:16px;padding:18px 20px}.mail-variant+ .mail-variant{border-left:1px solid #eeeae6}.mail-variant h3{margin:0 0 5px;font-size:15px}.mail-variant p{overflow:hidden;margin:0;color:var(--muted);font-size:12px;text-overflow:ellipsis;white-space:nowrap}.mail-badge{display:inline-flex;margin-left:7px;padding:3px 7px;border-radius:999px;background:#f0ece8;color:#665f59;font-size:10px;font-weight:800;text-transform:uppercase}.mail-badge.is-custom{background:#fff0e6;color:var(--brown)}.mail-actions{display:flex;align-items:center;gap:8px}.mail-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:1px solid #d9d2ca;border-radius:8px;padding:9px 13px;background:#fff;color:#4a4540;font-weight:800;text-decoration:none;cursor:pointer}.mail-btn:hover{border-color:var(--orange);color:var(--brown)}.mail-btn--danger{color:#9b2d24}.mail-view-only{color:#777;font-size:11px;font-weight:800;text-transform:uppercase}@media(max-width:780px){.mail-templates{padding-inline:12px}.mail-templates__head{align-items:start;flex-direction:column}.mail-variants{grid-template-columns:1fr}.mail-variant+ .mail-variant{border-top:1px solid #eeeae6;border-left:0}.mail-variant{grid-template-columns:1fr}.mail-actions{flex-wrap:wrap}}
</style>
@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text($key, $replace);
@endphp
<main class="mail-templates" aria-labelledby="mail-templates-title">
    <header class="mail-templates__head">
        <div><h1 id="mail-templates-title">{{ $ui('email_templates.title') }}</h1><p>{{ $ui('email_templates.intro') }}</p></div>
        @if($canCustomizeAppearance)<a class="mail-btn" href="{{ route('site.settings.index', ['locale' => app()->getLocale()]) }}#settings-email_design"><i class="fa fa-paint-brush" aria-hidden="true"></i>&nbsp; {{ $ui('email_templates.appearance_action') }}</a>@endif
    </header>
    <div class="mail-lock" role="note"><strong>{{ $ui('email_templates.delivery_locked_title') }}</strong> {{ $ui('email_templates.delivery_locked_body') }}</div>
    @if(!$canEditTemplates && !$canResetTemplates)<div class="mail-lock" role="status"><strong>{{ $ui('email_templates.read_only_title') }}</strong> {{ $ui('email_templates.read_only_index') }}</div>@endif
    <section class="mail-grid" aria-label="{{ $ui('email_templates.list_label') }}">
        @foreach($definitions as $key => $definition)
            <article class="mail-card" aria-labelledby="mail-template-{{ $key }}">
                <header class="mail-card__head"><h2 id="mail-template-{{ $key }}">{{ $definition['label'] }}</h2><p>{{ $definition['description'] }}</p></header>
                <div class="mail-variants">
                    @foreach($locales as $locale)
                        @php
                            $variant = $definition['variants'][$locale];
                            $language = $ui('email_templates.languages.'.$locale);
                            $variantStatus = $ui($variant['is_custom'] ? 'email_templates.status.customized' : 'email_templates.status.default');
                            $actionLabelKey = $canEditTemplates ? 'email_templates.actions.open_editor_label' : 'email_templates.actions.review_label';
                        @endphp
                        <section class="mail-variant">
                            <div><h3>{{ $language }} <span class="mail-badge {{ $variant['is_custom'] ? 'is-custom' : '' }}">{{ $variantStatus }}</span></h3><p title="{{ $variant['subject'] }}">{{ $variant['subject'] }}</p></div>
                            <div class="mail-actions"><a class="mail-btn" href="{{ route('transactional-mail.show', [$key, $locale]) }}" aria-label="{{ $ui($actionLabelKey, ['template' => $definition['label'], 'language' => $language]) }}">{{ $ui($canEditTemplates ? 'email_templates.actions.open_editor' : 'email_templates.actions.review') }}</a>@if($variant['is_custom'] && $canResetTemplates)<form method="POST" action="{{ route('transactional-mail.destroy', [$key, $locale]) }}" onsubmit="return confirm(@js($ui('email_templates.confirm.reset_language')))" aria-label="{{ $ui('email_templates.actions.reset_label', ['template' => $definition['label'], 'language' => $language]) }}">@csrf @method('DELETE')<button class="mail-btn mail-btn--danger" type="submit">{{ $ui('email_templates.actions.reset') }}</button></form>@endif</div>
                        </section>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>
</main>
@endsection
