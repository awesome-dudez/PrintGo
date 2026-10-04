<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Upload File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes allowed for upload at the kiosk.
    | Default: 50MB (52,428,800 bytes).
    |
    */
    'max_file_size' => (int) env('PRINT_MAX_FILE_SIZE', 52428800),

    /*
    |--------------------------------------------------------------------------
    | Printing Session TTL (Minutes)
    |--------------------------------------------------------------------------
    |
    | Inactivity timeout for a printing session in minutes.
    |
    */
    'session_ttl_minutes' => (int) env('PRINT_SESSION_TTL_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | File Cleanup After Hours
    |--------------------------------------------------------------------------
    |
    | Duration in hours after which completed, expired, or abandoned files
    | will be automatically pruned from disk.
    |
    */
    'file_cleanup_after_hours' => (int) env('PRINT_FILE_CLEANUP_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Default Currency Code
    |--------------------------------------------------------------------------
    |
    | Default currency code used in pricing and payment transactions.
    |
    */
    'currency' => env('PRINT_CURRENCY', 'PHP'),

    /*
    |--------------------------------------------------------------------------
    | Supported File Extensions
    |--------------------------------------------------------------------------
    |
    | Allowed file extensions for kiosk document processing.
    |
    */
    'supported_extensions' => [
        'pdf',
        'docx',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'heic',
        'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported MIME Types
    |--------------------------------------------------------------------------
    |
    | Allowed MIME types matching supported file extensions.
    |
    */
    'supported_mimes' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Print Agent Secret Token
    |--------------------------------------------------------------------------
    |
    | Authentication token used by hardware print agents to poll and update jobs.
    |
    */
    'agent_token' => env('PRINT_AGENT_TOKEN', 'kiosk-agent-secret-token-12345'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk used for storing uploaded files and preview renders.
    |
    */
    'storage_disk' => env('PRINT_STORAGE_DISK', 'local'),
];
