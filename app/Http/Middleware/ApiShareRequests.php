<?php

namespace App\Http\Middleware;

use App\Helper\Translation;
use App\Services\LocalizationManager;
use Closure;
use Illuminate\Http\Request;

class ApiShareRequests
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
        $requestedLocale = strtolower(trim((string) $request->header('locale', '')));
        $availableLocales = app(LocalizationManager::class)->publicLocales();
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $locale = $requestedLocale === '' ? $fallbackLocale : $requestedLocale;

        // Never use an arbitrary header value as a translation-file path or
        // return content in a locale that is not currently public.
        if (!in_array($locale, $availableLocales, true)) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported locale.',
                'available_locales' => array_values($availableLocales),
            ], 400);
        }

        app()->setLocale($locale);
        $share = (object) [
            'appName' => config('app.name'),
            'locale' => $locale,
            'language' => Translation::language($locale)['vue'],
        ];

        $request->share = $share;
        return $next($request);
    }

}
