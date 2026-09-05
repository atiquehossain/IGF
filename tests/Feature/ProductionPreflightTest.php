<?php

namespace Tests\Feature;

use App\Services\ProductionPreflightService;
use Mockery;
use Tests\TestCase;

class ProductionPreflightTest extends TestCase
{
    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'dom', 'exif', 'fileinfo', 'filter', 'hash', 'iconv', 'json',
        'libxml', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'sodium',
        'tokenizer', 'zlib',
    ];

    /** @var array<string,string> */
    private const DRIVER_EXTENSIONS = [
        'mysql' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'sqlsrv' => 'pdo_sqlsrv',
    ];

    public function test_safe_production_configuration_passes_every_check(): void
    {
        $this->configureSafeProduction();

        $checks = app(ProductionPreflightService::class)->evaluate([], '8.2.33', $this->safeExtensions());

        $this->assertNotEmpty($checks);
        $this->assertSame([], $this->failedKeys($checks));
    }

    public function test_every_required_unsafe_condition_fails_closed(): void
    {
        $this->app['env'] = 'local';
        config([
            'app.debug' => true,
            'app.key' => '',
            'app.url' => 'http://127.0.0.1:8000',
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => 'none',
            'cors.allowed_origins' => ['*'],
            'cors.allowed_origins_patterns' => ['.*'],
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
        ]);

        $checks = app(ProductionPreflightService::class)->evaluate([
            '__cypress__/run-php',
            '_debugbar/open',
            '_ignition/execute-solution',
        ], '8.2.12', []);

        $this->assertSame([
            'environment',
            'debug',
            'app_key',
            'app_url',
            'session',
            'cors',
            'database',
            'developer_routes',
            'php_version',
            'php_extensions',
        ], $this->failedKeys($checks));
        $this->assertStringContainsString('/__cypress__/run-php', $this->check($checks, 'developer_routes')['message']);
        $this->assertStringContainsString('/_debugbar/open', $this->check($checks, 'developer_routes')['message']);
        $this->assertStringContainsString('/_ignition/execute-solution', $this->check($checks, 'developer_routes')['message']);
    }

    public function test_php_check_enforces_the_security_baseline_for_each_supported_branch(): void
    {
        $this->configureSafeProduction();
        $service = app(ProductionPreflightService::class);

        foreach (['8.2.33', '8.3.33', '8.4.25', '8.5.10'] as $version) {
            $this->assertTrue($this->check($service->evaluate([], $version, $this->safeExtensions()), 'php_version')['passed'], $version);
        }

        foreach (['8.2.32', '8.3.32', '8.4.24', '8.5.9', '8.5.10RC1', '8.6.0'] as $version) {
            $this->assertFalse($this->check($service->evaluate([], $version, $this->safeExtensions()), 'php_version')['passed'], $version);
        }
    }

    public function test_required_php_extensions_fail_closed(): void
    {
        $this->configureSafeProduction();
        $service = app(ProductionPreflightService::class);

        $loaded = array_map('strtoupper', $this->safeExtensions());
        $this->assertTrue($this->check($service->evaluate([], '8.2.33', $loaded), 'php_extensions')['passed']);

        foreach ($this->safeExtensions() as $missing) {
            $check = $this->check(
                $service->evaluate([], '8.2.33', array_values(array_diff($this->safeExtensions(), [$missing]))),
                'php_extensions'
            );
            $this->assertFalse($check['passed'], "{$missing} must fail closed when absent.");
            $this->assertStringContainsString($missing, $check['message']);
        }
    }

    public function test_selected_production_database_requires_its_matching_pdo_driver(): void
    {
        $this->configureSafeProduction();
        $service = app(ProductionPreflightService::class);

        foreach (self::DRIVER_EXTENSIONS as $driver => $extension) {
            config([
                'database.default' => 'preflight',
                'database.connections.preflight.driver' => $driver,
            ]);

            $extensions = $this->safeExtensions($driver);
            $this->assertTrue(
                $this->check($service->evaluate([], '8.2.33', $extensions), 'php_extensions')['passed'],
                "{$driver} should pass when {$extension} is loaded."
            );

            $check = $this->check(
                $service->evaluate([], '8.2.33', array_values(array_diff($extensions, [$extension]))),
                'php_extensions'
            );
            $this->assertFalse($check['passed'], "{$driver} must fail without {$extension}.");
            $this->assertStringContainsString($extension, $check['message']);
        }
    }

    public function test_application_key_must_match_the_configured_cipher(): void
    {
        $this->configureSafeProduction();
        $service = app(ProductionPreflightService::class);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertTrue($this->check($service->evaluate([], '8.2.33', $this->safeExtensions()), 'app_key')['passed']);

        foreach (['', 'base64:not-valid-base64***', 'base64:'.base64_encode(random_bytes(16)), str_repeat('x', 31)] as $key) {
            config(['app.key' => $key]);
            $this->assertFalse($this->check($service->evaluate([], '8.2.33', $this->safeExtensions()), 'app_key')['passed']);
        }
    }

    public function test_command_returns_failure_when_any_blocking_check_fails(): void
    {
        $preflight = Mockery::mock(ProductionPreflightService::class);
        $preflight->shouldReceive('evaluate')->once()->andReturn([
            ['key' => 'debug', 'label' => 'Debug mode', 'passed' => false, 'message' => 'APP_DEBUG must be false in production.'],
        ]);
        $this->app->instance(ProductionPreflightService::class, $preflight);

        $this->artisan('igf:production-preflight')
            ->expectsOutputToContain('FAIL  Debug mode')
            ->expectsOutputToContain('Production preflight failed with 1 blocking check(s).')
            ->assertFailed();
    }

    public function test_command_returns_success_only_when_every_check_passes(): void
    {
        $preflight = Mockery::mock(ProductionPreflightService::class);
        $preflight->shouldReceive('evaluate')->once()->andReturn([
            ['key' => 'environment', 'label' => 'Application environment', 'passed' => true, 'message' => 'The application environment is production.'],
        ]);
        $this->app->instance(ProductionPreflightService::class, $preflight);

        $this->artisan('igf:production-preflight')
            ->expectsOutputToContain('PASS  Application environment')
            ->expectsOutputToContain('Production preflight passed.')
            ->assertSuccessful();
    }

    public function test_remote_deploy_runs_preflight_against_cached_configuration_and_routes_before_startup(): void
    {
        $pipeline = file_get_contents(base_path('bitbucket-pipelines.yml'));
        $remote = substr($pipeline, strpos($pipeline, 'release_sha="$1"'));
        $configCache = strpos($remote, 'php artisan config:cache');
        $routeCache = strpos($remote, 'php artisan route:cache');
        $platformCheck = strpos($remote, 'composer check-platform-reqs --no-dev');
        $preflight = strpos($remote, 'php artisan igf:production-preflight');
        $migrationStatus = strpos($remote, 'php artisan migrate:status');
        $migration = strpos($remote, 'php artisan migrate --force');
        $applicationUp = strpos($remote, 'php artisan up');

        $this->assertNotFalse($configCache);
        $this->assertNotFalse($routeCache);
        $this->assertNotFalse($platformCheck);
        $this->assertNotFalse($preflight);
        $this->assertNotFalse($migrationStatus);
        $this->assertNotFalse($migration);
        $this->assertNotFalse($applicationUp);
        $this->assertLessThan($preflight, $configCache);
        $this->assertLessThan($preflight, $routeCache);
        $this->assertLessThan($preflight, $platformCheck);
        $this->assertLessThan($migrationStatus, $preflight);
        $this->assertLessThan($migration, $preflight);
        $this->assertLessThan($applicationUp, $preflight);
    }

    private function configureSafeProduction(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.url' => 'https://ignite.example',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'cors.allowed_origins' => ['https://ignite.example'],
            'cors.allowed_origins_patterns' => [],
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
        ]);
    }

    /** @return list<string> */
    private function safeExtensions(string $databaseDriver = 'mysql'): array
    {
        return [...self::REQUIRED_EXTENSIONS, self::DRIVER_EXTENSIONS[$databaseDriver]];
    }

    /**
     * @param  list<array{key:string,label:string,passed:bool,message:string}>  $checks
     * @return list<string>
     */
    private function failedKeys(array $checks): array
    {
        return array_values(array_map(
            static fn (array $check): string => $check['key'],
            array_filter($checks, static fn (array $check): bool => !$check['passed'])
        ));
    }

    /**
     * @param  list<array{key:string,label:string,passed:bool,message:string}>  $checks
     * @return array{key:string,label:string,passed:bool,message:string}
     */
    private function check(array $checks, string $key): array
    {
        foreach ($checks as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("Missing preflight check: {$key}");
    }
}
