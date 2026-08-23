@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">
        <h1 class="sr-only">{{ $title }}</h1>

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('service.index') }}">
                                    <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($isLocalization)
                            <ul class="nav nav-pills mb-3" id="service-create-tabs" role="tablist" aria-label="Service language">
                                @foreach ($translations as $translation)
                                    <?php
                                    $isActive = '';
                                    if ($translation->id == 'en') {
                                        $isActive = 'active';
                                    }
                                    ?>
                                    <li class="nav-item" data-id="{{ $translation->id }}">
                                        <a class="nav-link {{ $isActive }}" id="service-create-tab-{{ $translation->id }}"
                                            data-toggle="pill" href="#service-create-pane-{{ $translation->id }}" role="tab"
                                            aria-controls="service-create-pane-{{ $translation->id }}"
                                            aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{ $translation->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form action="{{ route('service.store') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="service-create-tab-content">

                                @csrf
                                @foreach ($translations as $translation)
                                    <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    if ($translation->id == 'en') {
                                        $isActive = 'show active';
                                    }

                                    $categories = $categorylist->where('language', $lang);
                                    ?>
                                <div class="tab-pane fade {{ $isActive }}" id="service-create-pane-{{ $translation->id }}"
                                        role="tabpanel" aria-labelledby="service-create-tab-{{ $translation->id }}">
                                        <input name="language[{{ $lang }}]" type="hidden" class="form-control"
                                            value="{{ $lang }}">

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="card border mb-2 p-3">
                                                <div class="card-title bg-secondary text-white">
                                                    <h4 class="m-0 p-2">{{ $Lang->Services }} {{ $Lang->Common->Information }}</h4>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-9">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <div class="form-group has-success">
                                                                        <label for="service-create-name-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Title }}</label>
                                                                        <input id="service-create-name-{{$lang}}" name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang) }}" class="form-control" data-e2e="page-name-{{ $lang }}">
                                                                        @if ($errors->has('name.'. $lang))
                                                                        <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                <div class="col-6">
                                                                    <div class="form-group has-success">
                                                                        <label for="service-create-category-{{$lang}}">{{ $Lang->Category }}</label>
                                                                        <select id="service-create-category-{{$lang}}" class="form-control form-control-danger" name="category_id[{{$lang}}]"
                                                                            data-e2e="page-category-id-{{ $lang }}">
                                                                            <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Category }}</option>
                                                                            @foreach ($categories as $category)
                                                                            <option value="{{ @$category->id }}" @if (old('category_id.'. $lang)==$category->id) selected @endif>
                                                                                {{ @$category->name }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        @if ($errors->has('category_id.'. $lang))
                                                                        <small class="help-block form-text text-danger">{{ $errors->first('category_id.'. $lang) }}</small>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                
                                                                
                                                            </div>
                                                        </div>

                                                        <div class="col-md-3">
                                                            <div class="form-group text-center">
                                                                <small
                                                                    class="help-block form-text text-info">{{ $Lang->Common->Form->Provide400X343px }}
                                                                    *</small>
                                                                <div class="file-upload">
                                                                <label for="service-create-image-{{ $lang }}"
                                                                        class="file-upload_label">
                                                                        <img class="file-upload_img"
                                                                            id="upload_img_{{ $lang }}"
                                                                            src="{{ asset('/') }}image/no-image.png">
                                                                    </label>
                                                                    <input type="file"
                                                                        onchange="changefile(event, `upload_img_{{ $lang }}`)"
                                                                        name="image[{{ $lang }}]"
                                                                        value="{{ old('image.' . $lang) }}"
                                                                        id="service-create-image-{{ $lang }}"
                                                                        class="file-upload_input"
                                                                        data-e2e="project-image-{{ $lang }}">
                                                                </div>
                                                                <div style="clear: both"></div>
                                                                @if ($errors->has('image.' . $lang))
                                                                    <small
                                                                        class="help-block form-text text-danger">{{ $errors->first('image.' . $lang) }}</small>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <!-- end row -->
                                                     
                                                        <div class="col-md-12">
                                                            <div class="form-group has-success">
                                                                <label for="service-create-description-{{$lang}}">{{ $Lang->Common->Form->Description }}</label>
                                                                <textarea id="service-create-description-{{$lang}}" class="form-control form-control-danger my-editor" name="description[{{$lang}}]" data-e2e="page-description-{{ $lang }}">{{ old('description.'. $lang) }}</textarea>
                                                                @if ($errors->has('description.'. $lang))
                                                                <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        
                                                        
                                                        <div class="col-md-3">
                                                            <div class="form-group">
                                                                <label for="service-create-order-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                                                <input id="service-create-order-{{$lang}}" name="order_by[{{$lang}}]" type="number" class="form-control" value="{{ old('order_by.'. $lang) }}" data-e2e="page-order-by-{{ $lang }}">
                                                                @if ($errors->has('order_by.'. $lang))
                                                                <small class="help-block form-text text-danger">{{ $errors->first('order_by.'. $lang) }}</small>
                                                                @endif
                                                            </div>
                                                        </div>

                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                @endforeach

                                <div class="col-md-12 m-b-20 text-right">
                                    <button type="submit" class="btn igf-btn igf-btn-primary igf-btn-compact" name="save">
                                        <i class="fa fa-save" aria-hidden="true"></i> Create service
                                    </button>
                                    <button type="submit" name="save_and_update" value="1"
                                        class="btn igf-btn igf-btn-secondary igf-btn-compact">
                                        <i class="fa fa-save" aria-hidden="true"></i> Save and continue editing
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .file-upload_label {
            width: 200px;
            height: 80px;
        }
    </style>
@endsection

@section('custom-js')
    @include('admin.layouts.tinymce')
@endsection
