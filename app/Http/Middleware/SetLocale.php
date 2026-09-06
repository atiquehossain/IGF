<?php

namespace App\Http\Middleware;

use App\Services\LocalizationManager;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $isAdmin = $request->is('admin') || $request->is('admin/*');
        $sessionKey = $isAdmin ? 'admin_locale' : 'locale';
        $localization = app(LocalizationManager::class);
        $allowed = $isAdmin
            ? $localization->editorLocales()->pluck('id')->all()
            : $localization->publicLocales();
        $fallback = $isAdmin ? 'en' : (string) config('app.locale', 'en');
        if (!in_array($fallback, $allowed, true)) {
            $fallback = (string) ($allowed[0] ?? 'en');
        }
        $selected = (string) session($sessionKey, $fallback);

        app()->setLocale(in_array($selected, $allowed, true) ? $selected : $fallback);

        return $next($request);
    }
}
