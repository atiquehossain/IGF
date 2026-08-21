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
                  <form action="{{ route('annual.report.index') }}" method="get">
                    <div class="input-group search-input-group">
                      <input type="search" name="search" value="{{ @$search }}"
                        class="form-control search-form-control" aria-label="Search annual reports">
                      <span class="input-group-prepend">
                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i>
                          {{ $Lang->Common->Search }}</button>
                      </span>
                    </div>
                  </form>
                  <?php if (!empty($addNewLink)) { ?>
                  <a class="btn btn-info btn-sm ml-1 pull-right" href="{{ route($addNewLink) }}">
                    <i class="fa fa-plus-circle"></i> {{ $Lang->Common->Add }} {{ $Lang->Common->New }}
                  </a>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
          <div class="table-stats ov-h">
            <table class="table" id="annual_report_table">
              <thead>
                <tr>
                  <th width="10%" class="serial">{{ $Lang->Common->Form->ID }}</th>
                  <th width="35%"><strong>{{ $Lang->Common->Form->Title }}</strong></th>
                  {{-- <th width="20%"><strong>{{ $Lang->Common->Form->Location }}</strong></th> --}}
                  <th width="10%"><strong>{{ $Lang->Common->Form->ReleaseDate }}</strong></th>
                  <th width="10%"><strong>{{ $Lang->Common->Form->Order }}</strong></th>
                  <th width="15%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($annual_reports as $annual_report)
                  <tr id="{{ @$annual_report->id }}">
                    <td>{{ @$annual_report->id }} </td>
                    <td>{{ @$annual_report->title }}</td>
                    {{-- <td>{{ @$annual_report->location }}</td> --}}
                    <td>{{ date('M d, Y', strtotime(@$annual_report->published_at)) }}</td>
                    <td>{{ @$annual_report->order_by }}</td>
                    <td>
                      <?= App\Link::action(@$annual_report->id, @$annual_report->status) ?>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="pagination justify-content-end">
              {{ $annual_reports->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
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
      tableId: "annual_report_table",
      method: "DELETE"
    });

    itemStatus({
      tableId: "annual_report_table",
      method: "PUT"
    });

    $(".edit").click(function() {
      var spinner = $('.spinner');
      spinner.show();

      var id = $(this).data('id');
      window.location.href = "{{ route('annual.report.index') }}/" + id + "/edit";
    });
  </script>
@endsection
