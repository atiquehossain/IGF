<ol class="igf-menu-list" data-menu-list data-parent-uuid="{{ $parentUuid }}">
    @foreach($items as $item)
        <li class="igf-menu-item" data-uuid="{{ $item['uuid'] }}" draggable="{{ $canEditMenu ? 'true' : 'false' }}">
            <div class="igf-menu-item__row">
                @if($canEditMenu)<button class="igf-menu-drag" type="button" aria-label="Drag {{ $item['name'] }} to reorder" title="Drag to reorder">&#8942;&#8942;</button>@else<span aria-hidden="true"></span>@endif
                <div class="igf-menu-item__title">
                    <strong data-menu-title>{{ $item['name'] }}</strong>
                    <small data-menu-destination>{{ $item['destination'] }}</small>
                    <span class="igf-menu-status @if(!$item['status']) is-hidden @endif" data-menu-status>{{ $item['status'] ? 'Visible' : 'Hidden' }}</span>
                </div>
                <div class="igf-menu-actions" aria-label="Arrange {{ $item['name'] }}">
                    @if($canEditMenu)
                    <button class="igf-menu-action" type="button" data-menu-action="up" title="Move up" aria-label="Move {{ $item['name'] }} up">&uarr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="down" title="Move down" aria-label="Move {{ $item['name'] }} down">&darr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="indent" title="Make submenu" aria-label="Make {{ $item['name'] }} a submenu">&rarr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="outdent" title="Move out of submenu" aria-label="Move {{ $item['name'] }} out of submenu">&larr;</button>
                    @endif
                    @if($canEditMenu || $canStatusMenu || $canDeleteMenu)
                    <button class="igf-menu-action" type="button" data-menu-toggle aria-expanded="false" title="Edit item" aria-label="Edit {{ $item['name'] }}">&#9881;</button>
                    @endif
                </div>
            </div>
            @if($canEditMenu || $canStatusMenu || $canDeleteMenu)
            <section class="igf-menu-item__settings" hidden>
                @if($canEditMenu)
                <div class="igf-menu-settings-grid">
                    <label class="igf-nav-field"><span>Navigation label</span><input data-menu-label value="{{ $item['name'] }}" maxlength="120"></label>
                    <label class="igf-nav-field"><span>Short description (optional)</span><textarea data-menu-description maxlength="255" placeholder="Shown below submenu links">{{ $item['description'] }}</textarea></label>
                    @if($item['destination_type'] === 'custom')
                        <label class="igf-nav-field"><span>Custom URL</span><input data-menu-custom-url value="{{ $item['slug'] }}" maxlength="2048"></label>
                    @else
                        <div class="igf-nav-field"><span>Destination</span><input value="{{ $item['destination'] }}" disabled></div>
                    @endif
                </div>
                <label class="igf-nav-check" style="margin-top:12px"><input data-menu-enabled type="checkbox" @checked($item['status']) @disabled(!$canStatusMenu)> Show this item on the website</label>
                @if(!$canStatusMenu)<p class="igf-nav-help">Visibility is read-only because your role cannot publish navigation changes.</p>@endif
                @endif
                <div class="igf-menu-settings-actions">
                    @if($canDeleteMenu)<button class="igf-nav-button igf-nav-button--danger" type="button" data-delete-menu-item data-url="{{ route('page.menu.destroy', $item['uuid']) }}">Move to trash</button>@endif
                    @if($canEditMenu)<button class="igf-nav-button igf-nav-button--primary" type="button" data-save-menu-item data-url="{{ route('page.menu.item.update', $item['uuid']) }}">Save item</button>@elseif($canStatusMenu)<button class="igf-nav-button igf-nav-button--primary" type="button" data-toggle-menu-status data-current-status="{{ $item['status'] ? '1' : '0' }}" data-url="{{ route('page.menu.status', $item['uuid']) }}">{{ $item['status'] ? 'Hide from website' : 'Show on website' }}</button>@endif
                </div>
            </section>
            @endif
            @include('admin.page_menu._tree', ['items' => $item['children'], 'parentUuid' => $item['uuid'], 'depth' => $depth + 1, 'canEditMenu' => $canEditMenu, 'canStatusMenu' => $canStatusMenu, 'canDeleteMenu' => $canDeleteMenu])
        </li>
    @endforeach
</ol>
