@extends('admin.layouts.master')

@php
  $permissions = app(\App\Http\Middleware\Permission::class);
  $currentAdmin = auth('admin')->user();
  $canCreateTags = $permissions->allows($currentAdmin, 'tag.create');
  $canEditTags = $permissions->allows($currentAdmin, 'tag.edit');
  $canPublishTags = $permissions->allows($currentAdmin, 'tag.status');
  $canDeleteTags = $permissions->allows($currentAdmin, 'tag.destroy');
  $tagsAreReadOnly = !$canCreateTags && !$canEditTags && !$canPublishTags && !$canDeleteTags;
@endphp

@section('content')
  <div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    @if($tagsAreReadOnly)
      <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review projects, but your role cannot create, edit, publish, or remove them.</div>
    @endif

    <div class="row">
      @if($canCreateTags)
      <div class="col-lg-5 col-md-12">
        <div id="new_tag">
          <div class="card">
            <div class="card-header">
              <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->TagTitle }}</strong>
            </div>
            <div class="card-body">
              <div id="pay-invoice">
                <div class="card-body">
                  <form action="{{ route('tag.store') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="row">
                      <div class="col-12">
                        <div class="form-group has-success">
                          <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }}
                            <span>*</span></label>
                          <input id="name" name="name" type="text" value="{{ old('name') }}"
                            class="form-control" required>
                          @if ($errors->has('name'))
                            <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                          @endif
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="form-group has-success">
                          <label for="banner_id" class="control-label mb-1">{{ $Lang->Common->Form->Select }}
                            {{ $Lang->BannerTitle }}</label>
                          <select id="banner_id" name="banner_id" class="form-control" data-e2e="banner-id">
                            <option value="">{{ $Lang->Common->Form->Select }} {{ $Lang->Common->Page }}</option>
                            @foreach ($banners as $banner)
                              <option value="{{ $banner->id }}"
                                {{ old('banner_id') == $banner->id ? 'selected' : '' }}>
                                {{ $banner->name }}
                              </option>
                            @endforeach
                          </select>
                          @if ($errors->has('banner_id'))
                            <small class="help-block form-text text-danger">{{ $errors->first('banner_id') }}</small>
                          @endif
                        </div>
                      </div>
                    </div>

                    <div class="form-actions form-group text-right">
                      <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i>
                        Create project</button>
                      <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3"><i
                          class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                    </div>

                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      @endif
      <div class="{{ $canCreateTags ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-md-6">
                <strong class="card-title">{{ $Lang->TagTitle }} {{ $Lang->Common->List }}</strong>
              </div>
              <div class="col-md-6">
                <form action="{{ route('tag.index') }}" method="get">
                  <div class="input-group search-input-group">
                    <label class="sr-only" for="tag-search">Search projects</label>
                    <input id="tag-search" type="search" name="search" value="{{ @$search }}"
                      class="form-control search-form-control" aria-label="Search projects">
                    <span class="input-group-prepend">
                      <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i>
                        {{ $Lang->Common->Search }}</button>
                    </span>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div class="table-stats ov-h">
            <table class="table" id="tag_table">
              <thead>
                <tr>
                  <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                  <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                  <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($tags as $tag)
                  <tr id="{{ @$tag->id }}">
                    <td> #{{ @$tag->id }} </td>
                    <td> <span class="name">{{ @$tag->name }}</span> </td>
                    <td>
                      @if($canEditTags)
                        <button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact" data-id="{{ $tag->id }}" aria-label="Edit project" title="Edit project"><i class="fa fa-edit" aria-hidden="true"></i> Edit</button>
                      @endif
                      @if($canPublishTags)
                        <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status" data-id="{{ $tag->id }}" data-url="{{ route('tag.status', $tag->id) }}" data-token="{{ csrf_token() }}" aria-label="{{ $tag->status ? 'Unpublish' : 'Publish' }} project" title="{{ $tag->status ? 'Unpublish' : 'Publish' }} project" aria-pressed="{{ $tag->status ? 'true' : 'false' }}"><i class="fa {{ $tag->status ? 'fa-check-square' : 'fa-square' }}" aria-hidden="true"></i> {{ $tag->status ? 'Unpublish' : 'Publish' }}</button>
                      @endif
                      @if($canDeleteTags)
                        <button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash" data-id="{{ $tag->id }}" data-url="{{ route('tag.destroy', $tag->id) }}" data-token="{{ csrf_token() }}" data-item-label="project {{ $tag->name }}" aria-label="Delete project" title="Delete project"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</button>
                      @endif
                      @if(!$canEditTags && !$canPublishTags && !$canDeleteTags)<span class="badge badge-light">View only</span>@endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="pagination justify-content-end">
              {{ $tags->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Modal --}}
  @if($canEditTags)
  <div class="modal fade" id="tagModal" tabindex="-1" role="dialog" data-backdrop="static"
    aria-labelledby="tagModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <form action="{{ route('tag.update') }}" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h2 class="card-title h5 mb-0" id="tagModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->TagTitle }}</h2>
            <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">

            {{ csrf_field() }}
            @method('PUT')
            <input name="id" id="e_id" type="hidden" value="{{ old('id') }}" class="form-control"
              required>

            <div class="form-group has-success">
              <label for="e_name" class="control-label mb-1">{{ $Lang->Common->Form->Name }}<span>*</span></label>
              <input id="e_name" name="name" type="text" value="{{ old('name') }}" class="form-control"
                required>
              @if ($errors->has('name'))
                <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
              @endif
            </div>

            <div class="form-group has-success">
              <label for="e_banner_id" class="control-label mb-1">{{ $Lang->Common->Form->Select }}
                {{ $Lang->BannerTitle }}</label>
              <select id="e_banner_id" name="banner_id" type="text" class="form-control" data-e2e="banner-id">
                <option value="">{{ $Lang->Common->Form->Select }} {{ $Lang->Common->Page }}</option>
                @foreach ($banners as $banner)
                  <option value="{{ $banner->id }}" {{ old('banner_id') == $banner->id ? 'selected' : '' }}>
                    {{ $banner->name }}
                  </option>
                @endforeach
              </select>
              @if ($errors->has('banner_id'))
                <small class="help-block form-text text-danger">{{ $errors->first('banner_id') }}</small>
              @endif
            </div>

          </div>
          <div class="modal-footer">
            <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i>
              Save project</button>
            <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i
                class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif
