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
                            <strong class="card-title">{{ $Lang->BannerTitle }} {{ $Lang->Common->List }}</strong>
                        </div>

                        <div class="col-md-7">
                            <div class="input-group d-flex justify-content-end">
                                <form action="{{ route('banner.index') }}" method="get">
                                    <div class="input-group search-input-group">
                                        <input type="search" name="search" value="{{ @$search }}"
                                            class="form-control search-form-control" aria-label="Search banners">
                                        <span class="input-group-prepend">
                                            <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"
                                                    aria-hidden="true"></i>  {{ $Lang->Common->Search }}</button>
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
                <div class="table-stats table-responsive" tabindex="0" aria-label="Banner records">
                    <table class="table" id="banner_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="20%" class="avatar"><strong>{{ $Lang->Common->Form->Avatar }} </strong></th>
                                <th width="30%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Type }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($banners as $banner)
                            <tr id="{{ @$banner->uuid }}">
                                <td> #{{@$banner->id}} </td>
                                <td class="avatar">
                                    <div class="round-img">
                                        <img class="rounded" src="{{route('banner.image', [
                                            @$banner->path
                                        ]) }}" alt="{{ $banner->name }} banner">
                                    </div>
                                </td>

                                <td> <span class="name">{{@$banner->name}}</span> </td>
                                <td> <span class="name">{{@$banner->type}}</span> </td>
                                <td>
                                    <?= App\Link::action(@$banner->uuid, @$banner->status, 'banner ' . ($banner->name ?? '')) ?>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center py-4">{{ $search ? 'No banners match this search. Clear the search and try again.' : 'No banners have been added yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $banners->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .file-upload_label {
        width: 192px !important;
    }
</style>

@endsection

@section('custom-js')

<script src="{{ asset('admin-assets/assets/js/jquery.form.min.js')}}"></script>
<script>
    itemDelete({
        tableId: "banner_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "banner_table",
        method: "PUT"
    });

    $(".edit").click(function() {
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');
        window.location.href = "{{ route('banner.index') }}/" + id + "/edit";;
    });

    $('.fileUploadForm_').ajaxForm({
        beforeSend: function() {
            var percentage = '0';
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentage = percentComplete;
            $('.upload_progress .progress-bar').html(percentage + '%');
            $('.upload_progress .progress-bar').css("width", percentage + '%', function() {
                return $(this).attr("aria-valuenow", percentage) + "%";
            })
        },
        error: function(err) {
            console.log(err.responseJSON.errors.name[0]);
            $('.spinner').hide();
        },
        complete: function(xhr) {
            // console.log('File has uploaded');
            $('.spinner').hide();
        },
        success: function(data) {
            $('.spinner').hide();
            window.location.href = "{{ route('banner.index')}}";
        }
    });
</script>

@endsection
