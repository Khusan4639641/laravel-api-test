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
        foreach (['main', 'bonus', 'deposit'] as $type) {
            Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type,
                ],
                [
                    'currency' => 'KZT',
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
        ?string $description = null,
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
            'affects_balance' => true,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);

        if ($source) {
            $transaction->source()->associate($source);
        }

        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    public function debit(
        Wallet $wallet,
        float|string $amount,
        string $type,
        mixed $source = null,
        array $metadata = [],
        ?string $description = null,
    ): WalletTransaction
    {
        $amount = (string) $amount;

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Debit amount must be greater than zero.');
        }

        if (bccomp((string) $wallet->balance, $amount, 2) < 0) {
            throw new InvalidArgumentException('Insufficient wallet balance.');
        }

        $balanceBefore = (string) $wallet->balance;
        $balanceAfter = bcsub($balanceBefore, $amount, 2);

        $wallet->forceFill([
            'balance' => $balanceAfter,
        ])->save();

        $transaction = new WalletTransaction([
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'affects_balance' => true,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);

        if ($source) {
            $transaction->source()->associate($source);
        }

        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    public function recordNonBalanceOperation(
        Wallet $wallet,
        float|string $amount,
        string $type,
        mixed $source = null,
        array $metadata = [],
        ?string $description = null,
        string $direction = 'neutral',
    ): WalletTransaction {
        $amount = (string) $amount;

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Operation amount must be greater than zero.');
        }

        $balance = (string) $wallet->balance;

        $transaction = new WalletTransaction([
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $balance,
            'balance_after' => $balance,
            'status' => 'completed',
            'affects_balance' => false,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);

        if ($source) {
            $transaction->source()->associate($source);
        }

        $wallet->transactions()->save($transaction);

        return $transaction;
    }
}
