<?php

namespace Database\Seeders;

use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;

class BonusDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('login', 'aidar')->with('wallets')->first();

        if (! $user) {
            return;
        }

        $mainWallet = $user->wallets->firstWhere('type', 'main');
        $bonusWallet = $user->wallets->firstWhere('type', 'bonus');

        $items = [
            ['type' => 'referral', 'amount' => 240000, 'source' => 'Партнерские подключения'],
            ['type' => 'binary', 'amount' => 380000, 'source' => 'Расчет меньшей ветки'],
            ['type' => 'status', 'amount' => 250000, 'source' => 'Достижение статуса'],
            ['type' => 'cashback', 'amount' => 60000, 'source' => 'Личные покупки'],
        ];

        foreach ($items as $item) {
            $bonus = BonusTransaction::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bonus_type' => $item['type'],
                    'amount' => $item['amount'],
                ],
                [
                    'status' => 'completed',
                    'left_pv' => $user->left_pv,
                    'right_pv' => $user->right_pv,
                    'matched_pv' => min((float) $user->left_pv, (float) $user->right_pv),
                    'metadata' => ['source' => $item['source']],
                    'calculated_at' => now()->subDays(rand(1, 14)),
                ]
            );

            $wallet = $item['type'] === 'cashback' ? $bonusWallet : $mainWallet;

            if (! $wallet) {
                continue;
            }

            $walletTransaction = WalletTransaction::query()->updateOrCreate(
                [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'type' => $item['type'].'_bonus_demo',
                ],
                [
                    'direction' => 'credit',
                    'amount' => $item['amount'],
                    'balance_before' => 0,
                    'balance_after' => $item['amount'],
                    'status' => 'completed',
                    'source_type' => BonusTransaction::class,
                    'source_id' => $bonus->id,
                    'description' => $item['source'],
                ]
            );

            $bonus->forceFill(['wallet_transaction_id' => $walletTransaction->id])->save();
        }
    }
}
