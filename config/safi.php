<?php

return [
    'user_package_changes_enabled' => (bool) env('SAFI_USER_PACKAGE_CHANGES_ENABLED', false),

    'withdrawals' => [
        'payout_period_days' => 14,
        'payment_methods' => [
            'ip_account',
            'card_account',
        ],
    ],
];
