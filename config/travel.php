<?php

return [
    // OFF by default until signed contracts, safety reviews, supplier sandbox
    // certification, independent settlement, refund support and monitoring.
    'requests_enabled' => env('RESAVAR_TRAVEL_REQUESTS_ENABLED', false),

    // Intentionally empty until certified supplier adapters are installed.
    // Never infer actual availability or issue a ticket from a catalog offer.
    'supplier_adapters' => [],
    'request_ttl_minutes' => 15,
];
