@extends('admin.layouts.master')

@section('content')
@php
    $permission = app(\App\Http\Middleware\Permission::class);
    $admin = auth('admin')->user();
    $canEditSeo = $permission->allows($admin, 'seo.content.edit');
    $canCreatePage = $permission->allows($admin, 'page.create');
    $canEditPageBuilder = $permission->allows($admin, 'page.builder.edit');
@endphp
<div class="content pb-0">

    <div class="row justify-content-md-center justify-content-lg-center">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('category.index') }}" id="go-back">
                                    <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
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
                        if ($translation->id == 'en') {
                            $isActive = 'active';
                        }
                        ?>
                        <li class="nav-item" data-id="{{$translation->id}}">
                            <a class="nav-link {{ $isActive }}" id="{{$translation->id}}-tab" data-toggle="pill"
                                href="#{{$translation->id}}" role="tab" aria-controls="{{$translation->id}}"
                                aria-selected="true">{{$translation->name}}</a>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                    <form action="{{ route('category.update') }}" method="post" enctype="multipart/form-data">
                        <div class="tab-content" id="pills-tabContent">
                            @method('PUT')
                            @csrf

                            <input name="uuid" type="hidden" class="form-control" value="{{ @$uuid }}">
                            @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = @$translation->id;
                                if (@$translation->id == 'en') {
                                    $isActive = 'show active';
                                }

                                $category = $categories->where('language', $lang)->first();
                                $banners = @$bannerList->where('language', $lang);
                                $landingPages = $landingPagesByLanguage->get($lang, collect());
                                $displayMode = old('display_mode.'. $lang, @$category->display_mode ?: 'archive');
                                $landingPageUuid = old('landing_page_uuid.'. $lang, @$category->landing_page_uuid);
                                $selectedLandingPage = $landingPages->firstWhere('uuid', $landingPageUuid);

                                $isValidUrl = filter_var(@$category->path, FILTER_VALIDATE_URL);
                                if (empty($isValidUrl) && $category) {
                                    $category->path = route('category.image', $category->path);
                                }
                                ?>

                            <div class="tab-pane fade {{ @$isActive }}" id="{{@$translation->id}}" role="tabpanel" aria-labelledby="{{@$translation->id}}-tab">

                            <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$category->id }}">
                                <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group has-success">
                                            <label for="type[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Type }} <span>*</span></label>
                                            <select name="type[{{$lang}}]" type="text" class="form-control" required data-e2e="category-type-{{ $lang }}">
                                                <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                                <option value="category-services" {{ old('type.'. $lang, @$category->type) == 'category-services' ? 'selected' : '' }}>{{ $Lang->Services }}</option>
                                                <option value="category-pages" {{ old('type.'. $lang, @$category->type) == 'category-pages' ? 'selected' : '' }}>{{ $Lang->Pages }}</option>
                                            </select>
                                            @if($errors->has('type.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('type.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="name" class="control-label mb-1"> {{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input id="name" name="name[{{$lang}}]" type="text" value="{{old('name.'. $lang, @$category->name)}}"
                                                class="form-control" required data-e2e="category-name-{{ $lang }}">
                                            @if ($errors->has('name.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
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
                                                        src="{{ @$category->path }}">
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
                                     </div> --}}

                                     <div class="col-9">
                                        <div class="form-group has-success">
                                            <label for="banner_id" class="control-label mb-1">{{ $Lang->Common->Form-> Select }}
                                                {{ $Lang->BannerTitle }}</label>
                                            <select name="banner_id[{{$lang}}]" type="text" class="form-control" data-e2e="page-banner-id-{{ $lang }}">
                                                <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->BannerTitle }}</option>
                                                @foreach ($banners as $banner)
                                                <option value="{{  @$banner->id }}" {{  @$category->banner_id == $banner->id ? 'selected' : '' }}>
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
                                            <input name="name_enabled[{{$lang}}]" id="name_enabled_${{$lang}}" type="checkbox" value="1"
                                            <?php if($category->name_enabled == '1') { echo 'checked'; } ?>>
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
                                                <option value="landing_page" @selected($displayMode === 'landing_page')
                                                    @disabled($landingPages->isEmpty() && $displayMode !== 'landing_page')>Landing page — use one Page Builder page</option>
                                            </select>
                                            <small class="form-text text-muted">Landing mode replaces the card archive with the selected page's Page Builder sections.</small>
                                            @if ($errors->has('display_mode.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('display_mode.'. $lang) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group has-success">
                                            <label for="landing_page_uuid_{{ $lang }}" class="control-label mb-1">Landing page</label>
                                            <div class="d-flex align-items-center">
                                                <select id="landing_page_uuid_{{ $lang }}" name="landing_page_uuid[{{ $lang }}]"
                                                    class="form-control js-category-landing-page" @disabled($displayMode !== 'landing_page')
                                                    data-e2e="category-landing-page-{{ $lang }}">
                                                    <option value="">Choose a {{ strtoupper($lang) }} page assigned to this category</option>
                                                    @foreach($landingPages as $landingPage)
                                                        @php
                                                            $publicationStatus = $landingPage->publication_status ?: ($landingPage->status ? 'published' : 'draft');
                                                            $builderUrl = route('page.builder.edit', ['uuid' => $landingPage->uuid, 'locale' => $lang]);
                                                        @endphp
                                                        <option value="{{ $landingPage->uuid }}"
                                                            data-builder-url="{{ $builderUrl }}"
                                                            @selected($landingPageUuid === $landingPage->uuid)>
                                                            {{ $landingPage->name }} ({{ str_replace('_', ' ', $publicationStatus) }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if($canEditPageBuilder)
                                                    <a class="btn btn-sm btn-outline-primary ml-2 js-category-builder-shortcut {{ $selectedLandingPage ? '' : 'd-none' }}"
                                                        href="{{ $selectedLandingPage ? route('page.builder.edit', ['uuid' => $selectedLandingPage->uuid, 'locale' => $lang]) : '#' }}">
                                                        Open Page Builder
                                                    </a>
                                                @endif
                                            </div>
                                            @if($landingPages->isEmpty())
                                                <small class="form-text text-muted">No {{ strtoupper($lang) }} pages are assigned to this category.@if($canCreatePage) <a href="{{ route('page.create') }}">Create and assign one</a>.@endif</small>
                                            @else
                                                <small class="form-text text-muted">Only pages assigned to this category and language can be selected.</small>
                                            @endif
                                            @if ($errors->has('landing_page_uuid.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('landing_page_uuid.'. $lang) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group has-success">
                                    <label for="description"> {{ $Lang->Common->Form->Description }}</label>
                                    <textarea class="form-control form-control-danger my-editor" name="description[{{$lang}}]"
                                        rows="4" data-e2e="category-description-{{ $lang }}">{{old('description.'. $lang, @$category->description)}}</textarea>
                                    @if ($errors->has('description.'. $lang))
                                    <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang)
                                        }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="inline_css">{{ $Lang->Common->Form->CSS }}</label>
                                    <textarea class="form-control form-control-danger" name="inline_css[{{$lang}}]"
                                        rows="4">{{old('inline_css.'. $lang, @$category->inline_css)}}</textarea>
                                    @if ($errors->has('inline_css.'. $lang))
                                    <small class="help-block form-text text-danger">{{ $errors->first('inline_css.'. $lang)
                                        }}</small>
                                    @endif
                                </div>

                                <div class="alert alert-info" role="note">
                                    <strong>Search &amp; Sharing:</strong>
                                    @if($category && $canEditSeo)
                                        Use the single guided editor for Google previews, social cards, visibility, permalink and schema.
                                        <a class="btn btn-sm btn-outline-primary ml-2" href="{{ route('seo.content.edit', ['type' => 'category', 'id' => $category->id, 'locale' => $lang]) }}">Open Search &amp; Sharing</a>
                                    @elseif($category)
                                        Your SEO editor can manage this category from Search &amp; Sharing.
                                    @else
                                        Save this translation first; its guided Search &amp; Sharing editor will then become available.
                                    @endif
                                </div>

                            </div>
                            @endforeach
                            <div class="col-md-12 m-b-20 text-right">
                                <button type="submit" class="btn btn-success btn-sm" name="save">
                                    <i class="fa fa-save"></i> {{ $Lang->Common->Save }}
                                </button>
                                <button type="submit" name="save_and_update" value="1" class="btn igf-btn igf-btn-secondary igf-btn-compact">
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
@endsection

@section('custom-js')
@include('admin.layouts.tinymce')

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-category-landing-config').forEach(function (container) {
        var mode = container.querySelector('.js-category-display-mode');
        var page = container.querySelector('.js-category-landing-page');
        var shortcut = container.querySelector('.js-category-builder-shortcut');

        function syncLandingPageControls() {
            var usesLandingPage = mode.value === 'landing_page';
            page.disabled = !usesLandingPage;

            if (!shortcut) {
                return;
            }

            var selectedOption = page.options[page.selectedIndex];
            var builderUrl = selectedOption ? selectedOption.dataset.builderUrl : '';
            shortcut.classList.toggle('d-none', !usesLandingPage || !builderUrl);
            shortcut.href = builderUrl || '#';
        }

        mode.addEventListener('change', syncLandingPageControls);
        page.addEventListener('change', syncLandingPageControls);
        syncLandingPageControls();
    });
});
</script>

@endsection
