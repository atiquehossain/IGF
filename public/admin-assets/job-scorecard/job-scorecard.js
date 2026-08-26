(() => {
    'use strict';

    const body = document.getElementById('scorecard-criteria');
    const template = document.getElementById('scorecard-criterion-template');
    const addButton = document.getElementById('add-scorecard-criterion');
    if (!body || !template || !addButton) {
        return;
    }

    const removeEmptyState = () => body.querySelector('[data-scorecard-empty]')?.remove();

    const showEmptyState = () => {
        if (body.querySelector('[data-scorecard-row]')) {
            return;
        }
        const row = document.createElement('tr');
        row.setAttribute('data-scorecard-empty', '');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'text-center text-muted';
        cell.textContent = 'No scorecard criteria. Reviewers can still use workflow statuses and private notes.';
        row.appendChild(cell);
        body.appendChild(row);
    };

    const bindRemove = (row) => {
        row.querySelector('[data-remove-scorecard]')?.addEventListener('click', () => {
            row.remove();
            showEmptyState();
        });
    };

    body.querySelectorAll('[data-scorecard-row]').forEach(bindRemove);
    addButton.addEventListener('click', () => {
        const currentRows = body.querySelectorAll('[data-scorecard-row]').length;
        if (currentRows >= 20) {
            return;
        }
        removeEmptyState();
        const index = Number.parseInt(body.dataset.nextIndex || '0', 10);
        body.dataset.nextIndex = String(index + 1);
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name], [id], label[for]').forEach((element) => {
            for (const attribute of ['name', 'id', 'for']) {
                const value = element.getAttribute(attribute);
                if (value) {
                    element.setAttribute(attribute, value.replaceAll('__INDEX__', String(index)));
                }
            }
        });
        const row = fragment.querySelector('[data-scorecard-row]');
        if (!row) {
            return;
        }
        body.appendChild(fragment);
        bindRemove(row);
        row.querySelector('input:not([type="hidden"])')?.focus();
    });
})();
