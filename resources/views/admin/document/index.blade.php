@extends('admin.layouts.master')
<?php

use App\Helper\StaticUtil;
?>

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>
    <div class="row">
        <div class="col-lg-5 col-md-12">
            <div id="new_banner">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->Document }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form class="fileUploadForm" action="{{ route('document.store') }}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}
                                    <div class="form-group has-success">
                                        <label for="image_path" class="control-label mb-1">
                                            {{ $Lang->DisplayImage }}
                                        </label>
                                        <div class="form-group text-center">
                                            <div class="file-upload">
                                                <label for="image_path" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_image" src="{{ asset('/') }}image/no-image.png" alt="Document display image preview">
                                                </label>
                                                <input type="file" onchange="changefile(event, 'upload_image')" name="image_path" value="{{ old('image_path') }}" id="image_path" class="file-upload_input">
                                            </div>
                                            <div style="clear: both"></div>
                                            @if ($errors->has('image_path'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('image_path') }}</small>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="file" class="control-label mb-1">
                                            File (pdf, xls, xlsx, csv, docs, doc)
                                            <span>*</span>
                                        </label>

                                        <div class="form-group text-center">
                                            <div class="file-upload">
                                                <label for="file" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_file" src="{{ asset('/') }}image/no-image.png" alt="Document file preview">
                                                </label>
                                                <input type="file" onchange="changefile(event, 'upload_file')" name="file" value="{{ old('file') }}" id="file" class="file-upload_input">
                                            </div>
                                            <div style="clear: both"></div>
                                            @if ($errors->has('file'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('file') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group has-success">
                                        <label for="url" class="control-label mb-1">Url <span>*</span></label>
                                        <input id="url" name="url" type="text" value="{{old('url')}}" class="form-control">
                                        @if($errors->has('url'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="title" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                                                <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-control" required>
                                                @if ($errors->has('title'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('title') }}</small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="release_at" class="control-label mb-1">{{ $Lang->DateOfRelease}} <span>*</span></label>
                                                <input id="release_at" name="release_at" type="text" value="{{ old('release_at') }}" class="form-control datepicker" readonly required>
                                                @if ($errors->has('release_at'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('release_at') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="upload_progress">
                                            <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">100%</div>
                                        </div>
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i> Create document</button>
                                        <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-lg-7 col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->Document }}s {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('document.index') }}" method="get">
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="document-search">Search documents</label>
                                    <input id="document-search" type="search" name="search" value="{{ @$search }}" class="form-control search-form-control" aria-label="Search documents">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="banner_table">
                        <thead>
                            <tr>
                                <th width="20%" class="avatar"><strong>{{ $Lang->Common->Form->Avatar }} </strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="10%"><strong>{{ $Lang->Common->Form->Type }}</strong></th>
                                <th width="10%"><strong>{{ $Lang->Common->Form->Size }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->ReleaseDate }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                            <tr id="{{ @$document->id }}">
                                <td class="avatar">
                                    <div class="round-img">
                                        <?php
                                        $notice_board_path = asset('/image/no-image.png');
                                        $fileExtension = ['csv', 'xls', 'pdf', 'xlsx', 'docx', 'doc'];
                                        if (array_search($document->file_type, $fileExtension) > -1) {
                                            $notice_board_path = asset('/image/' . $document->file_type . '.png');
                                        } else {
                                            $notice_board_path = route('document.image', [@$document->path]);
                                        }

                                        if (@$document->image_path) {
                                            $notice_board_path = route('document.image', [@$document->image_path]);
                                        }

                                        ?>
                                        <img class="rounded" src="{{ $notice_board_path }}" alt="Preview for {{ $document->title }}">
                                    </div>
                                </td>
                                <td>{{ @$document->title }} </td>
                                <td>{{ @$document->file_type }} </td>
                                <td>{{ StaticUtil::formatBytes($document->file_size , 2) }}</td>
                                <td>{{ @$document->date_at }} </td>
                                <td>
                                    <?= App\Link::action(@$document->id, @$document->status, 'document ' . ($document->title ?? '')) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                    {{ $documents->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="bannerModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="documentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form class="fileUploadFormEdit" action="{{ route('document.update') }}" method="POST" enctype="multipart/form-data">
                {{ csrf_field() }}
                @method('PUT')
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="documentModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->Document }}</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <input name="id" id="e_id" type="hidden" value="{{ old('id') }}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="e_image_path" class="control-label mb-1">
                            {{ $Lang->DisplayImage }}
                        </label>
                        <div class="form-group text-center">
                            <div class="file-upload">
                                <label for="e_image_path" class="file-upload_label">
                                    <img class="file-upload_img" id="e_upload_image" src="{{ asset('/') }}image/no-image.png" alt="Document display image preview">
                                </label>
                                <input type="file" onchange="changefile(event, 'e_upload_image')" name="image_path" value="{{ old('image_path') }}" id="e_image_path" class="file-upload_input">
                            </div>
                            <div style="clear: both"></div>
                            @if ($errors->has('image_path'))
                            <small class="help-block form-text text-danger">{{ $errors->first('image_path') }}</small>
                            @endif
                        </div>
                    </div>

                    <div class="form-group has-success">
                        <label for="e_file" class="control-label mb-1">
                            File (pdf, xls, xlsx, csv, docs, doc)
                            <span>*</span>
                        </label>

                        <div class="form-group text-center">
                            <div class="file-upload">
                                <label for="e_file" class="file-upload_label">
                                    <img class="file-upload_img" id="e_upload_file" src="{{ asset('/') }}image/no-image.png" alt="Document file preview">
                                </label>
                                <input type="file" onchange="changefile(event, 'e_upload_file')" name="file" value="{{ old('file') }}" id="e_file" class="file-upload_input">
                            </div>
                            <div style="clear: both"></div>
                            @if ($errors->has('file'))
                            <small class="help-block form-text text-danger">{{ $errors->first('file') }}</small>
                            @endif
                        </div>
                    </div>
                    <div class="form-group has-success">
                        <label for="e_url" class="control-label mb-1">URL <span>*</span></label>
                        <input id="e_url" name="url" type="text" value="{{old('url')}}" class="form-control">
                        @if($errors->has('url'))
                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                        @endif
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="e_title" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                                <input id="e_title" name="title" type="text" value="{{ old('title') }}" class="form-control" required>
                                @if ($errors->has('title'))
                                <small class="help-block form-text text-danger">{{ $errors->first('title') }}</small>
                                @endif
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="e_release_at" class="control-label mb-1">{{ $Lang->DateOfRelease}} <span>*</span></label>
                                <input id="e_release_at" name="release_at" type="text" value="{{ old('release_at') }}" class="form-control datepicker" readonly required>
                                @if ($errors->has('release_at'))
                                <small class="help-block form-text text-danger">{{ $errors->first('release_at') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="upload_progress">
                            <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">100%</div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save document</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
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
<script src="{{ asset('admin-assets/assets/js/jquery.form.min.js') }}"></script>
<script>
    itemDelete({
        tableId: "banner_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "banner_table",
        method: "PUT"
    });

    $(".cancel").click(function() {
        clear();
    });

    var is_edit = "{{ old('id') }}";
    if (is_edit) {
        $('#new_banner .form-group .help-block').hide();
        $("#new_banner input").val("");
        $('#bannerModal').modal('show');
    }

    function clear() {
        $("input").val("");
    }
    $(".edit").click(function() {
        $('#bannerModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('document.index') }}/" + id + "/edit",
            success: function(res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $(".modal input[name=title]").val(res.data.title);
                    $('.modal #e_upload_image').attr('src', res.data.image_path);
                    $(".modal textarea[name=description]").val(res.data.description);
                    $(".modal input[name=url]").val(res.data.url);
                    $(".modal input[name=release_at]").val(res.data.date_at);

                    let file_type = res.data?.file_path.split('.').pop();

                    let fileExtension = ["csv", "xls", "pdf", 'xlsx', 'docx', 'doc'];
                    if (fileExtension.indexOf(file_type) > -1) {
                        $('.modal #e_upload_file').attr('src', `/image/${file_type}.png`);
                    }
                }
                spinner.hide();
            },
            error: function(err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

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
            window.location.href = "{{ route('document.index') }}";
        }
    });
</script>
@endsection
