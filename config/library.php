<?php

/** Application settings read from the environment (never hardcoded, Art. IX). */
return [
    'jwt_secret' => env('JWT_SECRET'),
    'token_ttl_seconds' => 28800,
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'covers_dir' => env('COVERS_DIR', storage_path('app/covers')),
    'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
    // Demo only (ADR-006): shifts the API clock, in days. 0 means the real clock.
    'clock_offset_days' => (int) env('CLOCK_OFFSET_DAYS', 0),
];
