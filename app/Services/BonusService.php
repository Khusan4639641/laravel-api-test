<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use Illuminate\Support\Facades\DB;

class BonusService
{
    private const REFERRAL_PERCENT = '10';

    private const DEPOSIT_CASHBACK_PERCENT = '20';

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
                $bonusTransaction
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
                    'binary_percent' => $percent,
                    'main_percent' => '90.00',
                    'deposit_percent' => '10.00',
                    'main_amount' => $mainAmount,
                    'deposit_amount' => $bonusAmount,
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
            $depositWalletTransaction = $this->walletService->credit(
                $depositWallet,
                $bonusAmount,
                'binary_bonus_deposit',
                $bonusTransaction
            );

            $metadata = $bonusTransaction->metadata;
            $metadata['main_wallet_transaction_id'] = $mainWalletTransaction->id;
            $metadata['deposit_wallet_transaction_id'] = $depositWalletTransaction->id;

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $mainWalletTransaction->id,
                'metadata' => $metadata,
            ])->save();

            $user->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
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
                $bonusTransaction
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
