@extends('admin.layouts.master')

@php
  $permissions = app(\App\Http\Middleware\Permission::class);
  $currentAdmin = auth('admin')->user();
  $canCreateTestimonials = $permissions->allows($currentAdmin, 'testimonial.create');
  $canEditTestimonials = $permissions->allows($currentAdmin, 'testimonial.edit');
  $canPublishTestimonials = $permissions->allows($currentAdmin, 'testimonial.status');
  $canDeleteTestimonials = $permissions->allows($currentAdmin, 'testimonial.destroy');
  $testimonialsAreReadOnly = !$canCreateTestimonials && !$canEditTestimonials && !$canPublishTestimonials && !$canDeleteTestimonials;
@endphp

@section('content')
  <div class="content pb-0">

    @if($testimonialsAreReadOnly)
      <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review testimonial names and photos, but your role cannot create, edit, publish, or remove testimonials.</div>
    @endif

    <div class="row">
      @if($canCreateTestimonials)
      <div class="col-lg-5 col-md-12">
        <div id="new_testimonial">
          <div class="card">
            <div class="card-header">
              <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->TestimonialTitle }}</strong>
            </div>
            <div class="card-body">
              <div id="pay-invoice">
                <div class="card-body">
                  <form action="{{ route('testimonial.store') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    @if ($isLocalization)
                      <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        @foreach ($translations as $translation)
                          <?php
                          $isActive = '';
                          if ($translation->id == 'en') {
                              $isActive = 'active';
                          }
                          ?>
                          <li class="nav-item" data-id="{{ $translation->id }}">
                            <a class="nav-link {{ $isActive }}" id="{{ $translation->id }}-tab" data-toggle="pill"
                              href="#{{ $translation->id }}" role="tab" aria-controls="{{ $translation->id }}"
                              aria-selected="true">{{ $translation->name }}</a>
                          </li>
                        @endforeach
                      </ul>
                    @endif

                    <div class="tab-content" id="pills-tabContent">
                      @foreach ($translations as $translation)
                        <?php
                        $isActive = '';
                        $lang = $translation->id;
                        if ($translation->id == 'en') {
                            $isActive = 'show active';
                        }
                        ?>
                        <div class="tab-pane fade {{ $isActive }}" id="{{ $translation->id }}" role="tabpanel"
                          aria-labelledby="{{ $translation->id }}-tab">

                          <input name="language[{{ $lang }}]" type="hidden" class="form-control"
                            value="{{ $lang }}">
                          <div class="row">
                            <div class="col-md-12">
                              <div class="form-group has-success">
                                <label for="name[{{ $lang }}]"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Name }}
                                  <span>*</span></label>
                                <input id="name[{{ $lang }}]" name="name[{{ $lang }}]" type="text"
                                  value="{{ old('name.' . $lang) }}" class="form-control" required>
                                @if ($errors->has('name.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('name.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                            <div class="col-md-12">
                              <div class="form-group has-success">
                                <label for="designation[{{ $lang }}]"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Designation }}
                                  <span>*</span></label>
                                <input id="designation[{{ $lang }}]" name="designation[{{ $lang }}]"
                                  type="text" value="{{ old('designation.' . $lang) }}" class="form-control" required>
                                @if ($errors->has('designation.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('designation.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                <label for="order_by"
                                  class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                <input name="order_by[{{ $lang }}]" type="number" class="form-control"
                                  value="{{ old('order_by.' . $lang) }}" data-e2e="page-order-by-{{ $lang }}">
                                @if ($errors->has('order_by.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('order_by.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                            <div class="col-6">
                              <div class="form-group text-center">
                                <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide300px }}
                                  <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                                <div class="file-upload">
                                  <label for="photo{{ $lang }}" class="file-upload_label">
                                    <img class="file-upload_img" id="upload_img_{{ $lang }}"
                                      src="{{ asset('/') }}image/no-image.png">
                                  </label>
                                  <input type="file" onchange="changefile(event, 'upload_img_{{ $lang }}')"
                                    name="photo[{{ $lang }}]" value="{{ old('photo.' . $lang) }}"
                                    id="photo{{ $lang }}" class="file-upload_input"
                                    data-e2e="photo-{{ $lang }}">
                                </div>
                                <div style="clear: both"></div>
                                @if ($errors->has('photo.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('photo.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>

                            <div class="col-md-12">
                              <div class="form-group has-success">
                                <label for="testimonial[{{ $lang }}]"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Testimonial }}
                                  <span>*</span></label>
                                <textarea id="testimonial[{{ $lang }}]" name="testimonial[{{ $lang }}]" class="form-control"
                                  rows="6" required>{{ old('testimonial.' . $lang) }}</textarea>
                                @if ($errors->has('testimonial.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('testimonial.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                          </div>
                        </div>
                      @endforeach
                    </div>

                    <div class="form-actions form-group text-right">
                      <button type="submit" class="btn btn-info submit_ mt-3"><i class="fa fa-lock fa-lg"></i>&nbsp;
                        {{ $Lang->Common->Submit }}</button>
                      <button type="button" class="btn btn-danger cancel mt-3"><i
                          class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                    </div>

                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      @endif
      <div class="{{ $canCreateTestimonials ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-md-6">
                <strong class="card-title">{{ $Lang->TestimonialTitle }} {{ $Lang->Common->List }}</strong>
              </div>
              <div class="col-md-6">
                <form action="{{ route('testimonial.index') }}" method="get">
                  <div class="input-group search-input-group">
                    <input type="search" name="search" value="{{ @$search }}"
                      class="form-control search-form-control">
                    <span class="input-group-prepend">
                      <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"
                          aria-hidden="true"></i>
                        {{ $Lang->Common->Search }}</button>
                    </span>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div class="table-stats ov-h">
            <table class="table" id="testimonial_table">
              <thead>
                <tr>
                  <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                  <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                  <th width="35%"><strong>{{ $Lang->Activity->Image }}</strong></th>
                  <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($testimonials as $testimonial)
                  <tr id="{{ @$testimonial->uuid }}">
                    <td> #{{ @$testimonial->id }} </td>
                    <td> <span class="name">{{ @$testimonial->name }}</span> </td>
                    <td class="avatar">
                      <div class="round-img">
                        <img class="rounded"
                            src="{{ str_starts_with((string) $testimonial->photo, '/') ? $testimonial->photo : route('testimonial.photo', [$testimonial->photo]) }}"
                            alt="Photo of {{ $testimonial->name }}">
                      </div>
                    </td>
                    <td>
                      @if($canEditTestimonials)
                      <button type="button" class="edit btn btn-info btn-sm1"
                        data-id="{{ $testimonial->uuid }}" aria-label="Edit {{ $testimonial->name }}"
                        title="Edit testimonial"><i class="fa fa-edit" aria-hidden="true"></i></button>
                      @endif
                      @if($canPublishTestimonials)
                      <button type="button" class="btn btn-warning btn-sm1 status"
                        aria-label="{{ $testimonial->status ? 'Unpublish' : 'Publish' }} {{ $testimonial->name }}"
                        title="{{ $testimonial->status ? 'Unpublish' : 'Publish' }} testimonial"
                        aria-pressed="{{ $testimonial->status ? 'true' : 'false' }}"
                        data-id="{{ $testimonial->uuid }}"
                        data-url="{{ route('testimonial.status', $testimonial->uuid) }}"
                        data-token="{{ csrf_token() }}"><i
                          class="fa text-white {{ $testimonial->status ? 'fa-check-square' : 'fa-square' }}"
                          aria-hidden="true"></i></button>
                      @endif
                      @if($canDeleteTestimonials)
                      <button type="button" class="btn btn-danger btn-sm1 trash"
                        aria-label="Delete {{ $testimonial->name }}" title="Delete testimonial"
                        data-item-label="testimonial from {{ $testimonial->name }}"
                        data-id="{{ $testimonial->uuid }}"
                        data-url="{{ route('testimonial.destroy', $testimonial->uuid) }}"
                        data-token="{{ csrf_token() }}"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                      @endif
                      @if(!$canEditTestimonials && !$canPublishTestimonials && !$canDeleteTestimonials)<span class="badge badge-light">View only</span>@endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="pagination justify-content-end">
              {{ $testimonials->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Modal --}}
  @if($canEditTestimonials)
  <div class="modal fade" id="testimonialModal" tabindex="-1" role="dialog" data-backdrop="static"
    aria-labelledby="mediumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <form action="{{ route('testimonial.update') }}" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <strong class="card-title">{{ $Lang->Common->Edit }} {{ $Lang->TestimonialTitle }}</strong>
            <button type="button" class="close cancel" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">

            {{ csrf_field() }}
            @method('PUT')
            <input name="uuid" id="e_id" type="hidden" value="{{ old('uuid') }}" class="form-control"
              required>
            @if ($isLocalization)
              <ul class="nav nav-pills mb-3" id="e_pills-tab" role="tablist">
                @foreach ($translations as $translation)
                  <?php
                  $isActive = '';
                  if ($translation->id == 'en') {
                      $isActive = 'active';
                  }
                  ?>
                  <li class="nav-item" data-id="e_{{ $translation->id }}">
                    <a class="nav-link {{ $isActive }}" id="e_{{ $translation->id }}-tab" data-toggle="pill"
                      href="#e_{{ $translation->id }}" role="tab" aria-controls="e_{{ $translation->id }}"
                      aria-selected="true">{{ $translation->name }}</a>
                  </li>
                @endforeach
              </ul>
            @endif
            <div class="tab-content" id="e_pills-tabContent">
              @foreach ($translations as $translation)
                <?php
                $isActive = '';
                $lang = $translation->id;
                if ($translation->id == 'en') {
                    $isActive = 'show active';
                }
                ?>
                <div class="tab-pane fade {{ $isActive }}" id="e_{{ $translation->id }}" role="tabpanel"
                  aria-labelledby="e_{{ $translation->id }}-tab">

                  <input name="language[{{ $lang }}]" type="hidden" class="form-control"
                    value="{{ $lang }}" id="e_language[{{ $lang }}]">

                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group has-success">
                        <label for="name[{{ $lang }}]"
                          class="control-label mb-1">{{ $Lang->Common->Form->Name }}
                          <span>*</span></label>
                        <input id="e_name[{{ $lang }}]" name="name[{{ $lang }}]" type="text"
                          value="{{ old('name.' . $lang) }}" class="form-control" required>
                        @if ($errors->has('name.' . $lang))
                          <small class="help-block form-text text-danger">{{ $errors->first('name.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="form-group has-success">
                        <label for="designation[{{ $lang }}]"
                          class="control-label mb-1">{{ $Lang->Common->Form->Designation }}
                          <span>*</span></label>
                        <input id="e_designation[{{ $lang }}]" name="designation[{{ $lang }}]"
                          type="text" value="{{ old('designation.' . $lang) }}" class="form-control" required>
                        @if ($errors->has('designation.' . $lang))
                          <small
                            class="help-block form-text text-danger">{{ $errors->first('designation.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                    <div class="col-md-6 col-sm-6">
                      <div class="form-group">
                        <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                        <input name="order_by[{{ $lang }}]" type="number" class="form-control"
                          id="e_order_by[{{ $lang }}]" value="{{ old('order_by.' . $lang) }}"
                          data-e2e="page-order-by-{{ $lang }}">
                        @if ($errors->has('order_by.' . $lang))
                          <small
                            class="help-block form-text text-danger">{{ $errors->first('order_by.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="form-group text-center">
                        <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide300px }}
                          <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                        <div class="file-upload">
                          <label for="e_photo{{ $lang }}" class="file-upload_label">
                            <img class="file-upload_img" id="e_upload_img_{{ $lang }}"
                              src="{{ asset('/') }}image/no-image.png">
                          </label>
                          <input type="file" onchange="changefile(event, 'e_upload_img_{{ $lang }}')"
                            name="photo[{{ $lang }}]" value="{{ old('photo.' . $lang) }}"
                            id="e_photo{{ $lang }}" class="file-upload_input"
                            data-e2e="e-photo-{{ $lang }}">
                        </div>
                        <div style="clear: both"></div>
                        @if ($errors->has('photo.' . $lang))
                          <small
                            class="help-block form-text text-danger">{{ $errors->first('photo.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group has-success">
                        <label for="testimonial[{{ $lang }}]"
                          class="control-label mb-1">{{ $Lang->Common->Form->Testimonial }}
                          <span>*</span></label>
                        <textarea id="e_testimonial[{{ $lang }}]" name="testimonial[{{ $lang }}]" class="form-control"
                          rows="6" required>{{ old('testimonial.' . $lang) }}</textarea>
                        @if ($errors->has('testimonial.' . $lang))
                          <small
                            class="help-block form-text text-danger">{{ $errors->first('testimonial.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-info submit_ mt-3"><i class="fa fa-magic"></i>&nbsp;
              {{ $Lang->Common->Submit }}</button>
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
  <script>
    @if($canDeleteTestimonials)
      itemDelete({
        tableId: "testimonial_table",
        method: "DELETE"
      });
    @endif
    @if($canPublishTestimonials)
      itemStatus({
        tableId: "testimonial_table",
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

    @if($canEditTestimonials)
    var is_edit = "{{ old('id') }}";
    if (is_edit) {
      $('#new_testimonial .form-group .help-block').hide();
      $("#new_testimonial input:not([type=hidden])").val("");
      $('#testimonialModal').modal('show');
    }

    $(".edit").click(function() {
      $('#testimonialModal').modal('show');
      $('.form-group .help-block').hide();
      var spinner = $('.spinner');
      spinner.show();

      const translations = @json($translations);

      var id = $(this).data('id');

      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: 'get',
        url: "{{ route('testimonial.index') }}/" + id + "/edit",
        success: function(res) {
          if (res.data) {
            for (let i = 0; i < translations.length; i++) {
              let lang = translations[i].id;
              const data = res.data?.find(item => item.language == lang);

              $(`.modal #e_id`).val(data.uuid);
              $(`.modal #e_language\\[${lang}\\]`).val(lang);
              $(`.modal #e_name\\[${lang}\\]`).val(data.name);
              $(`.modal #e_designation\\[${lang}\\]`).val(data.designation);
              $(`.modal #e_order_by\\[${lang}\\]`).val(data.order_by);
              $(`.modal #e_testimonial\\[${lang}\\]`).val(data.testimonial);
              if (data.photo) {
                const photoUrl = `{{ url('admin/testimonial/photo') }}/` + encodeURIComponent(data.photo);
                $(`.modal #e_upload_img_${lang}`).attr('src', photoUrl);
              } else {
                $(`.modal #e_upload_img_${lang}`).attr('src', "{{ asset('/') }}image/no-image.png");
              }
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
    @endif
  </script>
@endsection
