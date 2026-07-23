<?php

return [
    'name' => env('APP_NAME', 'Azari Residences'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),

    'payments' => [
        'flutterwave' => [
            'enabled' => env('FLUTTERWAVE_ENABLED', false),
            'mode' => env('FLUTTERWAVE_MODE', 'test'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            'callback_url' => env('FLUTTERWAVE_CALLBACK_URL'),
        ],

        'pesapal' => [
            'enabled' => env('PESAPAL_ENABLED', false),
            'mode' => env('PESAPAL_MODE', 'sandbox'),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'callback_url' => env('PESAPAL_CALLBACK_URL'),
            'notification_id' => env('PESAPAL_NOTIFICATION_ID'),
        ],

        'intouch' => [
            'enabled' => env('INTOUCH_ENABLED', false),
            'mode' => env('INTOUCH_MODE', 'test'),
            'merchant_id' => env('INTOUCH_MERCHANT_ID'),
            'username' => env('INTOUCH_USERNAME'),
            'password' => env('INTOUCH_PASSWORD'),
            'secret' => env('INTOUCH_SECRET'),
            'callback_url' => env('INTOUCH_CALLBACK_URL'),
            'webhook_secret' => env('INTOUCH_WEBHOOK_SECRET'),
        ],
    ],

    'twilio' => [
        'enabled' => env('TWILIO_ENABLED', false),
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        'status_callback_url' => env('TWILIO_STATUS_CALLBACK_URL'),
    ],

    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED', false),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret_key' => env('CAPTCHA_SECRET_KEY'),
    ],

    'maps' => [
        'enabled' => env('MAPS_ENABLED', false),
        'provider' => env('MAPS_PROVIDER'),
        'api_key' => env('MAPS_API_KEY'),
    ],
];
