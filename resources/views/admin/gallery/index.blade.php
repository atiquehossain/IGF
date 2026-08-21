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
                            <strong class="card-title">{{ $Lang->Menu-> Gallery}} {{ $Lang->Common->List }}</strong>
                        </div>

                        <div class="col-md-7">
                            <div class="input-group d-flex justify-content-end">
                                <form action="{{ route('gallery.index') }}" method="get">
                                    <div class="input-group search-input-group">
                                        <input type="search" name="search" value="{{ @$search }}"
                                            class="form-control search-form-control" aria-label="Search gallery items">
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
                    <table class="table" id="gallery_table">
                        <thead>
                            <tr>
                                <th width="20%" class="avatar"><strong>{{ $Lang->Common->Form->Avatar }} </strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Album }} {{ $Lang->Common->Form->Name}}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gallerys as $gallery)
                            <tr id="{{ @$gallery->uuid }}">
                                <td class="avatar">
                                    <div class="round-img">
                                        <img class="rounded" src="{{route('gallery.image', [
                                            @$gallery->id,
                                            '430X360',
                                            @$gallery->path
                                        ]) }}" alt="{{ $gallery->name }} gallery image">
                                    </div>
                                </td>
                                <td> <span class="name">{{@$gallery->name}}</span> </td>
                                <td> <span class="name">{{@$gallery->album_name}}</span> </td>
                                <td>
                                    <?=App\Link::action(@$gallery->uuid, @$gallery->status)?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $gallerys->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
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
        tableId: "gallery_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "gallery_table",
        method: "PUT"
    });

    $(".edit").click(function() {
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');
        window.location.href = "{{ route('gallery.index') }}/" + id + "/edit";;
    });
</script>

@endsection
