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
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn btn-sm btn-secondary float-right" href="{{ route('gallery.index') }}" id="go-back">
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
                            <li class="nav-item main" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="{{$translation->id}}-tab" data-toggle="pill" href="#{{$translation->id}}" role="tab" aria-controls="{{$translation->id}}" aria-selected="true">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <form action="{{ route('gallery.store') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="pills-tabContent">

                                @csrf
                                @foreach ($translations as $translation)
                                <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    if($translation->id == 'en') {
                                        $isActive  = 'show active';
                                    }

                                  $albumsList =  $albums->where('language', $lang);
                                ?>
                                    <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">

                                        <div>
                                        <label for="type" class="control-label mb-1">{{ $Lang->Album }} <span>*</span></label>
                                            <div class="input-group mb-3">
                                                <select name="album_id[{{$lang}}]" class="form-control" required data-e2e="gallery-album-id-{{ $lang }}">
                                                    <option value="">{{ $Lang->Common->Form-> Select }} album</option>
                                                    @foreach ($albumsList as $album)
                                                    <option value="{{ $album->id }}" {{ old('album_id.'. $lang) == $album->id ? 'selected' : '' }}>
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
                                                <label for="name[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                                <input name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang) }}"
                                                    class="form-control" required data-e2e="gallery-name-{{ $lang }}">
                                                @if ($errors->has('name.'. $lang))
                                                    <small
                                                        class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                                @endif
                                        </div>


                                        <div class="form-group has-success">
                                            <label for="description[{{$lang}}]">Image alternative text ( <small class="text-info">Describe the image for visitors using a screen reader; maximum 120 characters.</small>)</label>
                                            <textarea class="form-control form-control-danger" name="description[{{$lang}}]" rows="4" maxlength="120" data-e2e="gallery-description-{{ $lang }}">{{old('description.'. $lang)}}</textarea>
                                            @if ($errors->has('description.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('description.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success" style="display: none">
                                            <label for="url[{{$lang}}]" class="control-label mb-1">{{ $Lang->Common->Form->Redirect }} Url <span>*</span></label>
                                            <input name="url[{{$lang}}]" type="text" value="{{old('url.'. $lang)}}" class="form-control">
                                            @if($errors->has('url.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('url.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group text-center">
                                            <small class="help-block form-text text-info">{{ $Lang->Common->Form->Provide1180px }}
                                                <br> {{ $Lang->Common->Form->Provide1180px_2 }}</small>
                                            <div class="file-upload">
                                                <label for="gallery_image_{{$lang}}" class="file-upload_label">
                                                    <img class="file-upload_img" id="upload_img_{{$lang}}" src="{{ asset('/')}}image/no-image.png">
                                                </label>
                                                <input type="file" onchange="changefile(event, `upload_img_{{$lang}}`)" name="image[{{$lang}}]" value="{{old('image.'. $lang)}}" id="gallery_image_{{$lang}}" class="file-upload_input" data-e2e="gallery-image-{{ $lang }}">
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

@if($canCreateAlbum)
{{-- Modal Album --}}
<div class="modal fade" id="albamModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="mediumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form class="fileUploadFormEdit" action="{{route('album.store')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <strong class="card-title">Album</strong>
                    <button type="button" class="close cancel" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    @if($isLocalization)
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    @foreach ($translations as $translation)
                        <?php
                            $isActive = '';
                            if($translation->id == 'en') {
                                $isActive  = 'active';
                            }
                            ?>
                        <li class="nav-item">
                        <a class="nav-link {{ $isActive }}" id="{{$translation->id}}-tab" data-toggle="pill" href="#{{$translation->id}}-albam" role="tab" aria-controls="{{$translation->id}}" aria-selected="true">{{$translation->name}}</a>
                        </li>
                    @endforeach
                    </ul>
                    @endif

                    <div class="tab-content" id="pills-tabContent">

                    @csrf
                    @foreach ($translations as $translation)
                    <?php
                        $isActive = '';
                        $lang = $translation->id;
                        if($translation->id == 'en') {
                            $isActive  = 'show active';
                        }
                    ?>
                        <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}-albam" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                            <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">

                            <div class="form-group has-success">
                                <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name}}<span>*</span></label>
                                <input name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang) }}" class="form-control" required>
                                @if ($errors->has('name.'. $lang))
                                <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                @endif
                            </div>

                        </div>

                    @endforeach
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info submit_ mt-3"><i class="fa fa-magic"></i>&nbsp; {{ $Lang->Common->Submit }}</button>
                    <button type="button" class="btn btn-danger cancel mt-3" data-dismiss="modal"><i class="fa fa-trash-o"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .open_album {
        cursor: pointer;
    }
</style>
@endif

@endsection

@section('custom-js')
<script>
@if($canCreateAlbum)
$(".open_album").click(function() {
    $('#albamModal').modal('show');
});
@endif

</script>

@endsection
