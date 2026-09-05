<ol class="igf-menu-list" data-menu-list data-parent-uuid="{{ $parentUuid }}">
    @foreach($items as $item)
        <li class="igf-menu-item" data-uuid="{{ $item['uuid'] }}" draggable="{{ $canEditMenu ? 'true' : 'false' }}">
            <div class="igf-menu-item__row">
                @if($canEditMenu)<button class="igf-menu-drag" type="button" aria-label="{{ $ui('navigation.drag_item', ['name' => $item['name']]) }}" title="{{ $ui('navigation.drag_to_reorder') }}">&#8942;&#8942;</button>@else<span aria-hidden="true"></span>@endif
                <div class="igf-menu-item__title">
                    <strong data-menu-title>{{ $item['name'] }}</strong>
                    <small data-menu-destination>{{ $item['destination'] }}</small>
                    <span class="igf-menu-status @if(!$item['status']) is-hidden @endif" data-menu-status>{{ $item['status'] ? $ui('navigation.visible') : $ui('navigation.hidden') }}</span>
                </div>
                <div class="igf-menu-actions" aria-label="{{ $ui('navigation.arrange_item', ['name' => $item['name']]) }}">
                    @if($canEditMenu)
                    <button class="igf-menu-action" type="button" data-menu-action="up" title="{{ $ui('navigation.move_up_title') }}" aria-label="{{ $ui('navigation.move_up', ['name' => $item['name']]) }}">&uarr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="down" title="{{ $ui('navigation.move_down_title') }}" aria-label="{{ $ui('navigation.move_down', ['name' => $item['name']]) }}">&darr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="indent" title="{{ $ui('navigation.make_submenu') }}" aria-label="{{ $ui('navigation.make_child', ['name' => $item['name']]) }}" @disabled($depth >= 2)>&rarr;</button>
                    <button class="igf-menu-action" type="button" data-menu-action="outdent" title="{{ $ui('navigation.move_out_title') }}" aria-label="{{ $ui('navigation.move_out', ['name' => $item['name']]) }}">&larr;</button>
                    @endif
                    @if($canEditMenu || $canStatusMenu || $canDeleteMenu)
                    <button class="igf-menu-action" type="button" data-menu-toggle aria-expanded="false" title="{{ $ui('navigation.edit_item') }}" aria-label="{{ $ui('navigation.edit_item') }}: {{ $item['name'] }}">&#9881;</button>
                    @endif
                </div>
            </div>
            @if($canEditMenu || $canStatusMenu || $canDeleteMenu)
            <section class="igf-menu-item__settings" hidden>
                @if($canEditMenu)
                <div class="igf-menu-settings-grid">
                    <label class="igf-nav-field"><span>{{ $ui('navigation.navigation_label') }}</span><input data-menu-label value="{{ $item['name'] }}" maxlength="120"></label>
                    <label class="igf-nav-field"><span>{{ $ui('navigation.description') }}</span><textarea data-menu-description maxlength="255" placeholder="{{ $ui('navigation.shown_below_links') }}">{{ $item['description'] }}</textarea></label>
                    @if($item['destination_type'] === 'custom')
                        <label class="igf-nav-field"><span>{{ $ui('navigation.custom_url') }}</span><input data-menu-custom-url value="{{ $item['slug'] }}" maxlength="2048"></label>
                    @else
                        <div class="igf-nav-field"><span>{{ $ui('navigation.destination') }}</span><input value="{{ $item['destination'] }}" disabled></div>
                    @endif
                </div>
                <label class="igf-nav-check" style="margin-top:12px"><input data-menu-enabled type="checkbox" @checked($item['status']) @disabled(!$canStatusMenu)> {{ $ui('navigation.show_on_website') }}</label>
                @if(!$canStatusMenu)<p class="igf-nav-help">{{ $ui('navigation.visibility_readonly') }}</p>@endif
                @endif
                <div class="igf-menu-settings-actions">
                    @if($canDeleteMenu)<button class="igf-nav-button igf-nav-button--danger" type="button" data-delete-menu-item data-url="{{ route('page.menu.destroy', $item['uuid']) }}">{{ $ui('builder.move_to_trash') }}</button>@endif
                    @if($canEditMenu)<button class="igf-nav-button igf-nav-button--primary" type="button" data-save-menu-item data-url="{{ route('page.menu.item.update', $item['uuid']) }}">{{ $ui('navigation.save_item') }}</button>@elseif($canStatusMenu)<button class="igf-nav-button igf-nav-button--primary" type="button" data-toggle-menu-status data-current-status="{{ $item['status'] ? '1' : '0' }}" data-url="{{ route('page.menu.status', $item['uuid']) }}">{{ $item['status'] ? $ui('navigation.hide_from_website') : $ui('navigation.show_on_website_short') }}</button>@endif
                </div>
            </section>
            @endif
            @include('admin.page_menu._tree', ['items' => $item['children'], 'parentUuid' => $item['uuid'], 'depth' => $depth + 1, 'canEditMenu' => $canEditMenu, 'canStatusMenu' => $canStatusMenu, 'canDeleteMenu' => $canDeleteMenu])
        </li>
    @endforeach
</ol>
