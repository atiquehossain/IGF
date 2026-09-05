@extends('admin.layouts.master')

@section('content')
<style>
    .igf-nav-editor{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#6a6866;--line:#e4ded8;max-width:1320px;margin:28px auto;padding:0 22px;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-nav-editor *{box-sizing:border-box}.igf-nav-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:22px}.igf-nav-head h1{margin:0;font:700 40px/1.08 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-nav-head p{max-width:690px;margin:8px 0 0;color:var(--muted);line-height:1.6}.igf-nav-filters{display:flex;align-items:flex-end;gap:10px}.igf-nav-field{display:grid;gap:6px}.igf-nav-field label,.igf-nav-field>span{color:#4e4c49;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.igf-nav-field input,.igf-nav-field select{width:100%;min-height:44px;padding:9px 11px;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:var(--ink)}.igf-nav-field input:focus,.igf-nav-field select:focus{outline:3px solid rgba(255,117,0,.18);border-color:var(--orange)}.igf-nav-layout{display:grid;grid-template-columns:minmax(300px,360px) minmax(0,1fr);align-items:start;gap:22px}.igf-nav-card{border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 10px 30px rgba(25,28,29,.045)}.igf-nav-card__head{padding:20px 21px;border-bottom:1px solid var(--line)}.igf-nav-card__head h2{margin:0 0 5px;font:650 23px/1.2 'Literata',Georgia,serif}.igf-nav-card__head p{margin:0;color:var(--muted);font-size:13px;line-height:1.5}.igf-nav-card__body{padding:20px 21px}.igf-nav-add{display:grid;gap:16px}.igf-nav-check{display:flex;align-items:center;gap:9px;margin:0;color:#464442;font-size:13px;font-weight:750}.igf-nav-check input{width:18px;height:18px;accent-color:var(--orange)}.igf-nav-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:8px;padding:8px 14px;border:1px solid #d9d2cb;border-radius:8px;background:#fff;color:#3d3b39;font-size:13px;font-weight:800;cursor:pointer;text-decoration:none}.igf-nav-button:hover{border-color:var(--orange);color:var(--brown)}.igf-nav-button--primary{border-color:var(--orange);background:var(--orange);color:#fff}.igf-nav-button--primary:hover{background:var(--brown);color:#fff}.igf-nav-button--danger{color:#a72e25}.igf-nav-button[disabled]{opacity:.5;cursor:not-allowed}.igf-nav-notice{display:none;margin-bottom:16px;padding:12px 14px;border-radius:9px;background:#eef7f1;color:#23623a;font-size:13px;font-weight:700}.igf-nav-notice.is-visible{display:block}.igf-nav-notice.is-error{background:#fff0ee;color:#962d25}.igf-nav-notice.is-warning{background:#fff4df;color:#80530e}.igf-menu-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}.igf-menu-toolbar__hint{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px}.igf-menu-toolbar__hint i{color:var(--orange)}.igf-menu-tree{padding:18px 20px 22px;background:#f8f7f5}.igf-menu-list{display:grid;gap:9px;margin:0;padding:0;list-style:none}.igf-menu-list .igf-menu-list{min-height:0;margin:9px 0 0 42px;padding-left:13px;border-left:2px solid #e5ddd5}.igf-menu-list.is-drop-ready:empty{min-height:34px;border:1px dashed #f0a76c;border-radius:8px;background:#fff8f2}.igf-menu-item{margin:0}.igf-menu-item.is-dragging{opacity:.42}.igf-menu-item__row{display:grid;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:10px;min-height:66px;padding:10px 12px;border:1px solid #dfd9d3;border-radius:10px;background:#fff;box-shadow:0 4px 12px rgba(25,28,29,.025)}.igf-menu-item__row:hover{border-color:#cfc6bd}.igf-menu-drag{display:grid;width:34px;height:38px;place-content:center;border:0;border-radius:7px;background:#f3f1ef;color:#817b76;font-size:18px;cursor:grab}.igf-menu-drag:active{cursor:grabbing}.igf-menu-item__title{min-width:0}.igf-menu-item__title strong{display:block;overflow:hidden;color:var(--ink);font-size:14px;text-overflow:ellipsis;white-space:nowrap}.igf-menu-item__title small{display:block;overflow:hidden;margin-top:3px;color:#817c77;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.igf-menu-status{display:inline-flex;width:max-content;margin-top:5px;padding:3px 6px;border-radius:999px;background:#e6f5eb;color:#247542;font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.igf-menu-status.is-hidden{background:#eeeae7;color:#77716c}.igf-menu-actions{display:flex;align-items:center;gap:4px}.igf-menu-action{display:grid;width:32px;height:32px;place-content:center;border:1px solid #e7e0da;border-radius:7px;background:#fff;color:#5c5753;font-weight:900;cursor:pointer}.igf-menu-action:hover{border-color:var(--orange);color:var(--brown)}.igf-menu-action:focus-visible{outline:3px solid rgba(255,117,0,.2);outline-offset:1px}.igf-menu-item__settings{margin:-1px 10px 0;padding:15px;border:1px solid #e6dfd8;border-top:0;border-radius:0 0 10px 10px;background:#fff}.igf-menu-settings-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}.igf-menu-settings-actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px}.igf-menu-empty{padding:64px 20px;text-align:center}.igf-menu-empty i{display:grid;width:58px;height:58px;margin:0 auto 14px;place-content:center;border-radius:50%;background:#fff2e7;color:var(--orange);font-size:24px}.igf-menu-empty h3{margin:0 0 7px;font:650 22px 'Literata',Georgia,serif}.igf-menu-empty p{max-width:480px;margin:0 auto;color:var(--muted);line-height:1.6}.igf-menu-dirty{display:none;color:#946015;font-size:12px;font-weight:800}.igf-menu-dirty.is-visible{display:inline}.igf-nav-help{margin:16px 0 0;padding:14px;border-radius:10px;background:#f7f4f1;color:#66615c;font-size:12px;line-height:1.6}.igf-nav-help strong{color:#3e3b38}.igf-nav-visually-hidden{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(1px,1px,1px,1px)!important;white-space:nowrap!important}.igf-nav-spinner{display:none}.igf-nav-button.is-loading .igf-nav-spinner{display:inline-block}
    @media(max-width:1050px){.igf-nav-head{align-items:flex-start;flex-direction:column}.igf-nav-filters{width:100%}.igf-nav-filters .igf-nav-field{flex:1}.igf-nav-layout{grid-template-columns:1fr}.igf-nav-card--add{position:static}}
    @media(max-width:650px){.igf-nav-editor{margin-top:18px;padding:0 13px}.igf-nav-head h1{font-size:32px}.igf-nav-filters{align-items:stretch;flex-direction:column}.igf-menu-toolbar{align-items:flex-start;flex-direction:column}.igf-menu-item__row{grid-template-columns:34px minmax(0,1fr)}.igf-menu-actions{grid-column:1/-1;justify-content:flex-end}.igf-menu-list .igf-menu-list{margin-left:20px}.igf-menu-settings-grid{grid-template-columns:1fr}.igf-menu-action{width:38px;height:38px}}
    .igf-nav-field textarea{width:100%;min-height:78px;padding:9px 11px;border:1px solid #d8d1ca;border-radius:8px;background:#fff;color:var(--ink);resize:vertical}.igf-nav-field textarea:focus{outline:3px solid rgba(255,117,0,.18);border-color:var(--orange)}
    .igf-nav-read-only{margin-bottom:18px;padding:13px 15px;border:1px solid #edcf9f;border-radius:9px;background:#fff8e8;color:#74501d;font-size:13px;line-height:1.55}.igf-nav-read-only strong{display:block;color:#56370d}.igf-nav-card__body>p{color:var(--muted);font-size:13px;line-height:1.55}
    .igf-nav-editor,.igf-nav-layout,.igf-nav-card,.igf-nav-card__body,.igf-menu-tree,.igf-menu-item,.igf-menu-item__title{min-width:0;max-width:100%}.igf-nav-button,.igf-nav-check{min-height:44px}.igf-menu-item__row{grid-template-columns:44px minmax(0,1fr) auto}.igf-menu-drag,.igf-menu-action{width:44px;min-width:44px;height:44px;min-height:44px}.igf-menu-settings-actions{flex-wrap:wrap}.igf-menu-settings-actions .igf-nav-button{flex:1 1 180px}.igf-menu-actions{flex-wrap:wrap}
    @media(max-width:650px){.igf-nav-card__head,.igf-nav-card__body{padding:16px}.igf-menu-tree{padding:14px 10px 18px}.igf-menu-item__row{grid-template-columns:44px minmax(0,1fr);padding:10px 8px}.igf-menu-actions{justify-content:flex-start}.igf-menu-list .igf-menu-list{margin-left:10px;padding-left:8px}.igf-menu-toolbar>div:last-child{width:100%;flex-wrap:wrap}.igf-menu-toolbar>div:last-child .igf-nav-button{flex:1 1 150px}}
</style>

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canCreateMenu = $permissions->allows($admin, 'page.menu.store');
    $canEditMenu = $permissions->allows($admin, 'page.menu.item.update');
    $canStatusMenu = $permissions->allows($admin, 'page.menu.status');
    $canDeleteMenu = $permissions->allows($admin, 'page.menu.destroy');
    $canViewMenuTrash = $permissions->allows($admin, 'page.menu.trash');
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text($key, $replace);
    $isNavigationViewOnly = !$canCreateMenu && !$canEditMenu && !$canStatusMenu && !$canDeleteMenu;
    $navigationParentOptions = [];
    $collectNavigationParents = function (array $items, int $depth = 0) use (&$collectNavigationParents, &$navigationParentOptions): void {
        if ($depth >= 2) return;
        foreach ($items as $item) {
            $navigationParentOptions[] = [
                'uuid' => $item['uuid'],
                'label' => str_repeat('— ', $depth).$item['name'],
            ];
            $collectNavigationParents($item['children'] ?? [], $depth + 1);
        }
    };
    $collectNavigationParents($menuTree);
@endphp

<main class="igf-nav-editor">
    <header class="igf-nav-head">
        <div>
            <h1>{{ $ui('navigation.title') }}</h1>
            <p>{{ $ui($isNavigationViewOnly ? 'navigation.intro_view' : 'navigation.intro_edit') }}</p>
        </div>
        <form id="menu-filter-form" class="igf-nav-filters" method="GET" action="{{ route('page.menu.index') }}">
            <label class="igf-nav-field"><span>{{ $ui('navigation.menu_location') }}</span><select name="location" id="menu-location">@foreach($locations as $value => $label)<option value="{{ $value }}" @selected($location === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="igf-nav-field"><span>{{ $ui('navigation.language') }}</span><select name="locale" id="menu-locale">@foreach($translations as $translation)<option value="{{ $translation->id }}" @selected($locale === $translation->id)>{{ $translation->name }}</option>@endforeach</select></label>
        </form>
    </header>

    @if($isNavigationViewOnly)<div class="igf-nav-read-only" role="status"><strong>{{ $ui('navigation.readonly_title') }}</strong>{{ $ui('navigation.readonly_help') }}</div>@endif

    <div id="menu-notice" class="igf-nav-notice" role="status" aria-live="polite"></div>

    <div class="igf-nav-layout">
        <aside class="igf-nav-card igf-nav-card--add">
            <div class="igf-nav-card__head"><h2>{{ $ui($canCreateMenu ? 'navigation.add_title' : 'navigation.access_title') }}</h2><p>{{ $ui($canCreateMenu ? 'navigation.add_help' : 'navigation.access_help') }}</p></div>
            <div class="igf-nav-card__body">
                @if($canCreateMenu)
                <form id="add-menu-item" class="igf-nav-add">
                    <label class="igf-nav-field"><span>{{ $ui('navigation.link_to') }}</span><select id="destination-type"><option value="route">{{ $ui('navigation.built_in_page') }}</option><option value="page">{{ $ui('navigation.cms_page') }}</option><option value="category">{{ $ui('navigation.category') }}</option><option value="project">{{ $ui('navigation.project') }}</option><option value="custom">{{ $ui('navigation.custom_url') }}</option><option value="label">{{ $ui('navigation.parent_label') }}</option></select></label>
                    <label id="destination-select-field" class="igf-nav-field"><span>{{ $ui('navigation.choose_destination') }}</span><select id="destination-select"></select></label>
                    <label id="destination-custom-field" class="igf-nav-field" hidden><span>{{ $ui('navigation.custom_url') }}</span><input id="destination-custom" type="text" inputmode="url" placeholder="{{ $ui('navigation.url_placeholder') }}"></label>
                    <label class="igf-nav-field"><span>{{ $ui('navigation.navigation_label') }}</span><input id="navigation-label" type="text" maxlength="120" required placeholder="{{ $ui('navigation.label_placeholder') }}"></label>
                    <label class="igf-nav-field"><span>{{ $ui('navigation.description') }}</span><textarea id="navigation-description" maxlength="255" placeholder="{{ $ui('navigation.description_placeholder') }}"></textarea></label>
                    <label class="igf-nav-field"><span>{{ $ui('navigation.parent_item') }}</span><select id="navigation-parent"><option value="">{{ $ui('navigation.top_level') }}</option>@foreach($navigationParentOptions as $item)<option value="{{ $item['uuid'] }}">{{ $item['label'] }}</option>@endforeach</select></label>
                    <label class="igf-nav-check"><input id="navigation-enabled" type="checkbox" @checked($canStatusMenu) @disabled(!$canStatusMenu)> {{ $ui('navigation.show_on_website') }}</label>
                    @if(!$canStatusMenu)<p class="igf-nav-help">{{ $ui('navigation.hidden_until_published') }}</p>@endif
                    <button id="add-menu-button" class="igf-nav-button igf-nav-button--primary" type="submit"><span class="igf-nav-spinner" aria-hidden="true">&#8635;</span><span>{{ $ui('navigation.add_to_menu') }}</span></button>
                </form>
                <p class="igf-nav-help"><strong>{{ $ui('navigation.submenu_help_title') }}</strong> {{ $ui('navigation.submenu_help') }}</p>
                @else
                    <p>{{ $ui('navigation.adding_unavailable') }}</p>
                @endif
                @if($canViewMenuTrash)<a class="igf-nav-button" style="width:100%;margin-top:12px" href="{{ route('page.menu.trash') }}">{{ $ui('navigation.open_trash') }}</a>@endif
            </div>
        </aside>

        <section class="igf-nav-card">
            <div class="igf-nav-card__head">
                <div class="igf-menu-toolbar">
                    <div><h2>{{ $locations[$location] }}</h2><p>{{ $ui($canEditMenu ? 'navigation.reorder_help' : 'navigation.review_help') }}</p></div>
                    @if($canEditMenu)<div style="display:flex;align-items:center;gap:10px"><span id="menu-dirty" class="igf-menu-dirty">{{ $ui('navigation.unsaved_order') }}</span><button id="save-menu-order" class="igf-nav-button igf-nav-button--primary" type="button" @disabled(empty($menuTree))>{{ $ui('navigation.save_menu') }}</button></div>@endif
                </div>
            </div>
            <div class="igf-menu-tree" id="menu-tree">
                @if($menuTree)
                    @include('admin.page_menu._tree', ['items' => $menuTree, 'parentUuid' => null, 'depth' => 0, 'canEditMenu' => $canEditMenu, 'canStatusMenu' => $canStatusMenu, 'canDeleteMenu' => $canDeleteMenu])
                @else
                    <div class="igf-menu-empty"><i class="fa fa-sitemap" aria-hidden="true"></i><h3>{{ $ui('navigation.empty_title') }}</h3><p>{{ $ui('navigation.empty_help') }}</p></div>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection

@section('custom-js')
<script>
(() => {
    const ui = @json(\App\Support\AdminUi::section('navigation'));
    const destinationGroups = @json($destinationGroups);
    const routes = {
        @if($canCreateMenu)store: @json(route('page.menu.store')),@endif
        @if($canEditMenu)reorder: @json(route('page.menu.reorder')),@endif
    };
    const canEditMenu = @json($canEditMenu);
    const maxMenuDepth = 3;
    const locale = @json($locale);
    const locationName = @json($location);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const notice = document.getElementById('menu-notice');
    const destinationType = document.getElementById('destination-type');
    const destinationSelect = document.getElementById('destination-select');
    const destinationSelectField = document.getElementById('destination-select-field');
    const destinationCustomField = document.getElementById('destination-custom-field');
    const destinationCustom = document.getElementById('destination-custom');
    const labelInput = document.getElementById('navigation-label');
    const descriptionInput = document.getElementById('navigation-description');
    const tree = document.getElementById('menu-tree');
    const dirtyLabel = document.getElementById('menu-dirty');
    const saveOrderButton = document.getElementById('save-menu-order');
    let orderDirty = false;
    let labelWasEdited = false;
    let dragged = null;

    function showNotice(message, type = 'success') {
        notice.textContent = message;
        notice.className = `igf-nav-notice is-visible${type === 'error' ? ' is-error' : type === 'warning' ? ' is-warning' : ''}`;
        notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    function errorMessage(error, fallback = ui.messages.generic_error) {
        const errors = error?.errors || {};
        return Object.values(errors).flat()[0] || error?.message || fallback;
    }
    async function jsonRequest(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw payload;
        return payload;
    }
    function populateDestinations() {
        if (!destinationType) return;
        const type = destinationType.value;
        const items = destinationGroups[type] || [];
        const usesSelect = ['route', 'page', 'category', 'project'].includes(type);
        destinationSelectField.hidden = !usesSelect;
        destinationCustomField.hidden = type !== 'custom';
        destinationSelect.innerHTML = '';
        if (usesSelect) {
            if (!items.length) destinationSelect.add(new Option(ui.messages.no_available_items, ''));
            items.forEach(item => destinationSelect.add(new Option(item.label, item.value)));
            destinationSelect.disabled = !items.length;
            if (!labelWasEdited || !labelInput.value.trim()) labelInput.value = items[0]?.label || '';
        } else if (type === 'label' && (!labelWasEdited || !labelInput.value.trim())) {
            labelInput.value = '';
            labelInput.focus();
        }
    }
    destinationType?.addEventListener('change', () => { labelWasEdited = false; populateDestinations(); });
    destinationSelect?.addEventListener('change', () => { if (!labelWasEdited) labelInput.value = destinationSelect.selectedOptions[0]?.textContent || ''; });
    labelInput?.addEventListener('input', () => { labelWasEdited = true; });
    if (destinationType) populateDestinations();

    document.getElementById('add-menu-item')?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('add-menu-button');
        const type = destinationType.value;
        const destination = type === 'custom' ? destinationCustom.value : (type === 'label' ? '' : destinationSelect.value);
        button.disabled = true;
        button.classList.add('is-loading');
        try {
            await jsonRequest(routes.store, { method: 'POST', body: JSON.stringify({
                simple: true,
                locale,
                location: locationName,
                label: labelInput.value,
                description: descriptionInput.value,
                destination_type: type,
                destination,
                parent_uuid: document.getElementById('navigation-parent').value || null,
                enabled: document.getElementById('navigation-enabled').checked,
            }) });
            window.location.reload();
        } catch (error) {
            showNotice(errorMessage(error, ui.messages.add_failed), 'error');
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    });

    function directItems(list) { return [...list.children].filter(child => child.matches('.igf-menu-item')); }
    function childList(item) { return [...item.children].find(child => child.matches('.igf-menu-list')); }
    function markOrderDirty() {
        if (!canEditMenu) return;
        orderDirty = true;
        dirtyLabel?.classList.add('is-visible');
        saveOrderButton?.removeAttribute('disabled');
    }
    function listDepth(list) {
        let depth = 0;
        let cursor = list.parentElement;
        while (cursor) {
            if (cursor.matches?.('.igf-menu-item')) depth += 1;
            cursor = cursor.parentElement;
        }
        return depth;
    }
    function itemSubtreeDepth(item) {
        const children = directItems(childList(item));
        return children.length ? 1 + Math.max(...children.map(itemSubtreeDepth)) : 1;
    }
    function itemHasChildren(item) { return directItems(childList(item)).length > 0; }
    function canMoveToList(item, list) {
        return Boolean(item && list && !item.contains(list) && listDepth(list) + itemSubtreeDepth(item) <= maxMenuDepth);
    }

    tree.querySelectorAll('[data-menu-toggle]').forEach(button => button.addEventListener('click', () => {
        const panel = button.closest('.igf-menu-item').querySelector(':scope > .igf-menu-item__settings');
        const open = panel.hidden;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
    }));
    tree.addEventListener('click', async event => {
        const action = event.target.closest('[data-menu-action]');
        if (!action) return;
        const item = action.closest('.igf-menu-item');
        const list = item.parentElement;
        const items = directItems(list);
        const index = items.indexOf(item);
        const type = action.dataset.menuAction;
        if (type === 'up' && index > 0) list.insertBefore(item, items[index - 1]);
        else if (type === 'down' && index < items.length - 1) list.insertBefore(items[index + 1], item);
        else if (type === 'indent') {
            if (index < 1) return showNotice(ui.messages.place_after_parent, 'warning');
            const targetList = childList(items[index - 1]);
            if (!canMoveToList(item, targetList)) return showNotice(ui.messages.depth_limit, 'warning');
            targetList.append(item);
        } else if (type === 'outdent') {
            const parentItem = list.closest('.igf-menu-item');
            if (!parentItem) return showNotice(ui.messages.already_top_level, 'warning');
            parentItem.parentElement.insertBefore(item, parentItem.nextElementSibling);
        } else return;
        markOrderDirty();
    });

    tree.querySelectorAll('.igf-menu-item').forEach(item => {
        item.addEventListener('dragstart', event => {
            if (!event.target.closest('.igf-menu-drag')) { event.preventDefault(); return; }
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            document.querySelectorAll('[data-menu-list]').forEach(list => list.classList.add('is-drop-ready'));
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('is-dragging');
            document.querySelectorAll('[data-menu-list]').forEach(list => list.classList.remove('is-drop-ready'));
            dragged = null;
        });
    });
    tree.querySelectorAll('[data-menu-list]').forEach(list => {
        list.addEventListener('dragover', event => {
            event.preventDefault();
            event.stopPropagation();
            if (!canMoveToList(dragged, list)) return;
            const target = event.target.closest('.igf-menu-item');
            if (!target || target === dragged || target.parentElement !== list) return;
            const rect = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
        });
        list.addEventListener('drop', event => {
            event.preventDefault();
            event.stopPropagation();
            if (!canMoveToList(dragged, list)) return showNotice(ui.messages.invalid_move, 'warning');
            const target = event.target.closest('.igf-menu-item');
            if ((!target || target.parentElement !== list) && dragged.parentElement !== list) list.append(dragged);
            markOrderDirty();
        });
    });

    saveOrderButton?.addEventListener('click', async () => {
        const items = [];
        const visit = (list, parentUuid = null) => directItems(list).forEach((item, order) => {
            items.push({ uuid: item.dataset.uuid, parent_uuid: parentUuid, order });
            visit(childList(item), item.dataset.uuid);
        });
        const root = tree.querySelector(':scope > .igf-menu-list');
        if (!root) return;
        visit(root);
        saveOrderButton.disabled = true;
        try {
            await jsonRequest(routes.reorder, { method: 'PUT', body: JSON.stringify({ locale, location: locationName, items }) });
            orderDirty = false;
            dirtyLabel.classList.remove('is-visible');
            showNotice(ui.messages.order_saved);
        } catch (error) {
            saveOrderButton.disabled = false;
            showNotice(errorMessage(error, ui.messages.order_failed), 'error');
        }
    });

    tree.querySelectorAll('[data-save-menu-item]').forEach(button => button.addEventListener('click', async () => {
        const item = button.closest('.igf-menu-item');
        const settings = item.querySelector(':scope > .igf-menu-item__settings');
        button.disabled = true;
        try {
            const payload = await jsonRequest(button.dataset.url, { method: 'PUT', body: JSON.stringify({
                locale,
                label: settings.querySelector('[data-menu-label]').value,
                description: settings.querySelector('[data-menu-description]').value,
                enabled: settings.querySelector('[data-menu-enabled]').checked,
                custom_url: settings.querySelector('[data-menu-custom-url]')?.value || null,
            }) });
            item.querySelector(':scope > .igf-menu-item__row [data-menu-title]').textContent = payload.item.name;
            const badge = item.querySelector(':scope > .igf-menu-item__row [data-menu-status]');
            badge.textContent = payload.item.status ? ui.visible : ui.hidden;
            badge.classList.toggle('is-hidden', !payload.item.status);
            item.querySelector(':scope > .igf-menu-item__row [data-menu-destination]').textContent = payload.item.destination;
            showNotice(ui.messages.item_updated);
        } catch (error) {
            showNotice(errorMessage(error, ui.messages.item_save_failed), 'error');
        } finally { button.disabled = false; }
    }));

    tree.querySelectorAll('[data-toggle-menu-status]').forEach(button => button.addEventListener('click', async () => {
        const item = button.closest('.igf-menu-item');
        const badge = item.querySelector(':scope > .igf-menu-item__row [data-menu-status]');
        button.disabled = true;
        try {
            const payload = await jsonRequest(button.dataset.url, { method: 'PUT', body: '{}' });
            const enabled = button.dataset.currentStatus !== '1';
            button.dataset.currentStatus = enabled ? '1' : '0';
            button.textContent = enabled ? ui.hide_from_website : ui.show_on_website_short;
            badge.textContent = enabled ? ui.visible : ui.hidden;
            badge.classList.toggle('is-hidden', !enabled);
            showNotice(ui.messages.visibility_updated);
        } catch (error) {
            showNotice(errorMessage(error, ui.messages.visibility_failed), 'error');
        } finally { button.disabled = false; }
    }));

    tree.querySelectorAll('[data-delete-menu-item]').forEach(button => button.addEventListener('click', async () => {
        const item = button.closest('.igf-menu-item');
        if (itemHasChildren(item)) return showNotice(ui.messages.move_children_first, 'warning');
        if (!window.confirm(ui.messages.confirm_trash)) return;
        button.disabled = true;
        try {
            await jsonRequest(button.dataset.url, { method: 'DELETE' });
            item.remove();
            showNotice(ui.messages.item_removed);
        } catch (error) {
            showNotice(errorMessage(error, ui.messages.remove_failed), 'error');
            button.disabled = false;
        }
    }));

    document.querySelectorAll('#menu-location,#menu-locale').forEach(select => select.addEventListener('change', () => {
        if (orderDirty && !window.confirm(ui.messages.discard_order)) return;
        document.getElementById('menu-filter-form').submit();
    }));
    window.addEventListener('beforeunload', event => { if (orderDirty) { event.preventDefault(); event.returnValue = ''; } });
})();
</script>
@endsection
