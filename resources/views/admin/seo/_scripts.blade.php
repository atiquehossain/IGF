<script>
(() => {
    const form = document.querySelector('[data-seo-form]');
    if (!form) return;
    const one = selector => form.querySelector(selector);
    const all = selector => [...form.querySelectorAll(selector)];
    const title = one('[data-seo-title]');
    const description = one('[data-seo-description]');
    const focusPhrase = one('[data-focus-phrase]');
    const auto = one('[data-auto-content]');
    const image = one('[data-share-image]');
    const imageAlt = one('[data-share-image-alt]');
    const canonical = one('[data-canonical]');
    const slug = one('[data-permalink-slug]');
    const indexValue = one('[data-index-value]');
    const sitemapExclude = one('[data-sitemap-exclude]');
    const warning = one('[data-visibility-warning]');
    const dirtyStatus = one('[data-dirty-status]');
    const schemaValue = one('[data-schema-value]');
    const schemaTemplate = one('[data-schema-template]');
    const schemaGenerated = one('[data-schema-generated]');
    const schemaExpert = one('[data-schema-expert]');
    const schemaGeneratedWrap = one('[data-schema-generated-wrap]');
    const schemaExpertWrap = one('[data-schema-expert-wrap]');
    const schemaLibraryNode = document.querySelector('[data-schema-library]');
    const schemaLibrary = (() => { try { return JSON.parse(schemaLibraryNode?.textContent || '{}'); } catch (_) { return {}; } })();
    const contentAnalysisNode = document.querySelector('[data-content-analysis]');
    const contentAnalysis = (() => { try { return JSON.parse(contentAnalysisNode?.textContent || '{}'); } catch (_) { return {}; } })();
    let dirty = false;
    let mediaReturn = null;

    const effectiveTitle = () => (auto?.checked ? title.dataset.fallback : title.value.trim()) || title.dataset.fallback || 'Untitled page';
    const effectiveDescription = () => (auto?.checked ? description.dataset.fallback : description.value.trim()) || description.dataset.fallback || 'Add a clear description for this page.';
    const effectiveImage = () => image.value.trim() || image.dataset.fallback || '';
    const slugify = value => String(value || '').trim().toLowerCase().replace(/[^a-z0-9\u0980-\u09ff]+/g, '-').replace(/^-+|-+$/g, '');
    const normalizePhraseText = value => String(value || '').normalize('NFKC').trim().toLocaleLowerCase().replace(/\s+/g, ' ');
    const localizedEditorUrl = (value) => {
        try {
            const parsed = new URL(value, window.location.origin);
            const parameter = @json(config('seo.locale_query_parameter', 'lang'));
            const locale = @json($editor['locale']);
            const defaultLocale = @json(config('app.fallback_locale', 'en'));
            parsed.searchParams.delete(parameter);
            if (locale !== defaultLocale) parsed.searchParams.set(parameter, locale);
            return parsed.toString();
        } catch (_) {
            return value;
        }
    };
    const currentUrl = () => {
        if (canonical?.value.trim()) return localizedEditorUrl(canonical.value.trim());
        if (!slug || slug.disabled) return form.dataset.defaultUrl;
        const url = new URL(form.dataset.defaultUrl, window.location.origin);
        const prefix = @json($editor['permalink']['prefix'] ?? null);
        url.pathname = `${prefix || '/'}${slugify(slug.value)}`.replace(/\/{2,}/g, '/');
        return url.toString();
    };
    const setDirty = value => {
        dirty = value;
        dirtyStatus?.classList.toggle('is-visible', value);
    };
    const count = (node, value, goodMin, goodMax) => {
        if (!node) return;
        const length = value.length;
        node.textContent = `${length}/${goodMax}`;
        node.classList.toggle('is-good', length >= goodMin && length <= goodMax);
        node.classList.toggle('is-over', length > goodMax);
    };
    const renderImage = (container, url, emptyText) => {
        if (!container) return;
        container.replaceChildren();
        if (!url) {
            const span = document.createElement('span');
            span.textContent = emptyText;
            container.appendChild(span);
            return;
        }
        const img = document.createElement('img');
        img.src = url;
        img.alt = '';
        container.appendChild(img);
    };
    const syncSchema = () => {
        if (!schemaTemplate || !schemaValue) return;
        const mode = schemaTemplate.value;
        schemaGeneratedWrap.hidden = mode === 'expert';
        schemaExpertWrap.hidden = mode !== 'expert';
        if (mode === 'expert') {
            schemaValue.value = schemaExpert.value.trim();
            return;
        }
        if (mode === 'none') {
            schemaGenerated.value = '';
            schemaValue.value = '';
            return;
        }
        let generated = {};
        try { generated = JSON.parse(schemaLibrary[mode] || '{}'); } catch (_) { generated = {}; }
        generated.name = effectiveTitle();
        generated.url = currentUrl();
        generated.description = effectiveDescription();
        generated.inLanguage = @json($editor['locale']);
        if (effectiveImage()) generated.image = effectiveImage(); else delete generated.image;
        if (generated['@type'] === 'DonateAction') generated.target = currentUrl();
        schemaGenerated.value = JSON.stringify(generated, null, 2);
        schemaValue.value = schemaGenerated.value;
    };
    const refresh = () => {
        form.classList.toggle('is-auto', Boolean(auto?.checked));
        title.toggleAttribute('readonly', Boolean(auto?.checked));
        description.toggleAttribute('readonly', Boolean(auto?.checked));
        const previewTitle = effectiveTitle();
        const previewDescription = effectiveDescription();
        const previewUrl = currentUrl();
        document.querySelectorAll('[data-preview-title]').forEach(node => node.textContent = previewTitle);
        document.querySelectorAll('[data-preview-description]').forEach(node => node.textContent = previewDescription);
        document.querySelectorAll('[data-preview-url],[data-final-url-label]').forEach(node => node.textContent = previewUrl);
        document.querySelectorAll('[data-social-preview-title]').forEach(node => node.textContent = one('[data-social-title]')?.value.trim() || previewTitle);
        document.querySelectorAll('[data-social-preview-description]').forEach(node => node.textContent = one('[data-social-description]')?.value.trim() || previewDescription);
        renderImage(one('[data-social-image]'), effectiveImage(), 'Choose a social image');
        renderImage(one('[data-share-thumb]'), effectiveImage(), 'No image');
        count(one('[data-title-count]'), auto?.checked ? title.dataset.fallback : title.value, 35, 60);
        count(one('[data-description-count]'), auto?.checked ? description.dataset.fallback : description.value, 120, 160);
        const phrase = normalizePhraseText(focusPhrase?.value);
        const phraseSlug = slugify(phrase);
        const phraseMatchesSavedContentCheck = phrase !== '' && phrase === normalizePhraseText(contentAnalysis.saved_focus_phrase);
        const focusChecks = {
            title: phrase !== '' && normalizePhraseText(previewTitle).includes(phrase),
            description: phrase !== '' && normalizePhraseText(previewDescription).includes(phrase),
            url: phrase !== '' && (phraseSlug === '' || slugify(previewUrl).includes(phraseSlug)),
            headings: phraseMatchesSavedContentCheck && Boolean(contentAnalysis.focus_in_headings),
            body: phraseMatchesSavedContentCheck && Boolean(contentAnalysis.focus_in_body),
        };
        all('[data-focus-check]').forEach(item => {
            const complete = focusChecks[item.dataset.focusCheck] || false;
            item.classList.toggle('is-complete', complete);
            item.classList.toggle('is-optional', phrase === '');
        });
        const permalinkPreview = one('[data-permalink-preview]');
        if (permalinkPreview) permalinkPreview.textContent = previewUrl;
        syncSchema();
    };

    let autoWasChecked = Boolean(auto?.checked);
    const syncAutoFields = () => {
        if (!auto || !title || !description) return;
        const autoIsChecked = Boolean(auto.checked);
        if (autoIsChecked && !autoWasChecked) {
            title.dataset.customValue = title.value;
            description.dataset.customValue = description.value;
            title.value = title.dataset.fallback || '';
            description.value = description.dataset.fallback || '';
        } else if (!autoIsChecked && autoWasChecked) {
            title.value = title.dataset.customValue || title.dataset.fallback || '';
            description.value = description.dataset.customValue || description.dataset.fallback || '';
        }
        autoWasChecked = autoIsChecked;
    };
    auto?.addEventListener('change', syncAutoFields);
    form.querySelectorAll('input:not([type=hidden]),textarea:not([hidden]),select').forEach(control => control.addEventListener(control.matches('select,input[type=radio],input[type=checkbox]') ? 'change' : 'input', () => { setDirty(true); refresh(); }));
    all('[data-visibility]').forEach(radio => radio.addEventListener('change', () => {
        const visible = radio.value === 'index' && radio.checked;
        if (!radio.checked) return;
        indexValue.value = visible ? '1' : '0';
        sitemapExclude.value = visible ? '0' : '1';
        warning.hidden = visible;
    }));
    image?.addEventListener('input', () => { const twitter = one('[data-twitter-image]'); if (twitter) twitter.value = image.value; });
    schemaTemplate?.addEventListener('change', syncSchema);
    schemaExpert?.addEventListener('input', syncSchema);
    all('[data-preview-device]').forEach(button => button.addEventListener('click', () => {
        all('[data-preview-device]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        one('[data-google-preview]').dataset.device = button.dataset.previewDevice;
    }));

    const modal = document.querySelector('[data-media-modal]');
    const mediaSearch = modal?.querySelector('[data-media-search]');
    const mediaGrid = modal?.querySelector('[data-media-grid]');
    const mediaResultStatus = modal?.querySelector('[data-media-result-status]');
    const mediaPageStatus = modal?.querySelector('[data-media-page-status]');
    const mediaPrevious = modal?.querySelector('[data-media-previous]');
    const mediaNext = modal?.querySelector('[data-media-next]');
    let mediaPage = 1, mediaLastPage = 1, mediaTimer = null, mediaRequest = null;
    const mediaNode = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const renderMedia = payload => {
        if (!mediaGrid) return;
        mediaGrid.replaceChildren();
        const assets = Array.isArray(payload.data) ? payload.data : [];
        if (!assets.length) {
            const empty = mediaNode('div', 'seo2-empty');
            empty.appendChild(mediaNode('p', '', 'No matching images. Upload one in the Media Library or try another search.'));
            mediaGrid.appendChild(empty);
        }
        assets.forEach(asset => {
            const card = mediaNode('article', 'seo2-media-option');
            const choose = mediaNode('button', '', '');
            choose.type = 'button'; choose.dataset.mediaUrl = asset.url; choose.dataset.mediaAlt = asset.alt_text || '';
            choose.setAttribute('aria-label', `Choose ${asset.name}`);
            const preview = mediaNode('img'); preview.src = asset.url; preview.alt = asset.alt_text || ''; preview.loading = 'lazy';
            choose.appendChild(preview); choose.appendChild(mediaNode('span', '', asset.name));
            card.appendChild(choose);
            card.appendChild(mediaNode('span', 'seo2-media-option__meta', asset.width && asset.height ? `${asset.width} × ${asset.height}` : 'Dimensions unavailable'));
            if (asset.warnings?.length) card.appendChild(mediaNode('span', 'seo2-media-option__warning', asset.warnings.join(' · ')));
            if (asset.edit_url) {
                const edit = mediaNode('a', 'seo2-media-option__edit', 'Edit alt text and details'); edit.href = asset.edit_url; edit.target = '_blank'; edit.rel = 'noopener'; card.appendChild(edit);
            }
            mediaGrid.appendChild(card);
        });
        mediaPage = Number(payload.meta?.current_page || 1); mediaLastPage = Number(payload.meta?.last_page || 1);
        if (mediaPageStatus) mediaPageStatus.textContent = `Page ${mediaPage} of ${mediaLastPage}`;
        if (mediaResultStatus) mediaResultStatus.textContent = `${Number(payload.meta?.total || 0)} image${Number(payload.meta?.total || 0) === 1 ? '' : 's'}`;
        if (mediaPrevious) mediaPrevious.disabled = mediaPage <= 1;
        if (mediaNext) mediaNext.disabled = mediaPage >= mediaLastPage;
    };
    const loadMedia = async (page = 1) => {
        if (!modal || !mediaGrid) return;
        mediaRequest?.abort(); mediaRequest = new AbortController();
        const endpoint = new URL(modal.dataset.mediaEndpoint, window.location.origin);
        endpoint.searchParams.set('page', String(page));
        if (mediaSearch?.value.trim()) endpoint.searchParams.set('search', mediaSearch.value.trim());
        mediaGrid.replaceChildren(mediaNode('div', 'seo2-empty', 'Loading images…'));
        try {
            const response = await fetch(endpoint, {headers:{'Accept':'application/json'}, signal:mediaRequest.signal});
            if (!response.ok) throw new Error('Images could not be loaded.');
            renderMedia(await response.json());
        } catch (error) {
            if (error.name === 'AbortError') return;
            mediaGrid.replaceChildren(mediaNode('div', 'seo2-empty', error.message));
        }
    };
    const closeMedia = () => { if (!modal) return; modal.hidden = true; mediaReturn?.focus(); mediaReturn = null; };
    one('[data-media-open]')?.addEventListener('click', event => { mediaReturn = event.currentTarget; modal.hidden = false; mediaSearch.value = ''; loadMedia(1); window.setTimeout(() => mediaSearch.focus(), 20); });
    modal?.querySelector('[data-media-close]')?.addEventListener('click', closeMedia);
    modal?.addEventListener('click', event => { if (event.target === modal) closeMedia(); const option = event.target.closest('[data-media-url]'); if (!option) return; image.value = option.dataset.mediaUrl; if (imageAlt) imageAlt.value = option.dataset.mediaAlt || ''; image.dispatchEvent(new Event('input', {bubbles:true})); imageAlt?.dispatchEvent(new Event('input', {bubbles:true})); closeMedia(); });
    mediaSearch?.addEventListener('input', () => { window.clearTimeout(mediaTimer); mediaTimer = window.setTimeout(() => loadMedia(1), 250); });
    mediaPrevious?.addEventListener('click', () => loadMedia(Math.max(1, mediaPage - 1)));
    mediaNext?.addEventListener('click', () => loadMedia(Math.min(mediaLastPage, mediaPage + 1)));
    one('[data-media-clear]')?.addEventListener('click', () => { image.value = ''; if (imageAlt) imageAlt.value = ''; image.dispatchEvent(new Event('input', {bubbles:true})); imageAlt?.dispatchEvent(new Event('input', {bubbles:true})); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && !modal.hidden) { closeMedia(); return; }
        if (event.key !== 'Tab' || !modal || modal.hidden) return;
        const focusable = [...modal.querySelectorAll('button:not([hidden]),input:not([hidden]),a[href]')].filter(node => node.getClientRects().length);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.addEventListener('beforeunload', event => { if (!dirty) return; event.preventDefault(); event.returnValue = ''; });
    form.addEventListener('submit', event => {
        if (auto?.checked) { title.value = ''; description.value = ''; }
        syncSchema();
        const hidingNow = indexValue.value === '0' && form.dataset.originalIndexable === '1';
        if (hidingNow && !window.confirm('Hide this page from search engines and remove it from the sitemap?')) { event.preventDefault(); return; }
        setDirty(false);
    });
    refresh();
})();
</script>