@endsection

@section('custom-js')
  <script>
    @if($canDeleteTags)
      itemDelete({
        tableId: "tag_table",
        method: "DELETE"
      });
    @endif
    @if($canPublishTags)
      itemStatus({
        tableId: "tag_table",
        method: "PUT"
      });
    @endif

    $(".cancel").click(function() {
      clear($(this).closest("form"));
    });

    function clear($form) {
      if ($form.length) {
        $form.get(0).reset();
        $form.find(".chosen-select").trigger("chosen:updated");
      }
    }

    @if($canEditTags)
    var is_edit = "{{ old('id') }}";
    if (is_edit) {
      $('#new_tag .form-group .help-block').hide();
      $("#new_tag input:not([type=hidden])").val("");
      $('#tagModal').modal('show');
    }

    $(".edit").click(function() {
      $('#tagModal').modal('show');
      $('.form-group .help-block').hide();
      var spinner = $('.spinner');
      spinner.show();
      var id = $(this).data('id');

      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: 'get',
        url: "{{ route('tag.index') }}/" + id + "/edit",
        success: function(res) {
          if (res.data) {
            $('.modal #e_id').val(res.data.id);
            $('.modal #e_name').val(res.data.name);
            $('.modal #e_banner_id').val(res.data.banner_id).trigger('change');
          }
          spinner.hide();
        },
        error: function(err) {
          toastrMsg('error', err.responseJSON.message);
          spinner.hide();
        }
      });

    });
    @endif
  </script>
@endsection
