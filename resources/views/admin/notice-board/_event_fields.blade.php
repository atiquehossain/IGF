@php
  $record = $notice_board ?? null;
  $kind = old('content_kind', $record?->content_kind ?: 'article');
  $start = old('event_start_at', $record?->event_start_at?->format('Y-m-d\TH:i'));
  $end = old('event_end_at', $record?->event_end_at?->format('Y-m-d\TH:i'));
  $status = old('event_status', $record?->event_status ?: 'scheduled');
  $attendance = old('event_attendance_mode', $record?->event_attendance_mode ?: 'offline');
@endphp

<div class="col-md-12">
  <div class="card border mb-3">
    <div class="card-body py-3">
      <h5 class="mb-1">Search-event details</h5>
      <p class="text-muted mb-3">Choose “Scheduled event” only when this record has a real event date. These verified details are shown to visitors and search engines. A publication date is never reused as an event date.</p>
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label for="content_kind">Content format</label>
            <select class="form-control" id="content_kind" name="content_kind">
              <option value="article" @selected($kind === 'article')>News or publication</option>
              <option value="event" @selected($kind === 'event')>Scheduled event</option>
            </select>
            @error('content_kind')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="event_start_at">Event starts</label>
            <input class="form-control" id="event_start_at" name="event_start_at" type="datetime-local" value="{{ $start }}">
            @error('event_start_at')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="event_end_at">Event ends (optional)</label>
            <input class="form-control" id="event_end_at" name="event_end_at" type="datetime-local" value="{{ $end }}">
            @error('event_end_at')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group mb-md-0">
            <label for="event_status">Event status</label>
            <select class="form-control" id="event_status" name="event_status">
              <option value="scheduled" @selected($status === 'scheduled')>Scheduled</option>
              <option value="postponed" @selected($status === 'postponed')>Postponed</option>
              <option value="rescheduled" @selected($status === 'rescheduled')>Rescheduled</option>
              <option value="moved-online" @selected($status === 'moved-online')>Moved online</option>
              <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            </select>
            @error('event_status')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group mb-0">
            <label for="event_attendance_mode">Attendance</label>
            <select class="form-control" id="event_attendance_mode" name="event_attendance_mode">
              <option value="offline" @selected($attendance === 'offline')>At a physical location</option>
              <option value="online" @selected($attendance === 'online')>Online</option>
              <option value="mixed" @selected($attendance === 'mixed')>Physical and online</option>
            </select>
            <small class="form-text text-muted">A physical or mixed event also requires the location field below.</small>
            @error('event_attendance_mode')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
