<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        $now = CarbonImmutable::now();
        $usersTotal = User::query()->count();
        $activeUsers = User::query()
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'inactive');
            })
            ->count();

        $totalTurnover = (float) Order::query()->sum('total_amount');
        $totalBonusPaid = (float) BonusTransaction::query()
            ->whereIn('status', ['paid', 'completed', 'approved'])
            ->sum('amount');
        $pendingWithdrawals = (float) WithdrawalRequest::query()
            ->where('status', 'pending')
            ->sum('amount');
        $totalPv = (float) Order::query()->sum('total_pv');

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
                'new_14_days' => User::query()->where('created_at', '>=', $now->subDays(14))->count(),
                'active' => $activeUsers,
                'inactive' => max($usersTotal - $activeUsers, 0),
            ],
            'finance' => [
                'revenue' => $totalTurnover,
                'bonuses_paid' => $totalBonusPaid,
                'bonuses_total' => (float) BonusTransaction::query()->sum('amount'),
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'packages' => [
                'sold' => Order::query()->count(),
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

        $orders = Order::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'total_amount', 'total_pv']);
        $bonuses = BonusTransaction::query()
            ->where('created_at', '>=', $start)
            ->whereIn('status', ['paid', 'completed', 'approved'])
            ->get(['created_at', 'amount']);
        $withdrawals = WithdrawalRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'amount']);
        $users = User::query()
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
}
