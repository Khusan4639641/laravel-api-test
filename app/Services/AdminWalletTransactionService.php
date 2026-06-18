<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\TransactionAdminAudit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminWalletTransactionService
{
    public function __construct(
        private readonly TransactionNotificationTextFactory $notificationTextFactory,
    ) {
    }

    public function updateAmount(WalletTransaction $transaction, User $admin, string|int|float $amount, string $reason): WalletTransaction
    {
        $newAmount = $this->decimal($amount);

        return DB::transaction(function () use ($transaction, $admin, $newAmount, $reason): WalletTransaction {
            $lockedTransaction = $this->lockTransaction($transaction);
            $this->ensureEditable($lockedTransaction);

            $wallet = $this->lockWallet($lockedTransaction);
            $oldPayload = $this->payload($lockedTransaction);
            $oldAmount = $this->decimal($lockedTransaction->amount);
            $oldEffect = $this->financialEffect($lockedTransaction, $oldAmount);
            $newEffect = $this->financialEffect($lockedTransaction, $newAmount);
            $delta = bcsub($newEffect, $oldEffect, 2);

            $this->applyWalletDelta($wallet, $delta);

            $lockedTransaction->forceFill([
                'amount' => $newAmount,
                'balance_after' => bcadd((string) $lockedTransaction->balance_before, $newEffect, 2),
            ])->save();

            $this->syncBonusTransactionAmount($lockedTransaction, $newAmount);
            $this->syncNotification($lockedTransaction->refresh());

            TransactionAdminAudit::query()->create([
                'transaction_id' => $lockedTransaction->id,
                'admin_id' => $admin->id,
                'action' => TransactionAdminAudit::ACTION_AMOUNT_UPDATED,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'old_payload' => $oldPayload,
                'new_payload' => $this->payload($lockedTransaction->refresh()),
                'reason' => $reason,
            ]);

            return $lockedTransaction->load(['user.profile', 'wallet']);
        });
    }

    public function void(WalletTransaction $transaction, User $admin, string $reason): WalletTransaction
    {
        return DB::transaction(function () use ($transaction, $admin, $reason): WalletTransaction {
            $lockedTransaction = $this->lockTransaction($transaction);
            $this->ensureEditable($lockedTransaction);

            $wallet = $this->lockWallet($lockedTransaction);
            $oldPayload = $this->payload($lockedTransaction);
            $oldEffect = $this->financialEffect($lockedTransaction);
            $reverseDelta = bcmul($oldEffect, '-1', 2);

            $this->applyWalletDelta($wallet, $reverseDelta);

            $metadata = $lockedTransaction->metadata ?: [];
            $metadata['voided_by'] = $admin->id;
            $metadata['voided_at'] = now()->toISOString();
            $metadata['void_reason'] = $reason;
            $metadata['void_reverse_delta'] = $reverseDelta;
            $metadata['previous_status'] = $lockedTransaction->status;

            $lockedTransaction->forceFill([
                'status' => WalletTransaction::STATUS_VOIDED,
                'metadata' => $metadata,
            ])->save();

            $this->voidBonusTransaction($lockedTransaction);
            $this->deleteNotification($lockedTransaction);

            TransactionAdminAudit::query()->create([
                'transaction_id' => $lockedTransaction->id,
                'admin_id' => $admin->id,
                'action' => TransactionAdminAudit::ACTION_DELETED,
                'old_amount' => $this->decimal($lockedTransaction->amount),
                'new_amount' => null,
                'old_payload' => $oldPayload,
                'new_payload' => $this->payload($lockedTransaction->refresh()),
                'reason' => $reason,
            ]);

            return $lockedTransaction->load(['user.profile', 'wallet']);
        });
    }

    private function lockTransaction(WalletTransaction $transaction): WalletTransaction
    {
        return WalletTransaction::query()
            ->whereKey($transaction->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockWallet(WalletTransaction $transaction): Wallet
    {
        return Wallet::query()
            ->whereKey($transaction->wallet_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureEditable(WalletTransaction $transaction): void
    {
        if (in_array($transaction->status, ['reversed', WalletTransaction::STATUS_VOIDED, 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'transaction' => ['Транзакция уже удалена или отменена.'],
            ]);
        }
    }

    private function financialEffect(WalletTransaction $transaction, string|int|float|null $amount = null): string
    {
        if (! (bool) $transaction->affects_balance) {
            return '0.00';
        }

        $amount = $this->decimal($amount ?? $transaction->amount);

        return match ($transaction->direction) {
            'credit' => $amount,
            'debit' => bcmul($amount, '-1', 2),
            default => '0.00',
        };
    }

    private function applyWalletDelta(Wallet $wallet, string $delta): void
    {
        if (bccomp($delta, '0.00', 2) === 0) {
            return;
        }

        $wallet->forceFill([
            'balance' => bcadd((string) $wallet->balance, $delta, 2),
        ])->save();
    }

    private function syncBonusTransactionAmount(WalletTransaction $transaction, string $newAmount): void
    {
        BonusTransaction::query()
            ->where('wallet_transaction_id', $transaction->id)
            ->update(['amount' => $newAmount]);
    }

    private function voidBonusTransaction(WalletTransaction $transaction): void
    {
        BonusTransaction::query()
            ->where('wallet_transaction_id', $transaction->id)
            ->update(['status' => WalletTransaction::STATUS_VOIDED]);
    }

    private function syncNotification(WalletTransaction $transaction): void
    {
        $notifications = $this->transactionNotifications($transaction)->get();

        foreach ($notifications as $notification) {
            $notification->forceFill([
                'data' => $this->notificationTextFactory->makeForWalletTransaction($transaction, $notification->data),
            ])->save();
        }
    }

    private function deleteNotification(WalletTransaction $transaction): void
    {
        $this->transactionNotifications($transaction)->delete();
    }

    private function transactionNotifications(WalletTransaction $transaction)
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $transaction->user_id)
            ->where(function ($query) use ($transaction): void {
                $query
                    ->where('data->transaction_id', $transaction->id)
                    ->orWhere('data->transaction_id', (string) $transaction->id)
                    ->orWhere('data->wallet_transaction_id', $transaction->id)
                    ->orWhere('data->wallet_transaction_id', (string) $transaction->id);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WalletTransaction $transaction): array
    {
        return $transaction->fresh()?->toArray() ?? $transaction->toArray();
    }

    private function decimal(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
