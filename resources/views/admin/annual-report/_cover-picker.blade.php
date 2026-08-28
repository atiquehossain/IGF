@php
  $selectedCoverPath = old('cover_image_path', $selectedCoverPath ?? '');
  $selectedCoverAsset = $mediaAssets->firstWhere('path', $selectedCoverPath);
@endphp

<div class="col-md-6">
  <div class="form-group">
    <label for="cover_image_path" class="control-label mb-1">Cover image</label>
    <select id="cover_image_path" name="cover_image_path" class="form-control" data-report-cover-select>
      <option value="">No cover image (use the website fallback)</option>
      @foreach($mediaAssets as $asset)
        <option value="{{ $asset->path }}" data-image-url="{{ $asset->url }}" @selected($selectedCoverPath === $asset->path)>
          {{ $asset->original_name }}{{ $asset->trashed() ? ' (in Media Library trash)' : '' }}
        </option>
      @endforeach
    </select>
    <small class="form-text text-muted">Choose an uploaded image from the Media Library. This never replaces or exposes the private PDF.</small>
    @error('cover_image_path')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
    <div class="mt-2">
      <img data-report-cover-preview
        @if($selectedCoverAsset) src="{{ $selectedCoverAsset->url }}" @endif
        alt="Selected annual-report cover preview"
        style="{{ $selectedCoverAsset ? '' : 'display:none;' }}max-width:260px;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #ddd">
    </div>
  </div>
</div>
