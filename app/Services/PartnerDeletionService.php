<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\BinaryNode;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Models\UserX2Bonus;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PartnerDeletionService
{
    private const DELETED_USER_ACCOUNT_STATUS = 'inactive';

    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function previewDelete(User $user, bool $deleteSubtree): array
    {
        $rootNode = $user->binaryNode()->where('is_active', true)->first();
        $hasChildren = $this->hasActiveChildren($rootNode);
        $descendantsCount = $this->activeDescendantsCount($rootNode);
        $usersToDelete = $this->usersToDelete($user, $deleteSubtree);
        $userIds = $usersToDelete->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $affectedUplineIds = $this->affectedUplineIdsForUsers($usersToDelete);
        $walletBalance = Wallet::query()
            ->whereIn('user_id', $userIds ?: [0])
            ->sum('balance');
        $pvToRecalculate = PvTransaction::query()
            ->whereIn('buyer_id', $userIds ?: [0])
            ->whereNull('voided_at')
            ->sum('pv');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'can_delete_leaf' => ! $hasChildren,
            'has_children' => $hasChildren,
            'descendants_count' => $descendantsCount,
            'affected_uplines_count' => count($affectedUplineIds),
            'transactions_count' => WalletTransaction::query()->whereIn('user_id', $userIds ?: [0])->count(),
            'orders_count' => Order::query()->whereIn('user_id', $userIds ?: [0])->count(),
            'withdrawals_count' => WithdrawalRequest::query()->whereIn('user_id', $userIds ?: [0])->count(),
            'wallet_balance' => number_format((float) $walletBalance, 2, '.', ''),
            'pv_to_recalculate' => number_format((float) $pvToRecalculate, 2, '.', ''),
            'warning' => $hasChildren && ! $deleteSubtree
                ? 'У партнёра есть структура. Выберите удаление вместе с поддеревом.'
                : 'Будут выполнены soft delete, void/reversal операций и перерасчёт MLM для вышестоящих.',
        ];
    }

    public function deletePartner(User $user, User $admin, bool $deleteSubtree, string $reason): DeleteResult
    {
        $this->assertCanDelete($user, $admin);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Укажите причину удаления.'],
            ]);
        }

        $rootNode = $user->binaryNode()->where('is_active', true)->first();

        if ($this->hasActiveChildren($rootNode) && ! $deleteSubtree) {
            throw ValidationException::withMessages([
                'delete_subtree' => ['У партнёра есть структура. Выберите удаление вместе с поддеревом.'],
            ]);
        }

        return DB::transaction(function () use ($user, $admin, $deleteSubtree, $reason): DeleteResult {
            $usersToDelete = $this->usersToDelete($user, $deleteSubtree);
            $userIds = $usersToDelete->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $affectedUplineIds = $this->affectedUplineIdsForUsers($usersToDelete);

            $this->voidPvTransactions($userIds, $admin, $reason);
            $this->reverseBonuses($userIds, $affectedUplineIds, $admin, $reason);
            $this->voidPackageTransactions($userIds, $admin, $reason);
            $this->cancelPendingWithdrawals($userIds, $admin, $reason);
            $this->cancelActiveOrders($userIds, $admin, $reason);
            $this->archiveBinaryNodes($userIds, $admin, $reason);
            $this->closeDeletedUserWallets($userIds, $admin, $reason);
            $this->softDeleteUsers($usersToDelete, $admin, $deleteSubtree, $reason, $affectedUplineIds);
            $this->recalculateAffectedUplines($affectedUplineIds);
            $this->writeAuditLog($user, $admin, $deleteSubtree, $reason, $userIds, $affectedUplineIds);

            return new DeleteResult(
                deletedUsersCount: count($userIds),
                affectedUplinesCount: count($affectedUplineIds),
                deletedUserIds: $userIds,
                affectedUplineIds: $affectedUplineIds,
            );
        });
    }

    public function releaseDeletedUserIdentity(User $user): bool
    {
        /** @var User|null $user */
        $user = User::withTrashed()
            ->with('profile')
            ->find($user->id);

        if (! $user || ! $this->isArchivedOrSoftDeletedUser($user)) {
            return false;
        }

        $meta = is_array($user->deleted_meta) ? $user->deleted_meta : [];

        if (! empty($meta['identity_released_at'])) {
            return false;
        }

        $timestamp = now()->format('YmdHis');
        $technicalValue = "deleted_{$user->id}_{$timestamp}";
        $technicalEmail = "deleted+{$user->id}+{$timestamp}@safilife.deleted";
        $originalPhone = $this->currentUserPhone($user);
        $originalReferralCode = $this->currentReferralCode($user);

        $meta['original_login'] ??= $user->login;
        $meta['original_email'] ??= $user->email;
        $meta['original_phone'] ??= $originalPhone;
        $meta['original_referral_code'] ??= $originalReferralCode;
        $meta['identity_released_at'] = now()->toDateTimeString();

        $updates = [
            'login' => $this->uniqueDeletedUserValue('login', $technicalValue, $user),
            'email' => $this->uniqueDeletedUserValue('email', $technicalEmail, $user),
            'deleted_meta' => $meta,
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $updates['phone'] = $this->uniqueDeletedUserValue('phone', $technicalValue, $user);
        }

        if (Schema::hasColumn('users', 'referral_code')) {
            $updates['referral_code'] = $this->uniqueDeletedUserValue('referral_code', $technicalValue, $user);
        }

        $user->forceFill($updates)->save();

        if ($user->profile) {
            $user->profile->forceFill([
                'phone' => $technicalValue,
            ])->save();
        }

        return true;
    }

    /**
     * @param  array<int, int|string>  $affectedUplineIds
     */
    public function recalculateAfterDelete(array $affectedUplineIds): void
    {
        $this->recalculateAffectedUplines($affectedUplineIds);
    }

    /**
     * @param  SupportCollection<int, User>  $users
     */
    public function voidDeletedUserOperationalData(SupportCollection $users): void
    {
        $users = User::withTrashed()
            ->whereIn('id', $users->pluck('id')->filter()->all() ?: [0])
            ->get();

        $userIds = $users
            ->filter(fn (User $user): bool => $this->isArchivedOrSoftDeletedUser($user))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($userIds === []) {
            return;
        }

        $admin = $this->systemCleanupActor($users);
        $reason = 'Cleanup deleted user effects';
        $affectedUplineIds = $this->affectedUplineIdsForDeletedUserIds($userIds);

        $this->voidPvTransactions($userIds, $admin, $reason);
        $this->reverseBonuses($userIds, $affectedUplineIds, $admin, $reason);
        $this->voidPackageTransactions($userIds, $admin, $reason);
        $this->cancelPendingWithdrawals($userIds, $admin, $reason);
        $this->cancelActiveOrders($userIds, $admin, $reason);
        $this->archiveBinaryNodes($userIds, $admin, $reason);
        $this->closeDeletedUserWallets($userIds, $admin, $reason);

        $users->each(function (User $user): void {
            $this->releaseDeletedUserIdentity($user);
        });
        $this->recalculateAffectedUplines($affectedUplineIds);
    }

    /**
     * Rebuild cached branch PV for active uplines from non-voided PV transactions of non-deleted buyers.
     *
     * @param  array<int, int|string>  $affectedUplineIds
     */
    public function recalculateAffectedUplines(array $affectedUplineIds): void
    {
        $ids = collect($affectedUplineIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $ids)
            ->where('role', User::ROLE_USER)
            ->activeAccount()
            ->with('currentPackage')
            ->orderBy('id')
            ->get()
            ->each(function (User $upline): void {
                $volumes = app(DashboardBranchVolumeService::class)->getBranchVolumes($upline);
                $ownPv = $upline->currentPackage ? $upline->currentPackage->activityPv() : '0.00';
                $teamPv = bcadd($volumes['left_pv'], $volumes['right_pv'], 2);
                $totalPv = bcadd($ownPv, $teamPv, 2);
                $weakLegPv = bccomp($volumes['left_pv'], $volumes['right_pv'], 2) <= 0
                    ? $volumes['left_pv']
                    : $volumes['right_pv'];

                $upline->forceFill([
                    'left_pv' => $volumes['left_pv'],
                    'right_pv' => $volumes['right_pv'],
                    'remaining_left_pv' => $volumes['left_pv'],
                    'remaining_right_pv' => $volumes['right_pv'],
                    'total_pv' => $totalPv,
                    'status' => $this->statusService->statusForPv($weakLegPv),
                ])->save();
            });
    }

    public function recalculateAllActiveUsers(): int
    {
        $ids = User::query()
            ->where('role', User::ROLE_USER)
            ->activeAccount()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->recalculateAffectedUplines($ids);

        return count($ids);
    }

    private function assertCanDelete(User $user, User $admin): void
    {
        if (! $admin->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admin can delete partners.');
        }

        if (in_array($user->role, [User::ROLE_SUPPORT, User::ROLE_ACCOUNTANT, User::ROLE_ADMIN], true)) {
            throw ValidationException::withMessages([
                'user' => ['Нельзя удалить staff account через удаление партнёра.'],
            ]);
        }

        if ($user->isSuperAdmin() && User::query()->where('role', User::ROLE_SUPER_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['Нельзя удалить последнего super_admin.'],
            ]);
        }

        if ((int) $user->id === (int) $admin->id) {
            throw ValidationException::withMessages([
                'user' => ['Нельзя удалить самого себя.'],
            ]);
        }
    }

    private function hasActiveChildren(?BinaryNode $node): bool
    {
        if (! $node) {
            return false;
        }

        return BinaryNode::query()
            ->where('parent_id', $node->id)
            ->where('is_active', true)
            ->exists();
    }

    private function activeDescendantsCount(?BinaryNode $node): int
    {
        if (! $node?->path) {
            return 0;
        }

        return BinaryNode::query()
            ->where('path', 'like', $node->path.'.%')
            ->where('is_active', true)
            ->count();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function usersToDelete(User $user, bool $deleteSubtree): EloquentCollection
    {
        $ids = [(int) $user->id];

        if ($deleteSubtree) {
            $node = $user->binaryNode()->where('is_active', true)->first();

            if ($node?->path) {
                $descendantUserIds = BinaryNode::query()
                    ->where('path', 'like', $node->path.'.%')
                    ->where('is_active', true)
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                $ids = array_values(array_unique([...$ids, ...$descendantUserIds]));
            }
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     * @return array<int, int>
     */
    private function affectedUplineIdsForUsers(EloquentCollection $users): array
    {
        $uplineIds = [];

        $users->loadMissing('binaryNode');

        foreach ($users as $user) {
            $node = $user->binaryNode;

            if (! $node?->path) {
                continue;
            }

            $pathUserIds = collect(explode('.', $node->path))
                ->map(fn (string $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $user->id)
                ->all();

            $uplineIds = [...$uplineIds, ...$pathUserIds];
        }

        $deletingIds = $users->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return collect($uplineIds)
            ->diff($deletingIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function voidPvTransactions(array $userIds, User $admin, string $reason): void
    {
        PvTransaction::query()
            ->whereIn('buyer_id', $userIds ?: [0])
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get()
            ->each(function (PvTransaction $transaction) use ($admin, $reason): void {
                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
                $metadata['voided_by_partner_deletion'] = true;
                $metadata['voided_reason'] = $reason;
                $metadata['voided_at'] = now()->toISOString();
                $metadata['voided_by'] = $admin->id;

                $transaction->forceFill([
                    'voided_at' => now(),
                    'voided_by' => $admin->id,
                    'metadata' => $metadata,
                ])->save();
            });
    }

    /**
     * @param  array<int, int>  $deletingUserIds
     * @param  array<int, int>  $affectedUplineIds
     */
    private function reverseBonuses(array $deletingUserIds, array $affectedUplineIds, User $admin, string $reason): void
    {
        $orderIds = Order::query()->whereIn('user_id', $deletingUserIds ?: [0])->pluck('id');

        BonusTransaction::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($deletingUserIds, $affectedUplineIds, $orderIds): void {
                $query->whereIn('source_user_id', $deletingUserIds ?: [0])
                    ->orWhereIn('user_id', $deletingUserIds ?: [0])
                    ->orWhereIn('source_order_id', $orderIds->isEmpty() ? [0] : $orderIds->all())
                    ->orWhere(function ($uplineBonusQuery) use ($affectedUplineIds): void {
                        $uplineBonusQuery
                            ->whereIn('user_id', $affectedUplineIds ?: [0])
                            ->whereIn('bonus_type', ['binary', 'status', 'status_bonus', 'bonus_x2']);
                    });
            })
            ->orderBy('id')
            ->get()
            ->each(fn (BonusTransaction $bonus): ?BonusTransaction => $this->reverseBonus($bonus, $admin, $reason));
    }

    private function reverseBonus(BonusTransaction $bonus, User $admin, string $reason): ?BonusTransaction
    {
        $metadata = is_array($bonus->metadata) ? $bonus->metadata : [];

        if (($metadata['reversed_by_partner_deletion'] ?? false) === true) {
            return $bonus;
        }

        $reversalTransactionIds = [];

        WalletTransaction::query()
            ->where('source_type', BonusTransaction::class)
            ->where('source_id', $bonus->id)
            ->where('affects_balance', true)
            ->where('status', 'completed')
            ->orderBy('id')
            ->get()
            ->each(function (WalletTransaction $transaction) use ($bonus, $admin, $reason, &$reversalTransactionIds): void {
                $reversal = $this->reverseWalletTransaction($transaction, $bonus, $admin, $reason);

                if ($reversal) {
                    $reversalTransactionIds[] = $reversal->id;
                }
            });

        $metadata['reversed_by_partner_deletion'] = true;
        $metadata['reversed_at'] = now()->toISOString();
        $metadata['reversed_by'] = $admin->id;
        $metadata['reversal_reason'] = $reason;
        $metadata['reversal_wallet_transaction_ids'] = $reversalTransactionIds;

        $bonus->forceFill([
            'status' => 'reversed',
            'metadata' => $metadata,
        ])->save();

        $this->markStatusAndX2AwardsVoided($bonus, $admin, $reason);

        return $bonus->refresh();
    }

    private function reverseWalletTransaction(WalletTransaction $transaction, BonusTransaction $bonus, User $admin, string $reason): ?WalletTransaction
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        if (($metadata['reversed_by_partner_deletion'] ?? false) === true) {
            return null;
        }

        /** @var Wallet|null $wallet */
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
            'type' => $transaction->type.'_reversal',
            'direction' => $reversalDirection,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'affects_balance' => true,
            'description' => 'Reversal after partner deletion',
            'metadata' => [
                'source' => 'partner_deletion_reversal',
                'original_wallet_transaction_id' => $transaction->id,
                'bonus_transaction_id' => $bonus->id,
                'reason' => $reason,
                'admin_id' => $admin->id,
            ],
        ]);
        $reversal->source()->associate($bonus);
        $wallet->transactions()->save($reversal);

        $metadata['reversed_by_partner_deletion'] = true;
        $metadata['reversal_wallet_transaction_id'] = $reversal->id;
        $metadata['reversed_at'] = now()->toISOString();
        $transaction->forceFill([
            'status' => 'reversed',
            'metadata' => $metadata,
        ])->save();

        return $reversal;
    }

    private function markStatusAndX2AwardsVoided(BonusTransaction $bonus, User $admin, string $reason): void
    {
        $void = function ($award) use ($admin, $reason): void {
            $metadata = is_array($award->metadata) ? $award->metadata : [];
            $metadata['voided_by_partner_deletion'] = true;
            $metadata['voided_at'] = now()->toISOString();
            $metadata['voided_by'] = $admin->id;
            $metadata['voided_reason'] = $reason;
            $award->forceFill(['metadata' => $metadata])->save();
        };

        UserStatusBonus::query()
            ->where('bonus_transaction_id', $bonus->id)
            ->get()
            ->each($void);
        UserX2Bonus::query()
            ->where('bonus_transaction_id', $bonus->id)
            ->get()
            ->each($void);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function voidPackageTransactions(array $userIds, User $admin, string $reason): void
    {
        WalletTransaction::query()
            ->whereIn('user_id', $userIds ?: [0])
            ->whereIn('type', ['package_activation', 'package_assignment', 'package_upgrade'])
            ->where('affects_balance', false)
            ->where('status', 'completed')
            ->orderBy('id')
            ->get()
            ->each(function (WalletTransaction $transaction) use ($admin, $reason): void {
                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
                $metadata['voided_by_partner_deletion'] = true;
                $metadata['voided_at'] = now()->toISOString();
                $metadata['voided_by'] = $admin->id;
                $metadata['voided_reason'] = $reason;

                $transaction->forceFill([
                    'status' => 'voided',
                    'metadata' => $metadata,
                ])->save();
            });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function cancelPendingWithdrawals(array $userIds, User $admin, string $reason): void
    {
        WithdrawalRequest::query()
            ->whereIn('user_id', $userIds ?: [0])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->each(function (WithdrawalRequest $withdrawal) use ($admin, $reason): void {
                /** @var Wallet|null $wallet */
                $wallet = Wallet::query()->lockForUpdate()->find($withdrawal->wallet_id);

                if (! $wallet) {
                    return;
                }

                $amount = (string) $withdrawal->amount;
                $balanceBefore = (string) $wallet->balance;
                $holdBefore = (string) $wallet->hold_balance;
                $balanceAfter = bcadd($balanceBefore, $amount, 2);
                $holdAfter = bccomp($holdBefore, $amount, 2) >= 0 ? bcsub($holdBefore, $amount, 2) : '0.00';

                $wallet->forceFill([
                    'balance' => $balanceAfter,
                    'hold_balance' => $holdAfter,
                ])->save();

                $withdrawal->forceFill([
                    'status' => 'cancelled',
                    'admin_comment' => trim(($withdrawal->admin_comment ? $withdrawal->admin_comment."\n" : '')."Cancelled by partner deletion: {$reason}"),
                    'processed_at' => now(),
                ])->save();

                $walletTransaction = new WalletTransaction([
                    'user_id' => $withdrawal->user_id,
                    'type' => 'withdrawal_cancelled',
                    'direction' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'status' => 'completed',
                    'affects_balance' => true,
                    'description' => 'Withdrawal cancelled after partner deletion',
                    'metadata' => [
                        'hold_balance_before' => $holdBefore,
                        'hold_balance_after' => $holdAfter,
                        'admin_id' => $admin->id,
                        'reason' => $reason,
                    ],
                ]);
                $walletTransaction->source()->associate($withdrawal);
                $wallet->transactions()->save($walletTransaction);
            });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function cancelActiveOrders(array $userIds, User $admin, string $reason): void
    {
        Order::query()
            ->with('items.product')
            ->whereIn('user_id', $userIds ?: [0])
            ->whereNotIn('status', ['completed', 'cancelled', 'voided'])
            ->orderBy('id')
            ->get()
            ->each(function (Order $order) use ($admin, $reason): void {
                $metadata = is_array($order->metadata) ? $order->metadata : [];

                if (($metadata['stock_restored_by_partner_deletion'] ?? false) !== true) {
                    foreach ($order->items as $item) {
                        if ($item->product_id && $item->product) {
                            $item->product->increment('stock_quantity', (int) $item->quantity);
                        }
                    }
                }

                $metadata['voided_by_partner_deletion'] = true;
                $metadata['stock_restored_by_partner_deletion'] = true;
                $metadata['voided_at'] = now()->toISOString();
                $metadata['voided_by'] = $admin->id;
                $metadata['voided_reason'] = $reason;

                $order->forceFill([
                    'status' => 'voided',
                    'payment_status' => 'cancelled',
                    'metadata' => $metadata,
                ])->save();
            });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function archiveBinaryNodes(array $userIds, User $admin, string $reason): void
    {
        BinaryNode::query()
            ->whereIn('user_id', $userIds ?: [0])
            ->orderByDesc('depth')
            ->get()
            ->each(function (BinaryNode $node) use ($admin, $reason): void {
                $metadata = is_array($node->deleted_meta) ? $node->deleted_meta : [];
                $metadata['original_parent_id'] = $node->parent_id;
                $metadata['original_position'] = $node->position;
                $metadata['original_path'] = $node->path;
                $metadata['archived_at'] = now()->toISOString();

                $node->forceFill([
                    'parent_id' => null,
                    'position' => null,
                    'is_active' => false,
                    'deleted_by' => $admin->id,
                    'deleted_reason' => $reason,
                    'deleted_meta' => $metadata,
                ])->save();
                $node->delete();
            });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function closeDeletedUserWallets(array $userIds, User $admin, string $reason): void
    {
        Wallet::query()
            ->whereIn('user_id', $userIds ?: [0])
            ->orderBy('id')
            ->get()
            ->each(function (Wallet $wallet) use ($admin, $reason): void {
                $balance = (string) $wallet->balance;
                $holdBalance = (string) $wallet->hold_balance;

                if (bccomp($balance, '0', 2) > 0) {
                    $wallet->transactions()->create([
                        'user_id' => $wallet->user_id,
                        'type' => 'wallet_archived',
                        'direction' => 'debit',
                        'amount' => $balance,
                        'balance_before' => $balance,
                        'balance_after' => '0.00',
                        'status' => 'completed',
                        'affects_balance' => true,
                        'description' => 'Wallet closed after partner deletion',
                        'metadata' => [
                            'source' => 'partner_deletion_wallet_close',
                            'admin_id' => $admin->id,
                            'reason' => $reason,
                            'hold_balance_before' => $holdBalance,
                        ],
                    ]);
                }

                $wallet->forceFill([
                    'balance' => 0,
                    'hold_balance' => 0,
                    'status' => 'closed',
                ])->save();
            });
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     * @param  array<int, int>  $affectedUplineIds
     */
    private function softDeleteUsers(EloquentCollection $users, User $admin, bool $deleteSubtree, string $reason, array $affectedUplineIds): void
    {
        $users->each(function (User $user) use ($admin, $deleteSubtree, $reason, $affectedUplineIds): void {
            $user->tokens()->delete();
            $meta = is_array($user->deleted_meta) ? $user->deleted_meta : [];
            $meta['delete_subtree'] = $deleteSubtree;
            $meta['affected_upline_ids'] = $affectedUplineIds;
            $meta['deleted_at'] = now()->toISOString();

            $user->forceFill([
                'account_status' => self::DELETED_USER_ACCOUNT_STATUS,
                'deleted_by' => $admin->id,
                'deleted_reason' => $reason,
                'deleted_meta' => $meta,
            ])->save();
            $user->delete();
            $this->releaseDeletedUserIdentity($user);
        });
    }

    /**
     * @return array{left_pv: string, right_pv: string}
     */
    private function activeTransactionVolumesForUpline(User $upline): array
    {
        $volumes = ['left_pv' => '0.00', 'right_pv' => '0.00'];

        PvTransaction::query()
            ->selectRaw('branch, COALESCE(SUM(pv), 0) as total_pv')
            ->where('upline_id', $upline->id)
            ->whereColumn('buyer_id', '!=', 'upline_id')
            ->whereNull('voided_at')
            ->whereHas('buyer', fn ($query) => $query->where('role', User::ROLE_USER)->activeAccount())
            ->groupBy('branch')
            ->get()
            ->each(function (PvTransaction $row) use (&$volumes): void {
                if ($row->branch === 'L') {
                    $volumes['left_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }

                if ($row->branch === 'R') {
                    $volumes['right_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }
            });

        return $volumes;
    }

    private function isArchivedOrSoftDeletedUser(User $user): bool
    {
        return $user->trashed() || in_array((string) $user->account_status, ['deleted', 'archived'], true);
    }

    private function currentUserPhone(User $user): ?string
    {
        if (Schema::hasColumn('users', 'phone') && is_string($user->getAttribute('phone'))) {
            return $user->getAttribute('phone');
        }

        $user->loadMissing('profile');

        return $user->profile?->phone;
    }

    private function currentReferralCode(User $user): ?string
    {
        if (Schema::hasColumn('users', 'referral_code') && is_string($user->getAttribute('referral_code'))) {
            return $user->getAttribute('referral_code');
        }

        return $user->login ?: (string) $user->id;
    }

    private function uniqueDeletedUserValue(string $column, string $baseValue, User $user): string
    {
        if (! Schema::hasColumn('users', $column)) {
            return $baseValue;
        }

        $value = $baseValue;
        $attempt = 0;

        while (User::withTrashed()
            ->where($column, $value)
            ->whereKeyNot($user->id)
            ->exists()
        ) {
            $attempt++;
            $value = "{$baseValue}_{$attempt}";
        }

        return $value;
    }

    /**
     * @param  array<int, int>  $deletedUserIds
     * @return array<int, int>
     */
    private function affectedUplineIdsForDeletedUserIds(array $deletedUserIds): array
    {
        $uplineIds = [];

        User::withTrashed()
            ->whereIn('id', $deletedUserIds ?: [0])
            ->get()
            ->each(function (User $user) use (&$uplineIds): void {
                $meta = is_array($user->deleted_meta) ? $user->deleted_meta : [];
                $uplineIds = [
                    ...$uplineIds,
                    ...collect($meta['affected_upline_ids'] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->all(),
                ];
            });

        BinaryNode::withTrashed()
            ->whereIn('user_id', $deletedUserIds ?: [0])
            ->get(['user_id', 'path'])
            ->each(function (BinaryNode $node) use (&$uplineIds, $deletedUserIds): void {
                if (! $node->path) {
                    return;
                }

                $uplineIds = [
                    ...$uplineIds,
                    ...collect(explode('.', $node->path))
                        ->map(fn (string $id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0 && ! in_array($id, $deletedUserIds, true))
                        ->all(),
                ];
            });

        return collect($uplineIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  SupportCollection<int, User>  $fallbackUsers
     */
    private function systemCleanupActor(SupportCollection $fallbackUsers): User
    {
        $actor = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->orderBy('id')
            ->first()
            ?? User::withTrashed()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->orderBy('id')
                ->first()
            ?? $fallbackUsers->first();

        if (! $actor) {
            throw ValidationException::withMessages([
                'admin' => ['Не найден пользователь для audit cleanup.'],
            ]);
        }

        return $actor;
    }

    /**
     * @param  array<int, int>  $deletedUserIds
     * @param  array<int, int>  $affectedUplineIds
     */
    private function writeAuditLog(User $target, User $admin, bool $deleteSubtree, string $reason, array $deletedUserIds, array $affectedUplineIds): void
    {
        AdminActionLog::query()->create([
            'admin_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => 'partner_deleted',
            'reason' => $reason,
            'metadata' => [
                'delete_subtree' => $deleteSubtree,
                'deleted_user_ids' => $deletedUserIds,
                'deleted_users_count' => count($deletedUserIds),
                'affected_upline_ids' => $affectedUplineIds,
                'affected_uplines_count' => count($affectedUplineIds),
            ],
        ]);
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
