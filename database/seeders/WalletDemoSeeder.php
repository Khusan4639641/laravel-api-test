<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;

class WalletDemoSeeder extends Seeder
{
    public function run(): void
    {
        $balances = [
            'aidar' => ['main' => 320000, 'bonus' => 38000],
            'erlan' => ['main' => 120000, 'bonus' => 12000],
            'alisa' => ['main' => 45000, 'bonus' => 4000],
            'gulnara' => ['main' => 510000, 'bonus' => 75000],
            'alexey' => ['main' => 89000, 'bonus' => 9000],
            'dinara' => ['main' => 18000, 'bonus' => 1500],
            'admin' => ['main' => 0, 'bonus' => 0],
        ];

        foreach ($balances as $login => $wallets) {
            $user = User::query()->where('login', $login)->first();

            if (! $user) {
                continue;
            }

            foreach ($wallets as $type => $balance) {
                $wallet = Wallet::query()->updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    [
                        'currency' => 'KZT',
                        'balance' => $balance,
                        'hold_balance' => 0,
                        'status' => 'active',
                    ]
                );

                if ($balance > 0) {
                    WalletTransaction::query()->updateOrCreate(
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $user->id,
                            'type' => 'demo_initial_balance',
                        ],
                        [
                            'direction' => 'credit',
                            'amount' => $balance,
                            'balance_before' => 0,
                            'balance_after' => $balance,
                            'status' => 'completed',
                            'description' => 'Demo initial balance',
                        ]
                    );
                }
            }
        }
    }
}
