@extends('admin.layouts.master')

@php
  $permissions = app(\App\Http\Middleware\Permission::class);
  $currentAdmin = auth('admin')->user();
  $canCreateTestimonials = $permissions->allows($currentAdmin, 'testimonial.create');
  $canEditTestimonials = $permissions->allows($currentAdmin, 'testimonial.edit');
  $canPublishTestimonials = $permissions->allows($currentAdmin, 'testimonial.status');
  $canDeleteTestimonials = $permissions->allows($currentAdmin, 'testimonial.destroy');
  $testimonialsAreReadOnly = !$canCreateTestimonials && !$canEditTestimonials && !$canPublishTestimonials && !$canDeleteTestimonials;
  $mediaUrls = app(\App\Services\AdminMediaUrlResolver::class);
@endphp

@section('content')
  <div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

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
                      <ul class="nav nav-pills mb-3" id="testimonial-create-tabs" role="tablist" aria-label="Testimonial language">
                        @foreach ($translations as $translation)
                          <?php
                          $isActive = '';
                          if ($translation->id == 'en') {
                              $isActive = 'active';
                          }
                          ?>
                          <li class="nav-item" data-id="{{ $translation->id }}">
                            <a class="nav-link {{ $isActive }}" id="testimonial-create-tab-{{ $translation->id }}" data-toggle="pill"
                              href="#testimonial-create-pane-{{ $translation->id }}" role="tab" aria-controls="testimonial-create-pane-{{ $translation->id }}"
                              aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{ $translation->name }}</a>
                          </li>
                        @endforeach
                      </ul>
                    @endif

                    <div class="tab-content" id="testimonial-create-tab-content">
                      @foreach ($translations as $translation)
                        <?php
                        $isActive = '';
                        $lang = $translation->id;
                        if ($translation->id == 'en') {
                            $isActive = 'show active';
                        }
                        ?>
                        <div class="tab-pane fade {{ $isActive }}" id="testimonial-create-pane-{{ $translation->id }}" role="tabpanel"
                          aria-labelledby="testimonial-create-tab-{{ $translation->id }}">

                          <input name="language[{{ $lang }}]" type="hidden" class="form-control"
                            value="{{ $lang }}">
                          <div class="row">
                            <div class="col-md-12">
                              <div class="form-group has-success">
                                <label for="testimonial-create-name-{{ $lang }}"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Name }}
                                  <span>*</span></label>
                                <input id="testimonial-create-name-{{ $lang }}" name="name[{{ $lang }}]" type="text"
                                  value="{{ old('name.' . $lang) }}" class="form-control" required>
                                @if ($errors->has('name.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('name.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                            <div class="col-md-12">
                              <div class="form-group has-success">
                                <label for="testimonial-create-designation-{{ $lang }}"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Designation }}
                                  <span>*</span></label>
                                <input id="testimonial-create-designation-{{ $lang }}" name="designation[{{ $lang }}]"
                                  type="text" value="{{ old('designation.' . $lang) }}" class="form-control" required>
                                @if ($errors->has('designation.' . $lang))
                                  <small
                                    class="help-block form-text text-danger">{{ $errors->first('designation.' . $lang) }}</small>
                                @endif
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                <label for="testimonial-create-order-{{ $lang }}"
                                  class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                <input id="testimonial-create-order-{{ $lang }}" name="order_by[{{ $lang }}]" type="number" class="form-control"
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
                                  <label for="testimonial-create-photo-{{ $lang }}" class="file-upload_label">
                                    <img class="file-upload_img" id="upload_img_{{ $lang }}"
                                      src="{{ $mediaUrls->fallback() }}"
                                      onerror="this.onerror=null;this.src='{{ $mediaUrls->fallback() }}'"
                                      alt="Testimonial photo preview">
                                  </label>
                                  <input type="file" onchange="changefile(event, 'upload_img_{{ $lang }}')"
                                    name="photo[{{ $lang }}]" value="{{ old('photo.' . $lang) }}"
                                    id="testimonial-create-photo-{{ $lang }}" class="file-upload_input"
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
                                <label for="testimonial-create-body-{{ $lang }}"
                                  class="control-label mb-1">{{ $Lang->Common->Form->Testimonial }}
                                  <span>*</span></label>
                                <textarea id="testimonial-create-body-{{ $lang }}" name="testimonial[{{ $lang }}]" class="form-control"
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
                      <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i>
                        Create testimonial</button>
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
                    <label class="sr-only" for="testimonial-search">Search testimonials</label>
                    <input id="testimonial-search" type="search" name="search" value="{{ @$search }}"
                      class="form-control search-form-control" aria-label="Search testimonials">
                    <span class="input-group-prepend">
                      <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search"
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
                            src="{{ $mediaUrls->image($testimonial->getRawOriginal('photo'), 'testimonial') }}"
                            onerror="this.onerror=null;this.src='{{ $mediaUrls->fallback() }}'"
                            alt="Photo of {{ $testimonial->name }}">
                      </div>
                    </td>
                    <td>
                      @if($canEditTestimonials)
                      <button type="button" class="edit btn igf-btn igf-btn-secondary igf-btn-compact"
                        data-id="{{ $testimonial->uuid }}" aria-label="Edit {{ $testimonial->name }}"
                        title="Edit testimonial"><i class="fa fa-edit" aria-hidden="true"></i> Edit</button>
                      @endif
                      @if($canPublishTestimonials)
                      <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact status"
                        aria-label="{{ $testimonial->status ? 'Unpublish' : 'Publish' }} {{ $testimonial->name }}"
                        title="{{ $testimonial->status ? 'Unpublish' : 'Publish' }} testimonial"
                        aria-pressed="{{ $testimonial->status ? 'true' : 'false' }}"
                        data-id="{{ $testimonial->uuid }}"
                        data-url="{{ route('testimonial.status', $testimonial->uuid) }}"
                        data-token="{{ csrf_token() }}"><i
                          class="fa {{ $testimonial->status ? 'fa-check-square' : 'fa-square' }}"
                          aria-hidden="true"></i> {{ $testimonial->status ? 'Unpublish' : 'Publish' }}</button>
                      @endif
                      @if($canDeleteTestimonials)
                      <button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash"
                        aria-label="Delete {{ $testimonial->name }}" title="Delete testimonial"
                        data-item-label="testimonial from {{ $testimonial->name }}"
                        data-id="{{ $testimonial->uuid }}"
                        data-url="{{ route('testimonial.destroy', $testimonial->uuid) }}"
                        data-token="{{ csrf_token() }}"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</button>
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
    aria-labelledby="testimonialModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <form action="{{ route('testimonial.update') }}" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h2 class="card-title h5 mb-0" id="testimonialModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->TestimonialTitle }}</h2>
            <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">

            {{ csrf_field() }}
            @method('PUT')
            <input name="uuid" id="e_id" type="hidden" value="{{ old('uuid') }}" class="form-control"
              required>
            @if ($isLocalization)
              <ul class="nav nav-pills mb-3" id="testimonial-edit-tabs" role="tablist" aria-label="Edit testimonial language">
                @foreach ($translations as $translation)
                  <?php
                  $isActive = '';
                  if ($translation->id == 'en') {
                      $isActive = 'active';
                  }
                  ?>
                  <li class="nav-item" data-id="e_{{ $translation->id }}">
                    <a class="nav-link {{ $isActive }}" id="testimonial-edit-tab-{{ $translation->id }}" data-toggle="pill"
                      href="#testimonial-edit-pane-{{ $translation->id }}" role="tab" aria-controls="testimonial-edit-pane-{{ $translation->id }}"
                      aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{ $translation->name }}</a>
                  </li>
                @endforeach
              </ul>
            @endif
            <div class="tab-content" id="testimonial-edit-tab-content">
              @foreach ($translations as $translation)
                <?php
                $isActive = '';
                $lang = $translation->id;
                if ($translation->id == 'en') {
                    $isActive = 'show active';
                }
                ?>
                <div class="tab-pane fade {{ $isActive }}" id="testimonial-edit-pane-{{ $translation->id }}" role="tabpanel"
                  aria-labelledby="testimonial-edit-tab-{{ $translation->id }}">

                  <input name="language[{{ $lang }}]" type="hidden" class="form-control"
                    value="{{ $lang }}" id="testimonial-edit-language-{{ $lang }}">

                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group has-success">
                        <label for="testimonial-edit-name-{{ $lang }}"
                          class="control-label mb-1">{{ $Lang->Common->Form->Name }}
                          <span>*</span></label>
                        <input id="testimonial-edit-name-{{ $lang }}" name="name[{{ $lang }}]" type="text"
                          value="{{ old('name.' . $lang) }}" class="form-control" required>
                        @if ($errors->has('name.' . $lang))
                          <small class="help-block form-text text-danger">{{ $errors->first('name.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="form-group has-success">
                        <label for="testimonial-edit-designation-{{ $lang }}"
                          class="control-label mb-1">{{ $Lang->Common->Form->Designation }}
                          <span>*</span></label>
                        <input id="testimonial-edit-designation-{{ $lang }}" name="designation[{{ $lang }}]"
                          type="text" value="{{ old('designation.' . $lang) }}" class="form-control" required>
                        @if ($errors->has('designation.' . $lang))
                          <small
                            class="help-block form-text text-danger">{{ $errors->first('designation.' . $lang) }}</small>
                        @endif
                      </div>
                    </div>
                    <div class="col-md-6 col-sm-6">
                      <div class="form-group">
                        <label for="testimonial-edit-order-{{ $lang }}" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                        <input name="order_by[{{ $lang }}]" type="number" class="form-control"
                          id="testimonial-edit-order-{{ $lang }}" value="{{ old('order_by.' . $lang) }}"
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
                          <label for="testimonial-edit-photo-{{ $lang }}" class="file-upload_label">
                            <img class="file-upload_img" id="e_upload_img_{{ $lang }}"
                              src="{{ $mediaUrls->fallback() }}"
                              onerror="this.onerror=null;this.src='{{ $mediaUrls->fallback() }}'"
                              alt="Testimonial photo preview">
                          </label>
                          <input type="file" onchange="changefile(event, 'e_upload_img_{{ $lang }}')"
                            name="photo[{{ $lang }}]" value="{{ old('photo.' . $lang) }}"
                            id="testimonial-edit-photo-{{ $lang }}" class="file-upload_input"
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
                        <label for="testimonial-edit-body-{{ $lang }}"
                          class="control-label mb-1">{{ $Lang->Common->Form->Testimonial }}
                          <span>*</span></label>
                        <textarea id="testimonial-edit-body-{{ $lang }}" name="testimonial[{{ $lang }}]" class="form-control"
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
            <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i>
              Save testimonial</button>
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
    function testimonialPhotoUrl(photo) {
      const value = String(photo || '').trim();
      if (!value) return @json($mediaUrls->fallback());
      if (/^https?:\/\//i.test(value)) return value;

      const normalized = '/' + value.replace(/^\/+/, '');
      const modernMarker = normalized.toLowerCase().indexOf('/storage/media/');
      if (modernMarker >= 0) return normalized.slice(modernMarker);
      if (normalized.toLowerCase().startsWith('/storage/photos/')) return normalized;

      return @json(url('admin/testimonial/photo')) + '/' + encodeURIComponent(value);
    }

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
              $(`.modal #testimonial-edit-language-${lang}`).val(lang);
              $(`.modal #testimonial-edit-name-${lang}`).val(data.name);
              $(`.modal #testimonial-edit-designation-${lang}`).val(data.designation);
              $(`.modal #testimonial-edit-order-${lang}`).val(data.order_by);
              $(`.modal #testimonial-edit-body-${lang}`).val(data.testimonial);
              if (data.photo) {
                $(`.modal #e_upload_img_${lang}`).attr('src', testimonialPhotoUrl(data.photo));
              } else {
                $(`.modal #e_upload_img_${lang}`).attr('src', @json($mediaUrls->fallback()));
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
