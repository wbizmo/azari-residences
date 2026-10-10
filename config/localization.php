<?php

return [
    'default_locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'platform_timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),
    'default_currency' => env('APP_CURRENCY', 'USD'),
    'supported_locales' => [
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
    ],
    'supported_currencies' => [
        'USD' => 'US Dollar',
        'NGN' => 'Nigerian Naira',
        'GBP' => 'British Pound',
        'EUR' => 'Euro',
        'CAD' => 'Canadian Dollar',
        'AED' => 'UAE Dirham',
        'KES' => 'Kenyan Shilling',
        'GHS' => 'Ghanaian Cedi',
        'ZAR' => 'South African Rand',
    ],
];
