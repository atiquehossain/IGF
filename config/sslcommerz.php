<?php

return [
    'store_id'       => env('SSLCOMMERZ_STORE_ID'),
    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
    'sandbox'        => env('SSLCOMMERZ_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Donation payment methods
    |--------------------------------------------------------------------------
    |
    | Public forms submit only the stable keys below. Gateway identifiers stay
    | server-owned so a visitor cannot inject an arbitrary payment channel.
    | Nagad deliberately fails closed until SSLCommerz confirms the gateway key
    | enabled for the merchant account.
    |
    */
    'payment_methods' => [
        'bkash' => [
            'gateway_filter' => 'bkash',
            'enabled' => env('SSLCOMMERZ_BKASH_ENABLED', true),
        ],
        'nagad' => [
            'gateway_filter' => env('SSLCOMMERZ_NAGAD_GATEWAY_KEY'),
            'enabled' => env('SSLCOMMERZ_NAGAD_ENABLED', false),
        ],
        'card' => [
            'gateway_filter' => 'visacard,amexcard',
            'enabled' => env('SSLCOMMERZ_CARD_ENABLED', true),
        ],
    ],
];
