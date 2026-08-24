<?php

namespace App\Console\Commands;

use App\Models\BinaryNode;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\PartnerDeletionService;
use Illuminate\Console\Command;

class CleanupDeletedUserEffectsCommand extends Command
{
    protected $signature = 'safi:cleanup-deleted-user-effects {--dry-run : Only report affected data without writing changes}';

    protected $description = 'Report and cleanup operational effects that still belong to soft-deleted or archived users.';

    public function handle(PartnerDeletionService $partnerDeletionService): int
    {
        $users = $this->deletedOrArchivedUsersQuery()
            ->orderBy('id')
            ->get();
        $userIds = $users->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $stats = $this->stats($userIds, $users);
        $dryRun = (bool) $this->option('dry-run');

        $this->line('dry run: '.($dryRun ? 'yes' : 'no'));
        $this->line("found count: {$stats['found_users']}");
        $this->line("unreleased identities: {$stats['unreleased_identities']}");
        $this->line("active binary nodes: {$stats['active_binary_nodes']}");
        $this->line("non-voided pv transactions: {$stats['non_voided_pv_transactions']} ({$stats['non_voided_pv_sum']} PV)");
        $this->line("completed related bonuses: {$stats['completed_related_bonuses']} ({$stats['completed_related_bonus_sum']})");
        $this->line("completed package audit transactions: {$stats['completed_package_transactions']}");
        $this->line("pending withdrawals: {$stats['pending_withdrawals']} ({$stats['pending_withdrawal_sum']})");
        $this->line("active orders: {$stats['active_orders']}");
        $this->line("open wallet balance: {$stats['open_wallet_balance']}");

        if ($dryRun || $users->isEmpty()) {
            return self::SUCCESS;
        }

        $partnerDeletionService->voidDeletedUserOperationalData($users);
        $this->info('cleanup completed');

        return self::SUCCESS;
    }

    private function deletedOrArchivedUsersQuery()
    {
        return User::withTrashed()
            ->where(function ($query): void {
                $query->whereNotNull('deleted_at')
                    ->orWhereIn('account_status', ['deleted', 'archived']);
            });
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<string, int|string>
     */
    private function stats(array $userIds, $users): array
    {
        $userIds = $userIds ?: [0];
        $orderIds = Order::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();
        $relatedBonuses = BonusTransaction::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($userIds, $orderIds): void {
                $query->whereIn('user_id', $userIds)
                    ->orWhereIn('source_user_id', $userIds)
                    ->orWhereIn('source_order_id', $orderIds ?: [0]);
            });

        return [
            'found_users' => $users->count(),
            'unreleased_identities' => $users->filter(fn (User $user): bool => empty(($user->deleted_meta ?? [])['identity_released_at']))->count(),
            'active_binary_nodes' => BinaryNode::query()
                ->whereIn('user_id', $userIds)
                ->where('is_active', true)
                ->count(),
            'non_voided_pv_transactions' => PvTransaction::query()
                ->whereIn('buyer_id', $userIds)
                ->whereNull('voided_at')
                ->count(),
            'non_voided_pv_sum' => $this->decimal(PvTransaction::query()
                ->whereIn('buyer_id', $userIds)
                ->whereNull('voided_at')
                ->sum('pv')),
            'completed_related_bonuses' => (clone $relatedBonuses)->count(),
            'completed_related_bonus_sum' => $this->decimal((clone $relatedBonuses)->sum('amount')),
            'completed_package_transactions' => WalletTransaction::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('type', ['package_activation', 'package_assignment', 'package_upgrade'])
                ->where('status', 'completed')
                ->count(),
            'pending_withdrawals' => WithdrawalRequest::query()
                ->whereIn('user_id', $userIds)
                ->where('status', 'pending')
                ->count(),
            'pending_withdrawal_sum' => $this->decimal(WithdrawalRequest::query()
                ->whereIn('user_id', $userIds)
                ->where('status', 'pending')
                ->sum('amount')),
            'active_orders' => Order::query()
                ->whereIn('user_id', $userIds)
                ->whereNotIn('status', ['completed', 'cancelled', 'voided'])
                ->count(),
            'open_wallet_balance' => $this->decimal(Wallet::query()
                ->whereIn('user_id', $userIds)
                ->where('status', '!=', 'closed')
                ->sum('balance')),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
