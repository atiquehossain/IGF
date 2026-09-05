/* global document, requestAnimationFrame, fetch, FormData */

const EMPTY_OPERATORS = new Set(['is_empty', 'is_not_empty']);

const BUILDER_UI_FALLBACKS = Object.freeze({
    untitled: 'Untitled question',
    option: 'Option :number',
    reload: 'Reload current version',
    reload_required: 'Reload required',
    saving: 'Saving…',
    unsaved: 'Unsaved changes',
    all_saved: 'All changes saved',
    label: 'Label *',
    help_text: 'Help text',
    placeholder: 'Placeholder',
    protected_upload: 'Protected uploads are fixed to PDF only, maximum 5 MB.',
    minimum_length: 'Minimum length',
    maximum_length: 'Maximum length',
    minimum_value: 'Minimum value',
    maximum_value: 'Maximum value',
    minimum_selections: 'Minimum selections',
    maximum_selections: 'Maximum selections',
    english: 'English',
    bangla: 'Bangla',
    move_option_up: 'Move option :number up',
    move_option_down: 'Move option :number down',
    remove_option: 'Remove option :number',
    answer_options: 'Answer options',
    answer_options_help: 'Every option needs an English and Bangla label.',
    add_option: 'Add option',
    conditional_display: 'Conditional display',
    protected_always_shown: 'Protected identity and CV fields are always shown.',
    group: 'Group',
    join: 'Join',
    and: 'AND',
    or: 'OR',
    earlier_question: 'Earlier question',
    rule: 'Rule',
    comparison: 'Comparison',
    remove_condition: 'Remove condition :number',
    conditions_help: 'Groups are alternatives; rules inside a group follow their AND / OR setting.',
    add_condition: 'Add condition',
    always_shown: 'Always shown.',
    drag_named: 'Drag to reorder :name',
    drag: 'Drag to reorder',
    question_number: 'Question :number',
    move_up: 'Move :name up',
    move_down: 'Move :name down',
    duplicate: 'Duplicate :name',
    delete: 'Delete :name',
    question_type: 'Question type',
    protected_type: 'Protected system field type.',
    required: 'Required',
    english_heading: 'English',
    bangla_heading: 'বাংলা (Bangla)',
    no_questions: 'No questions',
    no_questions_help: 'Add a question to continue.',
    delete_confirm: 'Delete “:name”?',
    this_question: 'this question',
    copy_suffix: '(copy)',
    copy_suffix_bn: '(অনুলিপি)',
    saved: 'Saved.',
    unexpected_response: 'The server returned an unexpected response.',
    changed_save: 'This form changed in another editor. Reload before saving.',
    save_error: 'The draft could not be saved.',
    save_connection_error: 'The draft could not be saved. Check your connection and try again.',
    publish_confirm: 'Publish this form version? Existing published submissions remain attached to their original immutable version.',
    changed_publish: 'This form changed in another editor. Reload before publishing.',
    publish_error: 'The form could not be published.',
    publish_connection_error: 'The form could not be published. Check your connection and try again.',
    yes: 'Yes',
    no: 'No',
});

