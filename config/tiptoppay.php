<?php

return [
    'enabled' => (bool) env('TIPTOPPAY_ENABLED', false),
    'public_terminal_id' => env('TIPTOPPAY_PUBLIC_TERMINAL_ID'),
    'api_public_id' => env('TIPTOPPAY_API_PUBLIC_ID'),
    'api_password' => env('TIPTOPPAY_API_PASSWORD'),
    'secret' => env('TIPTOPPAY_SECRET', env('TIPTOPPAY_API_PASSWORD')),
    'webhook_secret' => env('TIPTOPPAY_WEBHOOK_SECRET', env('TIPTOPPAY_SECRET', env('TIPTOPPAY_API_PASSWORD'))),
    'payment_schema' => env('TIPTOPPAY_PAYMENT_SCHEMA', 'Single'),
    'test_mode' => (bool) env('TIPTOPPAY_TEST_MODE', true),
    'success_url' => env('TIPTOPPAY_SUCCESS_URL', env('APP_URL').'/payment/success'),
    'fail_url' => env('TIPTOPPAY_FAIL_URL', env('APP_URL').'/payment/fail'),
    'currency' => env('TIPTOPPAY_CURRENCY', 'KZT'),
];
