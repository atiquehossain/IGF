@extends('admin.layouts.master')
<?php
    $custom_inline_css  = '';
?>
@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="card-title">{{ $title }}</h4>
                        </div>
                        <div class="col-md-6">
                            <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('service.index') }}" id="go-back">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                            </a>
                        </div>
                    </div>
                </div>
                <?php 

                ?>
                <div class="modal-body">
                    <form class="form-horizontal" action="{{ route('service.update') }}" method="post" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        @method('PUT')
                        <input name="uuid" type="hidden" class="form-control" value="{{ @$id }}">
                        @if($isLocalization)
                        <ul class="nav nav-pills mb-3" id="service-edit-tabs" role="tablist" aria-label="Edit service language">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                if($translation->id == 'en') {
                                    $isActive  = 'active';
                                }
                             ?>
                            <li class="nav-item" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="service-edit-tab-{{$translation->id}}" data-toggle="pill" href="#service-edit-pane-{{$translation->id}}" role="tab" aria-controls="service-edit-pane-{{$translation->id}}" aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <div class="tab-content" id="service-edit-tab-content">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = $translation->id;
                             
                                if($translation->id == 'en') {
                                    $isActive  = 'show active';
                                }

                                $service = @$services->where('language', $lang)->first();

                                $categories = $categorylist->where('language', $lang);
                        

                                $isValidUrl = filter_var(@$service->path, FILTER_VALIDATE_URL);
                                if (empty($isValidUrl) && $service) {
                                    $service->path = route('service.image', ['img' => $service->path ?? '1']);
                                }
                             ?>
                            <div class="tab-pane fade {{ $isActive }}" id="service-edit-pane-{{$translation->id}}" role="tabpanel" aria-labelledby="service-edit-tab-{{$translation->id}}">

                                <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$service->id }}">
                                <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
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
                                                                    <label for="service-edit-name-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Title }}
                                                                        <span>*</span></label>
                                                                    <input id="service-edit-name-{{$lang}}" name="name[{{$lang}}]" type="text" value="{{  @$service->name }}" class="form-control" data-e2e="service-name-{{ $lang }}">
                                                                    @if ($errors->has('name.'. $lang))
                                                                    <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="col-6">
                                                                <div class="form-group has-success">
                                                                    <label for="service-edit-category-{{$lang}}"> {{ $Lang->Category }} <span>*</span></label>
                                                                    <select id="service-edit-category-{{$lang}}" class="form-control form-control-danger" name="category_id[{{$lang}}]"
                                                                        data-e2e="service-category-id-{{ $lang }}">
                                                                        <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Category }}</option>
                                                                        @foreach ($categories as $category)
                                                                        <option value="{{ @$category->id }}" @if ( @old('category_id.'. $lang, @$service->category_id) ==  @$category->id) selected @endif>
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
                                                                <label for="service-edit-image-{{ $lang }}"
                                                                    class="file-upload_label">
                                                                    <img class="file-upload_img"
                                                                        id="upload_img_{{ $lang }}"
                                                                        src="{{ @$service->path }}">
                                                                </label>
                                                                <input type="file"
                                                                    onchange="changefile(event, `upload_img_{{ $lang }}`)"
                                                                    name="image[{{ $lang }}]"
                                                                    value="{{ old('image.' . $lang, @$service->image) }}"
                                                                    id="service-edit-image-{{ $lang }}"
                                                                    class="file-upload_input"
                                                                    data-e2e="service-image-{{ $lang }}">
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
                                                            <label for="service-edit-description-{{$lang}}">{{ $Lang->Common->Form->Description }}</label>
                                                            <textarea id="service-edit-description-{{$lang}}" class="form-control form-control-danger my-editor" name="description[{{$lang}}]" data-e2e="service-description-{{ $lang }}">{{  @$service->description }}</textarea>
                                                            @if ($errors->has('description.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
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
                        </div>
                        <div class="col-md-12 m-b-20 text-right">
                            <button type="submit" class="btn igf-btn igf-btn-primary igf-btn-compact" name="save">
                                <i class="fa fa-save" aria-hidden="true"></i> Save service
                            </button>
                                <button type="submit" name="save_and_update" value="1" class="btn igf-btn igf-btn-secondary igf-btn-compact">
                                    <i class="fa fa-save" aria-hidden="true"></i> Save and continue editing
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
@include('admin.layouts.tinymce',['contentStyle' => @$custom_inline_css])
@endsection
