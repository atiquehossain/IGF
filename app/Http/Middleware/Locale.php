<?php

namespace App\Http\Middleware;

use App\Helper\Translation;
use Closure;
use Exception;
use Illuminate\Support\Facades\URL;
use App\Services\LocalizationManager;
use Symfony\Component\HttpFoundation\Response;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->method() === 'GET') {
            $segment = $request->segment(1);
            $locales = app(LocalizationManager::class)->publicLocales();
            $fallback = (string) config('app.fallback_locale', 'en');
            if (!in_array($fallback, $locales, true)) {
                $fallback = (string) ($locales[0] ?? 'en');
            }
            $queryLocale = (string) $request->query(config('seo.locale_query_parameter', 'lang'), '');
            if ($queryLocale !== '' && in_array($queryLocale, $locales, true)) {
                $segment = $queryLocale;
            } elseif (!in_array($segment, $locales, true)) {
                $storedLocale = (string) session('locale', '');
                $segment = in_array($storedLocale, $locales, true) ? $storedLocale : $fallback;
            //     return redirect()->to(implode('/', [$segment]));
            }
            session(['locale' => $segment]);
            app()->setLocale($segment);
        }

        try {
            $language = json_decode(json_encode(Translation::language()), false);
            $request->Lang = (object) @$language->vue;
            $request->meta_tag = (object) ['meta_keyword' => '', 'meta_title' => 'IGF', 'meta_description' => '', 'meta_image' => asset('frontend/images/logo.png')];
        } catch (Exception $exc) {
            $request->Lang = (object) [];
        }

        URL::defaults(['locale' => app()->getLocale()]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
