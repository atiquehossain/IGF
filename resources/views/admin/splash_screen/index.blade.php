@extends('admin.layouts.master')

@section('content')
    @php
        $canManageSplash = app(\App\Http\Middleware\Permission::class)
            ->allows(auth('admin')->user(), 'splash.screen.store');
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
                        </div>
                    </div>
                    <div class="card-body">
                        @unless($canManageSplash)
                            <div class="alert alert-info" role="status">Read-only access. You can review the visitor announcement, but you cannot change it.</div>
                        @endunless
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
                        <form action="{{ route('splash.screen.store') }}" method="post" enctype="multipart/form-data">
                            <fieldset @disabled(!$canManageSplash)>
                            <div class="tab-content" id="pills-tabContent">

                                @csrf
                                <input name="uuid" type="hidden" class="form-control" value="{{ @$uuid }}">
                                <input type="hidden" name="enabled" value="0">
                                <div class="alert alert-info"><strong>Visitor announcement</strong><p class="mb-2">When enabled, this appears once to each visitor after its release date. Saving new content makes the updated announcement appear again.</p><label class="mb-0"><input type="checkbox" name="enabled" value="1" @checked($splashEnabled)> Show this announcement on the public website</label></div>
                                @foreach ($translations as $translation)
                                <?php
                                    $isActive = '';
                                    $lang = $translation->id;
                                    $splashScreen = null;
                                    if($translation->id == 'en') {
                                        $isActive  = 'show active';
                                    }
                                    if ($splashScreens->isNotEmpty()) {
                                        $splashScreen = $splashScreens->where('language', $lang)->first();
                                    }
                                    
                                ?>
                                    <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">
                                        <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$splashScreen->id }}">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                        <div class="form-group has-success">
                                                <label for="title" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                                                <input id="title" name="title[{{$lang}}]" type="text" value="{{old('title.'. $lang, @$splashScreen->title )}}"
                                                    class="form-control" required data-e2e="splash-screen-title-{{ $lang }}">
                                                @if ($errors->has('title.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('title.'. $lang) }}</small>
                                                @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="details">{{ $Lang->Common->Form->Details }}</label>
                                            <textarea class="form-control form-control-danger my-editor" name="details[{{$lang}}]" data-e2e="splash-screen-details-{{ $lang }}">
                                                {{old('details.'. $lang, @$splashScreen->details)}}
                                            </textarea>
                                            @if ($errors->has('details.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('details.'. $lang) }}</small>
                                            @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="published_at_{{$lang}}">{{ $Lang->Common->Form->ReleaseDate }}</label>
                                            <input id="published_at_{{$lang}}" name="published_at[{{$lang}}]" type="text" class="form-control datepicker" required
                                                value="{{ old('published_at.'. $lang, $splashScreen?->published_at?->format('d-m-Y') ?? now()->format('d-m-Y')) }}">
                                            @if ($errors->has('published_at.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('published_at.'. $lang) }}</small>
                                            @endif
                                        </div>
                                        

                                    </div>
                                @endforeach

                                <div class="col-md-12 m-b-20 text-right">
                                    @if($canManageSplash)
                                    <button type="submit" class="btn btn-success btn-sm" name="save">
                                        <i class="fa fa-save"></i> {{ $Lang->Common->Save }}
                                    </button>
                                    @endif
                                </div>

                            </div>
                            </fieldset>
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
