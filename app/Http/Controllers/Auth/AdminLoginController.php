<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\Admin;
use App\Services\SiteSettingService;
use App\Support\AdminUi;

class AdminLoginController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest:admin')->except('adminLogout');
    }

    /**
     * Show the admin's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLogin() {
        $siteName = data_get(
            app(SiteSettingService::class)->values(app()->getLocale(), true),
            'branding.site_name',
            config('app.name', 'Ignite Global Foundation')
        );
        $title = AdminUi::text('admin_login.page_title', ['site' => $siteName]);
        return view('auth.admin-login')->with(compact('title'));
    }

    /**
     * Functionalities for login
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request) {

        //validate data
        $this->validate($request, [
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ], [
            'username.required' => AdminUi::text('admin_login.validation.username_required'),
            'username.string' => AdminUi::text('admin_login.validation.username_string'),
            'username.max' => AdminUi::text('admin_login.validation.username_max'),
            'password.required' => AdminUi::text('admin_login.validation.password_required'),
            'password.string' => AdminUi::text('admin_login.validation.password_string'),
            'password.min' => AdminUi::text('admin_login.validation.password_min'),
        ]);

        if (Auth::guard('admin')->attempt([
            'username' => $request->username,
            'password' => $request->password,
            'status' => 1,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $admin = Auth::guard('admin')->user();
            $request->session()->put(Admin::SESSION_AUTH_VERSION, (int) $admin->auth_version);

            if ($admin->must_change_password) {
                return redirect()->route('admin.password');
            }

            return redirect()->intended(route('dashboard.index'));
        }

        $request->session()->flash('message', AdminUi::text('admin_login.invalid_credentials'));

        return redirect(route('admin.login'));
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function adminLogout(Request $request) {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect(route('admin.login'));
    }

}
