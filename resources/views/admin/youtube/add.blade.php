@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">
        <h1 class="sr-only">{{ $title }}</h1>

        <div class="row justify-content-md-center justify-content-lg-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">{{ $title }}</h4>
                            </div>
                            <div class="col-md-6">
                                <a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('youtube.index') }}" id="go-back">
                                    <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($isLocalization)
                        <ul class="nav nav-pills mb-3" id="youtube-create-tabs" role="tablist" aria-label="YouTube item language">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                if($translation->id == 'en') {
                                    $isActive  = 'active';
                                }
                             ?>
                            <li class="nav-item" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="youtube-create-tab-{{$translation->id}}" data-toggle="pill" href="#youtube-create-pane-{{$translation->id}}" role="tab" aria-controls="youtube-create-pane-{{$translation->id}}" aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <form action="{{ route('youtube.store') }}" method="post" enctype="multipart/form-data">
                            <div class="tab-content" id="youtube-create-tab-content">

                                @csrf
                                @foreach ($translations as $translation)
                                <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    if($translation->id == 'en') {
                                        $isActive  = 'show active';
                                    }
                                ?>
                                    <div class="tab-pane fade {{ $isActive }}" id="youtube-create-pane-{{$translation->id}}" role="tabpanel" aria-labelledby="youtube-create-tab-{{$translation->id}}">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">

                                        <div class="form-group has-success">
                                            <label for="youtube-create-name-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                            <input id="youtube-create-name-{{$lang}}" name="name[{{$lang}}]" type="text" value="{{old('name.'. $lang)}}" class="form-control" required data-e2e="youtube-name-{{ $lang }}">
                                            @if($errors->has('name.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="youtube-create-video-id-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->VideoID}} <span>*</span></label>
                                            <input id="youtube-create-video-id-{{$lang}}" name="video_id[{{$lang}}]" type="text" value="{{old('video_id.'. $lang)}}" class="form-control" required data-e2e="youtube-video-id-{{ $lang }}">
                                            @if($errors->has('video_id.'. $lang))
                                            <small class="help-block form-text text-danger">{{ $errors->first('video_id.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="youtube-create-activation-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->ActivisionTime }} ({{ $Lang->Common->Form->Minute }}) </label>
                                                    <input id="youtube-create-activation-{{$lang}}" name="activision_time[{{$lang}}]" type="number" value="{{old('activision_time.'. $lang)}}" class="form-control" data-e2e="youtube-activision-time-{{ $lang }}">
                                                    @if($errors->has('activision_time.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('activision_time.'. $lang) }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="youtube-create-duration-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->DurationTime }} ({{ $Lang->Common->Form->Minute }})</label>
                                                    <input id="youtube-create-duration-{{$lang}}" name="duration_time[{{$lang}}]" type="number" value="{{old('duration_time.'. $lang)}}" class="form-control" data-e2e="youtube-duration-time-{{ $lang }}">
                                                    @if($errors->has('duration_time.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('duration_time.'. $lang) }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group has-success">
                                                    <label for="youtube-create-order-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->OrderNo }}</label>
                                                    <input id="youtube-create-order-{{$lang}}" name="order_by[{{$lang}}]" type="number" value="{{old('order_by.'. $lang)}}" class="form-control" data-e2e="youtube-order-by-{{ $lang }}">
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
                                    <button type="submit" class="btn igf-btn igf-btn-primary igf-btn-compact" name="save">
                                        <i class="fa fa-save" aria-hidden="true"></i> Create YouTube item
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
