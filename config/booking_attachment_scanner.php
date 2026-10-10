<?php

return [
    'host' => env('RESAVAR_CLAMAV_HOST'),
    'port' => (int) env('RESAVAR_CLAMAV_PORT', 3310),
    'timeout_seconds' => (int) env('RESAVAR_CLAMAV_TIMEOUT', 10),
];
