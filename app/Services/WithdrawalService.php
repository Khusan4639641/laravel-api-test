<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function requestWithdrawal(User $user, Wallet $wallet, float|string $amount, array $paymentDetails = []): WithdrawalRequest
    {
        return DB::transaction(function () use ($user, $wallet, $amount, $paymentDetails): WithdrawalRequest {
            $amount = (string) $amount;

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->whereKey($wallet->id)
                ->where('user_id', $user->id)
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $wallet->balance, $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient main wallet balance.',
                ]);
            }

            $balanceBefore = (string) $wallet->balance;
            $balanceAfter = bcsub($balanceBefore, $amount, 2);
            $holdBalanceAfter = bcadd((string) $wallet->hold_balance, $amount, 2);

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'hold_balance' => $holdBalanceAfter,
            ])->save();

            $withdrawalRequest = WithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'currency' => $wallet->currency,
                'status' => 'pending',
                'payment_method' => $paymentDetails['payment_method'] ?? null,
                'payment_details' => $paymentDetails['payment_details'] ?? null,
            ]);

            $walletTransaction = new WalletTransaction([
                'user_id' => $user->id,
                'type' => 'withdrawal_hold',
                'direction' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'metadata' => [
                    'hold_balance_after' => $holdBalanceAfter,
                ],
            ]);
            $walletTransaction->source()->associate($withdrawalRequest);
            $wallet->transactions()->save($walletTransaction);

            return $withdrawalRequest->refresh()->load(['wallet']);
        });
    }

    public function approveWithdrawal(WithdrawalRequest $withdrawalRequest): void
    {
        // TODO: Mark request approved and convert held funds into a final debit transaction.
    }

    public function rejectWithdrawal(WithdrawalRequest $withdrawalRequest, ?string $reason = null): void
    {
        // TODO: Release held funds and mark request rejected with admin comment.
    }
}
