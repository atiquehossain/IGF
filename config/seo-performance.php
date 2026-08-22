<?php

return [
    /*
     * Performance reporting is deliberately opt-in. The admin remains useful
     * when Google is not configured and never attempts an external request in
     * that state.
     */
    'enabled' => filter_var(env('SEO_PERFORMANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'cache_minutes' => max(5, (int) env('SEO_PERFORMANCE_CACHE_MINUTES', 360)),
    'request_timeout_seconds' => max(3, min(30, (int) env('SEO_PERFORMANCE_TIMEOUT_SECONDS', 12))),

    'search_console' => [
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', ''),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', ''),
        'row_limit' => max(50, min(25000, (int) env('GOOGLE_SEARCH_CONSOLE_ROW_LIMIT', 5000))),
        'low_ctr_percent' => max(0.1, min(25, (float) env('SEO_LOW_CTR_PERCENT', 3))),
        'opportunity_min_impressions' => max(1, (int) env('SEO_OPPORTUNITY_MIN_IMPRESSIONS', 50)),
    ],

    'analytics' => [
        'property_id' => env('ANALYTICS_VIEW_ID', ''),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', ''),
    ],
];
