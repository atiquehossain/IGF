<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectFileManagerMutations
{
    /**
     * UniSharp 2.x exposes these state-changing operations as GET routes.
     * Keep the package UI compatible while rejecting navigational/cross-site
     * requests that do not carry either Laravel's CSRF token or jQuery's
     * same-origin XMLHttpRequest signal.
     */
    private const MUTATING_ROUTES = [
        'unisharp.lfm.upload',
        'unisharp.lfm.doMove',
        'unisharp.lfm.getAddfolder',
        'unisharp.lfm.getCropImage',
        'unisharp.lfm.getNewCropImage',
        'unisharp.lfm.getRename',
        'unisharp.lfm.performResize',
        'unisharp.lfm.performResizeNew',
        'unisharp.lfm.getDelete',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        if (!in_array($routeName, self::MUTATING_ROUTES, true)) {
            return $next($request);
        }

        if (!$this->hasValidCsrfToken($request) && !$this->isSameOriginAjax($request)) {
            abort(419, 'Page Expired');
        }

        return $next($request);
    }

    private function hasValidCsrfToken(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $provided = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        return is_string($provided)
            && $provided !== ''
            && hash_equals((string) $request->session()->token(), $provided);
    }

    private function isSameOriginAjax(Request $request): bool
    {
        if (!$request->ajax()) {
            return false;
        }

        $fetchSite = strtolower((string) $request->header('Sec-Fetch-Site'));
        if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
            return false;
        }

        $source = (string) ($request->header('Origin') ?: $request->header('Referer'));
        if ($source === '') {
            // X-Requested-With is not a CORS-safelisted header, so a foreign
            // origin cannot add it without a successful preflight.
            return true;
        }

        $sourceScheme = parse_url($source, PHP_URL_SCHEME);
        $sourceHost = parse_url($source, PHP_URL_HOST);
        if (!is_string($sourceScheme) || !is_string($sourceHost)) {
            return false;
        }

        $sourcePort = parse_url($source, PHP_URL_PORT);
        $sourceOrigin = strtolower($sourceScheme . '://' . $sourceHost . ($sourcePort ? ':' . $sourcePort : ''));
        $requestOrigin = strtolower(rtrim($request->getSchemeAndHttpHost(), '/'));

        return hash_equals($requestOrigin, $sourceOrigin);
    }
}
