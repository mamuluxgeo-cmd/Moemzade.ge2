<?php

declare(strict_types=1);

return [
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', 'https://moemzade.ge'), '/'),
    'key' => (string) env('APP_KEY', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'Asia/Tbilisi'),
    'db' => [
        'host' => (string) env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => (string) env('DB_NAME', 'moemzade'),
        'user' => (string) env('DB_USER', 'moemzade'),
        'password' => (string) env('DB_PASSWORD', ''),
    ],
    'admin' => [
        'email' => strtolower((string) env('ADMIN_EMAIL', 'admin@moemzade.ge')),
        'password_hash' => (string) env('ADMIN_PASSWORD_HASH', ''),
    ],
    'media' => [
        'driver' => (string) env('MEDIA_DRIVER', 'local'),
        'max_upload_bytes' => (int) env('MEDIA_MAX_UPLOAD_MB', 10) * 1024 * 1024,
        'local_root' => BASE_PATH . '/' . trim((string) env('MEDIA_LOCAL_ROOT', 'media'), '/'),
        'public_url' => rtrim((string) env('MEDIA_PUBLIC_URL', '/media'), '/'),
        'r2' => [
            'account_id' => (string) env('R2_ACCOUNT_ID', ''),
            'access_key_id' => (string) env('R2_ACCESS_KEY_ID', ''),
            'secret_access_key' => (string) env('R2_SECRET_ACCESS_KEY', ''),
            'bucket' => (string) env('R2_BUCKET', ''),
            'public_url' => rtrim((string) env('R2_PUBLIC_URL', ''), '/'),
        ],
    ],
];

