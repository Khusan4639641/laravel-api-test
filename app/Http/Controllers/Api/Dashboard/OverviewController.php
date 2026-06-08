<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\BinaryNode;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\DashboardBranchVolumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function __invoke(Request $request, DashboardBranchVolumeService $branchVolumeService): JsonResponse
    {
        $user = $request->user()->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode']);
        $branchVolumes = $branchVolumeService->getBranchVolumes($user);
        $user->setAttribute('left_pv', $branchVolumes['left_pv']);
        $user->setAttribute('right_pv', $branchVolumes['right_pv']);
        $user->setAttribute('total_pv', $branchVolumes['total_pv']);
        $recentTransactions = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get();
        $bonusTotals = BonusTransaction::query()
            ->select('bonus_type', DB::raw('sum(amount) as total'))
            ->where('user_id', $user->id)
            ->groupBy('bonus_type')
            ->pluck('total', 'bonus_type');
        $mainWalletBalance = (string) $user->wallets
            ->where('type', 'main')
            ->sum('balance');
        $totalWalletBalance = (string) $user->wallets->sum('balance');
        $pendingBinaryAmount = (string) BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('pending_amount');
        $teamCount = $this->descendantsQuery($user->binaryNode?->path)
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->count();

        return response()->json([
            'user' => UserResource::make($user),
            'wallets' => WalletResource::collection($user->wallets),
            'balances' => [
                'available' => $mainWalletBalance,
                'withdrawable' => $mainWalletBalance,
                'total_wallet_balance' => $totalWalletBalance,
                'hold' => (string) $user->wallets->sum('hold_balance'),
                'total_earned' => (string) WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('direction', 'credit')
                    ->where('affects_balance', true)
                    ->sum('amount'),
                'pending_withdrawals' => (string) WithdrawalRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->sum('amount'),
                'pending_binary' => $pendingBinaryAmount,
                'withdrawn' => (string) WithdrawalRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->sum('amount'),
            ],
            'structure' => [
                'total_partners' => $teamCount,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'total_pv' => $branchVolumes['total_pv'],
                'weak_leg_pv' => $branchVolumes['weak_leg_pv'],
                'remaining_left_pv' => $user->remaining_left_pv,
                'remaining_right_pv' => $user->remaining_right_pv,
                'weak_leg' => $branchVolumes['weak_leg'],
            ],
            'team_count' => $teamCount,
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
            'weak_leg_pv' => $branchVolumes['weak_leg_pv'],
            'bonuses' => $bonusTotals,
            'bonuses_summary' => [
                'total' => (string) BonusTransaction::query()->where('user_id', $user->id)->sum('amount'),
                'by_type' => $bonusTotals,
                'pending_binary' => $pendingBinaryAmount,
            ],
            'orders_summary' => [
                'total' => Order::query()->where('user_id', $user->id)->count(),
                'completed' => Order::query()->where('user_id', $user->id)->where('status', 'completed')->count(),
                'pending' => Order::query()->where('user_id', $user->id)->whereIn('status', ['pending', 'new', 'processing'])->count(),
                'total_amount' => (string) Order::query()->where('user_id', $user->id)->sum('total_amount'),
                'total_pv' => (string) Order::query()->where('user_id', $user->id)->sum('total_pv'),
            ],
            'withdrawals_summary' => [
                'total' => WithdrawalRequest::query()->where('user_id', $user->id)->count(),
                'pending' => WithdrawalRequest::query()->where('user_id', $user->id)->where('status', 'pending')->count(),
                'approved' => WithdrawalRequest::query()->where('user_id', $user->id)->where('status', 'approved')->count(),
                'rejected' => WithdrawalRequest::query()->where('user_id', $user->id)->where('status', 'rejected')->count(),
                'total_amount' => (string) WithdrawalRequest::query()->where('user_id', $user->id)->sum('amount'),
                'pending_amount' => (string) WithdrawalRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->sum('amount'),
            ],
            'recent_transactions' => WalletTransactionResource::collection($recentTransactions),
        ]);
    }

    private function descendantsQuery(?string $path)
    {
        return BinaryNode::query()->when(
            $path,
            fn ($query) => $query->where('path', 'like', $path.'.%'),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }
}
