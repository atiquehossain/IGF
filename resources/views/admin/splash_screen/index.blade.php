@extends('admin.layouts.master')

@section('content')
    @php
        $canManageSplash = app(\App\Http\Middleware\Permission::class)
            ->allows(auth('admin')->user(), 'splash.screen.store');
    @endphp
    <div class="content pb-0">
        <h1 class="sr-only">{{ $title }}</h1>

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
                        <ul class="nav nav-pills mb-3" id="splash-screen-tabs" role="tablist" aria-label="Visitor announcement language">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                if($translation->id == 'en') {
                                    $isActive  = 'active';
                                }
                             ?>
                            <li class="nav-item" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="splash-screen-tab-{{$translation->id}}" data-toggle="pill" href="#splash-screen-pane-{{$translation->id}}" role="tab" aria-controls="splash-screen-pane-{{$translation->id}}" aria-selected="{{ $translation->id == 'en' ? 'true' : 'false' }}">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <form action="{{ route('splash.screen.store') }}" method="post" enctype="multipart/form-data">
                            <fieldset @disabled(!$canManageSplash)>
                            <div class="tab-content" id="splash-screen-tab-content">

                                @csrf
                                <input name="uuid" type="hidden" class="form-control" value="{{ @$uuid }}">
                                <input type="hidden" name="enabled" value="0">
                                <div class="alert alert-info"><strong>Visitor announcement</strong><p class="mb-2">When enabled, this appears once to each visitor after its release date. Saving new content makes the updated announcement appear again.</p><div class="custom-control custom-checkbox"><input id="splash-screen-enabled" class="custom-control-input" type="checkbox" name="enabled" value="1" @checked($splashEnabled)><label class="custom-control-label" for="splash-screen-enabled">Show this announcement on the public website</label></div></div>
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
                                    <div class="tab-pane fade {{ $isActive }}" id="splash-screen-pane-{{$translation->id}}" role="tabpanel" aria-labelledby="splash-screen-tab-{{$translation->id}}">
                                        <input name="id[{{$lang}}]" type="hidden" class="form-control" value="{{ @$splashScreen->id }}">
                                        <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                        <div class="form-group has-success">
                                                <label for="splash-screen-title-{{$lang}}" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                                                <input id="splash-screen-title-{{$lang}}" name="title[{{$lang}}]" type="text" value="{{old('title.'. $lang, @$splashScreen->title )}}"
                                                    class="form-control" required data-e2e="splash-screen-title-{{ $lang }}">
                                                @if ($errors->has('title.'. $lang))
                                                    <small class="help-block form-text text-danger">{{ $errors->first('title.'. $lang) }}</small>
                                                @endif
                                        </div>

                                        <div class="form-group has-success">
                                            <label for="splash-screen-details-{{$lang}}">{{ $Lang->Common->Form->Details }}</label>
                                            <textarea id="splash-screen-details-{{$lang}}" class="form-control form-control-danger my-editor" name="details[{{$lang}}]" data-e2e="splash-screen-details-{{ $lang }}">
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
                                    <button type="submit" class="btn igf-btn igf-btn-primary igf-btn-compact" name="save">
                                        <i class="fa fa-save" aria-hidden="true"></i> Save visitor announcement
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
