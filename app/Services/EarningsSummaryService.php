<?php

namespace App\Services;

use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EarningsSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $bonusTotals = $this->bonusTotals($user);
        $referralTotal = $this->decimal($bonusTotals->get('referral', '0'));
        $binaryTotal = $this->decimal($bonusTotals->get('binary', '0'));
        $statusTotal = $this->decimal($bonusTotals->get('status', '0'));
        $bonusX2Total = $this->decimal($bonusTotals->get('bonus_x2', '0'));
        $cashbackTotal = $this->decimal($bonusTotals->get('cashback', '0'));
        $totalEarned = $this->decimal(BonusTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount'));

        $availableToWithdraw = $this->decimal(Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'main')
            ->sum('balance'));
        $depositBalance = $this->decimal(Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'deposit')
            ->sum('balance'));
        $pendingBinary = $this->decimal(BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('pending_amount'));
        $withdrawnTotal = $this->decimal(WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'paid', 'completed'])
            ->sum('amount'));
        $pendingWithdrawal = $this->decimal(WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount'));

        return [
            'currency' => 'KZT',
            'total' => $totalEarned,
            'total_earned' => $totalEarned,
            'available_to_withdraw' => $availableToWithdraw,
            'pending_binary' => $pendingBinary,
            'referral_total' => $referralTotal,
            'binary_total' => $binaryTotal,
            'status_total' => $statusTotal,
            'bonus_x2_total' => $bonusX2Total,
            'cashback_total' => $cashbackTotal,
            'deposit_balance' => $depositBalance,
            'withdrawn_total' => $withdrawnTotal,
            'pending_withdrawal' => $pendingWithdrawal,
            'by_type' => [
                'referral' => $referralTotal,
                'binary' => $binaryTotal,
                'status' => $statusTotal,
                'bonus_x2' => $bonusX2Total,
                'cashback' => $cashbackTotal,
            ],
        ];
    }

    /**
     * @return Collection<string, string>
     */
    private function bonusTotals(User $user): Collection
    {
        return BonusTransaction::query()
            ->select('bonus_type', DB::raw('sum(amount) as total'))
            ->where('user_id', $user->id)
            ->groupBy('bonus_type')
            ->pluck('total', 'bonus_type')
            ->map(fn (mixed $value): string => $this->decimal($value));
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return (string) $value;
    }
}
