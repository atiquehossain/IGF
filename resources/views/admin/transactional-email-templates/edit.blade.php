@extends('admin.layouts.master')

@section('content')
<style>
    .mail-editor{--orange:#f26322;--brown:#8c3d0a;--ink:#25282a;--muted:#6d6d69;--email-bg:#f7efe8;--email-panel:#fff;--email-text:#202124;--email-button:#8c3d0a;--email-button-text:#fff;--email-border:#ead8ca;--email-radius:12px;--email-width:640px;max-width:1220px;margin:28px auto;padding:0 22px 60px;color:var(--ink)}.mail-editor *{box-sizing:border-box}.mail-editor__back{display:inline-flex;min-height:44px;align-items:center;color:var(--brown);font-weight:800;text-decoration:none}.mail-editor h1{margin:8px 0 5px;font:700 40px Georgia,serif}.mail-editor__lead{margin:0 0 20px;color:var(--muted)}.mail-notice{margin:0 0 18px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e;line-height:1.5}.mail-notice a{color:var(--brown);font-weight:800}.mail-notice--warning{border-color:#ebc98f;background:#fff8e9;color:#6e4a0f}.mail-placeholder-list{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0;padding:0;list-style:none}.mail-placeholder-list li{border:1px solid #ded8d2;border-radius:8px;padding:7px 9px;background:#fff}.mail-placeholder-list code{color:var(--brown);font-weight:800}.mail-workspace{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(340px,.95fr);align-items:start;gap:20px}.mail-form{display:grid;gap:18px;border:1px solid #e7e2dc;border-radius:12px;padding:22px;background:#fff}.mail-form__intro{margin:0;padding-bottom:16px;border-bottom:1px solid #eeeae6;color:var(--muted);line-height:1.55}.mail-field{display:grid;gap:7px}.mail-field label{font-weight:800}.mail-field small{color:var(--muted);line-height:1.45}.mail-field input,.mail-field textarea{width:100%;border:1px solid #d7d1ca;border-radius:8px;padding:11px;background:#fff;color:var(--ink);font:inherit}.mail-field input{min-height:46px}.mail-field textarea{min-height:102px;resize:vertical;line-height:1.55}.mail-field textarea[name=body]{min-height:180px}.mail-field input:focus,.mail-field textarea:focus{outline:3px solid rgba(242,99,34,.16);border-color:var(--orange)}.mail-field :disabled{cursor:not-allowed;background:#f4f3f1}.mail-error{color:#a32920;font-size:12px;font-weight:750}.mail-actions{display:flex;flex-wrap:wrap;gap:10px}.mail-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:1px solid #d9d2ca;border-radius:8px;padding:9px 15px;background:#fff;color:#4a4540;font-weight:800;text-decoration:none;cursor:pointer}.mail-btn--primary{border-color:var(--brown);background:var(--brown);color:#fff}.mail-btn--danger{color:#9b2d24}.mail-preview{position:sticky;top:18px;display:grid;gap:12px}.mail-preview__head h2{margin:0;font:700 24px Georgia,serif}.mail-preview__head p{margin:5px 0 0;color:var(--muted);font-size:13px}.mail-preview__frame{overflow:hidden;border:1px solid #ddd6ce;border-radius:12px;background:var(--email-bg);box-shadow:0 10px 28px rgba(37,40,42,.08)}.mail-preview__chrome{display:flex;gap:7px;padding:11px 14px;border-bottom:1px solid #ddd6ce;background:#fff}.mail-preview__chrome span{width:8px;height:8px;border-radius:50%;background:#d8d3cd}.mail-preview__subject{overflow-wrap:anywhere;padding:13px 18px;border-bottom:1px solid #ddd6ce;background:#fff;font-size:13px}.mail-preview__subject strong{display:block;margin-bottom:3px;color:var(--muted);font-size:10px;letter-spacing:.08em;text-transform:uppercase}.mail-preview__brand,.mail-preview__footer{width:min(calc(100% - 36px),var(--email-width));margin-inline:auto;color:var(--email-text)}.mail-preview__brand{padding-top:17px;font-size:15px;font-weight:800}.mail-preview__footer{padding:0 0 17px;font-size:11px;line-height:1.5;opacity:.82;white-space:pre-line}.mail-preview__message{width:min(calc(100% - 36px),var(--email-width));margin:12px auto;padding:24px;border:1px solid var(--email-border);border-radius:var(--email-radius);background:var(--email-panel);color:var(--email-text);line-height:1.6}.mail-preview__message h3{margin:0 0 16px;font:700 26px Georgia,serif;overflow-wrap:anywhere}.mail-preview__message p{margin:0 0 14px;white-space:pre-line;overflow-wrap:anywhere}.mail-preview__button{display:inline-flex;min-height:44px;align-items:center;margin:2px 0 16px;padding:8px 16px;border-radius:7px;background:var(--email-button);color:var(--email-button-text);font-weight:800;overflow-wrap:anywhere}.mail-preview__closing{color:var(--email-text);opacity:.82}.mail-preview__plain{border:1px solid #ddd6ce;border-radius:12px;background:#fff}.mail-preview__plain summary{min-height:44px;padding:12px 15px;cursor:pointer;font-weight:800}.mail-preview__plain pre{max-height:300px;overflow:auto;margin:0;padding:0 15px 15px;color:#343434;font:13px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;overflow-wrap:anywhere}.mail-sr-only{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}@media(max-width:920px){.mail-workspace{grid-template-columns:1fr}.mail-preview{position:static}}@media(max-width:700px){.mail-editor{padding-inline:12px}.mail-editor h1{font-size:34px}.mail-form{padding:15px}.mail-preview__message{width:calc(100% - 20px);padding:18px}.mail-preview__brand,.mail-preview__footer{width:calc(100% - 20px)}}
</style>
@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text($key, $replace);
    $language = $ui('email_templates.languages.'.$locale);
    $variantState = $ui($content['is_custom'] ? 'email_templates.status.customized_override' : 'email_templates.status.secure_default');
    $value = static fn (string $field): string => (string) old($field, $content[$field] ?? '');
    $describedBy = static function (string $field) use ($errors): string {
        return 'mail-'.$field.'-help'.($errors->has($field) ? ' mail-'.$field.'-error' : '');
    };
@endphp
<main class="mail-editor" data-mail-editor aria-labelledby="mail-editor-title" style="--email-bg:{{ $emailDesign['background_color'] }};--email-panel:{{ $emailDesign['panel_color'] }};--email-text:{{ $emailDesign['text_color'] }};--email-button:{{ $emailDesign['button_color'] }};--email-button-text:{{ $emailDesign['button_text_color'] }};--email-border:{{ $emailDesign['border_color'] }};--email-radius:{{ $emailDesign['corner_radius'] }};--email-width:{{ $emailDesign['content_width'] }}">
    <a class="mail-editor__back" href="{{ route('transactional-mail.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>&nbsp; {{ $ui('email_templates.back_all') }}</a>
    <h1 id="mail-editor-title">{{ $definition['label'] }}</h1>
    <p class="mail-editor__lead">{{ $ui('email_templates.variant_summary', ['language' => $language, 'state' => $variantState]) }}</p>
    <aside class="mail-notice" role="note" aria-labelledby="mail-placeholders-title">
        <strong id="mail-placeholders-title">{{ $ui('email_templates.allowed_placeholders') }}</strong>
        <ul class="mail-placeholder-list" aria-label="{{ $ui('email_templates.placeholder_list_label') }}">@foreach($definition['placeholders'] as $placeholder => $description)<li title="{{ $description }}"><code>&#123;&#123;{{ $placeholder }}&#125;&#125;</code></li>@endforeach</ul>
        <p style="margin:10px 0 0">{{ $ui('email_templates.placeholder_help') }}</p>
    </aside>
    @if($content['is_legacy'])<div class="mail-notice mail-notice--warning" role="status"><strong>{{ $ui('email_templates.legacy_title') }}</strong> {{ $ui('email_templates.legacy_body') }}</div>@endif
    @if(!$canEditTemplate)<div class="mail-notice" role="status"><strong>{{ $ui('email_templates.read_only_title') }}</strong> {{ $ui('email_templates.read_only_editor') }}</div>@endif
    <aside class="mail-notice" role="note"><strong>{{ $ui('email_templates.appearance_title') }}</strong> {{ $ui('email_templates.appearance_help') }} @if($canCustomizeAppearance)<a href="{{ route('site.settings.index', ['locale' => $locale]) }}#settings-email_design">{{ $ui('email_templates.appearance_action') }}</a>@endif</aside>

    <div class="mail-workspace">
        <form class="mail-form" method="POST" action="{{ route('transactional-mail.update', [$templateKey, $locale]) }}" aria-label="{{ $ui('email_templates.form_label', ['template' => $definition['label'], 'language' => $language]) }}">
            @csrf @method('PUT')
            <p class="mail-form__intro">{{ $ui('email_templates.guided_editor_help') }}</p>

            <div class="mail-field">
                <label for="mail-subject">{{ $ui('email_templates.fields.subject') }}</label>
                <input id="mail-subject" name="subject" value="{{ $value('subject') }}" maxlength="200" required data-mail-copy aria-describedby="{{ $describedBy('subject') }}" aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>
                <small id="mail-subject-help">{{ $ui('email_templates.fields.subject_help') }}</small>
                @error('subject')<span class="mail-error" id="mail-subject-error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="mail-field">
                <label for="mail-heading">{{ $ui('email_templates.fields.heading') }}</label>
                <input id="mail-heading" name="heading" value="{{ $value('heading') }}" maxlength="200" required data-mail-copy aria-describedby="{{ $describedBy('heading') }}" aria-invalid="{{ $errors->has('heading') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>
                <small id="mail-heading-help">{{ $ui('email_templates.fields.heading_help') }}</small>
                @error('heading')<span class="mail-error" id="mail-heading-error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="mail-field">
                <label for="mail-introduction">{{ $ui('email_templates.fields.introduction') }}</label>
                <textarea id="mail-introduction" name="introduction" maxlength="1200" required data-mail-copy aria-describedby="{{ $describedBy('introduction') }}" aria-invalid="{{ $errors->has('introduction') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>{{ $value('introduction') }}</textarea>
                <small id="mail-introduction-help">{{ $ui('email_templates.fields.introduction_help') }}</small>
                @error('introduction')<span class="mail-error" id="mail-introduction-error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="mail-field">
                <label for="mail-body">{{ $ui('email_templates.fields.body') }}</label>
                <textarea id="mail-body" name="body" maxlength="6000" required data-mail-copy aria-describedby="{{ $describedBy('body') }}" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>{{ $value('body') }}</textarea>
                <small id="mail-body-help">{{ $ui('email_templates.fields.body_help') }}</small>
                @error('body')<span class="mail-error" id="mail-body-error" role="alert">{{ $message }}</span>@enderror
            </div>

            @if($usesButton)
                <div class="mail-field">
                    <label for="mail-button-label">{{ $ui('email_templates.fields.button_label') }}</label>
                    <input id="mail-button-label" name="button_label" value="{{ $value('button_label') }}" maxlength="120" required data-mail-copy aria-describedby="{{ $describedBy('button_label') }}" aria-invalid="{{ $errors->has('button_label') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>
                    <small id="mail-button-label-help">{{ $ui('email_templates.fields.button_label_help') }}</small>
                    @error('button_label')<span class="mail-error" id="mail-button-label-error" role="alert">{{ $message }}</span>@enderror
                </div>
                <div class="mail-field">
                    <label for="mail-button-url">{{ $ui('email_templates.fields.button_url') }}</label>
                    <input id="mail-button-url" name="button_url" value="{{ $value('button_url') }}" maxlength="2048" required inputmode="url" data-mail-copy aria-describedby="{{ $describedBy('button_url') }}" aria-invalid="{{ $errors->has('button_url') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>
                    <small id="mail-button-url-help">{{ $ui('email_templates.fields.button_url_help') }}</small>
                    @error('button_url')<span class="mail-error" id="mail-button-url-error" role="alert">{{ $message }}</span>@enderror
                </div>
            @endif

            <div class="mail-field">
                <label for="mail-closing">{{ $ui('email_templates.fields.closing') }}</label>
                <textarea id="mail-closing" name="closing" maxlength="1500" required data-mail-copy aria-describedby="{{ $describedBy('closing') }}" aria-invalid="{{ $errors->has('closing') ? 'true' : 'false' }}" @disabled(!$canEditTemplate)>{{ $value('closing') }}</textarea>
                <small id="mail-closing-help">{{ $ui('email_templates.fields.closing_help') }}</small>
                @error('closing')<span class="mail-error" id="mail-closing-error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="mail-actions">@if($canEditTemplate)<button class="mail-btn mail-btn--primary" type="submit">{{ $ui('email_templates.save_safe_template') }}</button>@endif<a class="mail-btn igf-btn-secondary" href="{{ route('transactional-mail.index') }}">{{ $ui('common.cancel') }}</a></div>
        </form>

        <section class="mail-preview" aria-labelledby="mail-preview-title">
            <header class="mail-preview__head"><h2 id="mail-preview-title">{{ $ui('email_templates.preview.title') }}</h2><p>{{ $ui('email_templates.preview.help') }}</p></header>
            <div class="mail-preview__frame" role="group" aria-label="{{ $ui('email_templates.preview.visual_label') }}">
                <div class="mail-preview__chrome" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="mail-preview__subject"><strong>{{ $ui('email_templates.preview.subject') }}</strong><span data-preview-subject></span></div>
                @if($emailDesign['show_brand_name'])<div class="mail-preview__brand">{{ $emailDesign['brand_heading'] }}</div>@endif
                <article class="mail-preview__message">
                    <h3 data-preview-heading></h3>
                    <p data-preview-introduction></p>
                    <p data-preview-body></p>
                    @if($usesButton)<span class="mail-preview__button" data-preview-button></span>@endif
                    <p class="mail-preview__closing" data-preview-closing></p>
                </article>
                @if($emailDesign['footer_text'] !== '')<div class="mail-preview__footer">{{ $emailDesign['footer_text'] }}</div>@endif
            </div>
            <details class="mail-preview__plain"><summary>{{ $ui('email_templates.preview.plain_title') }}</summary><pre data-preview-plain></pre></details>
            <p class="mail-sr-only" role="status" aria-live="polite" data-preview-status></p>
        </section>
    </div>

    @if($content['is_custom'] && $canResetTemplate)<form method="POST" action="{{ route('transactional-mail.destroy', [$templateKey, $locale]) }}" onsubmit="return confirm(@js($ui('email_templates.confirm.restore_default')))" aria-label="{{ $ui('email_templates.actions.reset_label', ['template' => $definition['label'], 'language' => $language]) }}" style="margin-top:14px">@csrf @method('DELETE')<button class="mail-btn mail-btn--danger" type="submit">{{ $ui('email_templates.restore_code_default') }}</button></form>@endif
</main>
<script>
(() => {
    const editor = document.querySelector('[data-mail-editor]');
    if (!editor) return;
    const read = name => editor.querySelector(`[name="${name}"]`)?.value.trim() || '';
    const write = (selector, value) => { const node = editor.querySelector(selector); if (node) node.textContent = value; };
    const hasButton = @json($usesButton);
    const previewUpdated = @json($ui('email_templates.preview.updated'));
    let statusTimer;

    const render = announce => {
        const subject = read('subject');
        const heading = read('heading');
        const introduction = read('introduction');
        const body = read('body');
        const closing = read('closing');
        write('[data-preview-subject]', subject);
        write('[data-preview-heading]', heading);
        write('[data-preview-introduction]', introduction);
        write('[data-preview-body]', body);
        write('[data-preview-closing]', closing);
        if (hasButton) write('[data-preview-button]', read('button_label'));

        const parts = [heading, introduction, body];
        if (hasButton) parts.push(`${read('button_label')}: ${read('button_url')}`);
        parts.push(closing);
        write('[data-preview-plain]', parts.filter(Boolean).join('\n\n'));

        if (announce) {
            clearTimeout(statusTimer);
            statusTimer = setTimeout(() => write('[data-preview-status]', previewUpdated), 350);
        }
    };

    editor.querySelectorAll('[data-mail-copy]').forEach(field => field.addEventListener('input', () => render(true)));
    render(false);
})();
</script>
@endsection
