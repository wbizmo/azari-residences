<?php
return [
    'pagination' => ['per_page' => 10],
    'booking' => [
        'hold_minutes' => (int) env('AZARI_BOOKING_HOLD_MINUTES', 15),
        'default_tax_rate' => (float) env('AZARI_DEFAULT_TAX_RATE', 0),
        'default_service_fee' => (float) env('AZARI_DEFAULT_SERVICE_FEE', 0),
        'same_day_booking' => filter_var(env('AZARI_ALLOW_SAME_DAY_BOOKING', false), FILTER_VALIDATE_BOOL),
        'active_statuses' => ['hold','pending','approved','confirmed','checked_in'],
        'modifiable_statuses' => ['hold','pending','approved','confirmed'],
    ],
];
