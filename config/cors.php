<?php

$normalizeOrigin = static function (string $value): ?string {
    $value = trim($value);
    if ($value === '*') {
        return '*';
    }

    $parts = parse_url($value);
    if (!is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
        return null;
    }

    $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $origin .= ':'.(int) $parts['port'];
    }

    return $origin;
};

$allowedOrigins = array_values(array_unique(array_filter(array_map(
    $normalizeOrigin,
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
))));

if ($allowedOrigins === []) {
    $allowedOrigins = env('APP_ENV') === 'production'
        ? array_values(array_filter([$normalizeOrigin((string) env('APP_URL', ''))]))
        : ['*'];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Browser clients are same-origin by default in production. Native API
    // clients do not send an Origin header and are unaffected. Any additional
    // browser origin must be opted in explicitly before config is cached.
    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
