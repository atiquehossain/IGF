<?php

return [
    'schedule_enabled' => filter_var(env('TECHNICAL_SEO_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
     * The audit never opens arbitrary network URLs. It dispatches bounded,
     * anonymous GET requests back through this Laravel application and only
     * accepts paths belonging to the configured application origin.
     */
    'max_urls' => (int) env('TECHNICAL_SEO_MAX_URLS', 120),
    'max_seconds' => (int) env('TECHNICAL_SEO_MAX_SECONDS', 20),
    'max_response_bytes' => (int) env('TECHNICAL_SEO_MAX_RESPONSE_BYTES', 1048576),
    'max_links_per_page' => (int) env('TECHNICAL_SEO_MAX_LINKS_PER_PAGE', 250),
    'max_not_found_rows' => (int) env('TECHNICAL_SEO_MAX_404_ROWS', 10000),
    'snapshot_retention' => (int) env('TECHNICAL_SEO_SNAPSHOT_RETENTION', 20),

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
