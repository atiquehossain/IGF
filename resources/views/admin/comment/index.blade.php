@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                            </div>
                        </div>
                    </div>

                    <div class="modal-body">
                        <div class="row">

                        </div>
                        <div class="card border my-2 p-3">
                            <div class="card-title bg-secondary text-white">
                                <h4 class="m-0 p-2">{{ $Lang->Common->Comment }} {{ $Lang->Common->List }}</h4>
                            </div>

                            <form action="{{ route('comment.search') }}" method="post" class="mb-2" role="search">@csrf
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="sr-only" for="comment-private-search">Search comment text or page title</label>
                                        <input id="comment-private-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" required class="form-control search-form-control" placeholder="Search comment text or page title">
                                    </div>
                                    <div class="col-md-4"><button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button></div>
                                </div>
                            </form>
                            @if($search !== '')<form action="{{ route('comment.search.clear') }}" method="post" class="mb-2">@csrf<button type="submit" class="btn btn-light btn-sm">Clear private search</button></form>@endif
                            <form action="{{ route('comment.index') }}" method="get" aria-label="Filter comments by order and status">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="">
                                            <select name="order_by" class="form-control search-drop-form-control">
                                                <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Form->All }}</option>
                                                <option value="1" @if ($order_by == 1) selected @endif>ASC
                                                </option>
                                                <option value="0" @if ($order_by == 0) selected @endif>DESC
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <select name="status" class="form-control search-drop-form-control">
                                                <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Form->All }}</option>
                                                <option value="1" @if ($status == 1) selected @endif>{{ $Lang->Common->Form->Publish }}
                                                </option>
                                                <option value="2" @if ($status == 2) selected @endif>{{ $Lang->Common->Form->Pending }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2"><button type="submit" class="btn btn-info btn-sm">Apply filters</button></div>
                                </div>
                            </form>

                            <div class="mt-3">
                                <div class="table-stats order-table ov-h">
                                    <table class="table table-bordered" id="comment_table">
                                        <thead>
                                            <tr>
                                                <th width="5%" class="serial">{{ $Lang->Common->Form->ID }}</th>
                                                <th width="30%"><strong>{{ $Lang->Common->Comment }}</strong></th>
                                                <th width="30%"><strong>{{ $Lang->Common->Page }} Title</strong></th>
                                                <th width="10%"><strong>{{ $Lang->Common->Form->TotalLike }}</strong></th>
                                                <th width="10%">{{ $Lang->Common->Form->Publish }}/{{ $Lang->Common->Form->Pending }}</th>
                                                <?php if (!empty($deleteLink)) { ?>
                                                <th width="10%">{{ $Lang->Common->Form->Action }}
                                                <?php } ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($comments as $comment)
                                                <tr id="{{ @$comment->id }}">
                                                    <td>{{ @$comment->id }} </td>
                                                    <td>
                                                        <span>{{ @$comment->text }}</span>
                                                    </td>
                                                    <td>
                                                        <span>{{ substr_replace(@$comment->name, "...", 80) }}</span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-complete">{{ @$comment->total_like }}
                                                        </span>
                                                    </td>
                                                    <td class="{{ $canModerate ? 'editStatus' : '' }}" @if($canModerate) role="button" tabindex="0" @endif data-id="{{ @$comment->id }}"
                                                        data-title="{{ @$comment->name }}"
                                                        data-name="{{ @$comment->text }}"
                                                        data-status="{{ @$comment->status }}">
                                                        @if ($comment->status == 1)
                                                            <span class="badge badge-complete {{ $canModerate ? 'cursor-pointer' : '' }}">{{ $Lang->Common->Form->Publish }}</span>
                                                        @else
                                                            <span class="badge badge-pending {{ $canModerate ? 'cursor-pointer' : '' }}">{{ $Lang->Common->Form->Pending }}</span>
                                                        @endif
                                                    </td>
                                                    <?php if (!empty($deleteLink)) { ?>
                                                    <td>
                                                        <?=App\Link::action(@$comment->id, @$comment->status)?>
                                                    </td>
                                                    <?php } ?>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td colspan="4" style="display: none"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="pagination justify-content-end">
                                        {{ $comments->appends(['order_by' => @$order_by, 'status' => @$status])->links('vendor.pagination.bootstrap-4') }}
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>


            </div>
        </div>
    </div>

<style>
.cursor-pointer {
  cursor: pointer;
}
.toggle-on.btn, .toggle-off.btn {
    line-height: 230%;
}
</style>
    @if($canModerate)
    {{-- Modal --}}
    <div class="modal fade" id="commentPublishModal" tabindex="-1" role="dialog" data-backdrop="static"
        aria-labelledby="mediumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form class="fileUploadFormEdit" action="{{ route('page.status.comment') }}" method="POST"
                    enctype="multipart/form-data">
                    <div class="modal-header">
                        <strong class="card-title">{{ $Lang->Common->Comment }} {{ $Lang->Common->Info }}</strong>
                        <button type="button" class="close cancel" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        {{ csrf_field() }}
                        @method('PUT')
                        <input name="id" id="e_id" type="hidden" value="{{ old('id') }}" class="form-control"
                            required>
                        <strong class="card-title"></strong>
                        <br> <br>
                        <p class="card-content card-tex"></p>

                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-info submit_ mt-3"><i class="fa fa-magic">
                            </i>&nbsp;
                            <span class="submit_type">{{ $Lang->Common->Submit }}</span>
                        </button>
                        <button type="button" class="btn btn-danger cancel mt-3" data-dismiss="modal"><i
                                class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection

@section('custom-js')
    <link href="{{ asset('admin-assets/assets/js/lib/bootstrap4-toggle/bootstrap4-toggle.min.css') }}" rel="stylesheet">
    <script src="{{ asset('admin-assets/assets/js/lib/bootstrap4-toggle/bootstrap4-toggle.min.js') }}"></script>

    <script>
        itemDelete({
            tableId: "comment_table",
            method: "DELETE"
        });
        $(".editStatus").on('click keydown', function(event) {
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) {
                return;
            }
            event.preventDefault();
            $('#commentPublishModal').modal('show');
            $('#e_id').val($(this).data('id'));
            $('.modal-body .card-content').text($(this).data('name'));
            $('.modal-body .card-title').text($(this).data('title'));


            const status = $(this).data('status');
            if (status) {
                $('.submit_type').text("Unpublish")
            } else {
                $('.submit_type').text("Publish")
            }
        });
    </script>
@endsection
