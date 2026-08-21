@php
    $fieldPrefix = $prefix ?? 'new_';
    $selectedType = old('destination_type', 'restricted_fund');
    $selectedCategory = old('destination_category_uuid', '');
    $selectedPage = old('destination_page_uuid', '');
    $selectedMedia = old('image_media_uuid', '');
@endphp

<fieldset class="border rounded p-3 mt-3" aria-labelledby="{{ $fieldPrefix }}funding_destination_legend">
    <legend id="{{ $fieldPrefix }}funding_destination_legend" class="h6 px-2 mb-2">Where should gifts to this cause be used?</legend>
    <p class="small text-muted">Choose a guided destination. Visitors never enter IDs, and published causes are checked against active website content.</p>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}destination_type" class="control-label mb-1">Funding destination <span>*</span></label>
        <select id="{{ $fieldPrefix }}destination_type" name="destination_type" class="form-control" required data-destination-type="{{ $fieldPrefix }}">
            @foreach($destinationOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('destination_type')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group" data-destination-owner="{{ $fieldPrefix }}" data-destination-panel="restricted_fund">
        <label for="{{ $fieldPrefix }}destination_name" class="control-label mb-1">Restricted fund name <span>*</span></label>
        <input id="{{ $fieldPrefix }}destination_name" name="destination_name" type="text" value="{{ old('destination_name') }}" class="form-control" maxlength="255" placeholder="For example: Education Fund">
        <small class="form-text text-muted">Use the approved accounting designation donors and staff will recognize.</small>
        @error('destination_name')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group" data-destination-owner="{{ $fieldPrefix }}" data-destination-panel="category">
        <label for="{{ $fieldPrefix }}destination_category_uuid" class="control-label mb-1">Program or category <span>*</span></label>
        <select id="{{ $fieldPrefix }}destination_category_uuid" name="destination_category_uuid" class="form-control">
            <option value="">Choose an active program</option>
            @foreach($categories as $category)
                <option value="{{ $category->uuid }}" @selected($selectedCategory === $category->uuid) @disabled($category->destination_unavailable ?? false)>
                    {{ $category->name }}{{ ($category->destination_unavailable ?? false) ? ' — unavailable, choose another' : '' }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Only active program groupings with at least one page marked fundable appear here. Donors may optionally choose one of those published programs or projects.</small>
        @error('destination_category_uuid')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group" data-destination-owner="{{ $fieldPrefix }}" data-destination-panel="page">
        <label for="{{ $fieldPrefix }}destination_page_uuid" class="control-label mb-1">Specific fundable program or project <span>*</span></label>
        <select id="{{ $fieldPrefix }}destination_page_uuid" name="destination_page_uuid" class="form-control">
            <option value="">Choose a published fundable program or project</option>
            @foreach($pages as $page)
                <option value="{{ $page->uuid }}" @selected($selectedPage === $page->uuid) @disabled($page->destination_unavailable ?? false)>
                    {{ $page->name }} — {{ $page->category_label }}{{ $page->is_zakat_eligible ? ' · Zakat eligible' : '' }}{{ ($page->destination_unavailable ?? false) ? ' — unavailable, choose another' : '' }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">The full gift is attributed to this exact managed program/project. General website pages are excluded. Zakat causes also require Zakat eligibility.</small>
        @error('destination_page_uuid')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>
</fieldset>

<div class="form-group mt-3">
    <label for="{{ $fieldPrefix }}image_media_uuid" class="control-label mb-1">Cause card image</label>
    <select id="{{ $fieldPrefix }}image_media_uuid" name="image_media_uuid" class="form-control" data-cause-image-select="{{ $fieldPrefix }}">
        <option value="">No image</option>
        @foreach($mediaAssets as $asset)
            <option value="{{ $asset->uuid }}" data-image-url="{{ $asset->url }}" @selected($selectedMedia === $asset->uuid)>{{ $asset->original_name }}</option>
        @endforeach
    </select>
    <div class="mt-2"><img id="{{ $fieldPrefix }}image_preview" src="" alt="Selected cause-card image preview" style="display:none;max-width:180px;max-height:110px;object-fit:cover;border-radius:6px"></div>
    <small class="form-text text-muted">Only images already managed in the Media Library can be selected.</small>
    @error('image_media_uuid')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
</div>
