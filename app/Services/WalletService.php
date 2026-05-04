<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

class WalletService
{
    public function createUserWallets(User $user): void
    {
        foreach (['main', 'bonus'] as $type) {
            Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type,
                ],
                [
                    'currency' => 'USD',
                    'balance' => 0,
                    'hold_balance' => 0,
                    'status' => 'active',
                ]
            );
        }
    }

    public function credit(Wallet $wallet, float|string $amount, string $type, mixed $source = null): WalletTransaction
    {
        // TODO: Increase wallet balance and write an immutable credit transaction.
    }

    public function debit(Wallet $wallet, float|string $amount, string $type, mixed $source = null): WalletTransaction
    {
        // TODO: Validate available balance, decrease balance, and write debit transaction.
    }
}
