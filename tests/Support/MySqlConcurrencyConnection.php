<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

final class MySqlConcurrencyConnection
{
    public const NAME = 'mysql_concurrency';
    public const DSN_ENV = 'IGF_MYSQL_CONCURRENCY_DSN';
    public const USER_ENV = 'IGF_MYSQL_CONCURRENCY_USERNAME';
    public const PASSWORD_ENV = 'IGF_MYSQL_CONCURRENCY_PASSWORD';

    public static function available(): bool
    {
        return trim(self::environment(self::DSN_ENV)) !== '';
    }

    public static function configure(): void
    {
        config(['database.connections.' . self::NAME => self::configuration()]);
        DB::purge(self::NAME);
        DB::setDefaultConnection(self::NAME);
    }

    /** @return array<string, mixed> */
    public static function workerEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            self::DSN_ENV => self::environment(self::DSN_ENV),
            self::USER_ENV => self::environment(self::USER_ENV),
            self::PASSWORD_ENV => self::environment(self::PASSWORD_ENV),
        ];
    }

    /** @return array<string, mixed> */
    private static function configuration(): array
    {
        $dsn = trim(self::environment(self::DSN_ENV));
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new RuntimeException(self::DSN_ENV . ' must be an explicit mysql: PDO DSN.');
        }

        $parts = [];
        foreach (explode(';', substr($dsn, 6)) as $component) {
            if (!str_contains($component, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $component, 2));
            $parts[strtolower($key)] = $value;
        }

        $database = $parts['dbname'] ?? '';
        if ($database === '' || !str_contains(strtolower($database), 'test')) {
            throw new RuntimeException('The MySQL concurrency DSN must name a dedicated database containing "test"; migrate:fresh is destructive.');
        }

        return [
            'driver' => 'mysql',
            'url' => null,
            'host' => $parts['host'] ?? '127.0.0.1',
            'port' => $parts['port'] ?? '3306',
            'database' => $database,
            'username' => self::environment(self::USER_ENV),
            'password' => self::environment(self::PASSWORD_ENV),
            'unix_socket' => $parts['unix_socket'] ?? '',
            'charset' => $parts['charset'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => 'InnoDB',
            'options' => [PDO::ATTR_EMULATE_PREPARES => false],
        ];
    }

    private static function environment(string $key): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return (string) ($_SERVER[$key] ?? $_ENV[$key] ?? '');
    }
}
