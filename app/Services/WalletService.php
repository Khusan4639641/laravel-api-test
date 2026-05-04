<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use InvalidArgumentException;

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

    public function credit(
        Wallet $wallet,
        float|string $amount,
        string $type,
        mixed $source = null,
        array $metadata = [],
    ): WalletTransaction {
        $amount = (string) $amount;

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        $balanceBefore = (string) $wallet->balance;
        $balanceAfter = bcadd($balanceBefore, $amount, 2);

        $wallet->forceFill([
            'balance' => $balanceAfter,
        ])->save();

        $transaction = new WalletTransaction([
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'metadata' => $metadata ?: null,
        ]);

        if ($source) {
            $transaction->source()->associate($source);
        }

        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    public function debit(Wallet $wallet, float|string $amount, string $type, mixed $source = null): WalletTransaction
    {
        // TODO: Validate available balance, decrease balance, and write debit transaction.
    }
}
