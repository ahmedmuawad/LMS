<?php

declare(strict_types=1);

return [
    /*
     | ADR-003 — بنية روابط اللغة
     | العربية (الافتراضية) بلا بادئة:   /courses/laravel
     | الإنجليزية ببادئة:                /en/courses/laravel
     */
    'default' => 'ar',

    // اللغات التي تظهر ببادئة في المسار (كل شيء عدا الافتراضية)
    'prefixed' => ['en'],

    'supported' => [
        'ar' => ['name' => 'العربية',  'native' => 'العربية',  'dir' => 'rtl', 'flag' => '🇪🇬'],
        'en' => ['name' => 'English',  'native' => 'English',  'dir' => 'ltr', 'flag' => '🇬🇧'],
    ],
];
