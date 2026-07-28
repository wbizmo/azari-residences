<?php

return [
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'enabled' => filter_var(env('TWILIO_ENABLED', false), FILTER_VALIDATE_BOOL),
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'api_base_url' => env('TWILIO_API_BASE_URL', 'https://api.twilio.com'),
        'api_version' => env('TWILIO_API_VERSION', '2010-04-01'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'from' => env('TWILIO_FROM_NUMBER'),
        'status_callback' => env('TWILIO_STATUS_CALLBACK_URL'),
        'validate_webhooks' => filter_var(env('TWILIO_VALIDATE_WEBHOOKS', true), FILTER_VALIDATE_BOOL),
        'default_country_code' => env('TWILIO_DEFAULT_COUNTRY_CODE', '234'),
        'whatsapp_enabled' => filter_var(env('TWILIO_WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'whatsapp_content_sids' => [
            'booking-confirmed' => env('TWILIO_WHATSAPP_CONTENT_BOOKING_CONFIRMED'),
            'booking-reminder' => env('TWILIO_WHATSAPP_CONTENT_BOOKING_REMINDER'),
            'payment-confirmed' => env('TWILIO_WHATSAPP_CONTENT_PAYMENT_CONFIRMED'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'base_url' => env('PAYPAL_BASE_URL', env('PAYPAL_MODE', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com'),
    ],
];
