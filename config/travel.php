<?php

return [
    // OFF by default until signed contracts, safety reviews, supplier sandbox
    // certification, independent settlement, refund support and monitoring.
    'requests_enabled' => env('RESAVAR_TRAVEL_REQUESTS_ENABLED', false),
    'webhooks_enabled' => env('RESAVAR_TRAVEL_WEBHOOKS_ENABLED', false),
    // Enable only when actual provider payment capture, booking, refund,
    // chargeback and reconciliation adapters are contracted and deployed.
    'fulfillment_enabled' => env('RESAVAR_TRAVEL_FULFILLMENT_ENABLED', false),
    'payment_verifier' => null,
    'refund_verifier' => null,

    // Intentionally empty until certified supplier adapters are installed.
    // Never infer actual availability or issue a ticket from a catalog offer.
    'supplier_adapters' => [],
    'request_ttl_minutes' => 15,
];
