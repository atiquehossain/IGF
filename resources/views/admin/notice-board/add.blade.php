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
                <a class="btn btn-sm btn-secondary float-right" href="{{ route('notice.board.index') }}" id="go-back">
                  <i class="fa fa-arrow-circle-left"></i> {{ $Lang->Common->GoBack }}
                </a>
              </div>
            </div>
          </div>

          <div class="modal-body">
            <form class="form-horizontal" action="{{ route('notice.board.store') }}" method="post"
              enctype="multipart/form-data">
              {{ csrf_field() }}

              <div class="row">
                <div class="col-6">
                  <div class="form-group has-success">
                    <label for="title" class="control-label mb-1">{{ $Lang->Common->Form->Title }}</label>
                    <input name="title" type="text" value="{{ old('title') }}" class="form-control"
                      data-e2e="title">
                    @if ($errors->has('title'))
                      <small class="help-block form-text text-danger">{{ $errors->first('title') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-6">
                  <div class="form-group">
                    <label for="sub_title" class="control-label mb-1">{{ $Lang->Common->Form->SubTitle }}</label>
                    <input name="sub_title" type="text" class="form-control" value="{{ old('sub_title') }}"
                      data-e2e="sub-title">
                    @if ($errors->has('sub_title'))
                      <small class="help-block form-text text-danger">{{ $errors->first('sub_title') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-md-12">
                  <div class="card border mb-3">
                    <div class="card-body py-3">
                      <h5 class="mb-1">Language connection</h5>
                      <p class="text-muted mb-3">Choose the language. If this is a translated version of an existing English event, connect it here so search engines and the language switcher know they belong together.</p>
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group mb-md-0">
                            <label for="language">Content language</label>
                            <select class="form-control" id="language" name="language" required>
                              @foreach($locales as $locale)
                                <option value="{{ $locale->id }}" @selected(old('language', $defaultLocale) === $locale->id)>{{ $locale->name }} ({{ $locale->native_name }})</option>
                              @endforeach
                            </select>
                            @error('language')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                          </div>
                        </div>
                        <div class="col-md-8">
                          <div class="form-group mb-0">
                            <label for="translation_source_id">Translation of</label>
                            <select class="form-control" id="translation_source_id" name="translation_source_id">
                              <option value="">A new, separate event</option>
                              @foreach($translationSources as $source)
                                <option value="{{ $source->id }}" @selected((string) old('translation_source_id', $translationSourceId) === (string) $source->id)>{{ $source->title }}{{ $source->slug ? ' · /event/'.$source->slug : '' }}</option>
                              @endforeach
                            </select>
                            <small class="form-text text-muted">Leave this as “separate event” for new English content. Translation Center connects new Bangla versions automatically.</small>
                            @error('translation_source_id')<small class="help-block form-text text-danger">{{ $message }}</small>@enderror
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                @include('admin.notice-board._event_fields')

                <div class="col-md-3">
                  <div class="form-group">
                    <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                    <input name="order_by" type="number" class="form-control" value="{{ old('order_by') }}"
                      data-e2e="order-by">
                    @if ($errors->has('order_by'))
                      <small class="help-block form-text text-danger">{{ $errors->first('order_by') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-3">
                  <div class="form-group has-success">
                    <label for="published_at" class="control-label mb-1">{{ $Lang->DateOfRelease }}
                      <span>*</span></label>
                    <input name="published_at" type="text"
                      value="{{ date('d-m-Y', strtotime(old('published_at') ?? date('Y-m-d'))) }}"
                      class="form-control datepicker" readonly required>
                    @if ($errors->has('published_at'))
                      <small class="help-block form-text text-danger">{{ $errors->first('published_at') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-3">
                  <div class="form-group has-success">
                    <label for="location" class="control-label mb-1">{{ $Lang->Common->Form->Location }}</label>
                    <input name="location" type="text" value="{{ old('location') }}" class="form-control">
                    @if ($errors->has('location'))
                      <small class="help-block form-text text-danger">{{ $errors->first('location') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-3">
                  <div class="form-group text-center">
                    <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide410px }}
                      <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                    <div class="file-upload">
                      <label for="image_path" class="file-upload_label">
                        <img class="file-upload_img" id="upload_img" src="{{ asset('/') }}image/no-image.png">
                      </label>
                      <input type="file" onchange="changefile(event, `upload_img`)" name="image_path"
                        value="{{ old('image_path') }}" id="image_path" class="file-upload_input" data-e2e="image_path">
                    </div>
                    <div style="clear: both"></div>
                    @if ($errors->has('image_path'))
                      <small class="help-block form-text text-danger">{{ $errors->first('image_path') }}</small>
                    @endif
                  </div>
                </div>

                <div class="col-md-12">
                  <div class="form-group has-success">
                    <label for="description">{{ $Lang->Common->Form->Description }}</label>
                    <textarea class="form-control form-control-danger my-editor" name="description" data-e2e="description">{{ old('description') }}</textarea>
                    @if ($errors->has('description'))
                      <small class="help-block form-text text-danger">{{ $errors->first('description') }}</small>
                    @endif
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="form-group has-success">
                    <label for="description">CSS</label>
                    <textarea class="form-control form-control-danger" name="inline_css" rows="6" data-e2e="inline-css"> {{ old('inline_css') }}</textarea>
                    @if ($errors->has('inline_css'))
                      <small class="help-block form-text text-danger">{{ $errors->first('inline_css') }}</small>
                    @endif
                  </div>
                </div>

              </div>

              <div class="col-md-12 m-b-20 text-right">
                <button type="submit" class="btn btn-success btn-sm" name="save">
                  <i class="fa fa-save"></i> {{ $Lang->Common->Save }}
                </button>
                <button type="submit" name="save_and_update" value="1" class="btn btn-success btn-sm">
                  <i class="fa fa-save"></i> {{ $Lang->Common->SaveAndUpdate }}
                </button>
              </div>
            </form>

          </div>
        </div>


      </div>
    </div>
  </div>
@endsection

@section('custom-js')
  @include('admin.layouts.tinymce')
@endsection
