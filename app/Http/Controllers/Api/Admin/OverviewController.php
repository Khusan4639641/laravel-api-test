<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $usersTotal = $this->partnerUsers()->count();
        $activeUsers = $this->partnerUsers()
            ->where('account_status', 'active')
            ->count();
        $orders = $this->partnerOrders();
        $paidBonuses = $this->partnerBonuses()
            ->whereIn('status', ['paid', 'completed', 'approved']);
        $pendingBonuses = $this->partnerBonuses()
            ->where('status', 'pending');
        $pendingWithdrawals = $this->partnerWithdrawals()
            ->where('status', 'pending');
        $approvedWithdrawals = $this->partnerWithdrawals()
            ->whereIn('status', ['approved', 'paid', 'completed']);
        $rejectedWithdrawals = $this->partnerWithdrawals()
            ->whereIn('status', ['rejected', 'declined', 'failed']);
        $transactions = $this->partnerWalletTransactions();
        $recentTransactions = $this->partnerWalletTransactions()
            ->with(['user.profile', 'wallet'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'users' => [
                'total' => $usersTotal,
                'active' => $activeUsers,
                'inactive' => max($usersTotal - $activeUsers, 0),
            ],
            'orders' => [
                'total' => $orders->count(),
                'revenue' => (float) $this->partnerOrders()->sum('total_amount'),
                'total_pv' => (float) $this->partnerUsers()->sum('total_pv'),
                'packages_sold' => $this->partnerOrders()->count(),
            ],
            'bonuses' => [
                'total' => (float) $this->partnerBonuses()->sum('amount'),
                'paid' => (float) $paidBonuses->sum('amount'),
                'pending' => (float) $pendingBonuses->sum('amount'),
            ],
            'withdrawals' => [
                'total' => $this->partnerWithdrawals()->count(),
                'pending' => $pendingWithdrawals->count(),
                'pending_amount' => (float) $this->partnerWithdrawals()->where('status', 'pending')->sum('amount'),
                'approved' => $approvedWithdrawals->count(),
                'approved_amount' => (float) $this->partnerWithdrawals()->whereIn('status', ['approved', 'paid', 'completed'])->sum('amount'),
                'rejected' => $rejectedWithdrawals->count(),
            ],
            'transactions' => [
                'total' => $transactions->count(),
                'credit_amount' => (float) $this->partnerWalletTransactions()->where('direction', 'credit')->sum('amount'),
                'debit_amount' => (float) $this->partnerWalletTransactions()->where('direction', 'debit')->sum('amount'),
            ],
            'recent_transactions' => WalletTransactionResource::collection($recentTransactions)->resolve(),
            'chart' => [],
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

    private function partnerUsers(): Builder
    {
        return User::query()->where('role', User::ROLE_USER);
    }

    private function partnerOrders(): Builder
    {
        return Order::query()->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerBonuses(): Builder
    {
        return BonusTransaction::query()->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerWithdrawals(): Builder
    {
        return WithdrawalRequest::query()->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerWalletTransactions(): Builder
    {
        return WalletTransaction::query()->whereHas('user', $this->partnerRoleFilter());
    }

    private function partnerRoleFilter(): callable
    {
        return static fn (Builder $query): Builder => $query->where('role', User::ROLE_USER);
    }
}
