@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Reset administrator password</h1>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong>Reset administrator password</strong></div>
                <div class="card-body">
                    <p>You are resetting the password for <strong>{{ $admin->name }}</strong> ({{ $admin->username }}).</p>
                    <p class="text-muted">A cryptographically random temporary password will be shown once. The account will be forced to choose a new password immediately after signing in.</p>

                    @if(session('temporary_password'))
                        <div class="alert alert-warning">
                            <strong>Copy this temporary password now:</strong>
                            <div class="mt-2"><code style="font-size:1.1rem; user-select:all">{{ session('temporary_password') }}</code></div>
                            <small>It will not be shown again. Share it through an approved secure channel.</small>
                        </div>
                    @else
                        <form method="post" action="{{ route('admin.reset.perform', $admin->id) }}">
                            @csrf
                            <button class="btn igf-btn igf-btn-secondary" type="submit"><i class="fa fa-key" aria-hidden="true"></i> Generate one-time password</button>
                            <a class="btn igf-btn igf-btn-tertiary" href="{{ route('admin.index') }}"><i class="fa fa-times" aria-hidden="true"></i> Cancel</a>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
