<?php

namespace App\Helper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MyLogs
{
    public static function admin(Request $request, $table = null, $method = null): void
    {
        self::write('admin', $request, $table, $method);
    }

    public static function front(Request $request, $table = null, $method = null): void
    {
        self::write('public', $request, $table, $method);
    }

    public static function search(Request $request, $table = null, $method = null): void
    {
        self::write('search', $request, $table, $method);
    }

    public static function login(Request $request, $table = null, $method = null): void
    {
        self::write('login', $request, $table, $method);
    }

    private static function write(string $channel, Request $request, mixed $action, mixed $method): void
    {
        if (!in_array($request->method(), ['PATCH', 'POST', 'PUT', 'DELETE'], true)) {
            return;
        }

        // Deliberately omit request bodies, credentials, profile fields, IPs,
        // user agents, origins and referrers. Operational logs need only an
        // action, method, route and authenticated subject identifier.
        Log::info('Application write action.', [
            'channel' => $channel,
            'action' => mb_substr((string) $action, 0, 100),
            'method' => (string) ($method ?: $request->method()),
            'route' => (string) $request->route()?->getName(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);
    }
}
