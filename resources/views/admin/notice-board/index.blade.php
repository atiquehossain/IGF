@extends('admin.layouts.master')

@section('content')
  <div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
      <div class="col-lg-12 col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-md-5">
                <strong class="card-title">{{ $title }} {{ $Lang->Common->List }}</strong>
              </div>
              <div class="col-md-7">
                <div class="input-group d-flex justify-content-end">
                  <form action="{{ route('notice.board.index') }}" method="get">
                    <div class="input-group search-input-group">
                      <label class="sr-only" for="notice-board-search">Search events and news</label>
                      <input id="notice-board-search" type="search" name="search" value="{{ @$search }}"
                        class="form-control search-form-control" aria-label="Search events and news"
                        placeholder="Search events and news">
                      <span class="input-group-prepend">
                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i>
                          {{ $Lang->Common->Search }}</button>
                      </span>
                    </div>
                  </form>
                  <?php if (!empty($addNewLink)) { ?>
                  <a class="btn igf-btn igf-btn-primary igf-btn-compact ml-1 pull-right" href="{{ route($addNewLink) }}">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add event or news
                  </a>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
          <div class="table-stats ov-h">
            <table class="table" id="notice_board_table">
              <thead>
                <tr>
                  <th width="10%" class="serial">{{ $Lang->Common->Form->ID }}</th>
                  <th width="25%"><strong>{{ $Lang->Common->Form->Title }}</strong></th>
                  <th width="13%"><strong>Content format</strong></th>
                  <th width="10%"><strong>Language</strong></th>
                  <th width="12%"><strong>Location / directory scope</strong></th>
                  <th width="12%"><strong>Relevant date</strong></th>
                  <th width="8%"><strong>Display priority</strong></th>
                  <th width="10%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($notice_boards as $notice_board)
                  <tr id="{{ @$notice_board->id }}">
                    <td>{{ @$notice_board->id }} </td>
                    <td><span class="name">{{ @$notice_board->title }}</span></td>
                    <td>
                      @if($notice_board->content_kind === 'event')
                        <span class="badge badge-info">Scheduled event</span>
                        @if($notice_board->event_status && $notice_board->event_status !== 'scheduled')
                          <small class="d-block text-muted mt-1">{{ ucfirst(str_replace('-', ' ', $notice_board->event_status)) }}</small>
                        @endif
                        @if($notice_board->event_attendance_mode)
                          <small class="d-block text-muted">{{ $notice_board->event_attendance_mode === 'mixed' ? 'In person + online' : ucfirst($notice_board->event_attendance_mode) }}</small>
                        @endif
                      @else
                        <span class="badge badge-secondary">News / publication</span>
                      @endif
                    </td>
                    <td>
                      <span class="badge badge-light text-uppercase">{{ $notice_board->language ?: 'en' }}</span>
                      @if($notice_board->translations_count > 1)
                        <small class="d-block text-muted">{{ $notice_board->translations_count }} languages</small>
                      @else
                        <small class="d-block text-muted">Separate event</small>
                      @endif
                    </td>
                    <td>
                      {{ $notice_board->location ?: ($notice_board->content_kind === 'event' && $notice_board->event_attendance_mode === 'online' ? 'Online' : '—') }}
                      @if($notice_board->district && $notice_board->division)
                        <small class="d-block text-muted mt-1">Directory: {{ $notice_board->district->name }} District, {{ $notice_board->division->name }}</small>
                      @elseif($notice_board->division)
                        <small class="d-block text-muted mt-1">Directory: {{ $notice_board->division->name }} Division</small>
                      @else
                        <small class="d-block text-muted mt-1">Directory: National</small>
                      @endif
                    </td>
                    <td>
                      @php
                        $relevantDate = $notice_board->content_kind === 'event' ? $notice_board->event_start_at : $notice_board->published_at;
                        $relevantDate = $relevantDate ? \Illuminate\Support\Carbon::parse($relevantDate) : null;
                      @endphp
                      @if($relevantDate)
                        <time datetime="{{ $relevantDate->toIso8601String() }}">{{ $relevantDate->format('M d, Y') }}</time>
                        <small class="d-block text-muted">{{ $notice_board->content_kind === 'event' ? 'Event starts' : 'Published' }}</small>
                      @else
                        <span aria-label="No date set">—</span>
                      @endif
                    </td>
                    <td>{{ @$notice_board->order_by }}</td>
                    <td>
                      <?= App\Link::action(@$notice_board->id, @$notice_board->status, 'notice ' . ($notice_board->title ?? '')) ?>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="pagination justify-content-end">
              {{ $notice_boards->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('custom-js')
  <script>
    itemDelete({
      tableId: "notice_board_table",
      method: "DELETE"
    });

    itemStatus({
      tableId: "notice_board_table",
      method: "PUT"
    });

    $(".edit").click(function() {
      var spinner = $('.spinner');
      spinner.show();

      var id = $(this).data('id');
      window.location.href = "{{ route('notice.board.index') }}/" + id + "/edit";
    });
  </script>
@endsection
