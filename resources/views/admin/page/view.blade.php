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
                                <a class="btn btn-sm btn-secondary float-right" href="{{ route('page.index') }}">
                                    <i class="fa fa-arrow-circle-left"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card border mb-2 p-3">
                                    <div class="card-title bg-secondary text-white">
                                        <h4 class="m-0 p-2">{{ $Lang->Common->Page }} {{ $Lang->Common->Information }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12">{{ $page->name }}</div>
                                            <div class="col-12">

                                            </div>
                                        </div>

                                        <div class="modal-footer mt-5">
                                            @if($canToggleComments)
                                            <input type="checkbox" value="1" data-toggle="toggle"
                                                @if ($page->is_comment == 1) checked @endif data-on="Comment On"
                                                id="toggleCommentStatus" data-off="Comment Off" data-onstyle="success"
                                                data-offstyle="danger">
                                            @else
                                                <span class="badge {{ $page->is_comment ? 'badge-complete' : 'badge-pending' }}">
                                                    Comments {{ $page->is_comment ? 'enabled' : 'disabled' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="card border my-2 p-3">
                            <div class="card-title bg-secondary text-white">
                                <h4 class="m-0 p-2">{{ $Lang->Common->Comment }} {{ $Lang->Common->List }}</h4>
                            </div>

                            <form action="{{ route('page.view', $page->uuid) }}" method="get">
                                <div class="row">
                                    <div class="col-md-4">
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
                                    <div class="col-md-4">
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

                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-secondary btn-sm">Apply status and order</button>
                                    </div>
                                </div>
                            </form>
                            <form action="{{ route('page.comments.search', $page->uuid) }}" method="post" class="mt-3" role="search">
                                @csrf
                                <label for="page-comment-search">Search comment text</label>
                                <div class="input-group search-input-group">
                                    <input id="page-comment-search" type="search" name="search" value="{{ $search }}"
                                        class="form-control search-form-control" autocomplete="off" maxlength="100" required>
                                    <span class="input-group-append">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"
                                                aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                            @if($search !== '')
                                <form action="{{ route('page.comments.search.clear', $page->uuid) }}" method="post" class="mt-2">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm">Clear private search</button>
                                    <small class="text-muted">The search expires automatically after 10 minutes.</small>
                                </form>
                            @endif

                            <div class="mt-3">
                                <div class="table-stats order-table ov-h">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th width="10%" class="serial">{{ $Lang->Common->Form->ID }}</th>
                                                <th width="25%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                                <th width="20%"><strong>{{ $Lang->Common->Form->TotalLike }}</strong></th>
                                                <th width="10%">{{ $Lang->Common->Form->Publish }}/{{ $Lang->Common->Form->Pending }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($comments as $comment)
                                                <tr id="{{ @$comment->id }}">
                                                    <td>{{ @$comment->id }} </td>
                                                    <td>
                                                        <span class="name">{{ @$comment->text }}</span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-complete">{{ @$comment->total_like }}</span>
                                                    </td>
                                                    <td class="{{ $canModerate ? 'editStatus' : '' }}" @if($canModerate) role="button" tabindex="0" @endif data-id="{{ @$comment->id }}"
                                                        data-name="{{ @$comment->text }}"
                                                        data-status="{{ @$comment->status }}">
                                                        @if ($comment->status == 1)
                                                            <span class="badge badge-complete {{ $canModerate ? 'cursor-pointer' : '' }}">{{ $Lang->Common->Form->Publish }}</span>
                                                        @else
                                                            <span class="badge badge-pending {{ $canModerate ? 'cursor-pointer' : '' }}">{{ $Lang->Common->Form->Pending }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td colspan="4" style="display: none"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="pagination justify-content-end">
                                        {{ $comments->appends(['order_by' => $order_by, 'status' => $status])->links('vendor.pagination.bootstrap-4') }}
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
                        <strong class="card-title">{{ $page->name }}</strong>
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
    {{-- is_comment --}}
    <script>
        @if($canToggleComments)
        $('#toggleCommentStatus').change(function(ev) {
            try {
                const value = $(this).is(':checked');

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                var spinner = $('.spinner');
                var isConfirm = window.confirm("Change whether visitors may comment on this page?");
                if (isConfirm) {
                    spinner.show();
                    $.ajax({
                        type: 'PUT',
                        data: {
                            is_comment: value == true ? 1 : 0
                        },
                        url: `{{ route('page.is-comments', $page->id) }}`,
                        success: function(res) {
                            toastrMsg('success', res.message);
                            spinner.hide();
                        },
                        error: function(err) {
                            toastrMsg('error', err.responseJSON?.message || 'The page setting could not be changed.');
                            spinner.hide();
                        }
                    });
                }

            } catch (e) {
                toastrMsg('error', e);
            }
        });
        @endif
        $(".editStatus").on('click keydown', function(event) {
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) {
                return;
            }
            event.preventDefault();
            $('#commentPublishModal').modal('show');
            $('#e_id').val($(this).data('id'));
            $('.modal-body .card-content').text($(this).data('name'));

            const status = $(this).data('status');
            if (status) {
                $('.submit_type').text("Unpublish")
            } else {
                $('.submit_type').text("Publish")
            }
        });
    </script>
@endsection
