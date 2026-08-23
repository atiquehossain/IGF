<?php

return [
    /* Only these visitor-facing HTML routes may be route-level SEO targets. */
    'routes' => [
        'frontend.home' => ['label' => 'Home', 'path' => '/', 'page_slug' => 'home'],
        'frontend.about' => ['label' => 'About us', 'path' => '/about-us', 'page_slug' => 'about-us'],
        'frontend.zakat' => ['label' => 'Zakat', 'path' => '/zakat', 'page_slug' => 'zakat'],
        'frontend.contactUs' => ['label' => 'Contact us', 'path' => '/contact-us'],
        'frontend.gallery' => ['label' => 'Photo gallery', 'path' => '/gallery'],
        'frontend.sponsor_child' => ['label' => 'Sponsor a child', 'path' => '/sponsor-child', 'page_slug' => 'sponsor-a-child'],
        'frontend.events' => ['label' => 'Events & publications', 'path' => '/events'],
        'frontend.project' => ['label' => 'Projects', 'path' => '/projects'],
        'frontend.volunteer_registration.index' => ['label' => 'Volunteer registration', 'path' => '/volunteer/register'],
        'frontend.donate.index' => ['label' => 'Donate', 'path' => '/donate'],
        'frontend.donate.cause' => ['label' => 'Donate — Zakat', 'path' => '/donate/zakat'],
        'frontend.annual_report.index' => ['label' => 'Annual reports', 'path' => '/annual-report'],
    ],

    'locale_query_parameter' => 'lang',
    'sitemap_cache_seconds' => 300,

    'robots' => [
        // Indexing is fail-closed. Production must explicitly opt in. When
        // disabled, public pages remain crawlable so their page-level robots
        // metadata and matching X-Robots-Tag noindex directive can be observed.
        'indexing_enabled' => filter_var(env('SEO_INDEXING_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'redirects' => [
        // Redirects remain same-origin unless production explicitly opts in to
        // a finite HTTPS host allowlist.
        'allow_external' => filter_var(env('SEO_REDIRECT_ALLOW_EXTERNAL', false), FILTER_VALIDATE_BOOL),
        'allowed_external_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('SEO_REDIRECT_ALLOWED_HOSTS', ''))
        ))),
    ],
];
