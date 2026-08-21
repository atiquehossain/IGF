<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
//    https://pusher.com/tutorials/multiple-authentication-guards-laravel
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if ($guard == "admin" && Auth::guard($guard)->check()) {
                return redirect(route('admin.index'));
            }
            if ($guard == "web" && Auth::guard($guard)->check()) {
                return redirect(route('home'));
            }        
            if (Auth::guard($guard)->check()) {
                return redirect(route('home'));
            }
        }

        return $next($request);
    }
}
