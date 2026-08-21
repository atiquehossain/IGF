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
                                <strong class="card-title">{{ $Lang->Album }} {{ $Lang->Common->List }}</strong>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group d-flex justify-content-end">
                                    <form action="{{ route('album.index') }}" method="get">
                                        <div class="input-group search-input-group">
                                            <input type="search" name="search" value="{{ @$search }}"
                                                class="form-control search-form-control" aria-label="Search albums">
                                            <span class="input-group-prepend">
                                                <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"
                                                        aria-hidden="true"></i>  {{ $Lang->Common->Search }}</button>
                                            </span>
                                        </div>
                                    </form>
                                    <?php if (!empty($addNewLink)) {?>
                                    <a class="btn btn-info btn-sm ml-1 pull-right" href="{{ route($addNewLink) }}">
                                        <i class="fa fa-plus-circle"></i> {{ $Lang->Common->Add }} {{ $Lang->Common->New }}
                                    </a>
                                    <?php }?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-stats ov-h">
                        <table class="table" id="album_table">
                            <thead>
                                <tr>
                                    <th width="85%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                    <th width="15%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($albums as $album)
                                    <tr id="{{ @$album->uuid }}">
                                        <td> <span class="name">{{ @$album->name }}</span> </td>
                                        <td>
                                            <?=App\Link::action(@$album->uuid, @$album->status)?>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="pagination justify-content-end">
                            {{ $albums->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
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
            tableId: "album_table",
            method: "DELETE"
        });
        itemStatus({
            tableId: "album_table",
            method: "PUT"
        });

        $(".edit").click(function() {
            var spinner = $('.spinner');
            spinner.show();
            var id = $(this).data('id');
            window.location.href = "{{ route('album.index') }}/" + id + "/edit";
        });
    </script>
@endsection
