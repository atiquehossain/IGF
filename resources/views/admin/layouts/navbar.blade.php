<?php

use App\Helper\Translation;
use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Support\AdminUi;
use Illuminate\Support\Facades\Auth;

$user = Admin::findOrFail(Auth::guard('admin')->id());
$adminPermission = app(Permission::class);
$canSearchPages = $adminPermission->allows($user, 'page.index');
$canCreatePages = $adminPermission->allows($user, 'page.create');
$showPageCreateShortcut = $canCreatePages && !in_array((string) Route::currentRouteName(), ['page.index', 'page.create'], true);
$userImageUrl = $user->image
    ? route('admin.image', $user->image)
    : asset('image/no-user.png');
$translations = Translation::languageList();
$ui = static fn (string $key, array $replace = []): string => AdminUi::text($key, $replace);
?>

<div id="right-panel" class="right-panel">
    <header id="header" class="header igf-topbar">
        <div class="top-left igf-topbar-left">
            <button id="menuToggle" class="menutoggle" type="button" aria-label="{{ $ui('shell.toggle_navigation') }}" aria-expanded="false">
                <i class="fa fa-bars" aria-hidden="true"></i>
            </button>
            @if($canSearchPages)
                <form class="igf-admin-search" action="{{ route('page.index') }}" method="get" role="search">
                    <label class="sr-only" for="admin-search">{{ $ui('shell.search_content') }}</label>
                    <i class="fa fa-search" aria-hidden="true"></i>
                    <input id="admin-search" name="search" type="search" value="{{ request('search') }}" placeholder="{{ $ui('shell.search_placeholder') }}" autocomplete="off">
                </form>
                <details class="igf-mobile-search">
                    <summary aria-label="{{ $ui('shell.open_search') }}"><i class="fa fa-search" aria-hidden="true"></i></summary>
                    <form action="{{ route('page.index') }}" method="get" role="search">
                        <label class="sr-only" for="admin-mobile-search">{{ $ui('shell.search_content') }}</label>
                        <input id="admin-mobile-search" name="search" type="search" value="{{ request('search') }}" placeholder="{{ $ui('shell.search_placeholder') }}" autocomplete="off">
                        <button type="submit" aria-label="{{ $ui('shell.search_content') }}">{{ $ui('shell.search_button') }}</button>
                    </form>
                </details>
            @endif
        </div>

        <div class="top-right igf-topbar-right">
            @if($showPageCreateShortcut)
                <a class="igf-quick-create igf-btn igf-btn-primary" href="{{ route('page.create') }}" aria-label="{{ $ui('shell.create_page') }}">
                    <i class="fa fa-plus" aria-hidden="true"></i><span>{{ $ui('shell.new_page') }}</span>
                </a>
            @endif
            @if ($isLocalization)
                <div class="dropdown for-notification igf-language">
                    <button class="dropdown-toggle" type="button" id="notification" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ $ui('shell.change_language') }}">
                        <i class="fa fa-language" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="notification">
                        @foreach ($translations as $translation)
                            <a class="dropdown-item media" href="{{ route('admin.language', $translation->id) }}">
                                <img src="{{ asset($translation->assets) }}" alt=""> {{ $translation->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="user-area dropdown">
                <button class="dropdown-toggle active" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ $ui('shell.account_menu', ['name' => $user->name]) }}">
                    <img class="user-avatar rounded-circle" src="{{ $userImageUrl }}" alt="">
                </button>
                <div class="user-menu dropdown-menu dropdown-menu-right">
                    <span class="nav-link igf-user-name"><i class="fa fa-user" aria-hidden="true"></i>{{ $user->name }}</span>
                    <a class="nav-link" href="{{ route('admin.password') }}"><i class="fa fa-exchange" aria-hidden="true"></i>{{ $Lang->Common->ChangePassword }}</a>
                    <form method="post" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="nav-link btn btn-link"><i class="fa fa-power-off" aria-hidden="true"></i>{{ $ui('shell.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