export function translatedBuilderText(dictionary, key, replacements = {}) {
    const candidate = dictionary && typeof dictionary[key] === 'string' ? dictionary[key].trim() : '';
    let value = candidate || BUILDER_UI_FALLBACKS[key] || String(key).replaceAll('_', ' ');

    Object.entries(replacements).forEach(([name, replacement]) => {
        value = value
            .replaceAll(`:${name}`, String(replacement ?? ''))
            .replaceAll(`{${name}}`, String(replacement ?? ''));
    });

    return value;
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function stableKey(prefix = 'field') {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
}

export function isBlank(value) {
    if (value === null || value === undefined || value === '') return true;
    if (Array.isArray(value)) return value.length === 0;
    return typeof value === 'string' && value.trim() === '';
}

function comparable(value) {
    if (typeof value === 'boolean') return value ? 'yes' : 'no';
    return String(value ?? '').trim().toLocaleLowerCase();
}

export function conditionMatches(actual, operator, expected) {
    const values = Array.isArray(actual) ? actual.map(comparable) : null;
    const wanted = comparable(expected);
    const equal = values ? values.includes(wanted) : comparable(actual) === wanted;
    const contains = values ? values.includes(wanted) : comparable(actual).includes(wanted);

    switch (operator) {
    case 'equals': return equal;
    case 'not_equals': return !equal;
    case 'contains': return contains;
    case 'not_contains': return !contains;
    case 'is_empty': return isBlank(actual);
    case 'is_not_empty': return !isBlank(actual);
    case 'greater_than': return !isBlank(actual) && !isBlank(expected) && Number.isFinite(Number(actual)) && Number(actual) > Number(expected);
    case 'less_than': return !isBlank(actual) && !isBlank(expected) && Number.isFinite(Number(actual)) && Number(actual) < Number(expected);
    default: return false;
    }
}

export function groupedConditionsMatch(conditions = [], answers = {}) {
    if (!Array.isArray(conditions) || conditions.length === 0) return true;
    const groups = new Map();
    conditions.forEach(condition => {
        const group = Number.isInteger(Number(condition?.group)) ? Number(condition.group) : 1;
        if (!groups.has(group)) groups.set(group, []);
        groups.get(group).push(condition || {});
    });

    return [...groups.values()].some(rules => {
        let result = null;
        rules.forEach(rule => {
            const matched = conditionMatches(answers[rule.source_key], rule.operator, rule.value);
            result = result === null
                ? matched
                : (String(rule.connector || 'and').toLowerCase() === 'or' ? result || matched : result && matched);
        });
        return result ?? true;
    });
}

function readJsonControl(id, fallback) {
    const control = document.getElementById(id);
    if (!control) return fallback;
    try {
        return JSON.parse(control.value);
    } catch {
        return fallback;
    }
}

function deepCopy(value) {
    return JSON.parse(JSON.stringify(value));
}

function defaultOption(number) {
    return {
        key: stableKey('option'),
        translations: {
            en: { label: `Option ${number}` },
            bn: { label: `বিকল্প ${number}` },
        },
    };
}

function defaultField() {
    return {
        key: stableKey('field'),
        system_key: null,
        type: 'short_text',
        required: false,
        validation: {},
        translations: {
            en: { label: 'Untitled question', help: '', placeholder: '' },
            bn: { label: 'শিরোনামহীন প্রশ্ন', help: '', placeholder: '' },
        },
        options: [],
        conditions: [],
    };
}

function optionMarkup(options, selected) {
    return options.map(option => `<option value="${escapeHtml(option.value)}"${option.value === selected ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('');
}

function button(icon, label, attributes = '', disabled = false, danger = false) {
    return `<button class="afb-icon-button${danger ? ' is-danger' : ''}" type="button" ${attributes}${disabled ? ' disabled' : ''} aria-label="${escapeHtml(label)}" title="${escapeHtml(label)}"><i class="fa ${icon}" aria-hidden="true"></i></button>`;
}

function renderBuilder(root) {
    const form = document.getElementById('afb-builder-form');
    const list = document.getElementById('afb-field-list');
    const schemaInput = document.getElementById('afb-schema-input');
    const versionInput = document.getElementById('afb-editor-version');
    const config = readJsonControl('afb-builder-config', {});
    const ui = config.ui && typeof config.ui === 'object' ? config.ui : {};
    const text = (key, replacements = {}) => translatedBuilderText(ui, key, replacements);
    const htmlText = (key, replacements = {}) => escapeHtml(text(key, replacements));
    const parsedFields = readJsonControl('afb-schema-input', []);
    const state = {
        fields: Array.isArray(parsedFields) ? parsedFields : [],
        dirty: root.dataset.initialDirty === '1',
        busy: false,
        conflict: false,
        dragIndex: null,
        hasDraft: root.dataset.hasDraft === '1',
    };
    const choiceTypes = new Set(config.choice_types || []);
    const textTypes = new Set(config.text_types || []);
    const numberTypes = new Set(config.number_types || []);
    const maxFields = Number(config.max_fields || 100);
    const maxOptions = Number(config.max_options || 50);
    const alert = document.getElementById('afb-builder-alert');
    const status = root.querySelector('[data-save-state]');
    const publishButton = root.querySelector('[data-publish-form]');

    function announce(message, kind = 'success') {
        alert.hidden = false;
        alert.className = `afb-builder-alert${kind === 'error' ? ' is-error' : ''}${kind === 'conflict' ? ' is-conflict' : ''}`;
        alert.replaceChildren(document.createTextNode(message));
        if (kind === 'conflict') {
            const link = document.createElement('a');
            link.className = 'btn igf-btn igf-btn-secondary ml-2';
            link.href = root.dataset.editUrl;
            link.textContent = text('reload');
            alert.append(link);
        }
        alert.focus();
    }

    function updateStateLabel() {
        if (state.conflict) {
            status.textContent = text('reload_required');
            status.className = 'afb-save-state is-dirty';
        } else if (state.busy) {
            status.textContent = text('saving');
            status.className = 'afb-save-state is-saving';
        } else if (state.dirty) {
            status.textContent = text('unsaved');
            status.className = 'afb-save-state is-dirty';
        } else {
            status.textContent = text('all_saved');
            status.className = 'afb-save-state';
        }
        root.querySelectorAll('[data-save-draft], [data-add-field]').forEach(control => {
            control.disabled = state.busy || state.conflict || (control.matches('[data-add-field]') && state.fields.length >= maxFields);
        });
        publishButton.disabled = state.busy || state.conflict || (!state.hasDraft && !state.dirty);
    }

    function markDirty() {
        state.dirty = true;
        syncSchema();
        updateStateLabel();
    }

    function syncSchema() {
        schemaInput.value = JSON.stringify(state.fields);
        root.querySelector('[data-field-count]').textContent = String(state.fields.length);
    }

    function languagePanel(field, fieldIndex, locale, heading) {
        const copy = field.translations?.[locale] || { label: '', help: '', placeholder: '' };
        return `<section class="afb-language-panel" lang="${locale}">
            <h3>${escapeHtml(heading)}</h3>
            <label><span>${htmlText('label')}</span><input class="form-control" maxlength="255" required value="${escapeHtml(copy.label)}" data-copy-locale="${locale}" data-copy-key="label"></label>
            <label><span>${htmlText('help_text')}</span><textarea class="form-control" maxlength="2000" data-copy-locale="${locale}" data-copy-key="help">${escapeHtml(copy.help)}</textarea></label>
            <label><span>${htmlText('placeholder')}</span><input class="form-control" maxlength="255" value="${escapeHtml(copy.placeholder)}" data-copy-locale="${locale}" data-copy-key="placeholder"></label>
        </section>`;
    }

    function validationMarkup(field) {
        if (field.type === 'file') return `<div class="afb-fixed-file-note"><i class="fa fa-lock" aria-hidden="true"></i> ${htmlText('protected_upload')}</div>`;
        if (textTypes.has(field.type)) {
            return `<div class="afb-validation-grid">
                <label class="afb-input-group"><span>${htmlText('minimum_length')}</span><input class="form-control" type="number" min="0" max="20000" value="${escapeHtml(field.validation?.min_length ?? '')}" data-validation-key="min_length"></label>
                <label class="afb-input-group"><span>${htmlText('maximum_length')}</span><input class="form-control" type="number" min="0" max="20000" value="${escapeHtml(field.validation?.max_length ?? '')}" data-validation-key="max_length"></label>
            </div>`;
        }
        if (numberTypes.has(field.type) || field.type === 'checkboxes') {
            return `<div class="afb-validation-grid">
                <label class="afb-input-group"><span>${htmlText(field.type === 'checkboxes' ? 'minimum_selections' : 'minimum_value')}</span><input class="form-control" type="number" value="${escapeHtml(field.validation?.min ?? '')}" data-validation-key="min"></label>
                <label class="afb-input-group"><span>${htmlText(field.type === 'checkboxes' ? 'maximum_selections' : 'maximum_value')}</span><input class="form-control" type="number" value="${escapeHtml(field.validation?.max ?? '')}" data-validation-key="max"></label>
            </div>`;
        }
        return '';
    }

    function optionsMarkup(field) {
        if (!choiceTypes.has(field.type)) return '';
        const rows = (field.options || []).map((option, optionIndex) => `<div class="afb-option-row" data-option-index="${optionIndex}">
            <span class="afb-row-number">${optionIndex + 1}</span>
            <label><span>${htmlText('english')}</span><input class="form-control" maxlength="255" required value="${escapeHtml(option.translations?.en?.label)}" data-option-locale="en"></label>
            <label lang="bn"><span>${htmlText('bangla')}</span><input class="form-control" maxlength="255" required value="${escapeHtml(option.translations?.bn?.label)}" data-option-locale="bn"></label>
            <div class="afb-option-actions">
                ${button('fa-arrow-up', text('move_option_up', { number: optionIndex + 1 }), 'data-move-option="up"', optionIndex === 0)}
                ${button('fa-arrow-down', text('move_option_down', { number: optionIndex + 1 }), 'data-move-option="down"', optionIndex === field.options.length - 1)}
                ${button('fa-trash-o', text('remove_option', { number: optionIndex + 1 }), 'data-remove-option', field.options.length <= 2, true)}
            </div>
        </div>`).join('');
        return `<section class="afb-options"><div class="afb-subsection-header"><div><h3>${htmlText('answer_options')}</h3><p>${htmlText('answer_options_help')}</p></div><button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button" data-add-option${field.options.length >= maxOptions ? ' disabled' : ''}><i class="fa fa-plus" aria-hidden="true"></i> ${htmlText('add_option')}</button></div><div class="afb-option-list">${rows}</div></section>`;
    }

    function conditionsMarkup(field, fieldIndex) {
        if (field.system_key) {
            return `<section class="afb-conditions"><div class="afb-subsection-header"><div><h3>${htmlText('conditional_display')}</h3><p>${htmlText('protected_always_shown')}</p></div></div></section>`;
        }
        const earlierFields = state.fields.slice(0, fieldIndex);
        const sourceOptions = earlierFields.map(source => ({
            value: source.key,
            label: source.translations?.en?.label || 'Untitled question',
        }));
        const comparisonMarkup = condition => {
            if (EMPTY_OPERATORS.has(condition.operator)) return '<input class="form-control" value="" data-condition-key="value" disabled>';
            const source = earlierFields.find(candidate => candidate.key === condition.source_key);
            if (source && choiceTypes.has(source.type)) {
                const choices = (source.options || []).map(option => ({ value: option.key, label: option.translations?.en?.label || option.key }));
                return `<select class="form-control" data-condition-key="value">${optionMarkup(choices, condition.value)}</select>`;
            }
            if (source?.type === 'yes_no') {
                return `<select class="form-control" data-condition-key="value">${optionMarkup([{ value: 'yes', label: text('yes') }, { value: 'no', label: text('no') }], condition.value)}</select>`;
            }
            return `<input class="form-control" value="${escapeHtml(condition.value ?? '')}" data-condition-key="value">`;
        };
        const rows = (field.conditions || []).map((condition, conditionIndex) => `<div class="afb-condition-row" data-condition-index="${conditionIndex}">
            <label><span>${htmlText('group')}</span><input class="form-control" type="number" min="1" max="20" value="${escapeHtml(condition.group || 1)}" data-condition-key="group"></label>
            <label><span>${htmlText('join')}</span><select class="form-control" data-condition-key="connector"><option value="and"${condition.connector !== 'or' ? ' selected' : ''}>${htmlText('and')}</option><option value="or"${condition.connector === 'or' ? ' selected' : ''}>${htmlText('or')}</option></select></label>
            <label><span>${htmlText('earlier_question')}</span><select class="form-control" data-condition-key="source_key">${optionMarkup(sourceOptions, condition.source_key)}</select></label>
            <label><span>${htmlText('rule')}</span><select class="form-control" data-condition-key="operator">${optionMarkup(config.operators || [], condition.operator)}</select></label>
            <label><span>${htmlText('comparison')}</span>${comparisonMarkup(condition)}</label>
            ${button('fa-trash-o', text('remove_condition', { number: conditionIndex + 1 }), 'data-remove-condition', false, true)}
        </div>`).join('');
        return `<section class="afb-conditions"><div class="afb-subsection-header"><div><h3>${htmlText('conditional_display')}</h3><p>${htmlText('conditions_help')}</p></div><button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button" data-add-condition${fieldIndex === 0 || field.conditions.length >= 20 ? ' disabled' : ''}><i class="fa fa-random" aria-hidden="true"></i> ${htmlText('add_condition')}</button></div>${rows ? `<div class="afb-condition-list">${rows}</div>` : `<p class="text-muted mb-0">${htmlText('always_shown')}</p>`}</section>`;
    }

    function fieldMarkup(field, fieldIndex) {
        const system = Boolean(field.system_key);
        const label = field.translations?.en?.label || text('untitled');
        const systemLabel = config.system_fields?.[field.system_key] || field.system_key?.replaceAll('_', ' ');
        return `<article class="afb-field-card${system ? ' is-system' : ''}" data-field-index="${fieldIndex}">
            <header class="afb-field-card-header">
                <button class="afb-drag-handle" type="button" draggable="true" data-drag-field aria-label="${htmlText('drag_named', { name: label })}" title="${htmlText('drag')}"><i class="fa fa-bars" aria-hidden="true"></i></button>
                <div class="afb-field-summary"><strong data-summary-label>${escapeHtml(label)}</strong><small>${escapeHtml(config.types?.find(type => type.value === field.type)?.label || field.type)} · ${htmlText('question_number', { number: fieldIndex + 1 })}</small>${system ? `<span class="afb-system-badge"><i class="fa fa-lock" aria-hidden="true"></i> ${escapeHtml(systemLabel)}</span>` : ''}</div>
                <div class="afb-field-header-actions">
                    ${button('fa-arrow-up', text('move_up', { name: label }), 'data-move-field="up"', fieldIndex === 0)}
                    ${button('fa-arrow-down', text('move_down', { name: label }), 'data-move-field="down"', fieldIndex === state.fields.length - 1)}
                    ${button('fa-copy', text('duplicate', { name: label }), 'data-duplicate-field', system || state.fields.length >= maxFields)}
                    ${button('fa-trash-o', text('delete', { name: label }), 'data-delete-field', system, true)}
                </div>
            </header>
            <div class="afb-field-card-body">
                <div class="afb-field-settings-grid">
                    <label class="afb-input-group"><span>${htmlText('question_type')}</span><select class="form-control" data-field-type${system ? ' disabled aria-disabled="true"' : ''}>${optionMarkup(config.types || [], field.type)}</select>${system ? `<small>${htmlText('protected_type')}</small>` : ''}</label>
                    <div class="afb-field-flags"><label class="afb-check"><input type="checkbox" data-required${field.required ? ' checked' : ''}${system ? ' disabled aria-disabled="true"' : ''}> ${htmlText('required')}</label></div>
                </div>
                <div class="afb-language-grid">${languagePanel(field, fieldIndex, 'en', text('english_heading'))}${languagePanel(field, fieldIndex, 'bn', text('bangla_heading'))}</div>
                ${validationMarkup(field)}
                ${optionsMarkup(field)}
                ${conditionsMarkup(field, fieldIndex)}
            </div>
        </article>`;
    }

    function render(focusIndex = null) {
        list.innerHTML = state.fields.length
            ? state.fields.map(fieldMarkup).join('')
            : `<div class="afb-builder-empty"><h2>${htmlText('no_questions')}</h2><p>${htmlText('no_questions_help')}</p></div>`;
        syncSchema();
        updateStateLabel();
        if (focusIndex !== null) {
            requestAnimationFrame(() => {
                const summary = list.querySelector(`[data-field-index="${focusIndex}"] .afb-field-summary`);
                summary?.setAttribute('tabindex', '-1');
                summary?.focus();
            });
        }
    }

    function sanitizeConditions() {
        const indexes = new Map(state.fields.map((field, index) => [field.key, index]));
        state.fields.forEach((field, targetIndex) => {
            field.conditions = (field.conditions || []).filter(condition => (indexes.get(condition.source_key) ?? Infinity) < targetIndex);
        });
    }

    function moveField(from, to) {
        if (from === to || to < 0 || to >= state.fields.length) return;
        const [field] = state.fields.splice(from, 1);
        state.fields.splice(to, 0, field);
        sanitizeConditions();
        markDirty();
        render(to);
    }

    function ensureOptions(field) {
        if (!choiceTypes.has(field.type)) {
            field.options = [];
            return;
        }
        field.options ||= [];
        while (field.options.length < 2) field.options.push(defaultOption(field.options.length + 1));
    }

    list.addEventListener('input', event => {
        const card = event.target.closest('[data-field-index]');
        if (!card) return;
        const field = state.fields[Number(card.dataset.fieldIndex)];
        const optionRow = event.target.closest('[data-option-index]');
        const conditionRow = event.target.closest('[data-condition-index]');
        if (event.target.dataset.copyLocale) {
            field.translations[event.target.dataset.copyLocale][event.target.dataset.copyKey] = event.target.value;
            if (event.target.dataset.copyLocale === 'en' && event.target.dataset.copyKey === 'label') card.querySelector('[data-summary-label]').textContent = event.target.value || text('untitled');
        } else if (event.target.dataset.validationKey) {
            const key = event.target.dataset.validationKey;
            if (event.target.value === '') delete field.validation[key];
            else field.validation[key] = event.target.value;
        } else if (optionRow && event.target.dataset.optionLocale) {
            field.options[Number(optionRow.dataset.optionIndex)].translations[event.target.dataset.optionLocale].label = event.target.value;
        } else if (conditionRow && event.target.dataset.conditionKey) {
            const condition = field.conditions[Number(conditionRow.dataset.conditionIndex)];
            const key = event.target.dataset.conditionKey;
            condition[key] = key === 'group' ? Number(event.target.value || 1) : event.target.value;
        } else {
            return;
        }
        markDirty();
    });

    list.addEventListener('change', event => {
        const card = event.target.closest('[data-field-index]');
        if (!card) return;
        const fieldIndex = Number(card.dataset.fieldIndex);
        const field = state.fields[fieldIndex];
        if (event.target.matches('[data-field-type]')) {
            field.type = event.target.value;
            field.validation = field.type === 'file' ? { max_kb: 5120, extensions: ['pdf'] } : {};
            ensureOptions(field);
            markDirty();
            render(fieldIndex);
        } else if (event.target.matches('[data-required]')) {
            field.required = event.target.checked;
            markDirty();
        } else if (event.target.dataset.conditionKey === 'operator') {
            const row = event.target.closest('[data-condition-index]');
            const condition = field.conditions[Number(row.dataset.conditionIndex)];
            condition.operator = event.target.value;
            if (EMPTY_OPERATORS.has(condition.operator)) condition.value = null;
            markDirty();
            render(fieldIndex);
        } else if (event.target.dataset.conditionKey) {
            const row = event.target.closest('[data-condition-index]');
            const condition = field.conditions[Number(row.dataset.conditionIndex)];
            const key = event.target.dataset.conditionKey;
            condition[key] = key === 'group' ? Number(event.target.value || 1) : event.target.value;
            markDirty();
            if (key === 'source_key') {
                const source = state.fields.find(candidate => candidate.key === condition.source_key);
                condition.value = source && choiceTypes.has(source.type) ? source.options?.[0]?.key || '' : (source?.type === 'yes_no' ? 'yes' : '');
                render(fieldIndex);
            }
        } else if (event.target.dataset.validationKey || event.target.dataset.optionLocale) {
            markDirty();
        }
    });

    list.addEventListener('click', event => {
        const control = event.target.closest('button');
        const card = event.target.closest('[data-field-index]');
        if (!control || !card) return;
        const fieldIndex = Number(card.dataset.fieldIndex);
        const field = state.fields[fieldIndex];
        if (control.dataset.moveField) {
            moveField(fieldIndex, fieldIndex + (control.dataset.moveField === 'up' ? -1 : 1));
        } else if (control.hasAttribute('data-delete-field') && !field.system_key) {
            if (!globalThis.confirm(text('delete_confirm', { name: field.translations?.en?.label || text('this_question') }))) return;
            state.fields.splice(fieldIndex, 1);
            sanitizeConditions();
            markDirty();
            render(Math.min(fieldIndex, state.fields.length - 1));
        } else if (control.hasAttribute('data-duplicate-field') && !field.system_key && state.fields.length < maxFields) {
            const copy = deepCopy(field);
            copy.key = stableKey('field');
            copy.translations.en.label = `${copy.translations.en.label} ${text('copy_suffix')}`;
            copy.translations.bn.label = `${copy.translations.bn.label} ${text('copy_suffix_bn')}`;
            copy.options = (copy.options || []).map(option => ({ ...option, key: stableKey('option') }));
            state.fields.splice(fieldIndex + 1, 0, copy);
            markDirty();
            render(fieldIndex + 1);
        } else if (control.hasAttribute('data-add-option') && field.options.length < maxOptions) {
            field.options.push(defaultOption(field.options.length + 1));
            markDirty();
            render(fieldIndex);
        } else if (control.dataset.moveOption) {
            const optionIndex = Number(control.closest('[data-option-index]').dataset.optionIndex);
            const targetIndex = optionIndex + (control.dataset.moveOption === 'up' ? -1 : 1);
            if (targetIndex < 0 || targetIndex >= field.options.length) return;
            [field.options[optionIndex], field.options[targetIndex]] = [field.options[targetIndex], field.options[optionIndex]];
            markDirty();
            render(fieldIndex);
        } else if (control.hasAttribute('data-remove-option')) {
            const optionIndex = Number(control.closest('[data-option-index]').dataset.optionIndex);
            if (field.options.length <= 2) return;
            const removedKey = field.options[optionIndex].key;
            field.options.splice(optionIndex, 1);
            state.fields.forEach(candidate => {
                (candidate.conditions || []).forEach(condition => {
                    if (condition.source_key === field.key && condition.value === removedKey) condition.value = field.options[0]?.key || '';
                });
            });
            markDirty();
            render(fieldIndex);
        } else if (control.hasAttribute('data-add-condition') && !field.system_key && fieldIndex > 0 && field.conditions.length < 20) {
            const source = state.fields[fieldIndex - 1];
            field.conditions.push({
                source_key: source.key,
                group: 1,
                connector: 'and',
                operator: 'equals',
                value: choiceTypes.has(source.type) ? source.options?.[0]?.key || '' : (source.type === 'yes_no' ? 'yes' : ''),
            });
            markDirty();
            render(fieldIndex);
        } else if (control.hasAttribute('data-remove-condition')) {
            field.conditions.splice(Number(control.closest('[data-condition-index]').dataset.conditionIndex), 1);
            markDirty();
            render(fieldIndex);
        }
    });

    list.addEventListener('dragstart', event => {
        const handle = event.target.closest('[data-drag-field]');
        if (!handle) return;
        const card = handle.closest('[data-field-index]');
        state.dragIndex = Number(card.dataset.fieldIndex);
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(state.dragIndex));
    });
    list.addEventListener('dragover', event => {
        if (state.dragIndex === null || !event.target.closest('[data-field-index]')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    });
    list.addEventListener('drop', event => {
        const card = event.target.closest('[data-field-index]');
        if (!card || state.dragIndex === null) return;
        event.preventDefault();
        const from = state.dragIndex;
        state.dragIndex = null;
        moveField(from, Number(card.dataset.fieldIndex));
    });
    list.addEventListener('dragend', () => {
        state.dragIndex = null;
        list.querySelectorAll('.is-dragging').forEach(card => card.classList.remove('is-dragging'));
    });

    root.querySelectorAll('[data-add-field]').forEach(control => control.addEventListener('click', () => {
        if (state.fields.length >= maxFields || state.busy || state.conflict) return;
        state.fields.push(defaultField());
        markDirty();
        render(state.fields.length - 1);
    }));

    async function responsePayload(response) {
        const type = response.headers.get('content-type') || '';
        if (!type.includes('application/json')) return { message: response.ok ? text('saved') : text('unexpected_response') };
        return response.json();
    }

    function errorMessage(payload, fallback) {
        if (payload?.errors) {
            const messages = Object.values(payload.errors).flat().filter(Boolean);
            if (messages.length) return messages.join(' ');
        }
        return payload?.message || fallback;
    }

    async function saveDraft({ quiet = false } = {}) {
        if (state.busy || state.conflict) return false;
        syncSchema();
        state.busy = true;
        updateStateLabel();
        try {
            const response = await fetch(root.dataset.updateUrl, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await responsePayload(response);
            if (response.status === 409) {
                state.conflict = true;
                announce(errorMessage(payload, text('changed_save')), 'conflict');
                return false;
            }
            if (!response.ok) {
                announce(errorMessage(payload, text('save_error')), 'error');
                return false;
            }
            versionInput.value = String(payload.editor_version);
            const versionLabel = document.querySelector('[data-editor-version-label]');
            if (versionLabel) versionLabel.textContent = String(payload.editor_version);
            state.dirty = false;
            state.hasDraft = true;
            if (!quiet) announce(payload.message || text('saved'));
            return true;
        } catch {
            announce(text('save_connection_error'), 'error');
            return false;
        } finally {
            state.busy = false;
            updateStateLabel();
        }
    }

    async function publishForm() {
        if (state.busy || state.conflict || (!state.hasDraft && !state.dirty)) return;
        if (state.dirty && !await saveDraft({ quiet: true })) return;
        if (!globalThis.confirm(text('publish_confirm'))) return;
        state.busy = true;
        updateStateLabel();
        const data = new FormData();
        data.append('_token', form.querySelector('[name="_token"]').value);
        data.append('editor_version', versionInput.value);
        try {
            const response = await fetch(root.dataset.publishUrl, {
                method: 'POST',
                body: data,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await responsePayload(response);
            if (response.status === 409) {
                state.conflict = true;
                announce(errorMessage(payload, text('changed_publish')), 'conflict');
                return;
            }
            if (!response.ok) {
                announce(errorMessage(payload, text('publish_error')), 'error');
                return;
            }
            state.dirty = false;
            globalThis.location.assign(root.dataset.editUrl);
        } catch {
            announce(text('publish_connection_error'), 'error');
        } finally {
            state.busy = false;
            updateStateLabel();
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        saveDraft();
    });
    publishButton.addEventListener('click', publishForm);
    globalThis.addEventListener('beforeunload', event => {
        if (!state.dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    render();
}

function previewValue(fieldNode) {
    const controls = [...fieldNode.querySelectorAll('input:not([type="file"]), select, textarea')];
    const checkboxes = controls.filter(control => control.type === 'checkbox');
    if (checkboxes.length) return checkboxes.filter(control => control.checked).map(control => control.value);
    const radios = controls.filter(control => control.type === 'radio');
    if (radios.length) return radios.find(control => control.checked)?.value ?? '';
    return controls[0]?.value ?? '';
}

function renderPreview(root) {
    const schema = readJsonControl('afb-preview-schema', { fields: [] });
    const fields = Array.isArray(schema.fields) ? schema.fields : [];

    function refresh() {
        for (let pass = 0; pass <= fields.length; pass += 1) {
            const answers = {};
            fields.forEach(field => {
                const node = root.querySelector(`[data-preview-field="${field.key}"]`);
                if (node && !node.hidden) answers[field.key] = previewValue(node);
            });
            let changed = false;
            fields.forEach(field => {
                const node = root.querySelector(`[data-preview-field="${field.key}"]`);
                if (!node) return;
                const visible = groupedConditionsMatch(field.conditions, answers);
                if (node.hidden === visible) changed = true;
                node.hidden = !visible;
                node.setAttribute('aria-hidden', String(!visible));
                node.querySelectorAll('input, select, textarea').forEach(control => {
                    control.disabled = !visible || control.dataset.previewLocked === '1';
                });
            });
            if (!changed) break;
        }
    }

    root.addEventListener('input', refresh);
    root.addEventListener('change', refresh);
    refresh();
}

if (typeof document !== 'undefined') {
    const builder = document.getElementById('application-form-builder');
    if (builder) renderBuilder(builder);
    const preview = document.querySelector('[data-form-preview]');
    if (preview) renderPreview(preview);
}
