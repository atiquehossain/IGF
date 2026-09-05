@extends('admin.layouts.master')

@php
    $initialAmounts = old('amount_cards', $amountCards->map(fn ($card) => [
        'uuid' => $card->uuid,
        'amount' => $card->amount,
        'impact' => (array) $card->impact,
        'enabled' => (bool) $card->enabled,
    ])->all());
    $initialSections = old('landing_sections', $landingSections->map(fn ($section) => [
        'uuid' => $section->uuid,
        'layout' => $section->layout,
        'title' => (array) $section->title,
        'body' => (array) $section->body,
        'image_media_uuid' => $section->image_media_uuid,
        'image_alt' => (array) $section->image_alt,
        'video_media_uuid' => $section->video_media_uuid,
        'video_url' => $section->video_url,
        'video_title' => (array) $section->video_title,
        'video_transcript' => (array) $section->video_transcript,
        'cta_label' => (array) $section->cta_label,
        'cta_url' => $section->cta_url,
        'enabled' => (bool) $section->enabled,
    ])->all());
@endphp

@section('content')
<style>
    .cause-content-shell{max-width:1180px;margin:0 auto}.cause-content-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}.cause-editor-card{border:1px solid #e4ded9;border-radius:10px;padding:18px;background:#fff;box-shadow:0 5px 18px rgba(20,20,20,.04)}.cause-editor-list{display:grid;gap:16px}.cause-editor-row{position:relative;border:1px solid #ddd6d0;border-left:4px solid #ff7500;border-radius:9px;padding:18px;background:#fcfbfa}.cause-editor-row__heading{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px}.cause-editor-actions{display:flex;flex-wrap:wrap;gap:7px}.cause-editor-empty{border:2px dashed #ddd6d0;border-radius:9px;padding:24px;background:#faf9f8;color:#68635f;text-align:center}.cause-editor-help{border-left:4px solid #2f6f63;padding:12px 15px;background:#eef6f3;color:#254e46}.cause-editor-media-note{padding:10px 12px;border-radius:7px;background:#fff7ee;color:#71421f}.cause-content-save{position:sticky;z-index:10;bottom:14px;display:flex;justify-content:flex-end;margin-top:24px;pointer-events:none}.cause-content-save .btn{pointer-events:auto;box-shadow:0 8px 24px rgba(0,0,0,.18)}@media(max-width:767px){.cause-editor-row{padding:14px}.cause-editor-actions .btn{min-height:42px}.cause-content-toolbar .btn{width:100%}}
</style>

<div class="content pb-4">
    <div class="cause-content-shell">
        <header class="cause-content-toolbar mb-4">
            <div>
                <a href="{{ route('donationType.index') }}" class="d-inline-block mb-2">&larr; {{ __('donation_content.view.back') }}</a>
                <h1 class="h3 mb-1">{{ __('donation_content.view.heading', ['name' => $donationType->name]) }}</h1>
                <p class="text-muted mb-0">{{ __('donation_content.view.introduction') }}</p>
            </div>
            <a href="{{ $publicUrl }}" class="btn igf-btn igf-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="fa fa-external-link" aria-hidden="true"></i> {{ __('donation_content.view.view_public_page') }}</a>
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" id="cause-content-errors">
                <strong>{{ __('donation_content.view.error_heading') }}</strong>
                <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="cause-content-form" action="{{ route('donationType.content.update', $donationType) }}" method="POST">
            @csrf
            @method('PUT')
            <input id="cause-content-editor-ready" type="hidden" name="content_editor_ready" value="0">
            <input id="cause-amounts-payload-ready" type="hidden" name="amount_cards_payload_ready" value="0">
            <input id="cause-sections-payload-ready" type="hidden" name="landing_sections_payload_ready" value="0">
            <input type="hidden" name="content_editor_version" value="{{ old('content_editor_version', (int) $donationType->content_editor_version) }}">

            <noscript>
                <div class="alert alert-danger" role="alert">
                    {{ __('donation_content.view.noscript') }}
                </div>
            </noscript>

            <section class="card mb-4" aria-labelledby="cause-amounts-heading">
                <div class="card-header cause-content-toolbar">
                    <div>
                        <h2 id="cause-amounts-heading" class="h5 mb-1">{{ __('donation_content.view.amounts.heading') }}</h2>
                        <small class="text-muted">{{ __('donation_content.view.amounts.summary') }}</small>
                    </div>
                    <button id="add-amount-card" type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-plus" aria-hidden="true"></i> {{ __('donation_content.view.amounts.add') }}</button>
                </div>
                <div class="card-body">
                    <p class="cause-editor-help"><strong>{{ __('donation_content.view.amounts.help_lead') }}</strong> {{ __('donation_content.view.amounts.help_body', ['min' => number_format($minDonationAmount), 'max' => number_format($maxDonationAmount), 'maximum' => $maxAmountCards]) }}</p>
                    <div id="amount-card-list" class="cause-editor-list"></div>
                    <p id="amount-card-empty" class="cause-editor-empty mb-0" hidden>{{ __('donation_content.view.amounts.empty') }}</p>
                </div>
            </section>

            <section class="card mb-4" aria-labelledby="cause-sections-heading">
                <div class="card-header cause-content-toolbar">
                    <div>
                        <h2 id="cause-sections-heading" class="h5 mb-1">{{ __('donation_content.view.sections.heading') }}</h2>
                        <small class="text-muted">{{ __('donation_content.view.sections.summary') }}</small>
                    </div>
                    <button id="add-landing-section" type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-plus" aria-hidden="true"></i> {{ __('donation_content.view.sections.add') }}</button>
                </div>
                <div class="card-body">
                    <p class="cause-editor-help"><strong>{{ __('donation_content.view.sections.help_lead') }}</strong> {{ __('donation_content.view.sections.help_body', ['maximum' => $maxLandingSections]) }}</p>
                    <div id="landing-section-list" class="cause-editor-list"></div>
                    <p id="landing-section-empty" class="cause-editor-empty mb-0" hidden>{{ __('donation_content.view.sections.empty') }}</p>
                </div>
            </section>

            <div class="cause-content-save">
                <button type="submit" class="btn igf-btn igf-btn-primary btn-lg"><i class="fa fa-save" aria-hidden="true"></i> {{ __('donation_content.view.save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const initialAmounts = @json(array_values($initialAmounts));
    const initialSections = @json(array_values($initialSections));
    const layoutOptions = @json($layoutOptions);
    const editorCopy = @json(__('donation_content.editor'));
    const imageOptions = @json($images->map(fn ($asset) => ['uuid' => $asset->uuid, 'label' => $asset->original_name ?: $asset->path])->values());
    const videoOptions = @json($videos->map(fn ($asset) => ['uuid' => $asset->uuid, 'label' => $asset->original_name ?: $asset->path])->values());
    const maxAmountCards = {{ (int) $maxAmountCards }};
    const maxLandingSections = {{ (int) $maxLandingSections }};
    const amountList = document.getElementById('amount-card-list');
    const sectionList = document.getElementById('landing-section-list');
    let editorSequence = 0;

    const escapeHtml = value => String(value == null ? '' : value)
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const localized = (value, locale) => value && typeof value === 'object' ? String(value[locale] || '') : '';
    const formatCopy = (value, replacements = {}) => Object.entries(replacements)
        .reduce((copy, [key, replacement]) => copy.replaceAll(`:${key}`, String(replacement)), String(value || ''));
    const optionsHtml = (options, selected, placeholder) => `<option value="">${escapeHtml(placeholder)}</option>` + options.map(option => `<option value="${escapeHtml(option.uuid)}"${String(selected || '') === String(option.uuid) ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('');
    const moveButtons = type => `<div class="cause-editor-actions"><button type="button" class="btn btn-sm igf-btn igf-btn-secondary" data-action="up" aria-label="${escapeHtml(formatCopy(editorCopy.common.move_up, { type }))}"><i class="fa fa-arrow-up" aria-hidden="true"></i> ${escapeHtml(editorCopy.common.up)}</button><button type="button" class="btn btn-sm igf-btn igf-btn-secondary" data-action="down" aria-label="${escapeHtml(formatCopy(editorCopy.common.move_down, { type }))}"><i class="fa fa-arrow-down" aria-hidden="true"></i> ${escapeHtml(editorCopy.common.down)}</button><button type="button" class="btn btn-sm igf-btn igf-btn-danger" data-action="remove" aria-label="${escapeHtml(formatCopy(editorCopy.common.delete_item, { type }))}"><i class="fa fa-trash-o" aria-hidden="true"></i> ${escapeHtml(editorCopy.common.delete)}</button></div>`;

    function amountRow(card = {}) {
        const row = document.createElement('div');
        const toggleToken = `amount-enabled-${Date.now()}-${++editorSequence}`;
        row.className = 'cause-editor-row';
        row.dataset.kind = 'amount';
        row.innerHTML = `
            <div class="cause-editor-row__heading"><strong data-row-title>${escapeHtml(editorCopy.amount.heading)}</strong>${moveButtons(editorCopy.amount.item)}</div>
            <input type="hidden" data-field="uuid" value="${escapeHtml(card.uuid || '')}">
            <div class="form-row">
                <div class="form-group col-md-3"><label>${escapeHtml(editorCopy.amount.amount_label)} <span>*</span></label><input class="form-control" data-field="amount" type="number" min="{{ $minDonationAmount }}" max="{{ $maxDonationAmount }}" step="1" value="${escapeHtml(card.amount || '')}" required></div>
                <div class="form-group col-md-9"><label>${escapeHtml(editorCopy.amount.english_impact)} <span>*</span></label><input class="form-control" data-field="impact.en" maxlength="300" value="${escapeHtml(localized(card.impact, 'en'))}" required><small class="form-text text-muted">${escapeHtml(editorCopy.amount.impact_example)}</small></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-9"><label>${escapeHtml(editorCopy.amount.bangla_impact)}</label><input class="form-control" data-field="impact.bn" maxlength="300" lang="bn" value="${escapeHtml(localized(card.impact, 'bn'))}"><small class="form-text text-muted">${escapeHtml(editorCopy.amount.bangla_fallback)}</small></div>
                <div class="form-group col-md-3 d-flex align-items-center"><input type="hidden" data-field="enabled" value="0"><div class="custom-control custom-switch"><input class="custom-control-input" data-field="enabled" type="checkbox" value="1" id="${toggleToken}"${card.enabled === false || String(card.enabled) === '0' ? '' : ' checked'}><label class="custom-control-label" for="${toggleToken}">${escapeHtml(editorCopy.amount.visible)}</label></div></div>
            </div>`;
        return row;
    }

    function sectionRow(section = {}) {
        const row = document.createElement('div');
        const editorToken = `cause-rich-${Date.now()}-${++editorSequence}`;
        row.className = 'cause-editor-row';
        row.dataset.kind = 'section';
        row.innerHTML = `
            <div class="cause-editor-row__heading"><strong data-row-title>${escapeHtml(editorCopy.section.heading)}</strong>${moveButtons(editorCopy.section.item)}</div>
            <input type="hidden" data-field="uuid" value="${escapeHtml(section.uuid || '')}">
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.layout)} <span>*</span></label><select class="form-control" data-field="layout" required>${Object.entries(layoutOptions).map(([value,label]) => `<option value="${escapeHtml(value)}"${String(section.layout || 'text') === value ? ' selected' : ''}>${escapeHtml(label)}</option>`).join('')}</select></div>
                <div class="form-group col-md-6 d-flex align-items-center"><input type="hidden" data-field="enabled" value="0"><div class="custom-control custom-switch"><input class="custom-control-input" data-field="enabled" type="checkbox" value="1" id="section-enabled-${editorToken}"${section.enabled === false || String(section.enabled) === '0' ? '' : ' checked'}><label class="custom-control-label" for="section-enabled-${editorToken}">${escapeHtml(editorCopy.section.visible)}</label></div></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.english_heading)}</label><input class="form-control" data-field="title.en" maxlength="255" value="${escapeHtml(localized(section.title, 'en'))}"></div>
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.bangla_heading)}</label><input class="form-control" data-field="title.bn" maxlength="255" lang="bn" value="${escapeHtml(localized(section.title, 'bn'))}"><small class="form-text text-muted">${escapeHtml(editorCopy.section.bangla_copy_fallback)}</small></div>
            </div>
            <div class="form-group"><label>${escapeHtml(editorCopy.section.english_rich_text)}</label><textarea id="${editorToken}-en" class="form-control my-editor" data-field="body.en" rows="8" maxlength="30000">${escapeHtml(localized(section.body, 'en'))}</textarea></div>
            <div class="form-group"><label>${escapeHtml(editorCopy.section.bangla_rich_text)}</label><textarea id="${editorToken}-bn" class="form-control my-editor" data-field="body.bn" rows="8" maxlength="30000" lang="bn">${escapeHtml(localized(section.body, 'bn'))}</textarea></div>
            <div class="cause-editor-media-note mb-3"><strong>${escapeHtml(editorCopy.section.media_note_lead)}</strong> ${escapeHtml(editorCopy.section.media_note_body)}</div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.managed_image)}</label><select class="form-control" data-field="image_media_uuid">${optionsHtml(imageOptions, section.image_media_uuid, editorCopy.section.no_image)}</select></div>
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.managed_video)}</label><select class="form-control" data-field="video_media_uuid">${optionsHtml(videoOptions, section.video_media_uuid, editorCopy.section.no_uploaded_video)}</select></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.external_video)}</label><input class="form-control" data-field="video_url" type="url" maxlength="2048" placeholder="https://www.youtube.com/watch?v=…" value="${escapeHtml(section.video_url || '')}"></div>
                <div class="form-group col-md-3"><label>${escapeHtml(editorCopy.section.english_image_alt)}</label><input class="form-control" data-field="image_alt.en" maxlength="255" value="${escapeHtml(localized(section.image_alt, 'en'))}"></div>
                <div class="form-group col-md-3"><label>${escapeHtml(editorCopy.section.bangla_image_alt)}</label><input class="form-control" data-field="image_alt.bn" maxlength="255" lang="bn" value="${escapeHtml(localized(section.image_alt, 'bn'))}"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.english_video_title)}</label><input class="form-control" data-field="video_title.en" maxlength="255" value="${escapeHtml(localized(section.video_title, 'en'))}"><small class="form-text text-muted">${escapeHtml(editorCopy.section.english_video_title_help)}</small></div>
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.bangla_video_title)}</label><input class="form-control" data-field="video_title.bn" maxlength="255" lang="bn" value="${escapeHtml(localized(section.video_title, 'bn'))}"><small class="form-text text-muted">${escapeHtml(editorCopy.section.bangla_video_title_help)}</small></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.english_video_transcript)}</label><textarea class="form-control" data-field="video_transcript.en" rows="5" maxlength="10000">${escapeHtml(localized(section.video_transcript, 'en'))}</textarea><small class="form-text text-muted">${escapeHtml(editorCopy.section.english_video_transcript_help)}</small></div>
                <div class="form-group col-md-6"><label>${escapeHtml(editorCopy.section.bangla_video_transcript)}</label><textarea class="form-control" data-field="video_transcript.bn" rows="5" maxlength="10000" lang="bn">${escapeHtml(localized(section.video_transcript, 'bn'))}</textarea><small class="form-text text-muted">${escapeHtml(editorCopy.section.bangla_video_transcript_help)}</small></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4"><label>${escapeHtml(editorCopy.section.english_button_label)}</label><input class="form-control" data-field="cta_label.en" maxlength="120" value="${escapeHtml(localized(section.cta_label, 'en'))}"></div>
                <div class="form-group col-md-4"><label>${escapeHtml(editorCopy.section.bangla_button_label)}</label><input class="form-control" data-field="cta_label.bn" maxlength="120" lang="bn" value="${escapeHtml(localized(section.cta_label, 'bn'))}"></div>
                <div class="form-group col-md-4"><label>${escapeHtml(editorCopy.section.button_destination)}</label><input class="form-control" data-field="cta_url" maxlength="2048" placeholder="/contact-us or https://…" value="${escapeHtml(section.cta_url || '')}"></div>
            </div>`;
        const mediaFields = Array.from(row.querySelectorAll('[data-field="image_media_uuid"],[data-field="video_media_uuid"],[data-field="video_url"]'));
        mediaFields.forEach(field => field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', () => {
            if (!String(field.value || '').trim()) return;
            mediaFields.filter(candidate => candidate !== field).forEach(candidate => { candidate.value = ''; });
        }));
        return row;
    }

    function reindex(list, prefix) {
        Array.from(list.children).forEach((row, index) => {
            row.querySelector('[data-row-title]').textContent = formatCopy(
                prefix === 'amount_cards' ? editorCopy.amount.numbered_heading : editorCopy.section.numbered_heading,
                { number: index + 1 }
            );
            row.querySelectorAll('[data-field]').forEach(field => field.name = `${prefix}[${index}][${field.dataset.field.replaceAll('.', '][')}]`);
            row.querySelector('[data-action="up"]').disabled = index === 0;
            row.querySelector('[data-action="down"]').disabled = index === list.children.length - 1;
        });
        document.getElementById(prefix === 'amount_cards' ? 'amount-card-empty' : 'landing-section-empty').hidden = list.children.length > 0;
    }

    function initializeEditors(row) {
        if (!window.tinymce || !window.editor_config) return;
        row.querySelectorAll('textarea.my-editor').forEach(textarea => {
            if (!window.tinymce.get(textarea.id)) window.tinymce.init({ ...window.editor_config, selector: `#${textarea.id}` });
        });
    }

    function bindList(list, prefix) {
        list.addEventListener('click', event => {
            const button = event.target.closest('[data-action]');
            const row = button?.closest('.cause-editor-row');
            if (!button || !row) return;
            const action = button.dataset.action;
            if (action === 'remove') {
                row.querySelectorAll('textarea.my-editor').forEach(textarea => window.tinymce?.get(textarea.id)?.remove());
                row.remove();
            } else if (action === 'up' && row.previousElementSibling) {
                list.insertBefore(row, row.previousElementSibling);
            } else if (action === 'down' && row.nextElementSibling) {
                list.insertBefore(row.nextElementSibling, row);
            }
            reindex(list, prefix);
        });
    }

    initialAmounts.forEach(card => amountList.appendChild(amountRow(card)));
    initialSections.forEach(section => sectionList.appendChild(sectionRow(section)));
    reindex(amountList, 'amount_cards');
    reindex(sectionList, 'landing_sections');
    bindList(amountList, 'amount_cards');
    bindList(sectionList, 'landing_sections');

    document.getElementById('add-amount-card').addEventListener('click', () => {
        if (amountList.children.length >= maxAmountCards) return window.alert(formatCopy(editorCopy.amount.limit, { maximum: maxAmountCards }));
        const row = amountRow({ enabled: true });
        amountList.appendChild(row);
        reindex(amountList, 'amount_cards');
        row.querySelector('[data-field="amount"]').focus();
    });

    document.getElementById('add-landing-section').addEventListener('click', () => {
        if (sectionList.children.length >= maxLandingSections) return window.alert(formatCopy(editorCopy.section.limit, { maximum: maxLandingSections }));
        const row = sectionRow({ enabled: true, layout: 'text' });
        sectionList.appendChild(row);
        reindex(sectionList, 'landing_sections');
        initializeEditors(row);
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    document.getElementById('cause-content-form').addEventListener('submit', () => {
        window.tinymce?.triggerSave();
        reindex(amountList, 'amount_cards');
        reindex(sectionList, 'landing_sections');
    });

    document.getElementById('cause-content-editor-ready').value = '1';
    document.getElementById('cause-amounts-payload-ready').value = '1';
    document.getElementById('cause-sections-payload-ready').value = '1';

    if (document.getElementById('cause-content-errors')) document.getElementById('cause-content-errors').focus();
})();
</script>

@include('admin.layouts.tinymce', [
    'editorHeight' => 320,
    'editorMenubar' => false,
    'editorToolbar' => 'undo redo | styleselect | bold italic | bullist numlist | blockquote link | removeformat code',
])
@endsection
