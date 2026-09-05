<?php

return [
    'editor_locales' => [
        'en' => ['name' => 'English', 'native_name' => 'English'],
        'bn' => ['name' => 'Bangla', 'native_name' => 'বাংলা'],
    ],

    // English and Bangla have complete release catalogs. The database locale
    // record remains the final operational gate for public exposure.
    'public_locales' => ['en', 'bn'],
    'public_switcher_enabled' => true,
];
