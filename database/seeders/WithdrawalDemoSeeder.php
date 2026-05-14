<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;

class WithdrawalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('login', 'aidar')->with('wallets')->first();
        $wallet = $user?->wallets->firstWhere('type', 'main');

        if (! $user || ! $wallet) {
            return;
        }

        $withdrawals = [
            ['amount' => 150000, 'status' => 'approved', 'processed_at' => now()->subDays(4), 'comment' => 'Успешный перевод'],
            ['amount' => 200000, 'status' => 'approved', 'processed_at' => now()->subDays(12), 'comment' => 'Успешный перевод'],
            ['amount' => 75000, 'status' => 'pending', 'processed_at' => null, 'comment' => null],
        ];

        foreach ($withdrawals as $index => $withdrawal) {
            WithdrawalRequest::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $withdrawal['amount'],
                ],
                [
                    'fee_amount' => 0,
                    'net_amount' => $withdrawal['amount'],
                    'currency' => 'KZT',
                    'status' => $withdrawal['status'],
                    'payment_method' => $index === 1 ? 'bank_account' : 'card',
                    'payment_details' => ['label' => $index === 1 ? 'Счет ИП' : 'Карта партнера'],
                    'admin_comment' => $withdrawal['comment'],
                    'processed_at' => $withdrawal['processed_at'],
                ]
            );
        }
    }
}
