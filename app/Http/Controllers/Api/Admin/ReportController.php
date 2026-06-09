<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        $now = CarbonImmutable::now();
        $usersTotal = $this->partnerUsers()->count();
        $activeUsers = $this->partnerUsers()
            ->where('account_status', 'active')
            ->count();

        $totalTurnover = (float) $this->partnerOrders()->sum('total_amount');
        $totalBonusPaid = (float) $this->partnerBonuses()
            ->whereIn('status', ['paid', 'completed', 'approved'])
            ->sum('amount');
        $pendingWithdrawals = (float) $this->partnerWithdrawals()
            ->where('status', 'pending')
            ->sum('amount');
        $totalPv = (float) $this->partnerUsers()->sum('total_pv');

        return response()->json([
            'summary' => [
                'total_users' => $usersTotal,
                'total_turnover' => $totalTurnover,
                'total_bonus_paid' => $totalBonusPaid,
                'pending_withdrawals' => $pendingWithdrawals,
                'total_pv' => $totalPv,
            ],
            'chart' => $this->chartData($now),
            'partners' => [
                'total' => $usersTotal,
                'new_14_days' => $this->partnerUsers()->where('created_at', '>=', $now->subDays(14))->count(),
                'active' => $activeUsers,
                'inactive' => max($usersTotal - $activeUsers, 0),
            ],
            'finance' => [
                'revenue' => $totalTurnover,
                'bonuses_paid' => $totalBonusPaid,
                'bonuses_total' => (float) $this->partnerBonuses()->sum('amount'),
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'packages' => [
                'sold' => $this->partnerOrders()->count(),
                'pv' => $totalPv,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function chartData(CarbonImmutable $now): array
    {
        $start = $now->startOfMonth()->subMonths(5);
        $periods = collect(range(0, 5))
            ->map(fn (int $offset): CarbonImmutable => $start->addMonths($offset));

        $orders = $this->partnerOrders()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'total_amount', 'total_pv']);
        $bonuses = $this->partnerBonuses()
            ->where('created_at', '>=', $start)
            ->whereIn('status', ['paid', 'completed', 'approved'])
            ->get(['created_at', 'amount']);
        $withdrawals = $this->partnerWithdrawals()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'amount']);
        $users = $this->partnerUsers()
            ->where('created_at', '>=', $start)
            ->get(['created_at']);

        return $periods
            ->map(function (CarbonImmutable $period) use ($orders, $bonuses, $withdrawals, $users): array {
                $periodKey = $period->format('Y-m');
                $periodOrders = $orders->filter(fn (Order $order): bool => $order->created_at?->format('Y-m') === $periodKey);

                return [
                    'period' => $periodKey,
                    'turnover' => (float) $periodOrders->sum('total_amount'),
                    'bonuses' => (float) $bonuses
                        ->filter(fn (BonusTransaction $bonus): bool => $bonus->created_at?->format('Y-m') === $periodKey)
                        ->sum('amount'),
                    'withdrawals' => (float) $withdrawals
                        ->filter(fn (WithdrawalRequest $withdrawal): bool => $withdrawal->created_at?->format('Y-m') === $periodKey)
                        ->sum('amount'),
                    'users' => $users
                        ->filter(fn (User $user): bool => $user->created_at?->format('Y-m') === $periodKey)
                        ->count(),
                    'package_sales' => $periodOrders->count(),
                    'pv' => (float) $periodOrders->sum('total_pv'),
                ];
            })
            ->values()
            ->all();
    }

    private function partnerUsers(): Builder
    {
        return User::query()->where('role', User::ROLE_USER);
    }

    private function partnerOrders(): Builder
    {
        return Order::query()
            ->where('status', '!=', 'voided')
            ->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerBonuses(): Builder
    {
        return BonusTransaction::query()
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->whereHas('user', $this->partnerRoleFilter())
            ->where(function (Builder $query): void {
                $query->whereNull('source_user_id')
                    ->orWhereHas('sourceUser', $this->partnerRoleFilter());
            });
    }

    private function partnerWithdrawals(): Builder
    {
        return WithdrawalRequest::query()->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerRoleFilter(): callable
    {
        return static fn (Builder $query): Builder => $query->where('role', User::ROLE_USER);
    }
}
