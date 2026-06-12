<?php

return [
    'public_registration_enabled' => (bool) env('SAFI_PUBLIC_REGISTRATION_ENABLED', false),
    'user_package_changes_enabled' => (bool) env('SAFI_USER_PACKAGE_CHANGES_ENABLED', false),
    'user_package_purchases_enabled' => (bool) env('SAFI_USER_PACKAGE_PURCHASES_ENABLED', false),

    'withdrawals' => [
        'payout_period_days' => 14,
        'payment_methods' => [
            'ip_account',
            'card_account',
        ],
    ],
];
