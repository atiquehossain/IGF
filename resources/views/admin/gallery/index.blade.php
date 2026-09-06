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
    #gallery_table { min-width: 1120px; }
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
                                <a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route('frontend.gallery') }}" target="_blank" rel="noopener">
                                    <i class="fa fa-external-link" aria-hidden="true"></i> View live gallery
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body border-bottom py-3">
                    <p class="mb-0 text-muted">Photos appear publicly only when both the photo and its album are published. Higher display-priority numbers appear first.</p>
                </div>

                <div class="table-stats ov-h gallery-table-scroll" role="region" aria-label="Scrollable gallery records" tabindex="0">
                    <table class="table" id="gallery_table">
                        <thead>
                            <tr>
                                <th width="12%" class="avatar"><strong>Photo</strong></th>
                                <th width="22%"><strong>Photo caption</strong></th>
                                <th width="17%"><strong>{{ $Lang->Album }}</strong></th>
                                <th width="10%"><strong>Display priority</strong></th>
                                <th width="16%"><strong>Public visibility</strong></th>
                                <th width="23%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gallerys as $gallery)
                            @php
                                $albumAllowsPublication = empty($gallery->album_id) || (int) $gallery->album_status === 1;
                                $isPublic = (int) $gallery->status === 1 && $albumAllowsPublication;
                                $previewAlt = trim(strip_tags((string) $gallery->description)) ?: (string) $gallery->name;
                            @endphp
                            <tr id="{{ @$gallery->uuid }}">
                                <td class="avatar">
                                    <div class="round-img">
                                        <img class="rounded" src="{{ $gallery->display_image_url }}"
                                            onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                            alt="{{ $previewAlt }}">
                                    </div>
                                </td>
                                <td>
                                    <span class="name">{{@$gallery->name}}</span>
                                    @if($isPublic)
                                        <div><a href="{{ route('frontend.gallery', ['search' => $gallery->name]) }}" target="_blank" rel="noopener" aria-label="Preview {{ $gallery->name }} in the live gallery">Preview live item</a></div>
                                    @endif
                                </td>
                                <td><span class="name">{{ @$gallery->album_name ?: (empty($gallery->album_id) ? 'No album (legacy)' : 'Missing or deleted album') }}</span></td>
                                <td>{{ $gallery->order_by === null ? 'Default' : number_format((int) $gallery->order_by) }}</td>
                                <td>
                                    <span
                                        class="badge {{ $isPublic ? 'badge-success' : ((int) $gallery->status === 1 ? 'badge-warning' : 'badge-secondary') }}"
                                        data-publication-status
                                        data-album-published="{{ $albumAllowsPublication ? '1' : '0' }}"
                                        data-published-label="Published"
                                        data-hidden-label="Hidden — album draft"
                                        data-draft-label="Draft"
                                    >{{ $isPublic ? 'Published' : ((int) $gallery->status === 1 ? 'Hidden — album draft' : 'Draft') }}</span>
                                </td>
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
