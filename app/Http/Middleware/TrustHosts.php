<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;

class TrustHosts extends Middleware
{
    /**
     * Host-derived SEO URLs are security-sensitive in every environment.
     * Laravel's default middleware skips enforcement for local/testing, which
     * would leave this boundary untested and make local proxy mistakes silent.
     */
    protected function shouldSpecifyTrustedHosts()
    {
        return true;
    }

    /**
     * Get the host patterns that should be trusted.
     *
     * @return array
     */
    public function hosts()
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Trust only the configured canonical host. An arbitrary subdomain is
        // still attacker-controlled in many DNS/proxy deployments and must not
        // become the origin used for canonical, sitemap or redirect URLs.
        return [$host ? '^'.preg_quote((string) $host).'$' : '^(?!)$'];
    }

    public function handle(Request $request, $next)
    {
        return parent::handle($request, function (Request $request) use ($next) {
            // Symfony's trusted-host check intentionally ignores the port.
            // Canonical URLs do not, so reject a hostile Host port as well.
            abort_unless(
                hash_equals($this->configuredHttpHost(), strtolower($request->getHttpHost())),
                400,
                'Untrusted Host header.'
            );

            return $next($request);
        });
    }

    private function configuredHttpHost(): string
    {
        $parts = parse_url((string) config('app.url'));
        abort_unless(is_array($parts) && isset($parts['host']), 400, 'The application host is not configured.');

        $host = strtolower((string) $parts['host']);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $host.($port !== null && $port !== $defaultPort ? ':'.$port : '');
    }
}
