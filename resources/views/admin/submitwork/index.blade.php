@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <strong class="card-title">{{ $Lang->Common->SubmitWork }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-4">
                            <form action="{{ route('submitwork.index')}}" method="get">
                                <div class="input-group search-input-group_">
                                    <label class="sr-only" for="submission-status-filter">Filter submissions by status</label>
                                    <select id="submission-status-filter" name="search" class="form-control" required>
                                        <option value="Submission" <?= @$search == 'Submission' ? 'selected' : '' ?>>{{ $Lang->Resource->Submission }}</option>
                                        <option value="Accept" <?= @$search == 'Accept' ? 'selected' : '' ?>>{{ $Lang->Resource->Accept }}</option>
                                        <option value="Not Accepted" <?= @$search == 'Not Accepted' ? 'selected' : '' ?>>{{ $Lang->Common->Form->NotAccepted }}</option>
                                    </select>
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-filter" aria-hidden="true"></i> Apply status filter</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats order-table ov-h">
                    <table class="table" id="submitwork_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }} </strong></th>
                                <th width="15%"><strong>{{ $Lang->Resource->Image }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Resource->Status }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submitworks as $submitwork)
                            <tr id="{{ $submitwork->id }}">
                                <td> #{{$submitwork->id}} </td>
                                <td> <span class="">{{@$submitwork->user->name}}</span> </td>
                                <td> <span class="">{{@$submitwork->user->phone_no}}</span> </td>
                                <td>
                                    <a href="{{@$submitwork->post_url}}" target="_blank" rel="noopener" aria-label="Open submission from {{ $submitwork->user->name ?? 'user' }} in a new tab">
                                        <img class="rounded" src="{{route('submitwork.image',@$submitwork->asset)}}" alt="Submission preview from {{ $submitwork->user->name ?? 'user' }}">
                                    </a>
                                </td>
                                <td>
                                    <?php if ($submitwork->status == 'Submission') { ?>
                                        <button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact" data-id="{{ $submitwork->id }}" aria-label="Review submission from {{ $submitwork->user->name ?? 'user' }}"><i class="fa fa-eye" aria-hidden="true"></i> Review</button>
                                    <?php } else if ($submitwork->status == 'Accept') { ?>
                                        <span class="badge badge-complete">{{@$submitwork->status}}</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">{{@$submitwork->status}}</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?= App\Link::action(@$submitwork->id, true, 'submission from ' . ($submitwork->user->name ?? 'user')) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>
                    <div class="pagination justify-content-end">
                        {{ $submitworks->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="submitworkModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="submitworkModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{route('submitwork.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="submitworkModalTitle">Review submitted work</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    <input name="id" id="e_id" type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="sw_status" class="control-label mb-1">Review status <span>*</span></label>
                        <select name="status" id="sw_status" class="form-control" required>
                            <option value="Submission">{{ $Lang->Resource->Submission }}</option>
                            <option value="Accept">Accept</option>
                            <option value="Not Accepted">{{ $Lang->Common->Form->NotAccepted }}</option>
                        </select>
                        @if($errors->has('status'))
                        <small class="help-block form-text text-danger">{{ $errors->first('status') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="points" class="control-label mb-1">Points <span>*</span></label>
                        <input id="points" name="points" type="number" value="0" class="form-control" required>
                        @if($errors->has('points'))
                        <small class="help-block form-text text-danger">{{ $errors->first('points') }}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save review</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>




@endsection

@section('custom-js')
<script>

    $(".cancel").click(function() {
        clear();
    });

    var is_edit = "{{old('id')}}";
    if (is_edit) {
        $('#new_youtube .form-group .help-block').hide();
        $("#new_youtube input").val("");
        $('#youtubeModal').modal('show');
    }


    function clear() {
        $("input").val("");
    }

    $(".edit").click(function() {
        $('#submitworkModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('submitwork.index')}}/" + id + "/edit",
            success: function(res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $('.modal input[name=name]').val(res.data.name);
                    $('.modal input[name=video_id]').val(res.data.video_id);
                    $('.modal input[name=activision_time]').val(res.data.activision_time);
                    $('.modal input[name=duration_time]').val(res.data.duration_time);
                }
                spinner.hide();
            },
            error: function(err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
</script>

@endsection
