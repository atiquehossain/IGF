const EMPTY_OPERATORS = new Set(['is_empty', 'is_not_empty']);

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
            link.textContent = 'Reload current version';
            alert.append(link);
        }
        alert.focus();
    }

    function updateStateLabel() {
        if (state.conflict) {
            status.textContent = 'Reload required';
            status.className = 'afb-save-state is-dirty';
        } else if (state.busy) {
            status.textContent = 'Saving…';
            status.className = 'afb-save-state is-saving';
        } else if (state.dirty) {
            status.textContent = 'Unsaved changes';
            status.className = 'afb-save-state is-dirty';
        } else {
            status.textContent = 'All changes saved';
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
            <h3>${heading}</h3>
            <label><span>Label *</span><input class="form-control" maxlength="255" required value="${escapeHtml(copy.label)}" data-copy-locale="${locale}" data-copy-key="label"></label>
            <label><span>Help text</span><textarea class="form-control" maxlength="2000" data-copy-locale="${locale}" data-copy-key="help">${escapeHtml(copy.help)}</textarea></label>
            <label><span>Placeholder</span><input class="form-control" maxlength="255" value="${escapeHtml(copy.placeholder)}" data-copy-locale="${locale}" data-copy-key="placeholder"></label>
        </section>`;
    }

    function validationMarkup(field) {
        if (field.type === 'file') return '<div class="afb-fixed-file-note"><i class="fa fa-lock" aria-hidden="true"></i> Protected uploads are fixed to PDF only, maximum 5 MB.</div>';
        if (textTypes.has(field.type)) {
            return `<div class="afb-validation-grid">
                <label class="afb-input-group"><span>Minimum length</span><input class="form-control" type="number" min="0" max="20000" value="${escapeHtml(field.validation?.min_length ?? '')}" data-validation-key="min_length"></label>
                <label class="afb-input-group"><span>Maximum length</span><input class="form-control" type="number" min="0" max="20000" value="${escapeHtml(field.validation?.max_length ?? '')}" data-validation-key="max_length"></label>
            </div>`;
        }
        if (numberTypes.has(field.type) || field.type === 'checkboxes') {
            return `<div class="afb-validation-grid">
                <label class="afb-input-group"><span>Minimum ${field.type === 'checkboxes' ? 'selections' : 'value'}</span><input class="form-control" type="number" value="${escapeHtml(field.validation?.min ?? '')}" data-validation-key="min"></label>
                <label class="afb-input-group"><span>Maximum ${field.type === 'checkboxes' ? 'selections' : 'value'}</span><input class="form-control" type="number" value="${escapeHtml(field.validation?.max ?? '')}" data-validation-key="max"></label>
            </div>`;
        }
        return '';
    }

    function optionsMarkup(field) {
        if (!choiceTypes.has(field.type)) return '';
        const rows = (field.options || []).map((option, optionIndex) => `<div class="afb-option-row" data-option-index="${optionIndex}">
            <span class="afb-row-number">${optionIndex + 1}</span>
            <label><span>English</span><input class="form-control" maxlength="255" required value="${escapeHtml(option.translations?.en?.label)}" data-option-locale="en"></label>
            <label lang="bn"><span>Bangla</span><input class="form-control" maxlength="255" required value="${escapeHtml(option.translations?.bn?.label)}" data-option-locale="bn"></label>
            <div class="afb-option-actions">
                ${button('fa-arrow-up', `Move option ${optionIndex + 1} up`, 'data-move-option="up"', optionIndex === 0)}
                ${button('fa-arrow-down', `Move option ${optionIndex + 1} down`, 'data-move-option="down"', optionIndex === field.options.length - 1)}
                ${button('fa-trash-o', `Remove option ${optionIndex + 1}`, 'data-remove-option', field.options.length <= 2, true)}
            </div>
        </div>`).join('');
        return `<section class="afb-options"><div class="afb-subsection-header"><div><h3>Answer options</h3><p>Every option needs an English and Bangla label.</p></div><button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button" data-add-option${field.options.length >= maxOptions ? ' disabled' : ''}><i class="fa fa-plus" aria-hidden="true"></i> Add option</button></div><div class="afb-option-list">${rows}</div></section>`;
    }

    function conditionsMarkup(field, fieldIndex) {
        if (field.system_key) {
            return '<section class="afb-conditions"><div class="afb-subsection-header"><div><h3>Conditional display</h3><p>Protected identity and CV fields are always shown.</p></div></div></section>';
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
                return `<select class="form-control" data-condition-key="value">${optionMarkup([{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }], condition.value)}</select>`;
            }
            return `<input class="form-control" value="${escapeHtml(condition.value ?? '')}" data-condition-key="value">`;
        };
        const rows = (field.conditions || []).map((condition, conditionIndex) => `<div class="afb-condition-row" data-condition-index="${conditionIndex}">
            <label><span>Group</span><input class="form-control" type="number" min="1" max="20" value="${escapeHtml(condition.group || 1)}" data-condition-key="group"></label>
            <label><span>Join</span><select class="form-control" data-condition-key="connector"><option value="and"${condition.connector !== 'or' ? ' selected' : ''}>AND</option><option value="or"${condition.connector === 'or' ? ' selected' : ''}>OR</option></select></label>
            <label><span>Earlier question</span><select class="form-control" data-condition-key="source_key">${optionMarkup(sourceOptions, condition.source_key)}</select></label>
            <label><span>Rule</span><select class="form-control" data-condition-key="operator">${optionMarkup(config.operators || [], condition.operator)}</select></label>
            <label><span>Comparison</span>${comparisonMarkup(condition)}</label>
            ${button('fa-trash-o', `Remove condition ${conditionIndex + 1}`, 'data-remove-condition', false, true)}
        </div>`).join('');
        return `<section class="afb-conditions"><div class="afb-subsection-header"><div><h3>Conditional display</h3><p>Groups are alternatives; rules inside a group follow their AND / OR setting.</p></div><button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button" data-add-condition${fieldIndex === 0 || field.conditions.length >= 20 ? ' disabled' : ''}><i class="fa fa-random" aria-hidden="true"></i> Add condition</button></div>${rows ? `<div class="afb-condition-list">${rows}</div>` : '<p class="text-muted mb-0">Always shown.</p>'}</section>`;
    }

    function fieldMarkup(field, fieldIndex) {
        const system = Boolean(field.system_key);
        const label = field.translations?.en?.label || 'Untitled question';
        return `<article class="afb-field-card${system ? ' is-system' : ''}" data-field-index="${fieldIndex}">
            <header class="afb-field-card-header">
                <button class="afb-drag-handle" type="button" draggable="true" data-drag-field aria-label="Drag to reorder ${escapeHtml(label)}" title="Drag to reorder"><i class="fa fa-bars" aria-hidden="true"></i></button>
                <div class="afb-field-summary"><strong data-summary-label>${escapeHtml(label)}</strong><small>${escapeHtml(config.types?.find(type => type.value === field.type)?.label || field.type)} · Question ${fieldIndex + 1}</small>${system ? `<span class="afb-system-badge"><i class="fa fa-lock" aria-hidden="true"></i> ${escapeHtml(field.system_key.replaceAll('_', ' '))}</span>` : ''}</div>
                <div class="afb-field-header-actions">
                    ${button('fa-arrow-up', `Move ${label} up`, 'data-move-field="up"', fieldIndex === 0)}
                    ${button('fa-arrow-down', `Move ${label} down`, 'data-move-field="down"', fieldIndex === state.fields.length - 1)}
                    ${button('fa-copy', `Duplicate ${label}`, 'data-duplicate-field', system || state.fields.length >= maxFields)}
                    ${button('fa-trash-o', `Delete ${label}`, 'data-delete-field', system, true)}
                </div>
            </header>
            <div class="afb-field-card-body">
                <div class="afb-field-settings-grid">
                    <label class="afb-input-group"><span>Question type</span><select class="form-control" data-field-type${system ? ' disabled aria-disabled="true"' : ''}>${optionMarkup(config.types || [], field.type)}</select>${system ? '<small>Protected system field type.</small>' : ''}</label>
                    <div class="afb-field-flags"><label class="afb-check"><input type="checkbox" data-required${field.required ? ' checked' : ''}${system ? ' disabled aria-disabled="true"' : ''}> Required</label></div>
                </div>
                <div class="afb-language-grid">${languagePanel(field, fieldIndex, 'en', 'English')}${languagePanel(field, fieldIndex, 'bn', 'বাংলা (Bangla)')}</div>
                ${validationMarkup(field)}
                ${optionsMarkup(field)}
                ${conditionsMarkup(field, fieldIndex)}
            </div>
        </article>`;
    }

    function render(focusIndex = null) {
        list.innerHTML = state.fields.length
            ? state.fields.map(fieldMarkup).join('')
            : '<div class="afb-builder-empty"><h2>No questions</h2><p>Add a question to continue.</p></div>';
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
            if (event.target.dataset.copyLocale === 'en' && event.target.dataset.copyKey === 'label') card.querySelector('[data-summary-label]').textContent = event.target.value || 'Untitled question';
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
            if (!globalThis.confirm(`Delete “${field.translations?.en?.label || 'this question'}”?`)) return;
            state.fields.splice(fieldIndex, 1);
            sanitizeConditions();
            markDirty();
            render(Math.min(fieldIndex, state.fields.length - 1));
        } else if (control.hasAttribute('data-duplicate-field') && !field.system_key && state.fields.length < maxFields) {
            const copy = deepCopy(field);
            copy.key = stableKey('field');
            copy.translations.en.label = `${copy.translations.en.label} (copy)`;
            copy.translations.bn.label = `${copy.translations.bn.label} (অনুলিপি)`;
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
        if (!type.includes('application/json')) return { message: response.ok ? 'Saved.' : 'The server returned an unexpected response.' };
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
                announce(errorMessage(payload, 'This form changed in another editor. Reload before saving.'), 'conflict');
                return false;
            }
            if (!response.ok) {
                announce(errorMessage(payload, 'The draft could not be saved.'), 'error');
                return false;
            }
            versionInput.value = String(payload.editor_version);
            const versionLabel = document.querySelector('[data-editor-version-label]');
            if (versionLabel) versionLabel.textContent = String(payload.editor_version);
            state.dirty = false;
            state.hasDraft = true;
            if (!quiet) announce(payload.message || 'Draft saved.');
            return true;
        } catch {
            announce('The draft could not be saved. Check your connection and try again.', 'error');
            return false;
        } finally {
            state.busy = false;
            updateStateLabel();
        }
    }

    async function publishForm() {
        if (state.busy || state.conflict || (!state.hasDraft && !state.dirty)) return;
        if (state.dirty && !await saveDraft({ quiet: true })) return;
        if (!globalThis.confirm('Publish this form version? Existing published submissions remain attached to their original immutable version.')) return;
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
                announce(errorMessage(payload, 'This form changed in another editor. Reload before publishing.'), 'conflict');
                return;
            }
            if (!response.ok) {
                announce(errorMessage(payload, 'The form could not be published.'), 'error');
                return;
            }
            state.dirty = false;
            globalThis.location.assign(root.dataset.editUrl);
        } catch {
            announce('The form could not be published. Check your connection and try again.', 'error');
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
