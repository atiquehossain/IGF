@php
  $selectedDivision = (string) old('division_id', $notice_board->division_id ?? '');
  $selectedDistrict = (string) old('district_id', $notice_board->district_id ?? '');
@endphp

<div class="col-md-12">
  <div class="card border mb-3" data-activity-geography>
    <div class="card-body py-3">
      <h5 class="mb-1">Meet the Heroes activity area (optional)</h5>
      <p class="text-muted mb-3">Connect this published item to a public directory area. This describes where the activity belongs; it is not a person’s home address.</p>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group mb-md-0">
            <label for="division_id">Activity division</label>
            <select id="division_id" name="division_id" class="form-control">
              <option value="">National (shown across Bangladesh)</option>
              @foreach($divisions as $division)
                <option value="{{ $division->id }}" @selected($selectedDivision === (string) $division->id)>{{ $division->name }}</option>
              @endforeach
            </select>
            <small class="form-text text-muted">Leave blank for national content.</small>
            @error('division_id')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group mb-0">
            <label for="district_id">Activity district</label>
            <select id="district_id" name="district_id" class="form-control">
              <option value="">Entire selected division</option>
              @foreach($districts as $district)
                <option value="{{ $district->id }}" data-division-id="{{ $district->division_id }}" @selected($selectedDistrict === (string) $district->id)>{{ $district->name }}</option>
              @endforeach
            </select>
            <small class="form-text text-muted">Choose a division first; leave blank for division-wide content.</small>
            @error('district_id')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
      </div>
      <div id="activity-geography-summary" class="alert alert-light border py-2 mb-0 mt-3" role="status" aria-live="polite"></div>
    </div>
  </div>
</div>
