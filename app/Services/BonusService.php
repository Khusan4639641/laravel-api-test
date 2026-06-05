<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use Illuminate\Support\Facades\DB;

class BonusService
{
    private const REFERRAL_PERCENT = '10';

    private const DEPOSIT_CASHBACK_PERCENT = '20';

    private const PV_MONEY_RATE = '500';

    private const BINARY_PERIOD_DAYS = 15;

    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function accrueReferralBonus(
        User $sponsor,
        User $referral,
        float|string $baseAmount,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): ?BonusTransaction
    {
        return DB::transaction(function () use ($sponsor, $referral, $baseAmount, $metadata, $idempotencyKey): ?BonusTransaction {
            if ($idempotencyKey !== null) {
                $existingBonus = $this->findExistingReferralBonus($sponsor, $referral, $idempotencyKey);

                if ($existingBonus) {
                    return $existingBonus;
                }
            }

            $sponsor->loadMissing('currentPackage');
            $percent = self::REFERRAL_PERCENT;

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

            $bonusMetadata = array_merge($metadata, [
                'base_amount' => (string) $baseAmount,
                'percent_source' => 'business_tz',
                'referral_percent' => $percent,
                'sponsor_package_id' => $sponsor->current_package_id,
                'referral_package_id' => $referral->current_package_id,
            ]);

            if ($idempotencyKey !== null) {
                $bonusMetadata['referral_bonus_key'] = $idempotencyKey;
            }

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $sponsor->id,
                'source_user_id' => $referral->id,
                'bonus_type' => 'referral',
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => $bonusMetadata,
                'calculated_at' => now(),
            ]);

            $walletTransaction = $this->walletService->credit(
                $wallet,
                $amount,
                'referral_bonus',
                $bonusTransaction,
                [
                    'source' => 'referral_bonus',
                    'referral_user_id' => $referral->id,
                ],
                "Referral bonus: {$referral->login}",
            );

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            $sponsor->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    private function findExistingReferralBonus(User $sponsor, User $referral, string $idempotencyKey): ?BonusTransaction
    {
        return BonusTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('source_user_id', $referral->id)
            ->where('bonus_type', 'referral')
            ->get()
            ->first(fn (BonusTransaction $bonus): bool => ($bonus->metadata['referral_bonus_key'] ?? null) === $idempotencyKey);
    }

    public function calculateBinaryBonus(User $user): ?BonusTransaction
    {
        return DB::transaction(function () use ($user): ?BonusTransaction {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);

            if ($this->hasActiveBinaryRun($user)) {
                return null;
            }

            $basePv = $this->minDecimal((string) $user->remaining_left_pv, (string) $user->remaining_right_pv);
            $percent = (string) ($user->currentPackage?->binary_percent ?? 0);

            if (bccomp($basePv, '0', 2) <= 0 || bccomp($percent, '0', 2) <= 0) {
                return null;
            }

            $moneyBaseAmount = bcmul($basePv, self::PV_MONEY_RATE, 2);
            $amount = bcdiv(bcmul($moneyBaseAmount, $percent, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $mainAmount = bcdiv(bcmul($amount, '90', 2), '100', 2);
            $bonusAmount = bcsub($amount, $mainAmount, 2);
            $periodStart = now();
            $periodEnd = $periodStart->copy()->addDays(self::BINARY_PERIOD_DAYS);
            $carryLeftPv = bcsub((string) $user->remaining_left_pv, $basePv, 2);
            $carryRightPv = bcsub((string) $user->remaining_right_pv, $basePv, 2);

            $user->forceFill([
                'remaining_left_pv' => $carryLeftPv,
                'remaining_right_pv' => $carryRightPv,
            ])->save();

            $run = BinaryBonusRun::query()->create([
                'user_id' => $user->id,
                'status' => 'completed',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'weak_leg_pv' => $basePv,
                'used_left_pv' => $basePv,
                'used_right_pv' => $basePv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'amount' => $amount,
                'metadata' => [
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'binary_percent' => $percent,
                    'package_id' => $user->current_package_id,
                ],
            ]);

            $this->walletService->createUserWallets($user);

            $mainWallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();
            $depositWallet = $user->wallets()
                ->where('type', 'deposit')
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
                    'money_base_amount' => $moneyBaseAmount,
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'binary_percent' => $percent,
                    'main_percent' => '90.00',
                    'deposit_percent' => '10.00',
                    'main_amount' => $mainAmount,
                    'deposit_amount' => $bonusAmount,
                    'package_id' => $user->current_package_id,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $periodEnd->toISOString(),
                    'used_left_pv' => $basePv,
                    'used_right_pv' => $basePv,
                    'carry_left_pv' => $carryLeftPv,
                    'carry_right_pv' => $carryRightPv,
                    'remaining_left_pv_after' => $carryLeftPv,
                    'remaining_right_pv_after' => $carryRightPv,
                ],
                'calculated_at' => now(),
            ]);

            $mainWalletTransaction = $this->walletService->credit(
                $mainWallet,
                $mainAmount,
                'binary_bonus_main',
                $bonusTransaction,
                [
                    'source' => 'binary_bonus',
                    'wallet_part' => 'main',
                    'base_pv' => $basePv,
                    'binary_percent' => $percent,
                ],
                'Binary bonus: main wallet 90%',
            );
            $depositWalletTransaction = $this->walletService->credit(
                $depositWallet,
                $bonusAmount,
                'binary_bonus_deposit',
                $bonusTransaction,
                [
                    'source' => 'binary_bonus',
                    'wallet_part' => 'deposit',
                    'base_pv' => $basePv,
                    'binary_percent' => $percent,
                ],
                'Binary bonus: deposit wallet 10%',
            );

            $metadata = $bonusTransaction->metadata;
            $metadata['main_wallet_transaction_id'] = $mainWalletTransaction->id;
            $metadata['deposit_wallet_transaction_id'] = $depositWalletTransaction->id;

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $mainWalletTransaction->id,
                'metadata' => $metadata,
            ])->save();

