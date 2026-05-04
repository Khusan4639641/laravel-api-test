<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BonusService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function accrueReferralBonus(User $sponsor, User $referral, float|string $baseAmount): ?BonusTransaction
    {
        return DB::transaction(function () use ($sponsor, $referral, $baseAmount): ?BonusTransaction {
            $sponsor->loadMissing('currentPackage');
            $percent = (string) ($sponsor->currentPackage?->referral_percent ?? 0);

            if (bccomp($percent, '0', 2) <= 0) {
                return null;
            }

            $amount = bcdiv(bcmul((string) $baseAmount, $percent, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $this->walletService->createUserWallets($sponsor);

            $wallet = $sponsor->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $sponsor->id,
                'source_user_id' => $referral->id,
                'bonus_type' => 'referral',
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'base_amount' => (string) $baseAmount,
                    'percent_source' => 'sponsor_package',
                    'referral_percent' => $percent,
                    'sponsor_package_id' => $sponsor->current_package_id,
                    'referral_package_id' => $referral->current_package_id,
                ],
                'calculated_at' => now(),
            ]);

            $walletTransaction = $this->walletService->credit(
                $wallet,
                $amount,
                'referral_bonus',
                $bonusTransaction
            );

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            return $bonusTransaction->refresh();
        });
    }

    public function calculateBinaryBonus(User $user): ?BonusTransaction
    {
        return DB::transaction(function () use ($user): ?BonusTransaction {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);

            $basePv = $this->minDecimal((string) $user->remaining_left_pv, (string) $user->remaining_right_pv);
            $percent = (string) ($user->currentPackage?->binary_percent ?? 0);

            if (bccomp($basePv, '0', 2) <= 0 || bccomp($percent, '0', 2) <= 0) {
                return null;
            }

            $amount = bcdiv(bcmul($basePv, $percent, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $mainAmount = bcdiv(bcmul($amount, '90', 2), '100', 2);
            $bonusAmount = bcsub($amount, $mainAmount, 2);

            $user->forceFill([
                'remaining_left_pv' => bcsub((string) $user->remaining_left_pv, $basePv, 2),
                'remaining_right_pv' => bcsub((string) $user->remaining_right_pv, $basePv, 2),
            ])->save();

            $this->walletService->createUserWallets($user);

            $mainWallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();
            $bonusWallet = $user->wallets()
                ->where('type', 'bonus')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'binary',
                'amount' => $amount,
                'left_pv' => $user->left_pv,
                'right_pv' => $user->right_pv,
                'matched_pv' => $basePv,
                'status' => 'completed',
                'metadata' => [
                    'base_pv' => $basePv,
                    'binary_percent' => $percent,
                    'main_percent' => '90.00',
                    'bonus_percent' => '10.00',
                    'main_amount' => $mainAmount,
                    'bonus_amount' => $bonusAmount,
                    'package_id' => $user->current_package_id,
                    'remaining_left_pv_after' => (string) $user->remaining_left_pv,
                    'remaining_right_pv_after' => (string) $user->remaining_right_pv,
                ],
                'calculated_at' => now(),
            ]);

            $mainWalletTransaction = $this->walletService->credit(
                $mainWallet,
                $mainAmount,
                'binary_bonus_main',
                $bonusTransaction
            );
            $bonusWalletTransaction = $this->walletService->credit(
                $bonusWallet,
                $bonusAmount,
                'binary_bonus_deposit',
                $bonusTransaction
            );

            $metadata = $bonusTransaction->metadata;
            $metadata['main_wallet_transaction_id'] = $mainWalletTransaction->id;
            $metadata['bonus_wallet_transaction_id'] = $bonusWalletTransaction->id;

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $mainWalletTransaction->id,
                'metadata' => $metadata,
            ])->save();

            return $bonusTransaction->refresh();
        });
    }

    private function minDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }
}
