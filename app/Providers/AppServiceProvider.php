<?php

namespace App\Providers;

use App\Contracts\PrivateFileDeletion;
use App\Contracts\SeoSearchPerformanceGateway;
use App\Contracts\SeoTrafficAnalyticsGateway;
use App\Services\GoogleAnalyticsSeoGateway;
use App\Services\GoogleSearchConsoleGateway;
use App\Services\PrivateApplicationDocumentService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Session;
use App\Helper\Translation;
use App\Http\Middleware\Permission;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SeoSearchPerformanceGateway::class, GoogleSearchConsoleGateway::class);
        $this->app->bind(SeoTrafficAnalyticsGateway::class, GoogleAnalyticsSeoGateway::class);
        $this->app->bind(PrivateFileDeletion::class, PrivateApplicationDocumentService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        //Link for Add New Button
        View::composer('*', function ($addLink) {
            try {
                if (!empty(Auth::user()) && Auth::guard('admin')->check()) {
                    $admin = Auth::guard('admin')->user();
                    $routeName = \Request::route()->getName();
                    $userMenus = AuthMenu::where('link', $routeName)->first();
                    $userMenuAction = MenuAction::where('auth_menu_id', @$userMenus->id)
                        ->where('type', 1)->where('status', 1)->first();
                    if ($userMenuAction
                        && Route::has($userMenuAction->link)
                        && app(Permission::class)->allows($admin, $userMenuAction->link)) {
                        $addLink->with('addNewLink', @$userMenuAction->link);
                    } else {
                        $addLink->with('addNewLink', null);
                    }
                } else {
                    $addLink->with('addNewLink', null);
                }
            } catch (Exception $exc) {
                $addLink->with('addNewLink', null);
            }
        });

        View::composer('*', function ($deleteLink) {
            try {
                if (!empty(Auth::user()) && Auth::guard('admin')->check()) {
                    $admin = Auth::guard('admin')->user();
                    $routeName = \Request::route()->getName();
                    $userMenus = AuthMenu::where('link', $routeName)->first();
                    $userMenuAction = MenuAction::where('auth_menu_id', @$userMenus->id)
                        ->where('type', 4)->where('status', 1)->first();
                    if ($userMenuAction
                        && Route::has($userMenuAction->link)
                        && app(Permission::class)->allows($admin, $userMenuAction->link)) {
                        $deleteLink->with('deleteLink', @$userMenuAction->link);
                    } else {
                        $deleteLink->with('deleteLink', null);
                    }
                } else {
                    $deleteLink->with('deleteLink', null);
                }
            } catch (Exception $exc) {
                $deleteLink->with('deleteLink', null);
            }
        });

        View::composer('*', function ($view) {
            $data = json_decode(json_encode(Translation::language()), FALSE);
            $view->with('Lang', @$data->admin);
            $view->with('isLocalization', config('app.localization'));
        });

        Inertia::share([
            'errors' => function () {
                return Session::get('errors')
                    ? Session::get('errors')->getBag('default')->getMessages()
                    : (object) [];
            },
        ]);

        Inertia::share('flash', function () {
            return [
                'message' => Session::get('message'),
                'success' => Session::get('success'),
                'error'   => Session::get('error'),
            ];
        });
    }
}
