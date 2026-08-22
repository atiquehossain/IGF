<?php

namespace App\Http\Middleware;

use App\Services\SeoIndexingPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', $request->is('admin/*') ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "base-uri 'self'; frame-ancestors 'self'; object-src 'none'",
        );
        if (!app(SeoIndexingPolicy::class)->indexingAllowed()) {
            // Header coverage protects non-Inertia and non-HTML responses too;
            // the HTML head carries the same directive for browser hydration.
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        if ($this->mustNotStore($request)) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->isSecure() && config('security.hsts.enabled')) {
            $value = 'max-age='.(int) config('security.hsts.max_age', 31536000);
            if (config('security.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }
            if (config('security.hsts.preload')) {
                $value .= '; preload';
            }
            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }

    private function mustNotStore(Request $request): bool
    {
        if ($request->is(
            'admin',
            'admin/*',
            'login',
            'login/*',
            'login-*',
            'register',
            'change-password',
            'logout',
            'api/v1/auth/*'
        )) {
            return true;
        }

        // Bearer-authenticated API responses and signed-in Inertia responses
        // can contain tokens or member PII even when the public route itself is
        // otherwise cacheable. The user resolver is evaluated after the inner
        // authentication/session middleware has handled the request.
        return $request->bearerToken() !== null || $request->user() !== null;
    }
}
