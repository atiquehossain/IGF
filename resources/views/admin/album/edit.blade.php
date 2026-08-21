@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn btn-sm btn-secondary float-right" href="{{ route('album.index') }}" id="go-back">
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
                                            <label for="name" class="control-label mb-1"> {{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input id="name" name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang, @$album->name) }}"
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
