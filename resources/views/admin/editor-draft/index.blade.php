@extends('admin.layouts.master')

@section('content')
@php
    $canCreate = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'editorDraft.store');
    $canEdit = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'editorDraft.update');
@endphp
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
        @if($canCreate)
        <div class="col-lg-5 col-md-12">
            <div id="new_editorDraft">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->EditorDraftTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{route('editorDraft.store')}}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input id="name" name="name" type="text" value="{{old('name')}}" class="form-control" required data-e2e="edito-draft-name">
                                        @if($errors->has('name'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="description">{{ $Lang->Common->Form->Description }}</label>
                                        <textarea id="new-editor-draft-description" class="form-control form-control-danger" name="description" rows="4" aria-label="{{ $Lang->Common->Form->Description }}" data-e2e="edito-draft-description">{{old('description')}}</textarea>
                                        @if ($errors->has('description'))
                                            <small class="help-block form-text text-danger">{{ $errors->first('description') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn btn-info submit_ mt-3" name="save"><i class="fa fa-lock fa-lg"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                                        <button type="button" class="btn btn-danger cancel mt-3"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif
        <div class="{{ $canCreate ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->EditorDraftTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('editorDraft.index')}}" method="get">
                                <div class="input-group search-input-group">
                                    <input type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search editor drafts">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="editorDraft_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Slug }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($editorDrafts as $editorDraft)
                            <tr id="{{ @$editorDraft->id }}">
                                <td> #{{@$editorDraft->id}} </td>
                                <td>
                                    <span class="name">{{@$editorDraft->name}}</span>
                                 </td>
                                 <td>
                                    <span class="">{{@$editorDraft->slug}}</span>
                                 </td>
                                <td>
                                    <?=App\Link::action(@$editorDraft->id, @$editorDraft->status) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $editorDrafts->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEdit)
<div class="modal fade" id="editorDraftModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="mediumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{route('editorDraft.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <strong class="card-title">{{ $Lang->Common->Edit }} {{ $Lang->EditorDraftTitle }}</strong>
                    <button type="button" class="close cancel" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')
                    <input name="id" id="e_id" type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                        <input name="name" type="text" value="{{old('name')}}" class="form-control" aria-label="{{ $Lang->Common->Form->Name }}" required data-e2e="edito-draft-name-edit">
                        @if($errors->has('name'))
                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="description">{{ $Lang->Common->Form->Description }}</label>
                        <textarea class="form-control form-control-danger" name="description" rows="4" aria-label="{{ $Lang->Common->Form->Description }}" data-e2e="edito-draft-description-edit">{{old('description')}}</textarea>
                        @if ($errors->has('description'))
                            <small class="help-block form-text text-danger">{{ $errors->first('description') }}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info submit_ mt-3" name="update"><i class="fa fa-magic"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                    <button type="button" class="btn btn-danger cancel mt-3" data-dismiss="modal"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif



@endsection

@section('custom-js')
<script>
    itemDelete({tableId: "editorDraft_table",method: "DELETE"});
    itemStatus({tableId: "editorDraft_table",method: "PUT"});

    $(".cancel").click(function () {
        clear($(this).closest("form"));
    });

    var is_edit = "{{old('id')}}";
    if (is_edit) {
        $('#new_editorDraft .form-group .help-block').hide();
        $("#new_editorDraft input:not([type=hidden])").val("");
        $('#editorDraftModal').modal('show');
    }

    function clear($form) {
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }
    $(".edit").click(function () {
        $('#editorDraftModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('editorDraft.index')}}/" + id + "/edit",
            success: function (res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $(".modal input[name=name]").val(res.data.name);
                    $(".modal textarea[name=description]").val(res.data.description);
                }
                spinner.hide();
            },
            error: function (err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
</script>

@endsection
