@extends('admin.layouts.master')

@section('content')
<style>
    .gallery-toolbar { gap: 8px; }
    .gallery-search { flex: 1 1 280px; min-width: 0; }
    .gallery-search .input-group { width: 100%; flex-wrap: nowrap; }
    .gallery-search .form-control { min-width: 0; }
    .gallery-column { flex: 0 0 100%; min-width: 0; max-width: 100%; }
    .gallery-card { width: 100%; min-width: 0; max-width: 100%; overflow: hidden; }
    .gallery-table-scroll { display: block; width: 100%; min-width: 0; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
    #gallery_table { min-width: 900px; }
    @media (max-width: 575.98px) {
        html, body { overflow-x: hidden; }
        .gallery-content { float: none; width: 100vw !important; max-width: 100vw; overflow-x: hidden; }
        .gallery-content > .row { max-width: 100%; }
        .gallery-toolbar,
        .gallery-search,
        .gallery-toolbar > .igf-btn-primary { width: 100%; }
    }
</style>
<div class="content pb-0 gallery-content">
    <h1 class="sr-only">{{ $title }}</h1>
    <div class="row">
        <div class="col-lg-12 col-md-12 gallery-column">
            <div class="card gallery-card">

            <div class="card-header">
                    <div class="row">
                        <div class="col-md-5">
                            <strong class="card-title">{{ $Lang->Menu-> Gallery}} {{ $Lang->Common->List }}</strong>
                        </div>

                        <div class="col-md-7">
                            <div class="gallery-toolbar d-flex flex-wrap align-items-end justify-content-end">
                                <form class="gallery-search" action="{{ route('gallery.index') }}" method="get" role="search">
                                    <div class="input-group">
                                        <input type="search" name="search" value="{{ @$search }}"
                                            class="form-control" style="min-height: 44px;" aria-label="Search gallery items">
                                        <span class="input-group-append">
                                            <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search"
                                                    aria-hidden="true"></i> Search</button>
                                        </span>
                                    </div>
                                </form>
                                <?php if (!empty($addNewLink)) {?>
                                <a class="btn igf-btn igf-btn-primary igf-btn-compact" href="{{ route($addNewLink) }}">
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Add gallery item
                                </a>
                                <?php }?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-stats ov-h gallery-table-scroll" role="region" aria-label="Scrollable gallery records" tabindex="0">
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
                                        <img class="rounded" src="{{ $gallery->display_image_url }}"
                                            onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                            alt="{{ $gallery->name }} gallery image">
                                    </div>
                                </td>
                                <td> <span class="name">{{@$gallery->name}}</span> </td>
                                <td> <span class="name">{{@$gallery->album_name}}</span> </td>
                                <td>
                                    <?=App\Link::action(@$gallery->uuid, @$gallery->status, 'gallery item ' . ($gallery->name ?? ''))?>
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
