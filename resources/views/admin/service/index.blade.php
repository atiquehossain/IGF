@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-5">
                            <strong class="card-title">{{ $Lang->Services }} {{ $Lang->Common->List }}</strong>
                        </div>

                        <div class="col-md-7">
                            <div class="input-group d-flex justify-content-end">
                                <form action="{{ route('project.index') }}" method="get">
                                    <div class="input-group search-input-group">
                                        <input type="search" name="search" value="{{ @$search }}"
                                            class="form-control search-form-control">
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
                <div class="table-stats ov-h">
                    <table class="table" id="service_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="20%" class="avatar"><strong>{{ $Lang->Common->Form->Avatar }} </strong></th>
                                <th width="30%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                            <tr id="{{ @$service->uuid }}">
                                <td> #{{@$service->id}} </td>
                                <td class="avatar">
                                    <div class="round-img">
                                        <a href="javascript:void(0)"><img class="rounded" src="{{route('service.image', [
                                            @$service->path
                                        ]) }}" alt=""></a>
                                    </div>
                                </td>

                                <td> <span class="name">{{@$service->name}}</span> </td>
                                <td>
                                    <?= App\Link::action(@$service->uuid, @$service->status) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $services->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
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
        tableId: "service_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "service_table",
        method: "PUT"
    });

    $(".edit").click(function() {
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');
        window.location.href = "{{ route('service.index') }}/" + id + "/edit";;
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
            window.location.href = "{{ route('service.index')}}";
        }
    });
</script>

@endsection
