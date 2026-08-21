<?php

return [
    'editor_locales' => [
        'en' => ['name' => 'English', 'native_name' => 'English'],
        'bn' => ['name' => 'Bangla', 'native_name' => 'বাংলা'],
    ],

    // Public chrome is currently certified in English. Editors can prepare
    // translated content in the CMS; the switcher must remain disabled until
    // every public label, form, validation message, and SEO default is complete.
    'public_locales' => ['en'],
    'public_switcher_enabled' => false,
];
