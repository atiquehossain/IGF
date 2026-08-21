<!DOCTYPE html>
<html lang="en">

    <head>
        <!-- Meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <meta name="description" content="">
        <meta name="author" content="">


        <!-- Title -->
        <title>{{ $Lang->Admin }} | {{ $Lang->PlatformTitle }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{asset('image/favicon/apple-touch-icon.png')}}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{asset('image/favicon/favicon-32x32.png')}}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{asset('image/favicon/favicon-16x16.png')}}">
        <link rel="manifest" href="{{asset('image/favicon/site.webmanifest')}}">
        <link rel="stylesheet" href="{{asset('admin-assets/assets/css/login.bootstrap.min.css')}}">
        <link rel="stylesheet" href="{{asset('admin-assets/assets/css/login.style.css')}}">

        <style>
            .admin-section {max-width: 600px;float: left;}
        </style>
    </head>
    <body>

        <main id="form">
            <div class="container admin-section">
                <div class="col-lg-8 col-lg-offset-2 col-md-6 col-md-offset-3 col-md-8 col-md-offset-2">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <div class="logo-section text-center">
                                <img src="{{ asset('image/logo.png') }}" alt="Ignite">
                            </div>
                        </div>
                    </div>
                    <div id="userform">
                        <div class="tab-content">
                            <div class="tab-pane fade  active  in" id="signup">
                                <h1 class="text-uppercase text-center">Administrator sign in</h1>
                                @if (session('message'))
                                    <div class="alert alert-info" role="status">{{ session('message') }}</div>
                                @endif
                                @if ($errors->any())
                                    <div class="alert alert-danger" role="alert" id="admin-login-errors">
                                        Sign in was not successful. Check the highlighted fields and try again.
                                    </div>
                                @endif
                                <br>
                                <form method="POST" action="{{ route('admin.login') }}" @if($errors->any()) aria-describedby="admin-login-errors" @endif>
                                    {{ csrf_field() }}
                                    <div class="form-group">
                                        <label for="username">Administrator username <span class="req" aria-hidden="true">*</span></label>
                                        <input type="text" name="username" class="form-control" id="username" value="{{ old('username') }}" required autocomplete="username" autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                                        @if ($errors->has('username'))
                                        <span class="text-danger" id="username-error">
                                            <strong>{{ $errors->first('username') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="form-group mt-4">
                                        <label for="password">Password <span class="req" aria-hidden="true">*</span></label>
                                        <input type="password" name="password" class="form-control" id="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                                        @if ($errors->has('password'))
                                        <span class="text-danger" id="password-error">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="mrgn-30-top">
                                        <button type="submit" class="btn btn-larger btn-block">Log in</button>
                                    </div>
                                </form>

                                <br><br>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </main>
    </body>

    <script src="{{asset('admin-assets/assets/js/vendor/jquery.min.js')}}"></script>
    <script type="text/javascript">
    $('#form').find('input, textarea').on('keyup blur focus', function (e) {

        var $this = $(this),
                label = $this.prev('label');

        if (e.type === 'keyup') {
            if ($this.val() === '') {
                label.removeClass('active highlight');
            } else {
                label.addClass('active highlight');
            }
        } else if (e.type === 'blur') {
            if ($this.val() === '') {
                label.removeClass('active highlight');
            } else {
                label.removeClass('highlight');
            }
        } else if (e.type === 'focus') {

            if ($this.val() === '') {
                label.removeClass('highlight');
            }
            else if ($this.val() !== '') {
                label.addClass('highlight');
            }
        }

    });
    </script>
</html>
