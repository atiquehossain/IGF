<?php

return [
    /*
     * The public Vue application only needs visitor-facing routes. Keeping this
     * list explicit prevents admin, Debugbar, Ignition, Cypress, and OAuth route
     * metadata from being serialized into every public page response.
     */
    'groups' => [
        'frontend' => [
            'frontend.*',
            'api.frontend.*',
            'change.password',
            'login',
            'login.facebook',
            'login.google',
            'login2fa',
            'login2fa.perform',
            'login2fa.verify.perform',
            'register',
            'register.form',
            'showLogin',
            'notice.download',
            'notice.pdfViewer',
            'search',
            'chat.bootstrap',
            'chat.conversations.show',
            'chat.conversations.store',
            'chat.faqs.click',
            'chat.messages.store',
        ],
    ],
];
