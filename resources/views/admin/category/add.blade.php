@extends('admin.layouts.master')

@section('content')
    @php
        $permission = app(\App\Http\Middleware\Permission::class);
        $admin = auth('admin')->user();
        $canCreatePage = $permission->allows($admin, 'page.create');
    @endphp
    <div class="content pb-0">

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn btn-sm btn-secondary float-right" href="{{ route('category.index') }}" id="go-back">
                                    <i class="fa fa-arrow-circle-left"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($isLocalization)
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                if($translation->id == 'en') {
                                    $isActive  = 'active';
                                }
                             ?>
                            <li class="nav-item" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="{{$translation->id}}-tab" data-toggle="pill" href="#{{$translation->id}}" role="tab" aria-controls="{{$translation->id}}" aria-selected="true">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <form action="{{ route('category.store') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="pills-tabContent">

                            {{ csrf_field() }}
                            @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = $translation->id;
                                if($translation->id == 'en') {
                                    $isActive  = 'show active';
                                }

                                $banners = $bannerList->where('language', $lang);
                                $landingPages = $landingPagesByLanguage->get($lang, collect());
                                $displayMode = old('display_mode.'. $lang, 'archive');
                             ?>
                                <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                    <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group has-success">
                                                <label for="type[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Type }} <span>*</span></label>
                                                <select name="type[{{$lang}}]" type="text" class="form-control" required data-e2e="category-type-{{ $lang }}">
                                                    <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                                    <option value="category-services" {{ old('type.'. $lang) == 'category-services' ? 'selected' : '' }}>{{ $Lang->Services }}</option>
                                                    <option value="category-pages" {{ old('type.'. $lang) == 'category-pages' ? 'selected' : '' }}>{{ $Lang->Pages }}</option>
                                                </select>
                                                @if($errors->has('type.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('type.'. $lang) }}</small>
                                                @endif
                                            </div>
                                            <div class="form-group has-success">
                                                    <label for="name" class="control-label mb-1"> {{ $Lang->Common->Form->Name }} <span>*</span></label>
                                                    <input id="name" name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang) }}"
                                                        class="form-control" required data-e2e="category-name-{{ $lang }}">
                                                    @if ($errors->has('name.'. $lang))
                                                        <small
                                                            class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                    @endif
                                            </div>
                                        </div>

                                        {{-- <div class="col-md-3">
                                            <div class="form-group text-center">
                                                <small
                                                    class="help-block form-text text-info">{{ $Lang->Common->Form->Provide1180px }}
                                                    *</small>
                                                <div class="file-upload">
                                                    <label for="project_image_{{ $lang }}"
                                                        class="file-upload_label">
                                                        <img class="file-upload_img"
                                                            id="upload_img_{{ $lang }}"
                                                            src="{{ asset('/') }}image/no-image.png">
                                                    </label>
                                                    <input type="file"
                                                        onchange="changefile(event, `upload_img_{{ $lang }}`)"
                                                        name="image[{{ $lang }}]"
                                                        value="{{ old('image.' . $lang) }}"
                                                        id="project_image_{{ $lang }}"
                                                        class="file-upload_input"
                                                        data-e2e="project-image-{{ $lang }}">
                                                </div>
                                                <div style="clear: both"></div>
                                                @if ($errors->has('image.' . $lang))
                                                    <small
                                                        class="help-block form-text text-danger">{{ $errors->first('image.' . $lang) }}</small>
                                                @endif
                                            </div> 
                                        </div>--}}
                                    
                                        <div class="col-9">
                                            <div class="form-group has-success">
                                                <label for="banner_id" class="control-label mb-1">{{ $Lang->Common->Form-> Select }}
                                                    {{ $Lang->BannerTitle }}</label>
                                                <select name="banner_id[{{$lang}}]" type="text" class="form-control" data-e2e="category-banner-id-{{ $lang }}">
                                                    <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->BannerTitle }}</option>
                                                    @foreach ($banners as $banner)
                                                    <option value="{{ $banner->id }}" {{ old('banner_id.'. $lang) == $banner->id ? 'selected' : '' }}>
                                                        {{ $banner->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                @if ($errors->has('banner_id.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('banner_id.'. $lang) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-center">
                                            <div class="form-group m-0 mt-3">
                                                <label for="name_enabled_${{$lang}}" class="control-label">{{ $Lang->Common->Form->Name }} {{ $Lang->Common->Form->Enabled }}</label>
                                                <input name="name_enabled[{{$lang}}]" id="name_enabled_${{$lang}}" type="checkbox" value="1" checked>
                                                @if ($errors->has('name_enabled.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('name_enabled.'. $lang) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row js-category-landing-config">
                                        <div class="col-md-6">
                                            <div class="form-group has-success">
                                                <label for="display_mode_{{ $lang }}" class="control-label mb-1">Category display</label>
                                                <select id="display_mode_{{ $lang }}" name="display_mode[{{ $lang }}]"
                                                    class="form-control js-category-display-mode" data-e2e="category-display-mode-{{ $lang }}">
                                                    <option value="archive" @selected($displayMode === 'archive')>Archive — show category pages as cards</option>
                                                    <option value="landing_page" @selected($displayMode === 'landing_page') disabled>Landing page — use one Page Builder page</option>
                                                </select>
                                                <small class="form-text text-muted">New categories start as an archive. Save it, assign a page to this category, then return here to use that page as the landing experience.</small>
                                                @if ($errors->has('display_mode.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('display_mode.'. $lang) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group has-success">
                                                <label for="landing_page_uuid_{{ $lang }}" class="control-label mb-1">Landing page</label>
                                                <select id="landing_page_uuid_{{ $lang }}" name="landing_page_uuid[{{ $lang }}]"
                                                    class="form-control js-category-landing-page" disabled data-e2e="category-landing-page-{{ $lang }}">
                                                    <option value="">Save this category before choosing a page</option>
                                                </select>
                                                @if($canCreatePage)
                                                    <small class="form-text text-muted"><a href="{{ route('page.create') }}">Create a page after saving this category</a>, then assign it to this category.</small>
                                                @endif
                                                @if ($errors->has('landing_page_uuid.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('landing_page_uuid.'. $lang) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="description"> {{ $Lang->Common->Form->Description }} </label>
                                        <textarea class="form-control form-control-danger my-editor" name="description[{{$lang}}]"
                                            rows="4" data-e2e="category-description-{{ $lang }}">{{ old('description.'. $lang) }}</textarea>
                                        @if ($errors->has('description.'. $lang))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="inline_css">{{ $Lang->Common->Form->CSS }}</label>
                                        <textarea class="form-control form-control-danger" name="inline_css[{{$lang}}]"
                                            rows="4">{{ old('inline_css.'. $lang) }}</textarea>
                                        @if ($errors->has('inline_css.'. $lang))
                                            <small
                                                class="help-block form-text text-danger">{{ $errors->first('inline_css.'. $lang) }}</small>
                                        @endif
                                    </div>

                                    <div class="alert alert-info" role="note">
                                        <strong>Search &amp; Sharing:</strong> Create this category first, then use the guided editor for its Google preview, social image, visibility, permalink and schema.
                                    </div>
                                </div>
                            @endforeach

                            <div class="col-md-12 m-b-20 text-right">
                                <button type="submit" class="btn btn-success btn-sm" name="save">
                                    <i class="fa fa-save"></i> {{ $Lang->Common->Save }}
                                </button>
                                <button type="submit" name="save_and_update" value="1" class="btn btn-success btn-sm">
                                    <i class="fa fa-save"></i> {{ $Lang->Common->SaveAndUpdate }}
                                </button>
                            </div>

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
