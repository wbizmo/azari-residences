<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public System Status
    |--------------------------------------------------------------------------
    |
    | The generated snapshot contains only sanitized, public-safe health
    | information. The production snapshot itself may live outside the
    | web roots so both Azari domains can consume the same source.
    |
    */

    'snapshot_path' => env(
        'AZARI_STATUS_SNAPSHOT_PATH',
        storage_path('app/status/public-status.json')
    ),

    'public_urls' => array_values(array_filter(array_map(
        'trim',
        explode(
            ',',
            (string) env(
                'AZARI_STATUS_PUBLIC_URLS',
                env('APP_URL', '')
            )
        )
    ))),

    /*
    | A snapshot older than this is treated as delayed / unknown.
    */
    'stale_minutes' => (int) env(
        'AZARI_STATUS_STALE_MINUTES',
        10
    ),

    /*
    | 288 samples = 24 hours at one sample every five minutes.
    */
    'history_samples' => (int) env(
        'AZARI_STATUS_HISTORY_SAMPLES',
        288
    ),

];
