@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn btn-sm btn-secondary float-right" href="{{ route('youtube.index') }}" id="go-back">
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
                        <form action="{{ route('youtube.store') }}" method="post" enctype="multipart/form-data">
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
                                    <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">

                                        <div class="form-group has-success">
                                            <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input id="name" name="name[{{$lang}}]" type="text" value="{{old('name.'. $lang)}}" class="form-control" required data-e2e="youtube-name-{{ $lang }}">
                                            @if($errors->has('name.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="video_id" class="control-label mb-1">{{ $Lang->Common->Form->VideoID}} <span>*</span></label>
                                            <input id="video_id" name="video_id[{{$lang}}]" type="text" value="{{old('video_id.'. $lang)}}" class="form-control" required data-e2e="youtube-video-id-{{ $lang }}">
                                            @if($errors->has('video_id.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('video_id.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="activision_time" class="control-label mb-1">{{ $Lang->Common->Form->ActivisionTime }} ({{ $Lang->Common->Form->Minute }}) </label>
                                                    <input id="activision_time" name="activision_time[{{$lang}}]" type="number" value="{{old('activision_time.'. $lang)}}" class="form-control" data-e2e="youtube-activision-time-{{ $lang }}">
                                                    @if($errors->has('activision_time.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('activision_time.'. $lang) }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="duration_time" class="control-label mb-1">{{ $Lang->Common->Form->DurationTime }} ({{ $Lang->Common->Form->Minute }})</label>
                                                    <input id="duration_time" name="duration_time[{{$lang}}]" type="number" value="{{old('duration_time.'. $lang)}}" class="form-control" data-e2e="youtube-duration-time-{{ $lang }}">
                                                    @if($errors->has('duration_time.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('duration_time.'. $lang) }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderNo }}</label>
                                                    <input id="activision_time" name="order_by[{{$lang}}]" type="number" value="{{old('order_by.'. $lang)}}" class="form-control" data-e2e="youtube-order-by-{{ $lang }}">
                                                    @if($errors->has('order_by.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('order_by.'. $lang) }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-6">
                                            </div>
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
