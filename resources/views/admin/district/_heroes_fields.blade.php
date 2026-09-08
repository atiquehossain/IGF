@php
    $fieldPrefix = $fieldPrefix ?? '';
    $editing = $editing ?? false;
    $selectedMediaUuid = (string) old('hero_image_media_uuid', '');
    $selectedMedia = $mediaAssets->firstWhere('uuid', $selectedMediaUuid);
    $previewUrl = $selectedMedia?->url ?? '';
    $canOpenMediaLibrary = app(\App\Http\Middleware\Permission::class)
        ->allows(auth('admin')->user(), 'media.index');
@endphp

<fieldset class="border rounded p-3 mt-3">
    <legend class="h6 px-2 mb-2">Meet the Heroes district page</legend>
    <p class="small text-muted">These optional fields help non-technical editors introduce the district and show a representative group image. Use organizational images only—do not invent personal details.</p>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}description" class="control-label mb-1">District introduction (English)</label>
        <textarea id="{{ $fieldPrefix }}description" name="description" class="form-control" rows="5" maxlength="5000">{{ old('description') }}</textarea>
        <small class="form-text text-muted">Plain-language overview shown on the English district page.</small>
        @error('description')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}description_bn" class="control-label mb-1">District introduction (Bangla)</label>
        <textarea id="{{ $fieldPrefix }}description_bn" name="description_bn" class="form-control" rows="5" maxlength="5000" lang="bn">{{ old('description_bn') }}</textarea>
        <small class="form-text text-muted">English is used as a fallback when this is blank.</small>
        @error('description_bn')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}hero_image_media_uuid" class="control-label mb-1">Choose a group image from Media Library</label>
        <select id="{{ $fieldPrefix }}hero_image_media_uuid" name="hero_image_media_uuid" class="form-control" data-district-media-select>
            <option value="">Keep the current image / no library image</option>
            @foreach($mediaAssets as $asset)
                <option value="{{ $asset->uuid }}" data-image-url="{{ $asset->url }}" @selected($selectedMediaUuid === $asset->uuid)>{{ $asset->original_name }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Select an image already approved in Media Library, or upload a new JPG, PNG, or WebP below.</small>
        @if($canOpenMediaLibrary)
            <a class="d-inline-block mt-1" href="{{ route('media.index', ['type' => 'image']) }}">Open Media Library in another page</a>
        @endif
        @error('hero_image_media_uuid')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}hero_image_upload" class="control-label mb-1">Upload a new group image</label>
        <input id="{{ $fieldPrefix }}hero_image_upload" name="hero_image_upload" type="file" class="form-control-file" accept="image/jpeg,image/png,image/webp" data-district-image-upload data-preview-id="{{ $fieldPrefix }}hero_image_preview">
        <small class="form-text text-muted">Optional. Maximum 3 MB. The website creates an optimized copy; the original file is not exposed.</small>
        @error('hero_image_upload')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}hero_image_alt" class="control-label mb-1">Image description (English)</label>
        <input id="{{ $fieldPrefix }}hero_image_alt" name="hero_image_alt" type="text" value="{{ old('hero_image_alt') }}" class="form-control" maxlength="255" aria-describedby="{{ $fieldPrefix }}hero-image-alt-help">
        <small id="{{ $fieldPrefix }}hero-image-alt-help" class="form-text text-muted">Required when choosing a new image. Briefly describe who or what is visible; do not start with “Image of”.</small>
        @error('hero_image_alt')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}hero_image_alt_bn" class="control-label mb-1">Image description (Bangla)</label>
        <input id="{{ $fieldPrefix }}hero_image_alt_bn" name="hero_image_alt_bn" type="text" value="{{ old('hero_image_alt_bn') }}" class="form-control" maxlength="255" lang="bn" aria-describedby="{{ $fieldPrefix }}hero-image-alt-bn-help">
        <small id="{{ $fieldPrefix }}hero-image-alt-bn-help" class="form-text text-muted">Optional. Bangla visitors see this description; English is used as a fallback when it is blank.</small>
        @error('hero_image_alt_bn')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    </div>

    @if($editing)
        <div class="form-check mb-3">
            <input id="{{ $fieldPrefix }}remove_hero_image" name="remove_hero_image" type="checkbox" value="1" class="form-check-input" @checked(old('remove_hero_image'))>
            <label for="{{ $fieldPrefix }}remove_hero_image" class="form-check-label">Remove the current group image</label>
        </div>
    @endif

    <img id="{{ $fieldPrefix }}hero_image_preview" data-district-image-preview src="{{ $previewUrl }}" alt="Selected district group image preview" style="{{ $previewUrl ? '' : 'display:none;' }}max-width:100%;max-height:220px;object-fit:cover;border-radius:8px;border:1px solid #ddd">
</fieldset>
