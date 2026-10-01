<?php

return [
    'timezone' => env('AZARI_TIMEZONE', env('APP_TIMEZONE', 'Africa/Lagos')),
    'pagination' => ['per_page' => 10],
    'currency' => 'USD',

    'owners' => [
        // Platform commission on owner-property room sales is disabled.
        'default_azari_share_percentage' => 0.00,
        'default_owner_share_percentage' => 100.00,
    ],

    'booking' => [
        'hold_minutes' => (int) env('AZARI_BOOKING_HOLD_MINUTES', 15),
        'unpaid_booking_minutes' => max(1, (int) env('AZARI_UNPAID_BOOKING_MINUTES', 60)),
        'payment_reminder_minutes' => max(1, (int) env('AZARI_PAYMENT_REMINDER_MINUTES', 30)),
        'default_tax_rate' => (float) env('AZARI_DEFAULT_TAX_RATE', 0),
        'default_service_fee' => (float) env('AZARI_DEFAULT_SERVICE_FEE', 0),
        'same_day_booking' => filter_var(env('AZARI_ALLOW_SAME_DAY_BOOKING', false), FILTER_VALIDATE_BOOL),
        'active_statuses' => ['hold', 'pending', 'pending_payment', 'approved', 'confirmed', 'paid', 'check_in', 'checked_in'],
        'modifiable_statuses' => ['hold', 'pending', 'pending_payment', 'approved', 'confirmed'],
    ],

    'identity' => [
        'max_kilobytes' => (int) env('AZARI_IDENTITY_MAX_KB', 10240),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'dojah' => [
            'enabled' => filter_var(env('DOJAH_ENABLED', false), FILTER_VALIDATE_BOOL),
            'environment' => env('DOJAH_ENV', 'live'),
            'base_url' => rtrim((string) env('DOJAH_BASE_URL', 'https://api.dojah.io'), '/'),
            'app_id' => env('DOJAH_APP_ID'),
            'secret_key' => env('DOJAH_SECRET_KEY'),
            'public_key' => env('DOJAH_PUBLIC_KEY'),
            'widget_id' => env('DOJAH_WIDGET_ID', env('DOJAH_TOKEN_ID')),
            'widget_type' => env('DOJAH_WIDGET_TYPE', 'custom'),
            'token_name' => env('DOJAH_TOKEN_NAME'),
            'token_id' => env('DOJAH_TOKEN_ID'),
            'webhook_signature_header' => env('DOJAH_WEBHOOK_SIGNATURE_HEADER', 'x-dojah-signature'),
            'webhook_signature_v2_header' => env('DOJAH_WEBHOOK_SIGNATURE_V2_HEADER', 'x-dojah-signature-v2'),
            'required_steps' => array_values(array_filter(array_map(
                static fn (string $step): string => strtolower(trim($step)),
                explode(',', (string) env('DOJAH_REQUIRED_STEPS', ''))
            ))),
        ],
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
            'api_version' => env('FLUTTERWAVE_API_VERSION', 'v4'),
            'client_id' => env('FLUTTERWAVE_CLIENT_ID'),
            'client_secret' => env('FLUTTERWAVE_CLIENT_SECRET'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            'token_url' => env('FLUTTERWAVE_TOKEN_URL', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token'),
            'sandbox_base_url' => env('FLUTTERWAVE_SANDBOX_BASE_URL', 'https://developersandbox-api.flutterwave.com'),
            'live_base_url' => env('FLUTTERWAVE_LIVE_BASE_URL', 'https://f4bexperience.flutterwave.com'),
            'orchestrator_path' => env('FLUTTERWAVE_ORCHESTRATOR_PATH', '/orchestration/direct-charges'),
            'charge_path' => env('FLUTTERWAVE_CHARGE_PATH', '/charges/{id}'),
            'banks_path' => env('FLUTTERWAVE_BANKS_PATH', '/banks'),
            'allowed_payment_methods' => array_values(array_filter(array_map(
                static fn (string $method): string => strtolower(trim($method)),
                explode(',', (string) env('FLUTTERWAVE_ALLOWED_PAYMENT_METHODS', 'opay,ussd'))
            ))),
            'default_payment_method' => strtolower((string) env('FLUTTERWAVE_DEFAULT_PAYMENT_METHOD', 'opay')),
            'token_cache_seconds' => max(60, min(540, (int) env('FLUTTERWAVE_TOKEN_CACHE_SECONDS', 540))),
        ],

        'pesapal' => [
            'enabled' => filter_var(env('PESAPAL_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('PESAPAL_MODE', 'sandbox'),
            'base_url' => env('PESAPAL_BASE_URL', env('PESAPAL_MODE', 'sandbox') === 'live' ? 'https://pay.pesapal.com/v3' : 'https://cybqa.pesapal.com/pesapalv3'),
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
