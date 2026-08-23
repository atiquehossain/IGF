@extends('admin.layouts.master')

@section('content')
    @php
        $canCreateAlbum = app(\App\Http\Middleware\Permission::class)
            ->allows(auth('admin')->user(), 'album.store');
    @endphp
    <div class="content pb-0">

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h1 class="card-title">{{ $title }}</h1>
                            </div>
                            <div class="col-md-6">
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('gallery.index') }}" id="go-back">
                                    <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($isLocalization)
                        <ul class="nav nav-pills mb-3" id="gallery-language-tabs" role="tablist" aria-label="Gallery languages">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                if($translation->id == 'en') {
                                    $isActive  = 'active';
                                }
                             ?>
                            <li class="nav-item main" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="gallery-{{$translation->id}}-tab" data-toggle="pill" href="#gallery-{{$translation->id}}-panel" role="tab" aria-controls="gallery-{{$translation->id}}-panel" aria-selected="{{ $translation->id === 'en' ? 'true' : 'false' }}">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <form action="{{ route('gallery.update') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="gallery-language-panels">
                                @method('PUT')
                                @csrf

                                <input name="uuid" type="hidden" class="form-control" value="{{ @$uuid }}">
                                @foreach ($translations as $translation)
                                <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    if($translation->id == 'en') {
                                        $isActive  = 'show active';
                                    }

                                  $albumsList =  $albums->where('language', $lang);
                                  $gallery =  $galleries->where('language', $lang)->first();

                                ?>
                                    <div class="tab-pane fade {{ $isActive }}" id="gallery-{{$translation->id}}-panel" role="tabpanel" aria-labelledby="gallery-{{$translation->id}}-tab">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                        <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$gallery->id }}">
                                        <div>
                                        <label for="gallery_album_{{$lang}}" class="control-label mb-1">{{ $Lang->Album }} <span>*</span></label>
                                            <div class="input-group mb-3">
                                                <select id="gallery_album_{{$lang}}" name="album_id[{{$lang}}]" class="form-control" required data-gallery-album-language="{{ $lang }}" data-e2e="gallery-album-id-{{ $lang }}">
                                                    <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Album }}</option>
                                                    @foreach ($albumsList as $album)
                                                    <option value="{{ $album->id }}" {{ $gallery->album_id == $album->id ? 'selected' : '' }}>
                                                        {{ $album->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                @if($canCreateAlbum)
                                                    <div class="input-group-prepend">
                                                        <button type="button" class="input-group-text open_album" aria-label="Create a new album" title="Create a new album">+</button>
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($errors->has('album_id.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('album_id.'. $lang) }}
                                            </small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                                <label for="gallery_name_{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                                <input id="gallery_name_{{$lang}}" name="name[{{$lang}}]" type="text" value="{{old('name.'. $lang, @$gallery->name)}}"
                                                    class="form-control" required data-e2e="gallery-name-{{ $lang }}">
                                                @if ($errors->has('name.'. $lang))
                                                    <small
                                                        class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="gallery_description_{{$lang}}">Image alternative text ( <small class="text-info">Describe the image for visitors using a screen reader; maximum 120 characters.</small>)</label>
                                            <textarea id="gallery_description_{{$lang}}" class="form-control form-control-danger" name="description[{{$lang}}]" rows="4" maxlength="120" data-e2e="gallery-description-{{ $lang }}">{{old('description.'. $lang, @$gallery->description)}}</textarea>
                                            @if ($errors->has('description.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success" style="display: none">
                                            <label for="url[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Redirect }} Url <span>*</span></label>
                                            <input name="url[{{$lang}}]" type="text" value="{{old('url.'. $lang, @$gallery->url)}}" class="form-control">
                                            @if($errors->has('url.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('url.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group text-center">
                                            <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide1180px }}
                                                <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                                            <div class="file-upload">
                                                <label for="gallery_image_{{$lang}}" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_img_{{$lang}}"
                                                        src="{{ $gallery->display_image_url }}"
                                                        onerror="this.onerror=null;this.src='{{ asset('image/no-image.png') }}'"
                                                        alt="Current image for {{ $gallery->name }}">
                                                </label>
                                                <input type="file" onchange="changefile(event, `upload_img_{{$lang}}`)" name="image[{{$lang}}]" value="{{old('image.'. $lang, @$gallery->image)}}" id="gallery_image_{{$lang}}" class="file-upload_input" data-e2e="gallery-image-{{ $lang }}">
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

@if($canCreateAlbum)
{{-- Modal Album --}}
<div class="modal fade" id="albamModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="album-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form class="album-create-form" action="{{route('album.store')}}" method="POST">
                <div class="modal-header">
                    <strong class="card-title" id="album-modal-title">Create a new album</strong>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">The gallery information already entered on this page will be preserved.</p>
                    <div id="album-modal-feedback" class="alert alert-danger" role="alert" hidden></div>

                    @if($isLocalization)
                    <ul class="nav nav-pills mb-3" id="album-language-tabs" role="tablist" aria-label="Album languages">
                    @foreach ($translations as $translation)
                        <?php
                            $isActive = '';
                            if($translation->id == 'en') {
                                $isActive  = 'active';
                            }
                            ?>
                        <li class="nav-item">
                        <a class="nav-link {{ $isActive }}" id="album-{{$translation->id}}-tab" data-toggle="pill" href="#album-{{$translation->id}}-panel" role="tab" aria-controls="album-{{$translation->id}}-panel" aria-selected="{{ $translation->id === 'en' ? 'true' : 'false' }}">{{$translation->name}}</a>
                        </li>
                    @endforeach
                    </ul>
                    @endif

                    <div class="tab-content" id="album-language-panels">

                    @csrf
                    @foreach ($translations as $translation)
                    <?php
                        $isActive = '';
                        $lang = $translation->id;
                        if($translation->id == 'en') {
                            $isActive  = 'show active';
                        }
                    ?>
                        <div class="tab-pane fade {{ $isActive }}" id="album-{{$translation->id}}-panel" role="tabpanel" aria-labelledby="album-{{$translation->id}}-tab">
                            <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">

                            <div class="form-group has-success">
                                <label for="album_name_{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Name}}<span>*</span></label>
                                <input id="album_name_{{$lang}}" name="name[{{$lang}}]" type="text" value="" class="form-control" required autocomplete="off">
                                @if ($errors->has('name.'. $lang))
                                <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                @endif
                            </div>

                        </div>

                    @endforeach
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary album-create-submit mt-3"><i class="fa fa-plus" aria-hidden="true"></i>&nbsp; Create album</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .open_album {
        cursor: pointer;
        min-width: 44px;
        min-height: 44px;
    }
</style>
@endif

@endsection

@section('custom-js')
<script>
@if($canCreateAlbum)
var albumModal = $('#albamModal');
var albumForm = albumModal.find('.album-create-form');
var albumFeedback = $('#album-modal-feedback');

$(".open_album").click(function() {
    albumFeedback.prop('hidden', true).text('');
    albumModal.modal('show');
});

albumForm.on('submit', function(event) {
    event.preventDefault();
    var form = $(this);
    var submitButton = form.find('.album-create-submit');
    if (submitButton.prop('disabled')) {
        return;
    }

    submitButton.prop('disabled', true).attr('aria-busy', 'true');
    albumFeedback.prop('hidden', true).text('');
    setAdminBusy(true);

    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        headers: {'Accept': 'application/json'},
        success: function(response) {
            (response.albums || []).forEach(function(album) {
                var select = $('[data-gallery-album-language]').filter(function() {
                    return String($(this).data('gallery-album-language')) === String(album.language);
                });
                if (!select.length) {
                    return;
                }
                var option = select.find('option[value="' + album.id + '"]');
                if (!option.length) {
                    option = $('<option>', {value: album.id, text: album.name}).appendTo(select);
                }
                select.val(String(album.id)).trigger('change');
            });
            toastrMsg('success', response.message || 'Album created successfully.');
            form[0].reset();
            albumModal.modal('hide');
        },
        error: function(error) {
            var message = adminErrorMessage(error);
            if (error.responseJSON && error.responseJSON.errors) {
                var fields = Object.keys(error.responseJSON.errors);
                if (fields.length && error.responseJSON.errors[fields[0]].length) {
                    message = error.responseJSON.errors[fields[0]][0];
                }
            }
            albumFeedback.text(message).prop('hidden', false);
            toastrMsg('error', message);
        },
        complete: function() {
            submitButton.prop('disabled', false).removeAttr('aria-busy');
            setAdminBusy(false);
        }
    });
});

albumModal.on('hidden.bs.modal', function() {
    albumFeedback.prop('hidden', true).text('');
});
@endif

</script>

@endsection
