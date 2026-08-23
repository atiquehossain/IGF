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
                                <strong class="card-title">YouTube {{ $Lang->Common->List }}</strong>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group d-flex justify-content-end">
                                    <form action="{{ route('youtube.index') }}" method="get">
                                        <div class="input-group search-input-group">
                                            <input id="youtube-search" type="search" name="search" value="{{ @$search }}"
                                                class="form-control search-form-control" aria-label="Search YouTube items">
                                            <span class="input-group-prepend">
                                                <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search"
                                                        aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                            </span>
                                        </div>
                                    </form>
                                    <?php if (!empty($addNewLink)) { ?>
                                    <a class="btn igf-btn igf-btn-primary igf-btn-compact ml-1 pull-right" href="{{ route($addNewLink) }}">
                                        <i class="fa fa-plus" aria-hidden="true"></i> Add YouTube item
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-stats ov-h">
                        <table class="table" id="youtube_table">
                            <thead>
                                <tr>
                                <th width="20%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->VideoID }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->ActivisionTime }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->DurationTime }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($youtubes as $youtube)
                                    <tr id="{{ @$youtube->uuid }}">
                                        <td>{{ @$youtube->uuid }} </td>
                                        <td> <span class="">{{@$youtube->name}}</span> </td>
                                        <td> <span class="">{{@$youtube->video_id}}</span> </td>
                                        <td> <span class="">{{@$youtube->activision_time}}</span> </td>
                                        <td> <span class="">{{@$youtube->duration_time}}</span> </td>
                                        <td>
                                            <?= App\Link::action(@$youtube->uuid, @$youtube->status, 'YouTube video ' . ($youtube->name ?? '')) ?>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="pagination justify-content-end">
                            {{ $youtubes->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
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
            tableId: "youtube_table",
            method: "DELETE"
        });
        itemStatus({
            tableId: "youtube_table",
            method: "PUT"
        });

        $(".edit").click(function() {
            var spinner = $('.spinner');
            spinner.show();
            var id = $(this).data('id');
            window.location.href = "{{ route('youtube.index') }}/" + id + "/edit";
        });
    </script>
@endsection
