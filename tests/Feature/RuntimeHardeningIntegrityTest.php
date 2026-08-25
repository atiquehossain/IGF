<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RuntimeHardeningIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_cover_public_and_private_admin_responses(): void
    {
        $public = $this->get('/');
        $public->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'");

        $admin = $this->get('/admin/login');
        $admin->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', (string) $public->headers->get('Cache-Control'));
    }

    public function test_secret_bearing_member_auth_responses_are_never_stored(): void
    {
        $challenge = $this->withSession([
            'data' => [
                'status' => true,
                'title' => 'Login Verify',
                'access_token' => str_repeat('a', 64),
                'enrollment_required' => true,
                'qr_image' => 'data:image/png;base64,c2VjcmV0',
            ],
        ])->get('/login-2fa-verify');

        $challenge->assertOk();
        $this->assertStringContainsString('no-store', (string) $challenge->headers->get('Cache-Control'));
        $challenge->assertHeader('Pragma', 'no-cache');

        $api = $this->postJson('/api/v1/auth/login-2fa', []);
        $api->assertUnprocessable();
        $this->assertStringContainsString('no-store', (string) $api->headers->get('Cache-Control'));
        $api->assertHeader('Pragma', 'no-cache');

        $authorized = $this->withHeader('Authorization', 'Bearer cache-safety-probe')
            ->getJson('/api/v1/menu');
        $authorized->assertOk();
        $this->assertStringContainsString('no-store', (string) $authorized->headers->get('Cache-Control'));
    }

    public function test_database_exception_reporting_never_logs_sql_bindings_or_private_values(): void
    {
        Log::spy();
        $privateEmail = 'private-subscriber@example.test';
        $previous = new \PDOException('Duplicate value for ' . $privateEmail, 23000);
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $exception = new QueryException(
            'mysql',
            'insert into subscribers (email) values (?)',
            [$privateEmail],
            $previous
        );

        report($exception);

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context) use ($privateEmail): bool {
                $serialized = $message . json_encode($context);

                return $message === 'Database operation failed.'
                    && ($context['sql_state'] ?? null) === '23000'
                    && !str_contains($serialized, $privateEmail)
                    && !str_contains($serialized, 'subscribers')
                    && !str_contains($serialized, 'Duplicate entry');
            }
        );
    }

    public function test_hsts_is_emitted_only_for_secure_requests_when_enabled(): void
    {
        config()->set('security.hsts.enabled', true);
        config()->set('security.hsts.max_age', 86400);

        $this->get('/admin/login')->assertHeaderMissing('Strict-Transport-Security');
        $appUrl = parse_url((string) config('app.url'));
        $configuredHost = (string) ($appUrl['host'] ?? 'localhost');
        $configuredPort = isset($appUrl['port']) ? ':'.(int) $appUrl['port'] : '';
        $secureUrl = 'https://'.$configuredHost.$configuredPort.'/admin/login';

        $this->get($secureUrl)
            ->assertHeader('Strict-Transport-Security', 'max-age=86400');
    }

    public function test_trusted_proxy_origin_is_resolved_before_canonical_host_enforcement(): void
    {
        config()->set('app.url', 'https://canonical.example.test');
        config()->set('security.trusted_proxies', ['10.0.0.1']);

        $forwardedOrigin = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_HOST' => 'canonical.example.test',
            'HTTP_X_FORWARDED_HOST' => 'canonical.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];

        try {
            $this->withServerVariables($forwardedOrigin)
                ->get('/admin/login')
                ->assertOk();

            $this->withServerVariables(array_replace($forwardedOrigin, [
                'HTTP_X_FORWARDED_PORT' => '8443',
            ]))->get('/admin/login')->assertBadRequest();

            $this->withServerVariables(array_replace($forwardedOrigin, [
                'HTTP_X_FORWARDED_HOST' => 'attacker.example.test',
            ]))->get('/admin/login')->assertBadRequest();
        } finally {
            // Symfony keeps trusted-host patterns in process-global state.
            // Do not let this test's synthetic canonical host leak to later tests.
            \Illuminate\Http\Request::setTrustedHosts([]);
        }
    }

    public function test_cached_cors_policy_never_falls_back_to_a_production_wildcard(): void
    {
        $legacyMiddleware = file_get_contents(app_path('Http/Middleware/Cors.php'));
        $corsConfig = file_get_contents(config_path('cors.php'));

        $this->assertStringNotContainsString('env(', $legacyMiddleware);
        $this->assertStringContainsString("env('CORS_ALLOWED_ORIGINS', '')", $corsConfig);
        $this->assertStringContainsString("env('APP_ENV') === 'production'", $corsConfig);

        config()->set('cors.allowed_origins', ['https://approved.example.test']);

        $unapproved = $this->withHeader('Origin', 'https://unapproved.example.test')
            ->getJson('/api/v1/menu')
            ->assertOk();
        $unapproved->assertHeader('Access-Control-Allow-Origin', 'https://approved.example.test');
        $this->assertNotSame('*', $unapproved->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame(
            'https://unapproved.example.test',
            $unapproved->headers->get('Access-Control-Allow-Origin')
        );

        $this->withHeader('Origin', 'https://approved.example.test')
            ->getJson('/api/v1/menu')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://approved.example.test');
    }

    public function test_api_documentation_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) env('L5_SWAGGER_ENABLED', false));
        $this->assertFalse(Route::has('l5-swagger.default.api'));
        $this->assertFalse(Route::has('l5-swagger.default.docs'));
    }

    public function test_admin_login_and_shared_actions_have_accessible_failure_safe_contracts(): void
    {
        $login = file_get_contents(resource_path('views/auth/admin-login.blade.php'));
        $this->assertStringContainsString('for="username"', $login);
        $this->assertStringContainsString('autocomplete="username"', $login);
        $this->assertStringContainsString('for="password"', $login);
        $this->assertStringContainsString('autocomplete="current-password"', $login);
        $this->assertStringNotContainsString('type="username"', $login);
        $this->assertStringNotContainsString('<button type="submit" class="btn btn-larger btn-block" />', $login);

        $actions = file_get_contents(app_path('Link.php'));
        $this->assertStringContainsString('class="igf-action-group" role="group"', $actions);
        $this->assertStringContainsString('igf-btn igf-btn-secondary igf-btn-compact', $actions);
        $this->assertStringContainsString('igf-btn igf-btn-danger igf-btn-compact trash', $actions);
        $this->assertStringContainsString('<span>Edit</span>', $actions);
        $this->assertStringContainsString('<span>Delete</span>', $actions);
        $this->assertStringContainsString('aria-pressed=', $actions);
        $this->assertStringContainsString('data-item-label=', $actions);

        $scripts = file_get_contents(resource_path('views/admin/layouts/scripts.blade.php'));
        $this->assertStringContainsString('function adminErrorMessage', $scripts);
        $this->assertStringContainsString("button.prop('disabled', true)", $scripts);
        $this->assertStringNotContainsString('err.responseJSON.message', $scripts);
        $this->assertStringNotContainsString('window.confirm("Are you sure?")', $scripts);
    }

    public function test_bitbucket_deployment_is_manual_gated_and_uses_supported_tooling(): void
    {
        $pipeline = file_get_contents(base_path('bitbucket-pipelines.yml'));

        $this->assertStringContainsString('image: node:22-bookworm', $pipeline);
        $this->assertStringContainsString('trigger: manual', $pipeline);
        $this->assertStringContainsString('npm ci --no-audit --no-fund', $pipeline);
        $this->assertStringContainsString('npm run security:scan:test', $pipeline);
        $this->assertStringContainsString('php artisan test --display-warnings', $pipeline);
        $this->assertStringContainsString('npm run cypress:smoke', $pipeline);
        $this->assertStringContainsString('npm run security:scan:release', $pipeline);
        $this->assertStringContainsString('git archive --format=tar "$BITBUCKET_COMMIT"', $pipeline);
        $this->assertStringContainsString('release-gate/commit', $pipeline);
        $this->assertStringContainsString('release-gate/tree', $pipeline);
        $this->assertStringContainsString('php artisan migrate --force', $pipeline);
        $this->assertStringContainsString('php artisan config:cache', $pipeline);
        $this->assertStringContainsString('curl --fail', $pipeline);
        $this->assertStringContainsString('/usr/local/sbin/ignite-release-backup', $pipeline);
        $this->assertStringContainsString('BITBUCKET_COMMIT', $pipeline);
        $this->assertStringContainsString("\"bash -se -- '\$BITBUCKET_COMMIT' '\$healthcheck_b64'\" <<'EOF'", $pipeline);
        $this->assertStringContainsString('test "${#BITBUCKET_COMMIT}" -eq 40', $pipeline);
        $this->assertStringContainsString("git fetch --prune origin '+refs/heads/main:refs/remotes/origin/main'", $pipeline);
        $this->assertStringContainsString('git merge-base --is-ancestor "$release_sha" refs/remotes/origin/main', $pipeline);
        $this->assertStringContainsString('git checkout --detach "$release_sha"', $pipeline);
        $this->assertStringNotContainsString('git fetch --prune origin "$release_sha"', $pipeline);
        $this->assertStringNotContainsString('git pull --ff-only origin main', $pipeline);
        $this->assertStringNotContainsString('RELEASE_BACKUP_COMMAND', $pipeline);
        $this->assertStringContainsString('The application remains in maintenance mode', $pipeline);
        $this->assertLessThan(strpos($pipeline, '- step: *browser-smoke'), strpos($pipeline, '- step: *quality', strpos($pipeline, 'branches:')));
        $this->assertLessThan(strpos($pipeline, '- step: *release-gate'), strpos($pipeline, '- step: *browser-smoke'));
        $this->assertLessThan(strpos($pipeline, '- step: *deploy'), strpos($pipeline, '- step: *release-gate'));
        $remote = substr($pipeline, strpos($pipeline, 'release_sha="$1"'));
        $this->assertLessThan(
            strpos($remote, 'git checkout --detach "$release_sha"'),
            strpos($remote, 'php artisan down --retry=30')
        );
        $this->assertLessThan(
            strrpos($remote, '"$backup_script"'),
            strpos($remote, 'php artisan down --retry=30')
        );
        $this->assertLessThan(
            strpos($remote, 'git checkout --detach "$release_sha"'),
            strrpos($remote, '"$backup_script"')
        );
        $this->assertLessThan(
            strpos($remote, 'git checkout --detach "$release_sha"'),
            strpos($remote, 'php artisan optimize:clear')
        );
        $this->assertStringContainsString("case \"\$healthcheck_url\" in (https://*)", $pipeline);
        $this->assertStringContainsString("--proto '=https' --proto-redir '=https'", $pipeline);
        $this->assertStringNotContainsString('nvm use 14', $pipeline);
        $this->assertStringNotContainsString('StrictHostKeyChecking=no', $pipeline);
    }

    public function test_production_security_defaults_and_backup_contract_fail_safe(): void
    {
        $environment = file_get_contents(base_path('.env.example'));
        $session = file_get_contents(config_path('session.php'));
        $security = file_get_contents(config_path('security.php'));
        $readme = file_get_contents(base_path('README.md'));

        $this->assertStringNotContainsString('SESSION_SECURE_COOKIE=false', $environment);
        $this->assertStringNotContainsString('SECURITY_HSTS_ENABLED=false', $environment);
        $this->assertStringContainsString("env('APP_ENV') === 'production'", $session);
        $this->assertStringContainsString("env('APP_ENV') === 'production'", $security);

        foreach ([
            'entire `storage/app` tree',
            'storage/app/public',
            'storage/app/uploads/admin',
            'storage/app/uploads/users',
            'storage/app/annual-reports',
            'storage/app/content-purge-quarantine',
        ] as $persistentRoot) {
            $this->assertStringContainsString($persistentRoot, $readme);
        }
        $this->assertStringContainsString('APP_KEY', $readme);
        $this->assertStringContainsString('Passport key', $readme);
        $this->assertStringContainsString('recorded restore drill', $readme);
    }

    public function test_browser_smoke_uses_isolated_data_and_runtime_credentials(): void
    {
        $commands = file_get_contents(base_path('cypress/support/commands.js'));
        $environment = file_get_contents(base_path('.env.cypress.example'));
        $cypressConfig = file_get_contents(base_path('cypress.config.js'));
        $workflow = file_get_contents(base_path('.github/workflows/quality.yml'));
        $pipeline = file_get_contents(base_path('bitbucket-pipelines.yml'));

        $this->assertStringContainsString("cy.env(['ADMIN_USERNAME', 'ADMIN_PASSWORD'], { log: false })", $commands);
        $this->assertStringNotContainsString('Cypress.env(', $commands);
        $this->assertStringNotContainsString("type('super_admin')", $commands);
        $this->assertStringNotContainsString("type('123456')", $commands);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $environment);
        $this->assertStringContainsString('DB_DATABASE=database/cypress.sqlite', $environment);
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8001', $environment);
        $this->assertStringContainsString('LOCAL_ADMIN_PASSWORD=', $environment);
        $this->assertStringContainsString("baseUrl: 'http://127.0.0.1:8001/'", $cypressConfig);
        $this->assertStringContainsString('allowCypressEnv: false', $cypressConfig);
        $this->assertStringContainsString('browser-smoke:', $workflow);
        $this->assertStringContainsString('npm run cypress:smoke', $workflow);
        $this->assertStringContainsString('name: Isolated administrator browser smoke', $pipeline);
        $this->assertStringContainsString('CYPRESS_ADMIN_PASSWORD="$runtime_value"', $pipeline);
        $this->assertStringNotContainsString('CYPRESS_ADMIN_PASSWORD=123456', $pipeline);
    }

    public function test_legacy_admin_modal_content_is_inserted_as_text_not_html(): void
    {
        foreach ([
            resource_path('views/admin/comment/index.blade.php'),
            resource_path('views/admin/page/view.blade.php'),
        ] as $view) {
            $source = file_get_contents($view);
            $this->assertStringNotContainsString(".html($(this).data('name'))", $source);
            $this->assertStringContainsString(".text($(this).data('name'))", $source);
        }

        $menu = file_get_contents(resource_path('views/admin/page_menu/edit.blade.php'));
        $this->assertStringNotContainsString('output.join', $menu);
        $this->assertStringContainsString('new Option(', $menu);
    }

    public function test_admin_locale_switch_accepts_only_configured_editor_locales(): void
    {
        $role = Role::create([
            'name' => 'Locale owner',
            'is_owner' => true,
            'security_rank' => 0,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'Locale Administrator',
            'username' => 'locale-administrator',
            'email' => 'locale-admin@example.test',
            'role' => $role->id,
            'status' => 1,
            'password' => bcrypt('not-used-in-this-test'),
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession([Admin::SESSION_AUTH_VERSION => $admin->auth_version])
            ->from('/admin')
            ->get(route('admin.language', ['language' => 'bn']))
            ->assertRedirect('/admin')
            ->assertSessionHas('locale', 'bn');

        $this->get(route('admin.language', ['language' => 'not-a-locale']))
            ->assertNotFound();
        $this->assertSame('bn', session('locale'));
    }

    public function test_maintenance_automation_is_recoverable_and_destructive_policies_fail_closed(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $privacy = file_get_contents(config_path('privacy.php'));
        $technicalSeo = file_get_contents(config_path('technical-seo.php'));

        $this->assertStringContainsString('content:recover-purge-quarantine --age=15', $kernel);
        $this->assertStringContainsString('privacy:apply-retention --execute', $kernel);
        $this->assertStringContainsString("config('privacy.automation_enabled')", $kernel);
        $this->assertStringContainsString("config('technical-seo.schedule_enabled')", $kernel);
        $this->assertStringContainsString("env('PRIVACY_RETENTION_AUTOMATION_ENABLED', false)", $privacy);
        $this->assertStringContainsString("env('TECHNICAL_SEO_SCHEDULE_ENABLED', false)", $technicalSeo);
    }

    public function test_production_migrations_do_not_activate_the_demo_school_landing_content(): void
    {
        $this->assertFileDoesNotExist(database_path(
            'migrations/2026_08_19_120100_configure_ignite_school_landing_page.php'
        ));

        $seeder = file_get_contents(database_path('seeders/IgniteParityContentSeeder.php'));
        $this->assertStringContainsString("app()->environment(['local', 'testing'])", $seeder);
    }
}
