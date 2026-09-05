@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text($key, $replace);
    $interfaceLocale = app()->getLocale() === 'bn' ? 'bn' : 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ $interfaceLocale }}">

    <head>
        <!-- Meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <meta name="description" content="{{ $ui('admin_login.meta_description') }}">
        <meta name="author" content="Ignite Global Foundation">


        <!-- Title -->
        <title>{{ $title }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{asset('image/favicon/apple-touch-icon.png')}}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{asset('image/favicon/favicon-32x32.png')}}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{asset('image/favicon/favicon-16x16.png')}}">
        <link rel="manifest" href="{{asset('image/favicon/site.webmanifest')}}">
        <link rel="stylesheet" href="{{asset('admin-assets/assets/css/login.bootstrap.min.css')}}">
        <link rel="stylesheet" href="{{asset('admin-assets/assets/css/login.style.css')}}">

    </head>
    <body>

        <main id="form" aria-labelledby="admin-login-heading">
            <div class="container admin-section">
                <div class="admin-login-shell">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <div class="logo-section text-center">
                                <img src="{{ asset('image/logo.png') }}" alt="{{ $ui('admin_login.logo_alt') }}">
                            </div>
                        </div>
                    </div>
                    <div id="userform">
                        <div class="tab-content">
                            <div class="tab-pane fade  active  in" id="signup">
                                <h1 class="text-uppercase text-center" id="admin-login-heading">{{ $ui('admin_login.heading') }}</h1>
                                <p class="admin-login-intro">{{ $ui('admin_login.intro') }}</p>
                                @if (session('message'))
                                    <div class="alert alert-danger" role="alert">{{ session('message') }}</div>
                                @endif
                                @if ($errors->any())
                                    <div class="alert alert-danger" role="alert" id="admin-login-errors">
                                        {{ $ui('admin_login.failure_summary') }}
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('admin.login') }}" aria-label="{{ $ui('admin_login.form_label') }}" @if($errors->any()) aria-describedby="admin-login-errors" @endif>
                                    {{ csrf_field() }}
                                    <div class="form-group">
                                        <label for="username">{{ $ui('admin_login.username') }} <span class="req" aria-hidden="true">*</span><span class="sr-only"> ({{ $ui('admin_login.required') }})</span></label>
                                        <input type="text" name="username" class="form-control" id="username" value="{{ old('username') }}" required aria-required="true" autocomplete="username" autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                                        @if ($errors->has('username'))
                                        <span class="text-danger" id="username-error" role="alert">
                                            <strong>{{ $errors->first('username') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="form-group mt-4">
                                        <label for="password">{{ $ui('admin_login.password') }} <span class="req" aria-hidden="true">*</span><span class="sr-only"> ({{ $ui('admin_login.required') }})</span></label>
                                        <input type="password" name="password" class="form-control" id="password" required aria-required="true" autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                                        @if ($errors->has('password'))
                                        <span class="text-danger" id="password-error" role="alert">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="mrgn-30-top">
                                        <button type="submit" class="btn btn-larger btn-block">{{ $ui('admin_login.submit') }}</button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </main>
    </body>

</html>
