<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

class WalletService
{
    public function createUserWallets(User $user): void
    {
        // TODO: Create default wallet records for a newly registered user.
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
