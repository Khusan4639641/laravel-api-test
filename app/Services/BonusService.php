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
        // TODO: Match left/right remaining PV and calculate binary bonus by package percent.
        // TODO: Persist matched PV and update user remaining PV counters.
    }
}
