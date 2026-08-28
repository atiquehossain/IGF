<?php

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Encryption\Encrypter;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

class ProductionPreflightService
{
    /**
     * Security baselines published by php.net as of 2026-08-28. Keep each
     * branch current independently: comparing every branch to 8.2.33 would,
     * for example, incorrectly allow the obsolete 8.3.0 release.
     *
     * @var array<string,string>
     */
    private const MINIMUM_PHP_VERSIONS = [
        '8.2' => '8.2.33',
        '8.3' => '8.3.33',
        '8.4' => '8.4.25',
        '8.5' => '8.5.10',
    ];

    /** @var list<string> */
    private const DEVELOPER_ROUTE_PREFIXES = ['__cypress__', '_debugbar', '_ignition'];

    /** @var list<string> */
    private const PRODUCTION_DATABASE_DRIVERS = ['mysql', 'pgsql', 'sqlsrv'];

    public function __construct(private Application $app, private Router $router)
    {
    }

    /**
     * @param  iterable<Route|string>|null  $routes
     * @return list<array{key:string,label:string,passed:bool,message:string}>
     */
    public function evaluate(?iterable $routes = null, ?string $phpVersion = null): array
    {
        $environment = (string) $this->app->environment();
        $debug = config('app.debug');
        $appUrl = (string) config('app.url', '');
        $databaseConnection = (string) config('database.default', '');
        $databaseDriver = (string) config("database.connections.{$databaseConnection}.driver", '');
        $developerRoutes = $this->developerRoutes($routes ?? $this->router->getRoutes());
        $phpVersion ??= PHP_VERSION;

        $checks = [];
        $checks[] = $this->check(
            'environment',
            'Application environment',
            $environment === 'production',
            $environment === 'production'
                ? 'The application environment is production.'
                : "Expected production; current environment is {$environment}."
        );
        $checks[] = $this->check(
            'debug',
            'Debug mode',
            $debug === false,
            $debug === false
                ? 'Debug mode is disabled.'
                : 'APP_DEBUG must be false in production.'
        );

        $appKeySafe = $this->appKeyIsSafe();
        $checks[] = $this->check(
            'app_key',
            'Application encryption key',
            $appKeySafe,
            $appKeySafe
                ? 'APP_KEY is present and valid for the configured cipher.'
                : 'APP_KEY must contain valid key material for the configured application cipher.'
        );

        $checks[] = $this->check(
            'app_url',
            'Application URL',
            $this->isAbsoluteHttpsUrl($appUrl),
            $this->isAbsoluteHttpsUrl($appUrl)
                ? 'APP_URL is an absolute HTTPS URL.'
                : 'APP_URL must be an absolute HTTPS URL with a hostname.'
        );

        $sessionSafe = config('session.secure') === true
            && config('session.http_only') === true
            && in_array(strtolower((string) config('session.same_site')), ['lax', 'strict'], true);
        $checks[] = $this->check(
            'session',
            'Session cookie policy',
            $sessionSafe,
            $sessionSafe
                ? 'Session cookies are Secure, HttpOnly, and use a CSRF-resistant SameSite policy.'
                : 'Production sessions require Secure and HttpOnly cookies with SameSite=Lax or Strict.'
        );

        $corsSafe = $this->productionCorsIsSafe();
        $checks[] = $this->check(
            'cors',
            'CORS origin policy',
            $corsSafe,
            $corsSafe
                ? 'CORS allows only explicit HTTPS origins.'
                : 'Production CORS must contain explicit HTTPS origins, no wildcard origin, and no origin regex patterns.'
        );

        $databaseSafe = in_array($databaseDriver, self::PRODUCTION_DATABASE_DRIVERS, true);
        $checks[] = $this->check(
            'database',
            'Database driver',
            $databaseSafe,
            $databaseSafe
                ? "The {$databaseConnection} connection uses the supported {$databaseDriver} driver."
                : 'Production DB_CONNECTION must resolve to mysql, pgsql, or sqlsrv; SQLite and unknown drivers are blocked.'
        );

        $checks[] = $this->check(
            'developer_routes',
            'Developer route inventory',
            $developerRoutes === [],
            $developerRoutes === []
                ? 'No Cypress, Debugbar, or Ignition developer routes are registered.'
                : 'Developer routes are registered: '.implode(', ', $developerRoutes)
        );

        $phpSafe = $this->phpVersionIsSafe($phpVersion);
        $checks[] = $this->check(
            'php_version',
            'PHP security patch level',
            $phpSafe,
            $phpSafe
                ? "PHP {$phpVersion} meets the production security baseline."
                : "PHP must be a stable release on a supported 8.2-8.5 branch at or above that branch's security baseline; current version is {$phpVersion}."
        );

        return $checks;
    }

    private function appKeyIsSafe(): bool
    {
        $key = (string) config('app.key', '');
        $cipher = (string) config('app.cipher', '');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (!is_string($decoded)) {
                return false;
            }

            $key = $decoded;
        }

        return Encrypter::supported($key, $cipher);
    }

    private function isAbsoluteHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && trim((string) ($parts['host'] ?? '')) !== ''
            && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment']);
    }

    private function productionCorsIsSafe(): bool
    {
        $origins = config('cors.allowed_origins');
        $patterns = config('cors.allowed_origins_patterns');
        if (!is_array($origins) || $origins === [] || !is_array($patterns) || $patterns !== []) {
            return false;
        }

        foreach ($origins as $origin) {
            if (!is_string($origin) || $origin === '*' || !$this->isAbsoluteHttpsUrl($origin)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  iterable<Route|string>  $routes
     * @return list<string>
     */
    private function developerRoutes(iterable $routes): array
    {
        $blocked = [];
        foreach ($routes as $route) {
            $uri = ltrim($route instanceof Route ? $route->uri() : (string) $route, '/');
            foreach (self::DEVELOPER_ROUTE_PREFIXES as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                    $blocked[] = '/'.$uri;
                    break;
                }
            }
        }

        sort($blocked, SORT_STRING);

        return array_values(array_unique($blocked));
    }

    private function phpVersionIsSafe(string $version): bool
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return false;
        }

        $parts = explode('.', $version);
        $branch = $parts[0].'.'.$parts[1];

        $minimum = self::MINIMUM_PHP_VERSIONS[$branch] ?? null;

        return $minimum !== null && version_compare($version, $minimum, '>=');
    }

    /** @return array{key:string,label:string,passed:bool,message:string} */
    private function check(string $key, string $label, bool $passed, string $message): array
    {
        return compact('key', 'label', 'passed', 'message');
    }
}
