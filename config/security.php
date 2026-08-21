<?php

$trustedProxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

return [
    /*
    | Social registrations follow the same approval workflow as local member
    | registrations. A deployment may opt in to automatic approval only after
    | the organization has accepted that account-abuse risk explicitly.
    */
    'social_registration_auto_approve' => filter_var(
        env('MEMBER_SOCIAL_AUTO_APPROVE', false),
        FILTER_VALIDATE_BOOL
    ),

    'trusted_proxies' => $trustedProxies,

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
        'max_age' => max(0, (int) env('SECURITY_HSTS_MAX_AGE', 31536000)),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],
];
