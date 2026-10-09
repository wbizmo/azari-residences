<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => true,
            'report' => true,
        ],

        'public' => [
            'driver' => 'local',

            // Store uploads directly in the real public directory.
            'root' => public_path('storage'),

            // Keep generated links as /storage/... without exposing /public.
            'url' => rtrim((string) env('APP_URL', ''), '/').'/storage',

            'visibility' => 'public',
            'directory_visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'resavar_offsite' => [
            'driver' => 's3',
            'key' => env('RESAVAR_OFFSITE_ACCESS_KEY_ID'),
            'secret' => env('RESAVAR_OFFSITE_SECRET_ACCESS_KEY'),
            'region' => env('RESAVAR_OFFSITE_REGION'),
            'bucket' => env('RESAVAR_OFFSITE_BUCKET'),
            'endpoint' => env('RESAVAR_OFFSITE_ENDPOINT'),
            'use_path_style_endpoint' => env('RESAVAR_OFFSITE_PATH_STYLE', false),
            'root' => env('RESAVAR_OFFSITE_ROOT', 'resavar-backups'),
            'throw' => true,
        ],

        // Operators set this to an EXISTING empty directory for an isolated
        // recovery drill. Never point it at storage/app/private.
        'resavar_media_restore' => [
            'driver' => 'local',
            'root' => env('RESAVAR_MEDIA_RESTORE_ROOT'),
            'throw' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false
            ),
            'throw' => false,
            'report' => false,
        ],
    ],

    // Offsite backup credentials MUST be independent of application media
    // storage. There is deliberately no fallback to local/private/public.
    'offsite_backup_disk' => env('RESAVAR_OFFSITE_BACKUP_DISK'),

    // No symlink is required because files are written directly there.
    'links' => [],
];