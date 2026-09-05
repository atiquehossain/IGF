<?php

namespace App\Http\Middleware;

use App\Helper\StaticUtil;
use App\Services\PublicSystemPageMetaService;
use App\Services\SeoMetadataService;
use App\Services\SeoRouteRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRouteSeo
{
    public function __construct(
        private SeoMetadataService $seo,
        private SeoRouteRegistry $routes,
        private PublicSystemPageMetaService $systemMeta,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = (string) $route?->getName();
        $locale = (string) app()->getLocale();
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        if ($locale !== $defaultLocale
            && $this->seo->hasPublishedSpecialPage($routeName, $locale) === false
            && !$this->systemMeta->supportsLocalizedRouteFallback($routeName, $locale)) {
            // Do not return a 200/indexable fallback for a locale that has no
            // real Page translation behind this special route.
            abort(404);
        }
        $hasDynamicValue = $route && collect($route->parameters())
            ->contains(fn ($value) => $value !== null && $value !== '');
        $parameterizedRouteIsCurated = $hasDynamicValue
            && $this->requestMatchesCuratedFixedPath($request, $routeName);
        $meta = $route
            && (!$hasDynamicValue || $parameterizedRouteIsCurated)
            && $this->routes->has($routeName)
            ? $this->seo->metaForRoute($routeName, $locale)
            : [];

        if ($meta !== []) {
            $request->attributes->set('route_seo', $meta);
            StaticUtil::ssr($meta);
        }

        return $next($request);
    }

    private function requestMatchesCuratedFixedPath(Request $request, string $routeName): bool
    {
        $curatedPath = $this->routes->path($routeName);
        if (!is_string($curatedPath)
            || str_contains($curatedPath, '{')
            || str_contains($curatedPath, '}')) {
            return false;
        }

        $requestPath = $this->normalizedPath($request->getPathInfo());
        $fixedPath = $this->normalizedPath($curatedPath);

        return $requestPath !== null && $fixedPath !== null && hash_equals($fixedPath, $requestPath);
    }

    private function normalizedPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')) {
            return null;
        }

        $decoded = rawurldecode($path);
        if (preg_match('/[\x00-\x1F\x7F]/', $decoded)
            || str_contains($decoded, '\\')
            || str_contains($decoded, '?')
            || str_contains($decoded, '#')) {
            return null;
        }

        $segments = explode('/', $decoded);
        if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        $normalized = preg_replace('#/+#', '/', $decoded) ?: '/';
        $normalized = $normalized === '/' ? '/' : rtrim($normalized, '/');

        return $normalized === '' ? '/' : $normalized;
    }
}
