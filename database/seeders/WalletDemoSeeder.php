<?php

namespace Database\Seeders;

use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;

class WalletDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('login', ['admin', 'support', 'aidar', 'erlan', 'alisa', 'gulnara', 'alexey', 'dinara', 'timur', 'madina', 'nurlan'])
            ->get()
            ->keyBy('login');

        $wallets = $this->seedWallets($users);
        $this->seedBonuses($users, $wallets);
        $this->seedWithdrawals($users, $wallets);
    }

    private function seedWallets($users): array
    {
        $balances = [
            'admin' => ['main' => [0, 0], 'bonus' => [0, 0]],
            'support' => ['main' => [0, 0], 'bonus' => [0, 0]],
            'aidar' => ['main' => [320000, 75000], 'bonus' => [38000, 0]],
            'erlan' => ['main' => [120000, 25000], 'bonus' => [12000, 0]],
            'alisa' => ['main' => [45000, 0], 'bonus' => [4000, 0]],
            'gulnara' => ['main' => [510000, 100000], 'bonus' => [75000, 0]],
            'alexey' => ['main' => [89000, 0], 'bonus' => [9000, 0]],
            'dinara' => ['main' => [18000, 0], 'bonus' => [1500, 0]],
            'timur' => ['main' => [76000, 0], 'bonus' => [8200, 0]],
            'madina' => ['main' => [12000, 0], 'bonus' => [900, 0]],
            'nurlan' => ['main' => [98000, 0], 'bonus' => [11000, 0]],
        ];

        $walletsByLogin = [];

        foreach ($balances as $login => $walletTypes) {
            $user = $users->get($login);

            if (! $user) {
                continue;
            }

            foreach ($walletTypes as $type => [$balance, $holdBalance]) {
                $wallet = Wallet::query()->updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    [
                        'currency' => 'KZT',
                        'balance' => $balance,
                        'hold_balance' => $holdBalance,
                        'status' => 'active',
                    ]
                );

                $walletsByLogin[$login][$type] = $wallet;

                if ($balance > 0) {
                    WalletTransaction::query()->updateOrCreate(
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $user->id,
                            'type' => 'demo_initial_'.$type,
                        ],
                        [
                            'direction' => 'credit',
                            'amount' => $balance,
                            'balance_before' => 0,
                            'balance_after' => $balance,
                            'status' => 'completed',
                            'description' => 'Demo initial '.$type.' wallet balance',
                            'metadata' => ['demo' => true],
                        ]
                    );
                }
            }
        }

        return $walletsByLogin;
    }

    private function seedBonuses($users, array $wallets): void
    {
        $bonuses = [
            ['login' => 'aidar', 'type' => 'referral', 'amount' => 240000, 'wallet' => 'main', 'source' => 'erlan', 'description' => 'Реферальный бонус за подключение Ерлана'],
            ['login' => 'aidar', 'type' => 'binary', 'amount' => 380000, 'wallet' => 'main', 'source' => 'alisa', 'description' => 'Бинарный бонус за меньшую ветку'],
            ['login' => 'aidar', 'type' => 'status', 'amount' => 250000, 'wallet' => 'main', 'source' => null, 'description' => 'Статусный бонус'],
            ['login' => 'aidar', 'type' => 'cashback', 'amount' => 60000, 'wallet' => 'bonus', 'source' => null, 'description' => 'Кешбэк за личные покупки'],
            ['login' => 'erlan', 'type' => 'referral', 'amount' => 90000, 'wallet' => 'main', 'source' => 'gulnara', 'description' => 'Реферальный бонус за Гульнару'],
            ['login' => 'erlan', 'type' => 'binary', 'amount' => 110000, 'wallet' => 'main', 'source' => 'alexey', 'description' => 'Бинарный бонус команды Ерлана'],
            ['login' => 'gulnara', 'type' => 'status', 'amount' => 200000, 'wallet' => 'main', 'source' => null, 'description' => 'Бонус за статус Директор'],
            ['login' => 'timur', 'type' => 'referral', 'amount' => 45000, 'wallet' => 'main', 'source' => 'dinara', 'description' => 'Реферальный бонус за активность структуры'],
        ];

        foreach ($bonuses as $index => $item) {
            $user = $users->get($item['login']);
            $wallet = $wallets[$item['login']][$item['wallet']] ?? null;

            if (! $user || ! $wallet) {
                continue;
            }

            $sourceUser = $item['source'] ? $users->get($item['source']) : null;
            $bonus = BonusTransaction::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bonus_type' => $item['type'],
                    'amount' => $item['amount'],
                ],
                [
                    'source_user_id' => $sourceUser?->id,
                    'status' => 'completed',
                    'left_pv' => $user->left_pv,
                    'right_pv' => $user->right_pv,
                    'matched_pv' => min((float) $user->left_pv, (float) $user->right_pv),
                    'metadata' => ['source' => $item['description']],
                    'calculated_at' => now()->subDays($index + 1),
                ]
            );

            $walletTransaction = WalletTransaction::query()->updateOrCreate(
                [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'type' => $item['type'].'_bonus_demo_'.$index,
                ],
                [
                    'direction' => 'credit',
                    'amount' => $item['amount'],
                    'balance_before' => 0,
                    'balance_after' => $item['amount'],
                    'status' => 'completed',
                    'source_type' => BonusTransaction::class,
                    'source_id' => $bonus->id,
                    'description' => $item['description'],
                    'metadata' => ['demo' => true],
                ]
            );

            $bonus->forceFill(['wallet_transaction_id' => $walletTransaction->id])->save();
        }
    }

    private function seedWithdrawals($users, array $wallets): void
    {
        $withdrawals = [
            ['login' => 'aidar', 'amount' => 150000, 'status' => 'approved', 'processed_at' => now()->subDays(4), 'comment' => 'Успешный перевод на карту'],
            ['login' => 'aidar', 'amount' => 200000, 'status' => 'approved', 'processed_at' => now()->subDays(12), 'comment' => 'Успешный перевод на счет ИП'],
            ['login' => 'aidar', 'amount' => 75000, 'status' => 'pending', 'processed_at' => null, 'comment' => null],
            ['login' => 'erlan', 'amount' => 25000, 'status' => 'pending', 'processed_at' => null, 'comment' => null],
            ['login' => 'gulnara', 'amount' => 100000, 'status' => 'approved', 'processed_at' => now()->subDays(2), 'comment' => 'Выплата подтверждена'],
            ['login' => 'alexey', 'amount' => 30000, 'status' => 'rejected', 'processed_at' => now()->subDays(6), 'comment' => 'Проверьте реквизиты карты'],
        ];

        foreach ($withdrawals as $index => $withdrawalData) {
            $user = $users->get($withdrawalData['login']);
            $wallet = $wallets[$withdrawalData['login']]['main'] ?? null;

            if (! $user || ! $wallet) {
                continue;
            }

            $withdrawal = WithdrawalRequest::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $withdrawalData['amount'],
                ],
                [
                    'fee_amount' => 0,
                    'net_amount' => $withdrawalData['amount'],
                    'currency' => 'KZT',
                    'status' => $withdrawalData['status'],
                    'payment_method' => $index % 2 === 0 ? 'card' : 'bank_account',
                    'payment_details' => ['label' => $index % 2 === 0 ? 'Карта партнера' : 'Счет партнера'],
                    'admin_comment' => $withdrawalData['comment'],
                    'processed_at' => $withdrawalData['processed_at'],
                ]
            );

            if ($withdrawalData['status'] !== 'pending') {
                WalletTransaction::query()->updateOrCreate(
                    [
                        'wallet_id' => $wallet->id,
                        'user_id' => $user->id,
                        'type' => 'withdrawal_'.$withdrawalData['status'].'_demo_'.$index,
                    ],
                    [
                        'direction' => $withdrawalData['status'] === 'approved' ? 'debit' : 'credit',
                        'amount' => $withdrawalData['amount'],
                        'balance_before' => $wallet->balance,
                        'balance_after' => $wallet->balance,
                        'status' => 'completed',
                        'source_type' => WithdrawalRequest::class,
                        'source_id' => $withdrawal->id,
                        'description' => $withdrawalData['comment'] ?? 'Demo withdrawal',
                        'metadata' => ['demo' => true],
                    ]
                );
            }
        }
    }
}
