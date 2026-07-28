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

    'integrations' => [
        'require_https_in_production' => filter_var(env('AZARI_INTEGRATIONS_REQUIRE_HTTPS', true), FILTER_VALIDATE_BOOL),
        'http_timeout' => max(5, (int) env('AZARI_INTEGRATION_HTTP_TIMEOUT', 20)),
        'connect_timeout' => max(2, (int) env('AZARI_INTEGRATION_CONNECT_TIMEOUT', 8)),
        'reconcile_limit' => max(1, min(500, (int) env('AZARI_PAYMENT_RECONCILE_LIMIT', 100))),
        'webhook_queue' => env('AZARI_WEBHOOK_QUEUE', 'integrations'),
    ],

    'payments' => [
        'flutterwave' => [
            'enabled' => filter_var(env('FLUTTERWAVE_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('FLUTTERWAVE_MODE', 'test'),
            'api_version' => env('FLUTTERWAVE_API_VERSION', 'v3'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
            'initialise_path' => env('FLUTTERWAVE_INITIALISE_PATH', '/v3/payments'),
            'verify_path' => env('FLUTTERWAVE_VERIFY_PATH', '/v3/transactions/{id}/verify'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            'payload_hash_enabled' => filter_var(env('FLUTTERWAVE_PAYLOAD_HASH_ENABLED', false), FILTER_VALIDATE_BOOL),
        ],

        'pesapal' => [
            'enabled' => filter_var(env('PESAPAL_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('PESAPAL_MODE', 'sandbox'),
            'base_url' => env(
                'PESAPAL_BASE_URL',
                env('PESAPAL_MODE', 'sandbox') === 'live'
                    ? 'https://pay.pesapal.com/v3'
                    : 'https://cybqa.pesapal.com/pesapalv3'
            ),
            'auth_path' => env('PESAPAL_AUTH_PATH', '/api/Auth/RequestToken'),
            'submit_order_path' => env('PESAPAL_SUBMIT_ORDER_PATH', '/api/Transactions/SubmitOrderRequest'),
            'status_path' => env('PESAPAL_STATUS_PATH', '/api/Transactions/GetTransactionStatus'),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'callback_url' => env('PESAPAL_CALLBACK_URL'),
            'notification_id' => env('PESAPAL_NOTIFICATION_ID'),
            'token_cache_seconds' => max(60, (int) env('PESAPAL_TOKEN_CACHE_SECONDS', 240)),
        ],

        'intouch' => [
            'enabled' => filter_var(env('INTOUCH_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('INTOUCH_MODE', 'test'),
            'profile' => env('INTOUCH_API_PROFILE', ''),
            'base_url' => env('INTOUCH_BASE_URL'),
            'merchant_id' => env('INTOUCH_MERCHANT_ID'),
            'username' => env('INTOUCH_USERNAME'),
            'password' => env('INTOUCH_PASSWORD'),
            'secret' => env('INTOUCH_SECRET'),
            'api_token' => env('INTOUCH_API_TOKEN'),
            'auth_mode' => env('INTOUCH_AUTH_MODE', 'basic_and_headers'),
            'token_header' => env('INTOUCH_TOKEN_HEADER', 'x-intouch-o-token'),
            'merchant_header' => env('INTOUCH_MERCHANT_HEADER', 'X-Merchant-ID'),
            'secret_header' => env('INTOUCH_SECRET_HEADER', 'X-API-Secret'),
            'callback_url' => env('INTOUCH_CALLBACK_URL'),
            'webhook_secret' => env('INTOUCH_WEBHOOK_SECRET'),
            'webhook_signature_header' => env('INTOUCH_WEBHOOK_SIGNATURE_HEADER', 'x-intouch-signature'),
            'initialise_path' => env('INTOUCH_INITIALISE_PATH', '/api/v1/payments/initialize'),
            'verify_path' => env('INTOUCH_VERIFY_PATH', '/api/v1/payments/{reference}'),
            'health_path' => env('INTOUCH_HEALTH_PATH', ''),
            'checkout_url_field' => env('INTOUCH_CHECKOUT_URL_FIELD', 'checkout_url'),
            'provider_reference_field' => env('INTOUCH_PROVIDER_REFERENCE_FIELD', 'transaction_id'),
            'merchant_reference_field' => env('INTOUCH_MERCHANT_REFERENCE_FIELD', 'merchant_reference'),
            'status_field' => env('INTOUCH_STATUS_FIELD', 'status'),
            'amount_field' => env('INTOUCH_AMOUNT_FIELD', 'amount'),
            'currency_field' => env('INTOUCH_CURRENCY_FIELD', 'currency'),
            'successful_statuses' => array_values(array_filter(array_map('trim', explode(',', env('INTOUCH_SUCCESSFUL_STATUSES', 'SUCCESS,SUCCESSFUL,COMPLETED,PAID'))))),
            'failed_statuses' => array_values(array_filter(array_map('trim', explode(',', env('INTOUCH_FAILED_STATUSES', 'FAILED,DECLINED,CANCELLED,INVALID'))))),
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