            $run->forceFill([
                'bonus_transaction_id' => $bonusTransaction->id,
            ])->save();

            BinaryBonusCalculation::query()->create([
                'binary_bonus_run_id' => $run->id,
                'user_id' => $user->id,
                'bonus_transaction_id' => $bonusTransaction->id,
                'left_pv' => $user->left_pv,
                'right_pv' => $user->right_pv,
                'weak_leg_pv' => $basePv,
                'used_left_pv' => $basePv,
                'used_right_pv' => $basePv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'money_base_amount' => $moneyBaseAmount,
                'binary_percent' => $percent,
                'bonus_amount' => $amount,
                'main_amount' => $mainAmount,
                'deposit_amount' => $bonusAmount,
                'metadata' => [
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $periodEnd->toISOString(),
                ],
            ]);

            $user->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    private function hasActiveBinaryRun(User $user): bool
    {
        return BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->where('period_end', '>', now())
            ->exists();
    }

    public function accrueDepositPurchaseCashback(
        User $user,
        float|string $purchaseAmount,
        ?WalletTransaction $sourceTransaction = null,
    ): ?BonusTransaction {
        return DB::transaction(function () use ($user, $purchaseAmount, $sourceTransaction): ?BonusTransaction {
            $purchaseAmount = (string) $purchaseAmount;
            $amount = bcdiv(bcmul($purchaseAmount, self::DEPOSIT_CASHBACK_PERCENT, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $this->walletService->createUserWallets($user);

            $wallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'cashback',
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'purchase_amount' => $purchaseAmount,
                    'cashback_percent' => self::DEPOSIT_CASHBACK_PERCENT,
                    'source' => 'deposit_purchase',
                    'deposit_wallet_transaction_id' => $sourceTransaction?->id,
                ],
                'calculated_at' => now(),
            ]);

            $walletTransaction = $this->walletService->credit(
                $wallet,
                $amount,
                'deposit_purchase_cashback',
                $bonusTransaction,
                [
                    'source' => 'deposit_purchase_cashback',
                    'purchase_amount' => $purchaseAmount,
                ],
                'Deposit purchase cashback',
            );

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            $user->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    private function minDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }
}
