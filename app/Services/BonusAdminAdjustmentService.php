<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BonusAdminAdjustmentService
{
    public function updateAmount(BonusTransaction $bonus, User $admin, float|string $amount, string $reason): BonusTransaction
    {
        $this->assertSuperAdmin($admin);
        $amount = $this->decimal((string) $amount);
        $reason = trim($reason);

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Сумма бонуса должна быть больше 0.'],
            ]);
        }

        return DB::transaction(function () use ($bonus, $admin, $amount, $reason): BonusTransaction {
            $bonus = BonusTransaction::query()
                ->lockForUpdate()
                ->findOrFail($bonus->id);
            $this->assertBonusCanBeChanged($bonus);

            $oldAmount = $this->decimal((string) $bonus->amount);

            if (bccomp($oldAmount, $amount, 2) === 0) {
                return $bonus->refresh();
            }

            if ($bonus->bonus_type === 'binary') {
                $this->adjustBinaryBonusWallets($bonus, $amount, $admin, $reason);
                $this->syncBinaryRunsAmount($bonus, $amount, $admin, $reason);
            } else {
                $this->adjustPrimaryBonusWallet($bonus, bcsub($amount, $oldAmount, 2), $admin, $reason);
            }

            $metadata = is_array($bonus->metadata) ? $bonus->metadata : [];
            $metadata['manual_adjustments'][] = [
                'action' => 'amount_updated',
                'old_amount' => $oldAmount,
                'new_amount' => $amount,
                'reason' => $reason,
                'admin_id' => $admin->id,
                'created_at' => now()->toISOString(),
            ];

            $bonus->forceFill([
                'amount' => $amount,
                'metadata' => $metadata,
            ])->save();

            $this->syncBonusNotifications($bonus->refresh());

            $this->writeAudit($bonus, $admin, 'bonus_amount_updated', $reason, [
                'old_amount' => $oldAmount,
                'new_amount' => $amount,
            ]);

            return $bonus->refresh()->load(['user.profile', 'sourceUser.profile', 'sourceOrder', 'walletTransaction']);
        });
    }

    public function delete(BonusTransaction $bonus, User $admin, string $reason): BonusTransaction
    {
        $this->assertSuperAdmin($admin);
        $reason = trim($reason);

        return DB::transaction(function () use ($bonus, $admin, $reason): BonusTransaction {
            $bonus = BonusTransaction::query()
                ->lockForUpdate()
                ->findOrFail($bonus->id);
            $this->assertBonusCanBeChanged($bonus);

            $reversalIds = [];

            $this->activeWalletTransactions($bonus)
                ->each(function (WalletTransaction $transaction) use ($bonus, $admin, $reason, &$reversalIds): void {
                    $reversal = $this->reverseWalletTransaction($transaction, $bonus, $admin, $reason);

                    if ($reversal) {
                        $reversalIds[] = $reversal->id;
                    }
                });

            $this->voidBinaryRuns($bonus, $admin, $reason);

            $metadata = is_array($bonus->metadata) ? $bonus->metadata : [];
            $metadata['manual_adjustments'][] = [
                'action' => 'deleted',
                'old_amount' => $this->decimal((string) $bonus->amount),
                'reason' => $reason,
                'admin_id' => $admin->id,
                'created_at' => now()->toISOString(),
                'reversal_wallet_transaction_ids' => $reversalIds,
            ];

            $bonus->forceFill([
                'status' => 'voided',
                'metadata' => $metadata,
            ])->save();

            $this->deleteBonusNotifications($bonus);

            $this->writeAudit($bonus, $admin, 'bonus_deleted', $reason, [
                'old_amount' => $this->decimal((string) $bonus->amount),
                'reversal_wallet_transaction_ids' => $reversalIds,
            ]);

            return $bonus->refresh()->load(['user.profile', 'sourceUser.profile', 'sourceOrder', 'walletTransaction']);
        });
    }

    private function adjustBinaryBonusWallets(BonusTransaction $bonus, string $newAmount, User $admin, string $reason): void
    {
        $newMainAmount = bcdiv(bcmul($newAmount, '90', 2), '100', 2);
        $newDepositAmount = bcsub($newAmount, $newMainAmount, 2);
        $current = $this->currentBinaryWalletAmounts($bonus);

        $mainWallet = $this->walletForPart($bonus, 'main');
        $depositWallet = $this->walletForPart($bonus, 'deposit');

        $this->applyWalletAdjustment(
            $mainWallet,
            bcsub($newMainAmount, $current['main'], 2),
            'binary_bonus_main_manual_adjustment',
            $bonus,
            $admin,
            $reason,
            'Ручная корректировка бинарного бонуса: основной кошелёк',
        );
        $this->applyWalletAdjustment(
            $depositWallet,
            bcsub($newDepositAmount, $current['deposit'], 2),
            'binary_bonus_deposit_manual_adjustment',
            $bonus,
            $admin,
            $reason,
            'Ручная корректировка бинарного бонуса: депозит',
        );
    }

    private function adjustPrimaryBonusWallet(BonusTransaction $bonus, string $delta, User $admin, string $reason): void
    {
        $transaction = $this->activeWalletTransactions($bonus)
            ->first()
            ?: ($bonus->wallet_transaction_id ? WalletTransaction::query()->find($bonus->wallet_transaction_id) : null);

        if (! $transaction) {
            return;
        }

        $wallet = Wallet::query()->lockForUpdate()->find($transaction->wallet_id);

        if (! $wallet) {
            return;
        }

        $this->applyWalletAdjustment(
            $wallet,
            $delta,
            'bonus_manual_adjustment',
            $bonus,
            $admin,
            $reason,
            'Ручная корректировка бонуса',
        );
    }

    /**
     * @return array{main: string, deposit: string}
     */
    private function currentBinaryWalletAmounts(BonusTransaction $bonus): array
    {
        $amounts = ['main' => '0.00', 'deposit' => '0.00'];

        $this->activeWalletTransactions($bonus)
            ->each(function (WalletTransaction $transaction) use (&$amounts): void {
                $part = str_contains($transaction->type, 'deposit') || $transaction->wallet?->type === 'deposit'
                    ? 'deposit'
                    : 'main';
                $signedAmount = $transaction->direction === 'debit'
                    ? bcmul((string) $transaction->amount, '-1', 2)
                    : (string) $transaction->amount;
                $amounts[$part] = bcadd($amounts[$part], $signedAmount, 2);
            });

        return [
            'main' => $this->decimal($amounts['main']),
            'deposit' => $this->decimal($amounts['deposit']),
        ];
    }

    private function walletForPart(BonusTransaction $bonus, string $part): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $bonus->user_id)
            ->where('type', $part)
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            return $wallet;
        }

        throw ValidationException::withMessages([
            'wallet' => ["Кошелёк {$part} не найден."],
        ]);
    }

    private function applyWalletAdjustment(
        Wallet $wallet,
        string $signedDelta,
        string $type,
        BonusTransaction $bonus,
        User $admin,
        string $reason,
        string $description,
    ): ?WalletTransaction {
        $signedDelta = $this->decimal($signedDelta);

        if (bccomp($signedDelta, '0', 2) === 0) {
            return null;
        }

        $direction = bccomp($signedDelta, '0', 2) > 0 ? 'credit' : 'debit';
        $amount = $direction === 'credit' ? $signedDelta : bcmul($signedDelta, '-1', 2);
        $balanceBefore = (string) $wallet->balance;
        $balanceAfter = $direction === 'credit'
            ? bcadd($balanceBefore, $amount, 2)
            : bcsub($balanceBefore, $amount, 2);

        $wallet->forceFill(['balance' => $balanceAfter])->save();

        $transaction = new WalletTransaction([
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'affects_balance' => true,
            'description' => $description,
            'metadata' => [
                'source' => 'manual_bonus_adjustment',
                'bonus_transaction_id' => $bonus->id,
                'admin_id' => $admin->id,
                'reason' => $reason,
            ],
        ]);
        $transaction->source()->associate($bonus);
        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    private function reverseWalletTransaction(WalletTransaction $transaction, BonusTransaction $bonus, User $admin, string $reason): ?WalletTransaction
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        if (($metadata['manual_bonus_reversed'] ?? false) === true) {
            return null;
        }

        $wallet = Wallet::query()->lockForUpdate()->find($transaction->wallet_id);

        if (! $wallet) {
            return null;
        }

        $amount = (string) $transaction->amount;
        $balanceBefore = (string) $wallet->balance;
        $reversalDirection = $transaction->direction === 'debit' ? 'credit' : 'debit';
        $balanceAfter = $reversalDirection === 'credit'
            ? bcadd($balanceBefore, $amount, 2)
            : bcsub($balanceBefore, $amount, 2);

        $wallet->forceFill(['balance' => $balanceAfter])->save();

        $reversal = new WalletTransaction([
            'user_id' => $transaction->user_id,
            'type' => $transaction->type.'_manual_reversal',
            'direction' => $reversalDirection,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'affects_balance' => true,
            'description' => 'Ручное удаление бонуса',
            'metadata' => [
                'source' => 'manual_bonus_delete',
                'original_wallet_transaction_id' => $transaction->id,
                'bonus_transaction_id' => $bonus->id,
                'admin_id' => $admin->id,
                'reason' => $reason,
            ],
        ]);
        $reversal->source()->associate($bonus);
        $wallet->transactions()->save($reversal);

        $metadata['manual_bonus_reversed'] = true;
        $metadata['manual_bonus_reversal_wallet_transaction_id'] = $reversal->id;
        $metadata['manual_bonus_reversed_at'] = now()->toISOString();
        $metadata['manual_bonus_reversed_by'] = $admin->id;

        $transaction->forceFill([
            'status' => 'reversed',
            'metadata' => $metadata,
        ])->save();

        return $reversal;
    }

    private function syncBinaryRunsAmount(BonusTransaction $bonus, string $newAmount, User $admin, string $reason): void
    {
        BinaryBonusRun::query()
            ->where('bonus_transaction_id', $bonus->id)
            ->whereIn('status', ['completed', 'pending'])
            ->lockForUpdate()
            ->get()
            ->each(function (BinaryBonusRun $run) use ($newAmount, $admin, $reason): void {
                $metadata = is_array($run->metadata) ? $run->metadata : [];
                $metadata['manual_bonus_adjustment'] = [
                    'new_amount' => $newAmount,
                    'admin_id' => $admin->id,
                    'reason' => $reason,
                    'created_at' => now()->toISOString(),
                ];

                $run->forceFill([
                    'amount' => $newAmount,
                    'metadata' => $metadata,
                ])->save();
            });
    }

    private function voidBinaryRuns(BonusTransaction $bonus, User $admin, string $reason): void
    {
        BinaryBonusRun::query()
            ->where('bonus_transaction_id', $bonus->id)
            ->whereIn('status', ['completed', 'pending'])
            ->lockForUpdate()
            ->get()
            ->each(function (BinaryBonusRun $run) use ($admin, $reason): void {
                $user = User::query()->lockForUpdate()->find($run->user_id);

                if ($user) {
                    $user->forceFill([
                        'remaining_left_pv' => bcadd((string) $user->remaining_left_pv, (string) $run->used_left_pv, 2),
                        'remaining_right_pv' => bcadd((string) $user->remaining_right_pv, (string) $run->used_right_pv, 2),
                    ])->save();
                }

                $metadata = is_array($run->metadata) ? $run->metadata : [];
                $metadata['manual_bonus_deleted'] = [
                    'admin_id' => $admin->id,
                    'reason' => $reason,
                    'created_at' => now()->toISOString(),
                ];

                $run->forceFill([
                    'status' => 'voided',
                    'metadata' => $metadata,
                ])->save();
            });
    }

    private function activeWalletTransactions(BonusTransaction $bonus)
    {
        return WalletTransaction::query()
            ->with('wallet')
            ->where('source_type', BonusTransaction::class)
            ->where('source_id', $bonus->id)
            ->where('affects_balance', true)
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();
    }

    private function assertBonusCanBeChanged(BonusTransaction $bonus): void
    {
        if (in_array($bonus->status, ['reversed', 'voided', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'bonus' => ['Этот бонус уже отменён или удалён.'],
            ]);
        }
    }

    private function assertSuperAdmin(User $admin): void
    {
        if (! $admin->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admin can manually change bonuses.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function writeAudit(BonusTransaction $bonus, User $admin, string $action, string $reason, array $metadata): void
    {
        AdminActionLog::query()->create([
            'admin_id' => $admin->id,
            'target_user_id' => $bonus->user_id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => [
                'bonus_transaction_id' => $bonus->id,
                'bonus_type' => $bonus->bonus_type,
                ...$metadata,
            ],
        ]);
    }

    private function syncBonusNotifications(BonusTransaction $bonus): void
    {
        $payload = app(TransactionNotificationTextFactory::class)->makeForBonusTransaction($bonus);

        $this->bonusNotifications($bonus)->each(function ($notification) use ($payload): void {
            $notification->forceFill(['data' => $payload])->save();
        });
    }

    private function deleteBonusNotifications(BonusTransaction $bonus): void
    {
        $this->bonusNotifications($bonus)->each->delete();
    }

    private function bonusNotifications(BonusTransaction $bonus)
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $bonus->user_id)
            ->get()
            ->filter(function ($notification) use ($bonus): bool {
                $data = json_decode((string) $notification->data, true);

                return is_array($data)
                    && (int) ($data['bonus_transaction_id'] ?? 0) === (int) $bonus->id;
            })
            ->map(fn ($notification) => \Illuminate\Notifications\DatabaseNotification::query()->find($notification->id))
            ->filter();
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
