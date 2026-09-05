<?php

return [
    /*
    | Delivery identities are deployment-owned. Database-backed templates may
    | change only approved subjects and body copy; they never control headers.
    */
    'admin_to' => env('TRANSACTIONAL_MAIL_ADMIN_TO') ?: env('MAIL_FROM_ADDRESS'),
    'contact_address' => env('TRANSACTIONAL_MAIL_CONTACT_ADDRESS') ?: env('MAIL_FROM_ADDRESS'),
    'admin_locale' => env('TRANSACTIONAL_MAIL_ADMIN_LOCALE', 'en'),
];
