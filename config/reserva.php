<?php

return [
    // Never permit a production environment flag to silently reactivate retired
    // flights, cars, dining, packages, supplier webhooks or channel syncs.
    // Non-production legacy fixture suites may explicitly opt out; production
    // product-scope changes require a reviewed code release and operator signoff.
    'accommodation_only' => (string) env('APP_ENV', 'production') === 'production'
        || filter_var(env('RESAVAR_ACCOMMODATION_ONLY', true), FILTER_VALIDATE_BOOL),
    'bookings' => [
        // New accommodation bookings are REQUESTS until owner/admin review.
        // An explicit false preserves the pre-refocus instant-booking contract.
        'require_approval' => filter_var(env('RESAVAR_BOOKINGS_REQUIRE_APPROVAL', true), FILTER_VALIDATE_BOOL),
        'review_hours' => max(1, min(168, (int) env('RESAVAR_BOOKING_REVIEW_HOURS', 24))),
    ],
    'fx' => [
        // Explicitly false until a gateway-certified charge/refund/ledger path exists.
        'checkout_enabled' => env('RESAVAR_FX_CHECKOUT_ENABLED', false),
    ],
    'channels' => [
        // Never enable in production: real providers require certified adapters.
        'sandbox_webhooks_enabled' => env('RESAVAR_SANDBOX_WEBHOOKS', false),
    ],
    'search' => [
        // Bound public result hydration until the faceted/paginated search layer lands.
        'max_properties' => (int) env('RESERVA_SEARCH_MAX_PROPERTIES', 60),
        'alternative_property_limit' => (int) env('RESERVA_ALTERNATIVE_PROPERTY_LIMIT', 12),
        'alternative_days' => (int) env('RESERVA_ALTERNATIVE_DAYS', 30),
        'autocomplete_default_limit' => (int) env('RESERVA_AUTOCOMPLETE_LIMIT', 8),
        'autocomplete_max_limit' => (int) env('RESERVA_AUTOCOMPLETE_MAX_LIMIT', 10),
        'autocomplete_min_chars' => (int) env('RESERVA_AUTOCOMPLETE_MIN_CHARS', 2),
        'autocomplete_cache_seconds' => (int) env('RESERVA_AUTOCOMPLETE_CACHE_SECONDS', 60),
    ],

    'media' => [
        'responsive_widths' => [480, 768, 1200],
        'formats' => ['webp', 'avif'],
        'node_binary' => env('RESARVA_NODE_BINARY', 'node'),
    ],

    'performance' => [
        // Product budgets used by browser/observability checks.
        'lcp_ms' => 2500,
        'inp_ms' => 200,
        'cls' => 0.10,
    ],
];
