/* global document, window, navigator, Element */
(() => {
    'use strict';

    const status = document.querySelector('[data-copy-status]');
    let statusTimer;
    const announce = (message) => {
        if (!status) return;
        status.textContent = message;
        status.classList.add('is-visible');
        window.clearTimeout(statusTimer);
        statusTimer = window.setTimeout(() => status.classList.remove('is-visible'), 1800);
    };

    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-copy-value]')
            : null;
        if (!button) return;
        const value = button.dataset.copyValue || '';
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            announce('Copied to clipboard.');
        } catch {
            const input = document.createElement('textarea');
            input.value = value;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch {
                copied = false;
            }
            input.remove();
            announce(copied ? 'Copied to clipboard.' : 'Copy failed. Select the value manually.');
        }
    });

    const bulkForm = document.querySelector('[data-bulk-form]');
    if (!bulkForm) return;
    const all = bulkForm.querySelector('[data-select-all]');
    const rows = [...bulkForm.querySelectorAll('[data-row-select]')];
    const count = bulkForm.querySelector('[data-selected-count]');
    const operation = bulkForm.querySelector('[data-bulk-operation]');
    const statusFields = bulkForm.querySelector('[data-bulk-status]');
    const assignmentFields = bulkForm.querySelector('[data-bulk-assignment]');

    const refreshCount = () => {
        const selected = rows.filter((row) => row.checked).length;
        if (count) count.textContent = `${selected} selected · maximum 100`;
        if (all) {
            all.checked = rows.length > 0 && selected === rows.length;
            all.indeterminate = selected > 0 && selected < rows.length;
        }
    };
    all?.addEventListener('change', () => {
        rows.forEach((row) => { row.checked = all.checked; });
        refreshCount();
    });
    rows.forEach((row) => row.addEventListener('change', refreshCount));
    operation?.addEventListener('change', () => {
        if (statusFields) statusFields.hidden = operation.value !== 'status';
        if (assignmentFields) assignmentFields.hidden = operation.value !== 'assignment';
    });
    bulkForm.addEventListener('submit', (event) => {
        if (rows.filter((row) => row.checked).length === 0) {
            event.preventDefault();
            announce('Select at least one record.');
            rows[0]?.focus();
        }
    });
    refreshCount();
})();
