<?php

return [
    'automation_enabled' => filter_var(env('PRIVACY_RETENTION_AUTOMATION_ENABLED', false), FILTER_VALIDATE_BOOL),

    'newsletter' => [
        // Signed confirmation links expire, and repeated submissions for the
        // same unconfirmed address do not generate an email storm.
        'confirmation_ttl_minutes' => 1440,
        'resend_cooldown_minutes' => 15,
    ],

    /*
    | Retention is deliberately fail-safe: a policy with no positive number
    | of days is disabled. The command also requires --execute, so deploying
    | code or running a dry preview can never erase or anonymize records.
    */
    'retention' => [
        'contact_enquiries' => ['days' => env('PRIVACY_CONTACT_ENQUIRY_DAYS')],
        'sponsorship_enquiries' => ['days' => env('PRIVACY_SPONSORSHIP_ENQUIRY_DAYS')],
        'volunteer_applications' => ['days' => env('PRIVACY_VOLUNTEER_APPLICATION_DAYS')],
        'closed_chat' => ['days' => env('PRIVACY_CLOSED_CHAT_DAYS')],
        'subscribers' => ['days' => env('PRIVACY_SUBSCRIBER_DAYS')],
    ],
];
