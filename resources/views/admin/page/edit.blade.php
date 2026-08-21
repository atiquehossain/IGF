@extends('admin.layouts.master')
<?php
    $custom_inline_css  = '';
?>
@section('content')
@php($canEditSeo = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'seo.content.edit'))
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
                            <a class="btn btn-sm btn-secondary float-right" href="{{ route('page.index') }}" id="go-back">
                                <i class="fa fa-arrow-circle-left"></i> {{ $Lang->Common->GoBack }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal" action="{{ route('page.update') }}" method="post" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        @method('PUT')
                        <input name="uuid" type="hidden" class="form-control" value="{{ @$id }}">
                        <input name="expected_version" type="hidden" value="{{ (int) $editorVersion }}">
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
                        <div class="tab-content" id="pills-tabContent">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = $translation->id;
                                if($translation->id == 'en') {
                                    $isActive  = 'show active';
                                }

                                $page = @$pages->where('language', $lang)->first();
                                $categories = @$categorylist->where('language', $lang);
                                $banners = @$bannerList->where('language', $lang);
                                $custom_inline_css .= @$page->inline_css;

                                $isValidUrl = filter_var(@$page->thumbnail, FILTER_VALIDATE_URL);
                                if (empty($isValidUrl) && $page) {
                                    $page->thumbnail = route('page.thumbnail', $page->thumbnail);
                                }
                             ?>
                            <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">

                                <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$page->id }}">
                                <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card border mb-2 p-3">
                                            <div class="card-title bg-secondary text-white">
                                                <h4 class="m-0 p-2">{{ $Lang->Common->Page }} {{ $Lang->Common->Information }}</h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-group has-success">
                                                            <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Title }}
                                                                <span>*</span></label>
                                                            <input name="name[{{$lang}}]" type="text" value="{{  @$page->name }}" class="form-control" data-e2e="page-name-{{ $lang }}">
                                                            @if ($errors->has('name.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-6">
                                                        <div class="form-group">
                                                            <label for="sub_title" class="control-label mb-1">{{ $Lang->Common->Form->SubTitle }}</label>
                                                            <input name="sub_title[{{$lang}}]" type="text" class="form-control" value="{{ @$page->sub_title }}" data-e2e="page-sub-title-{{ $lang }}">
                                                            @if ($errors->has('sub_title.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('sub_title.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="form-group has-success">
                                                            <label for="category_id"> {{ $Lang->Category }} <span>*</span></label>
                                                            <select class="form-control form-control-danger" name="category_id[{{$lang}}]" data-e2e="page-category-id-{{ $lang }}">
                                                                <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Category }}</option>
                                                                @foreach ($categories as $category)
                                                                <option value="{{ @$category->id }}" @if ( @$page->category_id ==  @$category->id) selected @endif>
                                                                    {{ @$category->name }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                            @if ($errors->has('category_id.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('category_id.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="form-group has-success">
                                                            <label for="banner_id" class="control-label mb-1">{{ $Lang->Common->Form-> Select }}
                                                                {{ $Lang->BannerTitle }}</label>
                                                            <select name="banner_id[{{$lang}}]" type="text" class="form-control" data-e2e="page-banner-id-{{ $lang }}">
                                                                <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Page }}</option>
                                                                @foreach ($banners as $banner)
                                                                <option value="{{  @$banner->id }}" {{  @$page->banner_id == $banner->id ? 'selected' : '' }}>
                                                                    {{ $banner->name }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                            @if ($errors->has('banner_id.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('banner_id.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-4">
                                                        <div class="form-group text-center">
                                                            <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide410px }}
                                                                <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                                                            <div class="file-upload">
                                                                <label for="thumbnail_{{$lang}}" class="file-upload_label">
                                                                    <img class="file-upload_img" id="upload_img_{{$lang}}" src="{{ @$page->thumbnail }}">
                                                                </label>
                                                                <input type="file" onchange="changefile(event, `upload_img_{{$lang}}`)" name="thumbnail[{{$lang}}]" value="{{old('thumbnail.'. $lang)}}" id="thumbnail_{{$lang}}" class="file-upload_input" data-e2e="thumbnail-{{ $lang }}">
                                                            </div>
                                                            <div style="clear: both"></div>
                                                            @if($errors->has('thumbnail.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('thumbnail.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-md-12">
                                                        <div class="form-group has-success">
                                                            <label for="description">{{ $Lang->Common->Form->Description }}</label>
                                                            <textarea class="form-control form-control-danger my-editor" name="description[{{$lang}}]" data-e2e="page-description-{{ $lang }}">{{  @$page->description }}</textarea>
                                                            @if ($errors->has('description.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="form-group has-success">
                                                            <label for="description">CSS</label>
                                                            <textarea class="form-control form-control-danger" name="inline_css[{{$lang}}]" rows="6" data-e2e="page-inline-css-{{ $lang }}">{{  @$page->inline_css }}</textarea>
                                                            @if ($errors->has('inline_css.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('inline_css.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="publish_by" class="control-label mb-1">Publish by</label>
                                                            <input name="publish_by[{{$lang}}]" type="text" class="form-control" value="{{  @$page->publish_by }}" required>
                                                            @if ($errors->has('publish_by'))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('publish_by') }}</small>
                                                            @endif
                                                        </div>
                                                    </div> -->
                                                    <!-- <div class="col-4">
                                                        <div class="form-group has-success">
                                                            <label for="published_at" class="control-label mb-1">Date of Release <span>*</span></label>
                                                            <?php $published_at = Date('d-m-Y', strtotime( @$page->published_at)); ?>
                                                            <input name="published_at[{{$lang}}]" type="text" value="{{  @$published_at }}" class="form-control datepicker" readonly required>
                                                            @if ($errors->has('published_at'))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('published_at') }}</small>
                                                            @endif
                                                        </div>
                                                    </div> -->
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                                            <input name="order_by[{{$lang}}]" type="number" class="form-control" value="{{  @$page->order_by }}" data-e2e="page-order-by-{{ $lang }}">
                                                            @if ($errors->has('order_by.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('order_by.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-center">
                                                        <div class="form-group m-0 mt-3">
                                                            <label for="name_enabled_${{$lang}}" class="control-label">{{ $Lang->Common->Form->Title }} {{ $Lang->Common->Form->Enabled }}</label>
                                                            <input name="name_enabled[{{$lang}}]" id="name_enabled_${{$lang}}" type="checkbox" value="1"
                                                            <?php if($page->name_enabled == '1') { echo 'checked'; } ?>>
                                                            @if ($errors->has('name_enabled.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('name_enabled.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2 d-flex align-items-center">
                                                        <div class="form-group m-0 mt-3">
                                                            <label for="sub_title_enabled${{$lang}}" class="control-label">Sub {{ $Lang->Common->Form->Title }} {{ $Lang->Common->Form->Enabled }}</label>
                                                            <input name="sub_title_enabled[{{$lang}}]" id="sub_title_enabled${{$lang}}" type="checkbox" value="1"
                                                            <?php if($page->name_enabled == '1') { echo 'checked'; } ?>>
                                                            @if ($errors->has('sub_title_enabled.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('sub_title_enabled.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="form-group">
                                                            <label for="is_relationship${{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->HasRelationship }}</label>
                                                            <input name="is_relationship[{{$lang}}]" id="is_relationship-{{$lang}}" type="checkbox" value="{{ @$page->is_relationship }}" {{ @$page->is_relationship ? 'checked' : '' }}>
                                                            <select data-placeholder="{{ $Lang->Common->Form->SelectOption }}" multiple="multiple" name="tags[{{$lang}}][]" class="form-control chosen-select" {{ !$page->is_relationship ? 'disabled' : '' }}>
                                                                @foreach ($tags as $tag)
                                                                 <option value="{{ $tag->id }}" {{ ($page->pageTags ?? collect())->contains('tag_id', $tag->id) ? 'selected' : '' }}>
                                                                    {{ $tag->name }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                            @if ($errors->has('is_relationship.'. $lang))
                                                            <small class="help-block form-text text-danger">{{ $errors->first('is_relationship.'. $lang) }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info my-2" role="note">
                                    <strong>Search &amp; sharing:</strong>
                                    @if($page && $canEditSeo)
                                        Use the single guided editor for Google previews, social cards, visibility, permalink and schema.
                                        <a class="btn btn-sm btn-outline-primary ml-2" href="{{ route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => $lang]) }}">Open Search &amp; Sharing</a>
                                    @elseif($page)
                                        Your SEO editor can manage this page from Search &amp; Sharing.
                                    @else
                                        Save this translation first; its guided Search &amp; Sharing editor will then become available.
                                    @endif
                                </div>
                            </div>
                        @endforeach
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
@include('admin.layouts.tinymce',['contentStyle' => @$custom_inline_css])

<link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/lib/chosen/chosen.min.css') }}">
<script src="{{ asset('admin-assets/assets/js/lib/chosen/chosen.jquery.min.js') }}"></script>
<script>
    $(".chosen-select").chosen({
        disable_search_threshold: 10,
        no_results_text: "Oops, nothing found!",
        width: "100%"
    });
    
    const lang = @json($lang);

    $(`#is_relationship-${lang}`).change(function () {
        const isChecked = $(this).is(':checked');
        const $select = $(`select[name="tags[${lang}][]"]`).prop('disabled', !isChecked);

        // Clear selection if unchecked
        if (!isChecked) $select.val([]);

        // Update Chosen UI
        $select.trigger("chosen:updated");
        
        // Update checkbox value
        $(this).val(isChecked ? 1 : 0);
    });

</script>
@endsection
