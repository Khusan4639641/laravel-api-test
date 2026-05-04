<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalRequestedNotification;
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

            $user->notify(new WithdrawalRequestedNotification($withdrawalRequest->refresh()));

            return $withdrawalRequest->refresh()->load(['wallet']);
        });
    }

    public function approveWithdrawal(WithdrawalRequest $withdrawalRequest): void
    {
        DB::transaction(function () use ($withdrawalRequest): void {
            /** @var WithdrawalRequest $withdrawalRequest */
            $withdrawalRequest = WithdrawalRequest::query()
                ->lockForUpdate()
                ->findOrFail($withdrawalRequest->id);

            $this->ensurePending($withdrawalRequest);

            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->whereKey($withdrawalRequest->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $wallet->hold_balance, (string) $withdrawalRequest->amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Insufficient hold balance.',
                ]);
            }

            $holdBefore = (string) $wallet->hold_balance;
            $holdAfter = bcsub($holdBefore, (string) $withdrawalRequest->amount, 2);

            $wallet->forceFill([
                'hold_balance' => $holdAfter,
            ])->save();

            $withdrawalRequest->forceFill([
                'status' => 'approved',
                'processed_at' => now(),
            ])->save();

            $walletTransaction = new WalletTransaction([
                'user_id' => $withdrawalRequest->user_id,
                'type' => 'withdrawal_approve',
                'direction' => 'debit',
                'amount' => $withdrawalRequest->amount,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'status' => 'completed',
                'metadata' => [
                    'hold_balance_before' => $holdBefore,
                    'hold_balance_after' => $holdAfter,
                ],
            ]);
            $walletTransaction->source()->associate($withdrawalRequest);
            $wallet->transactions()->save($walletTransaction);
        });
    }

    public function rejectWithdrawal(WithdrawalRequest $withdrawalRequest, ?string $reason = null): void
    {
        DB::transaction(function () use ($withdrawalRequest, $reason): void {
            /** @var WithdrawalRequest $withdrawalRequest */
            $withdrawalRequest = WithdrawalRequest::query()
                ->lockForUpdate()
                ->findOrFail($withdrawalRequest->id);

            $this->ensurePending($withdrawalRequest);

            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->whereKey($withdrawalRequest->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $wallet->hold_balance, (string) $withdrawalRequest->amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Insufficient hold balance.',
                ]);
            }

            $balanceBefore = (string) $wallet->balance;
            $balanceAfter = bcadd($balanceBefore, (string) $withdrawalRequest->amount, 2);
            $holdBefore = (string) $wallet->hold_balance;
            $holdAfter = bcsub($holdBefore, (string) $withdrawalRequest->amount, 2);

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'hold_balance' => $holdAfter,
            ])->save();

            $withdrawalRequest->forceFill([
                'status' => 'rejected',
                'admin_comment' => $reason,
                'processed_at' => now(),
            ])->save();

            $walletTransaction = new WalletTransaction([
                'user_id' => $withdrawalRequest->user_id,
                'type' => 'withdrawal_reject',
                'direction' => 'credit',
                'amount' => $withdrawalRequest->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'metadata' => [
                    'hold_balance_before' => $holdBefore,
                    'hold_balance_after' => $holdAfter,
                    'reason' => $reason,
                ],
            ]);
            $walletTransaction->source()->associate($withdrawalRequest);
            $wallet->transactions()->save($walletTransaction);
        });
    }

    private function ensurePending(WithdrawalRequest $withdrawalRequest): void
    {
        if ($withdrawalRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'withdrawal' => 'Only pending withdrawal requests can be processed.',
            ]);
        }
    }
}
