@extends('admin.layouts.master')

@section('content')
    @php($sourceAlbum = $albums->firstWhere('language', 'en') ?: $albums->first())
    <div class="content pb-0">

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6 d-flex flex-wrap justify-content-end" style="gap: 8px;">
                                <a class="btn igf-btn igf-btn-secondary" href="{{ (int) optional($sourceAlbum)->status === 1 ? route('frontend.gallery', ['album_id' => $sourceAlbum->id]) : route('frontend.gallery') }}" target="_blank" rel="noopener">
                                    <i class="fa fa-external-link" aria-hidden="true"></i> {{ (int) optional($sourceAlbum)->status === 1 ? 'Preview live album' : 'View live gallery' }}
                                </a>
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('album.index') }}" id="go-back">
                                    <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert {{ (int) optional($sourceAlbum)->status === 1 ? 'alert-success' : 'alert-warning' }}" role="status">
                            <strong>Public visibility:</strong> {{ (int) optional($sourceAlbum)->status === 1 ? 'Published. Published photos in this album can appear publicly.' : 'Draft. Every photo in this album is hidden from visitors.' }} Publication can be changed from the album list.
                        </div>
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
                        <form action="{{ route('album.update') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="pills-tabContent">
                                <input name="uuid" type="hidden" class="form-control" value="{{ $uuid }}">

                            @method('PUT')
                            @csrf
                            @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = $translation->id;
                                if($translation->id == 'en') {
                                    $isActive  = 'show active';
                                }
                                $album = $albums->where('language', $lang)->first();
                             ?>
                                <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                    <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                    <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$album->id }}">
                                    <div class="form-group has-success">
                                            <label for="album_name_{{$lang}}" class="control-label mb-1">Album name <span>*</span></label>
                                            <input id="album_name_{{$lang}}" name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang, @$album->name) }}"
                                                class="form-control" required data-e2e="album-name-{{ $lang }}">
                                            @if ($errors->has('name.'.$lang))
                                                <small
                                                    class="help-block form-text text-danger">{{ $errors->first('name.'.$lang) }}</small>
                                            @endif
                                    </div>
                                </div>
                            @endforeach

                            <div class="col-md-12 m-b-20 text-right">
                                <button type="submit" class="btn btn-success btn-sm" name="save" value="1">
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
