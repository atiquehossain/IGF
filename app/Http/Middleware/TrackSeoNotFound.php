<?php

namespace App\Http\Middleware;

use App\Services\SeoNotFoundRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TrackSeoNotFound
{
    private const SKIPPED_PREFIXES = [
        'admin', 'api', 'assets', 'build', 'css', 'fonts', 'image', 'images',
        'js', 'storage', 'vendor', 'chat', 'login', 'logout', 'password',
        'register', 'donation/payment', 'donate/payment',
    ];

    public function __construct(private SeoNotFoundRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            try {
                $this->recorder->record($request);
            } catch (Throwable) {
                // Analytics can never break the visitor's 404 response, and
                // pre-migration deployments must continue to work normally.
            }
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() !== 404
            || !in_array($request->method(), ['GET', 'HEAD'], true)
            || $request->expectsJson()
            || $request->headers->get('X-Seo-Audit') === '1') {
            return false;
        }

        $path = ltrim($request->path(), '/');
        foreach (self::SKIPPED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return !preg_match('/\.(?:css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|xml|txt)$/i', $path);
    }
}
