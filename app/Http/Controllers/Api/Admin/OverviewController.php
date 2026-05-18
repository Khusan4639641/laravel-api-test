<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $usersTotal = User::query()->count();
        $activeUsers = User::query()
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'inactive');
            })
            ->count();

        return response()->json([
            'users' => [
                'total' => $usersTotal,
                'active' => $activeUsers,
                'inactive' => max($usersTotal - $activeUsers, 0),
            ],
            'orders' => [
                'total' => Order::query()->count(),
                'revenue' => (float) Order::query()->sum('total_amount'),
                'total_pv' => (float) Order::query()->sum('total_pv'),
                'packages_sold' => Order::query()->count(),
            ],
            'bonuses' => [
                'total' => (float) BonusTransaction::query()->sum('amount'),
                'paid' => (float) BonusTransaction::query()
                    ->whereIn('status', ['paid', 'completed', 'approved'])
                    ->sum('amount'),
                'pending' => (float) BonusTransaction::query()
                    ->where('status', 'pending')
                    ->sum('amount'),
            ],
            'withdrawals' => [
                'total' => WithdrawalRequest::query()->count(),
                'pending' => WithdrawalRequest::query()->where('status', 'pending')->count(),
                'pending_amount' => (float) WithdrawalRequest::query()->where('status', 'pending')->sum('amount'),
                'approved' => WithdrawalRequest::query()->whereIn('status', ['approved', 'paid', 'completed'])->count(),
                'approved_amount' => (float) WithdrawalRequest::query()->whereIn('status', ['approved', 'paid', 'completed'])->sum('amount'),
                'rejected' => WithdrawalRequest::query()->whereIn('status', ['rejected', 'declined', 'failed'])->count(),
            ],
            'transactions' => [
                'total' => WalletTransaction::query()->count(),
                'credit_amount' => (float) WalletTransaction::query()->where('direction', 'credit')->sum('amount'),
                'debit_amount' => (float) WalletTransaction::query()->where('direction', 'debit')->sum('amount'),
            ],
            'catalog' => [
                'products' => Product::query()->count(),
                'packages' => Package::query()->count(),
            ],
            'support' => [
                'open' => SupportTicket::query()->where('status', SupportTicket::STATUS_OPEN)->count(),
                'in_progress' => SupportTicket::query()->where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
                'answered' => SupportTicket::query()->where('status', SupportTicket::STATUS_ANSWERED)->count(),
                'closed' => SupportTicket::query()->where('status', SupportTicket::STATUS_CLOSED)->count(),
            ],
        ]);
    }
}
