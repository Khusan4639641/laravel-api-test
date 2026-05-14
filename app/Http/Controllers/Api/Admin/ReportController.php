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

        return response()->json([
            'partners' => [
                'total' => $usersTotal,
                'new_14_days' => User::query()->where('created_at', '>=', $now->subDays(14))->count(),
                'active' => $activeUsers,
                'inactive' => max($usersTotal - $activeUsers, 0),
            ],
            'finance' => [
                'revenue' => (float) Order::query()->sum('total_amount'),
                'bonuses_paid' => (float) BonusTransaction::query()
                    ->whereIn('status', ['paid', 'completed', 'approved'])
                    ->sum('amount'),
                'bonuses_total' => (float) BonusTransaction::query()->sum('amount'),
                'pending_withdrawals' => (float) WithdrawalRequest::query()
                    ->where('status', 'pending')
                    ->sum('amount'),
            ],
            'packages' => [
                'sold' => Order::query()->count(),
                'pv' => (float) Order::query()->sum('total_pv'),
            ],
        ]);
    }
}
