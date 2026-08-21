<?php

namespace App\Http\Middleware;

use App\Services\SeoRedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySeoRedirects
{
    public function __construct(private SeoRedirectService $redirects)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $redirect = $this->redirects->resolveActiveForPath(
            $request->getPathInfo(),
            (string) app()->getLocale()
        );
        if (!$redirect) {
            return $next($request);
        }

        $this->redirects->recordHit($redirect);

        return redirect()->to($redirect->to_url, $redirect->status_code);
    }
}
