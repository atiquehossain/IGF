<?php

return [
    'schedule_enabled' => filter_var(env('TECHNICAL_SEO_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
     * The audit never opens arbitrary network URLs. It dispatches bounded,
     * anonymous GET requests back through this Laravel application and only
     * accepts paths belonging to the configured application origin.
     */
    // Keep the default above the current managed inventory so a normal scan
    // completes instead of silently becoming a partial snapshot. Deployments
    // with larger catalogs can raise this up to the service's hard cap (500).
    'max_urls' => (int) env('TECHNICAL_SEO_MAX_URLS', 150),
    'max_seconds' => (int) env('TECHNICAL_SEO_MAX_SECONDS', 20),
    'max_response_bytes' => (int) env('TECHNICAL_SEO_MAX_RESPONSE_BYTES', 1048576),
    'max_links_per_page' => (int) env('TECHNICAL_SEO_MAX_LINKS_PER_PAGE', 250),
    'max_not_found_rows' => (int) env('TECHNICAL_SEO_MAX_404_ROWS', 10000),
    'snapshot_retention' => (int) env('TECHNICAL_SEO_SNAPSHOT_RETENTION', 20),

    'alerts' => [
        // In-app alerts are stored with the bounded run history. Email is an
        // explicit opt-in and never prevents a scan from completing.
        'in_app_enabled' => filter_var(env('TECHNICAL_SEO_IN_APP_ALERTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'email_enabled' => filter_var(env('TECHNICAL_SEO_EMAIL_ALERTS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'email_recipients' => array_values(array_filter(array_map(
            static fn (string $address): string => trim($address),
            explode(',', (string) env('TECHNICAL_SEO_ALERT_EMAILS', ''))
        ))),
    ],

    // Framework/dev utility endpoints are not visitor content and must not
    // create work in the privacy-safe 404 inbox.
    'not_found_ignored_prefixes' => [
        '/_debugbar',
        '/_ignition',
        '/horizon',
        '/livewire',
        '/telescope',
    ],

    'excluded_prefixes' => [
        '/admin',
        '/api',
        '/chat',
        '/login',
        '/logout',
        '/password',
        '/register',
        '/donation/payment',
        '/donate/payment',
    ],
];
