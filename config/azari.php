<?php

return [
    'timezone' => env('AZARI_TIMEZONE', env('APP_TIMEZONE', 'Africa/Lagos')),
    'pagination' => ['per_page' => 10],
    'booking' => [
        'hold_minutes' => (int) env('AZARI_BOOKING_HOLD_MINUTES', 15),
        'default_tax_rate' => (float) env('AZARI_DEFAULT_TAX_RATE', 0),
        'default_service_fee' => (float) env('AZARI_DEFAULT_SERVICE_FEE', 0),
        'same_day_booking' => filter_var(env('AZARI_ALLOW_SAME_DAY_BOOKING', false), FILTER_VALIDATE_BOOL),
        'active_statuses' => ['hold', 'pending', 'pending_payment', 'approved', 'confirmed', 'paid', 'check_in', 'checked_in'],
        'modifiable_statuses' => ['hold', 'pending', 'pending_payment', 'approved', 'confirmed'],
    ],
    'identity' => [
        'max_kilobytes' => (int) env('AZARI_IDENTITY_MAX_KB', 10240),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],
    'phone_verification' => [
        'enabled' => filter_var(env('AZARI_PHONE_VERIFICATION_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
    'payments' => [
        'flutterwave' => [
            'enabled' => filter_var(env('FLUTTERWAVE_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('FLUTTERWAVE_MODE', 'test'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        ],
        'pesapal' => [
            'enabled' => filter_var(env('PESAPAL_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('PESAPAL_MODE', 'sandbox'),
            'base_url' => env('PESAPAL_BASE_URL', env('PESAPAL_MODE', 'sandbox') === 'live' ? 'https://pay.pesapal.com/v3' : 'https://cybqa.pesapal.com/pesapalv3'),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'callback_url' => env('PESAPAL_CALLBACK_URL'),
            'notification_id' => env('PESAPAL_NOTIFICATION_ID'),
        ],
        'intouch' => [
            'enabled' => filter_var(env('INTOUCH_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('INTOUCH_MODE', 'test'),
            'base_url' => env('INTOUCH_BASE_URL'),
            'merchant_id' => env('INTOUCH_MERCHANT_ID'),
            'username' => env('INTOUCH_USERNAME'),
            'password' => env('INTOUCH_PASSWORD'),
            'secret' => env('INTOUCH_SECRET'),
            'callback_url' => env('INTOUCH_CALLBACK_URL'),
            'webhook_secret' => env('INTOUCH_WEBHOOK_SECRET'),
            'initialise_path' => env('INTOUCH_INITIALISE_PATH', '/api/v1/payments/initialize'),
            'verify_path' => env('INTOUCH_VERIFY_PATH', '/api/v1/payments/{reference}'),
            'health_path' => env('INTOUCH_HEALTH_PATH', ''),
        ],
    ],
    'twilio' => [
        'enabled' => filter_var(env('TWILIO_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
    'service_request_email' => env('AZARI_SERVICE_REQUEST_EMAIL'),
    'support_ticket_email' => env('AZARI_SUPPORT_TICKET_EMAIL'),
    'contact_recipient_email' => env('AZARI_CONTACT_RECIPIENT_EMAIL'),
    'booking_notification_email' => env('AZARI_BOOKING_NOTIFICATION_EMAIL'),
];
