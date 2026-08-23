@extends('admin.layouts.master')

@section('content')
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
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('banner.index') }}" id="go-back">
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
                        <form action="{{ route('banner.update') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="pills-tabContent">
                                @csrf
                                @method('PUT')
                                <input name="uuid" type="hidden" class="form-control" value="{{ @$id }}">
                                @foreach ($translations as $translation)
                                <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    if($translation->id == 'en') {
                                        $isActive  = 'show active';
                                    }
                                    $banner = @$banners->where('language', $lang)->first();
                                    $legacyName = (string) @$banner->name;
                                    $legacyMatch = [];
                                    preg_match('/<b>(.*?)<\/b>/i', $legacyName, $legacyMatch);
                                    $legacyHeadline = isset($legacyMatch[1]) ? trim(strip_tags($legacyMatch[1])) : trim(strip_tags($legacyName));
                                    $legacySubheadline = isset($legacyMatch[0]) ? trim(strip_tags(str_replace($legacyMatch[0], '', $legacyName))) : '';
                                    $headlineValue = @$banner->headline ?: $legacyHeadline;
                                    $subheadlineValue = @$banner->subheadline ?: $legacySubheadline;
                                    $previewUrl = @$banner->image_url ?: asset('/image/no-image.png');
                                ?>
                                    <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                        <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$banner->id }}">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                        <div class="alert alert-info py-2">
                                            Add each part separately. The website will arrange the text consistently on desktop and mobile.
                                        </div>

                                        <div class="form-group has-success">
                                                <label for="eyebrow[{{$lang}}]" class="control-label mb-1">Small heading (optional)</label>
                                                <input name="eyebrow[{{$lang}}]" type="text" value="{{ old('eyebrow.'. $lang, @$banner->eyebrow) }}"
                                                    class="form-control" maxlength="255" data-e2e="banner-eyebrow-{{ $lang }}">
                                                <small class="form-text text-muted">Example: Our work. Leave empty to use the website-wide banner default.</small>
                                                @if ($errors->has('eyebrow.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('eyebrow.'. $lang) }}</small>
                                                @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="headline[{{$lang}}]" class="control-label mb-1">Headline <span>*</span></label>
                                            <input name="headline[{{$lang}}]" type="text" value="{{ old('headline.'. $lang, $headlineValue) }}"
                                                class="form-control" maxlength="255" required data-e2e="banner-headline-{{ $lang }}">
                                            @if ($errors->has('headline.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('headline.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="subheadline[{{$lang}}]" class="control-label mb-1">Supporting headline (optional)</label>
                                            <input name="subheadline[{{$lang}}]" type="text" value="{{ old('subheadline.'. $lang, $subheadlineValue) }}"
                                                class="form-control" maxlength="255" data-e2e="banner-subheadline-{{ $lang }}">
                                            @if ($errors->has('subheadline.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('subheadline.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="type[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Type }} <span>*</span></label>
                                            <select name="type[{{$lang}}]" type="text" class="form-control" required data-e2e="banner-type-{{ $lang }}">
                                                <option value="banner-home" {{ @$banner->type == 'banner-home' ? 'selected' : '' }}>{{ $Lang->BannerTitle }} {{ $Lang->HomeTitle }}</option>
                                                <option value="banner-page" {{ @$banner->type == 'banner-page' ? 'selected' : '' }}>{{ $Lang->BannerTitle }} {{ $Lang->Common->Page }}</option>
                                            </select>
                                            <small class="form-text text-muted">Home banners appear as the homepage slider when the Home page builder does not contain a Hero section. Page banners can be assigned to individual pages.</small>
                                            @if($errors->has('type.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('type.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="description[{{$lang}}]">Short description (optional)</label>
                                            <textarea class="form-control form-control-danger" name="description[{{$lang}}]" rows="4" maxlength="500" data-e2e="banner-description-{{ $lang }}">{{old('description.'. $lang, @$banner->description)}}</textarea>
                                            @if ($errors->has('description.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="cta_label[{{$lang}}]" class="control-label mb-1">Button label (optional)</label>
                                            <input name="cta_label[{{$lang}}]" type="text" value="{{old('cta_label.'. $lang, @$banner->cta_label)}}" class="form-control" maxlength="120" data-e2e="banner-cta-label-{{ $lang }}">
                                            <small class="form-text text-muted">Example: Donate now. Leave empty to use the website-wide default.</small>
                                            @if($errors->has('cta_label.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('cta_label.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="cta_url[{{$lang}}]" class="control-label mb-1">Button destination (optional)</label>
                                            <input name="cta_url[{{$lang}}]" type="text" value="{{old('cta_url.'. $lang, @$banner->cta_url)}}" class="form-control" maxlength="2048" placeholder="/donate" data-e2e="banner-cta-url-{{ $lang }}">
                                            @if($errors->has('cta_url.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('cta_url.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <details class="mb-3">
                                            <summary class="text-muted">Legacy compatibility fields</summary>
                                            <div class="form-group has-success mt-3">
                                                <label for="name[{{$lang}}]" class="control-label mb-1">Legacy combined heading (optional)</label>
                                                <input name="name[{{$lang}}]" type="text" value="{{old('name.'. $lang, @$banner->name)}}"
                                                    class="form-control" maxlength="255" data-e2e="banner-name-{{ $lang }}">
                                                <small class="form-text text-muted">Existing integrations may use this value. Clear it to generate a compatible value from the headline fields above.</small>
                                                @if ($errors->has('name.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                @endif
                                            </div>
                                            <div class="form-group has-success">
                                                <label for="url[{{$lang}}]" class="control-label mb-1">Legacy redirect URL (optional)</label>
                                                <input name="url[{{$lang}}]" type="text" value="{{old('url.'. $lang, @$banner->url)}}" class="form-control" maxlength="2048">
                                                <small class="form-text text-muted">Used only when the button destination above is empty.</small>
                                                @if($errors->has('url.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('url.'. $lang) }}</small>
                                                @endif
                                            </div>
                                        </details>

                                        <div class="form-group has-success">
                                            <label for="image_alt[{{$lang}}]" class="control-label mb-1">Image alternative text</label>
                                            <input name="image_alt[{{$lang}}]" type="text" value="{{old('image_alt.'. $lang, @$banner->image_alt)}}" class="form-control" maxlength="255" data-e2e="banner-image-alt-{{ $lang }}">
                                            <small class="form-text text-muted">Briefly describe what the image shows for visitors using screen readers.</small>
                                            @if($errors->has('image_alt.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('image_alt.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group text-center">
                                            <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide1590px }}
                                                <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                                            <div class="file-upload">
                                                <label for="banner_image_{{$lang}}" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_img_{{$lang}}" src="{{ $previewUrl }}" alt="">
                                                </label>
                                                <input type="file" onchange="changefile(event, `upload_img_{{$lang}}`)" name="image[{{$lang}}]" value="{{old('image.'. $lang, @$banner->image)}}" id="banner_image_{{$lang}}" class="file-upload_input" data-e2e="banner-image-{{ $lang }}">
                                            </div>
                                            <div style="clear: both"></div>
                                            @if($errors->has('image.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('image.'. $lang) }}</small>
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
<style>
    .file-upload_label {
        width: 200px;
        height: 80px;
    }
</style>
@endsection
