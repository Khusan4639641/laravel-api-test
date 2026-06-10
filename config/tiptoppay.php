<?php

return [
    'enabled' => (bool) env('TIPTOPPAY_ENABLED', false),
    'test_mode' => (bool) env('TIPTOPPAY_TEST_MODE', true),
    'public_terminal_id' => env('TIPTOPPAY_PUBLIC_TERMINAL_ID'),
    'payment_schema' => env('TIPTOPPAY_PAYMENT_SCHEMA', 'Single'),
    'currency' => env('TIPTOPPAY_CURRENCY', 'KZT'),
    'success_url' => env('TIPTOPPAY_SUCCESS_URL', env('APP_URL').'/payment/success'),
    'fail_url' => env('TIPTOPPAY_FAIL_URL', env('APP_URL').'/payment/fail'),
    'api_password' => env('TIPTOPPAY_API_PASSWORD'),
    'webhook_secret' => env('TIPTOPPAY_WEBHOOK_SECRET', env('TIPTOPPAY_API_PASSWORD')),
];
