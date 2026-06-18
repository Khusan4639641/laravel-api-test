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
use App\Models\User;
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
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->latest()
            ->limit(6)
            ->get();
        $bonusTotals = BonusTransaction::query()
            ->select('bonus_type', DB::raw('sum(amount) as total'))
            ->where('user_id', $user->id)
            ->where('status', 'completed')
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
            ->whereHas('user', fn ($query) => $query->where('role', 'user')->activeAccount())
            ->count();
        $canInvite = $user->canInvitePartners();

        return response()->json([
            'user' => UserResource::make($user),
            'can_invite' => $canInvite,
            'referral_links' => [
                'left' => $canInvite ? $this->referralLink($request, $user, 'left') : '',
                'right' => $canInvite ? $this->referralLink($request, $user, 'right') : '',
            ],
            'wallets' => WalletResource::collection($user->wallets),
            'balances' => [
                'available' => $mainWalletBalance,
                'withdrawable' => $mainWalletBalance,
                'total_wallet_balance' => $totalWalletBalance,
                'hold' => (string) $user->wallets->sum('hold_balance'),
                'total_earned' => (string) WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('direction', 'credit')
                    ->where('status', 'completed')
                    ->where('affects_balance', true)
                    ->whereIn('type', $this->incomeTypes())
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
                'total' => (string) BonusTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'by_type' => $bonusTotals,
                'pending_binary' => $pendingBinaryAmount,
            ],
            'orders_summary' => [
                'total' => Order::query()->where('user_id', $user->id)->where('status', '!=', 'voided')->count(),
                'completed' => Order::query()->where('user_id', $user->id)->where('status', 'completed')->count(),
                'pending' => Order::query()->where('user_id', $user->id)->whereIn('status', ['pending', 'new', 'processing'])->count(),
                'total_amount' => (string) Order::query()->where('user_id', $user->id)->where('status', '!=', 'voided')->sum('total_amount'),
                'total_pv' => (string) Order::query()->where('user_id', $user->id)->where('status', '!=', 'voided')->sum('total_pv'),
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
            fn ($query) => $query->where('path', 'like', $path.'.%')->where('is_active', true),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }

    private function referralLink(Request $request, User $user, string $branch): string
    {
        $referralCode = trim((string) $user->login);

        if ($referralCode === '') {
            return '';
        }

        return $request->getSchemeAndHttpHost().'/register-ref-branch?'.http_build_query([
            'ref' => $referralCode,
            'branch' => $branch,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function incomeTypes(): array
    {
        return [
            'referral_bonus',
            'binary_bonus_main',
            'status_bonus',
            'x2_bonus',
            'bonus_x2',
            'cashback',
            'deposit_purchase_cashback',
            'manual_credit',
            'manual_adjustment',
        ];
    }
}
