<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageService
{
    private const PV_MONEY_RATE = '500';

    private const VIP_ACTIVATION_REFERRAL_EXCLUDED_AMOUNT = '45000';

    private const UPGRADE_CHAIN = [
        'START' => 'VIP',
        'VIP' => 'ELITE',
    ];

    public function __construct(
        private readonly BonusService $bonusService,
        private readonly PvService $pvService,
    ) {
    }

    public function upgradePackage(User $user, Package $package, ?Order $sourceOrder = null): User
    {
        return DB::transaction(function () use ($user, $package): User {
            $user->forceFill([
                'current_package_id' => $package->id,
            ])->save();

            $activityPv = $package->activityPv();
            $turnoverPv = $package->turnoverPv();

            $this->pvService->addUserPv($user, $activityPv);
            $this->pvService->accruePvUpTree($user, $turnoverPv);

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->eligibleReferralAmountForActivation($package);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus($sponsor, $user->refresh(), $eligibleReferralAmount);
                    }
                }
            }

            return $user->refresh()->load(['currentPackage', 'sponsor', 'wallets']);
        });
    }

    public function canUpgrade(User $user, Package $package): bool
    {
        $user->loadMissing('currentPackage');

        if (! $user->currentPackage) {
            return $package->is_active && $package->status === 'active';
        }

        return $package->is_active
            && $package->status === 'active'
            && $package->code === (self::UPGRADE_CHAIN[$user->currentPackage->code] ?? null);
    }

    public function assignPackageManually(User $user, Package $package, bool $applyBusinessEffects = true): User
    {
        return DB::transaction(function () use ($user, $package, $applyBusinessEffects): User {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);

            $currentPackage = $user->currentPackage;
            $alreadyAssigned = $currentPackage?->id === $package->id;

            $user->forceFill([
                'current_package_id' => $package->id,
            ])->save();

            if (! $applyBusinessEffects || $alreadyAssigned) {
                return $user->refresh()->load(['currentPackage', 'sponsor', 'wallets']);
            }

            $pvEffects = $this->manualAssignmentPvEffects($currentPackage, $package);

            if (bccomp($pvEffects['user_pv'], '0', 2) > 0) {
                $this->pvService->addUserPv($user, $pvEffects['user_pv']);
            }

            if (bccomp($pvEffects['bonusable_turnover_pv'], '0', 2) > 0) {
                $this->pvService->accruePvUpTree($user, $pvEffects['bonusable_turnover_pv']);
            }

            if (bccomp($pvEffects['non_bonusable_turnover_pv'], '0', 2) > 0) {
                $this->pvService->accruePvUpTree($user, $pvEffects['non_bonusable_turnover_pv'], null, false);
            }

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->eligibleReferralAmountForManualAssignment($pvEffects);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus($sponsor, $user->refresh(), $eligibleReferralAmount);
                    }
                }
            }

            return $user->refresh()->load(['currentPackage', 'sponsor', 'wallets']);
        });
    }

    /**
     * @return array{user: User, payment_amount: string, additional_pv: string, cashback_amount: string}
     */
    public function upgradeExistingPackage(User $user, Package $targetPackage): array
    {
        return DB::transaction(function () use ($user, $targetPackage): array {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);

            if (! $user->currentPackage) {
                throw ValidationException::withMessages([
                    'package' => 'User must activate a package before upgrading.',
                ]);
            }

            $currentPackage = $user->currentPackage;
            $expectedNextCode = self::UPGRADE_CHAIN[$currentPackage->code] ?? null;

            if (! $targetPackage->is_active || $targetPackage->status !== 'active' || ! $targetPackage->is_upgradeable) {
                throw ValidationException::withMessages([
                    'package' => 'Package is inactive.',
                ]);
            }

            if ($expectedNextCode !== $targetPackage->code) {
                throw ValidationException::withMessages([
                    'package' => 'Invalid package upgrade step.',
                ]);
            }

            $paymentAmount = bcsub((string) $targetPackage->price, (string) $currentPackage->price, 2);
            $additionalPv = bcsub($targetPackage->activityPv(), $currentPackage->activityPv(), 2);

            if (bccomp($paymentAmount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'package' => 'Target package price must be greater than current package price.',
                ]);
            }

            $user->forceFill([
                'current_package_id' => $targetPackage->id,
            ])->save();

            if (bccomp($additionalPv, '0', 2) > 0) {
                $this->pvService->addUserPv($user, $additionalPv);
                $this->pvService->accruePvUpTree(
                    $user,
                    $additionalPv,
                    null,
                    $this->isUpgradePvBonusable($targetPackage),
                );
            }

            $cashbackAmount = '0.00';

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->eligibleReferralAmountForUpgrade($targetPackage, $paymentAmount);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus($sponsor, $user->refresh(), $eligibleReferralAmount);
                    }
                }
            }

            return [
                'user' => $user->refresh()->load(['currentPackage', 'wallets']),
                'payment_amount' => $paymentAmount,
                'additional_pv' => $additionalPv,
                'cashback_amount' => $cashbackAmount,
            ];
        });
    }

    private function eligibleReferralAmountForActivation(Package $package): string
    {
        if ($package->code === 'VIP') {
            // Business regression case: VIP 180000 must pay referral from 135000, not from the full price.
            return $this->positiveOrZero(bcsub((string) $package->price, self::VIP_ACTIVATION_REFERRAL_EXCLUDED_AMOUNT, 2));
        }

        if ($package->code === 'ELITE') {
            // The first 200 ELITE PV are turnover-only: 200 PV * 500 KZT does not create referral bonus.
            $excludedAmount = bcmul($package->turnoverPv(), self::PV_MONEY_RATE, 2);

            return $this->positiveOrZero(bcsub((string) $package->price, $excludedAmount, 2));
        }

        return (string) $package->price;
    }

    private function eligibleReferralAmountForUpgrade(Package $targetPackage, string $paymentAmount): string
    {
        if ($targetPackage->code === 'ELITE') {
            // VIP -> ELITE adds exactly the first 200 ELITE PV, which are excluded from referral and binary bonuses.
            return '0.00';
        }

        return $this->positiveOrZero($paymentAmount);
    }

    private function isUpgradePvBonusable(Package $targetPackage): bool
    {
        return $targetPackage->code !== 'ELITE';
    }

    /**
     * @return array{user_pv: string, bonusable_turnover_pv: string, non_bonusable_turnover_pv: string, referral_base_amount: string}
     */
    private function manualAssignmentPvEffects(?Package $currentPackage, Package $targetPackage): array
    {
        $userPv = $currentPackage
            ? $this->positiveOrZero(bcsub($targetPackage->activityPv(), $currentPackage->activityPv(), 2))
            : $targetPackage->activityPv();

        $turnoverPv = $this->manualAssignmentTurnoverPv($targetPackage, $userPv);

        if ($targetPackage->code !== 'ELITE') {
            return [
                'user_pv' => $userPv,
                'bonusable_turnover_pv' => $turnoverPv,
                'non_bonusable_turnover_pv' => '0.00',
                'referral_base_amount' => bcmul($userPv, self::PV_MONEY_RATE, 2),
            ];
        }

        return [
            'user_pv' => $userPv,
            'bonusable_turnover_pv' => '0.00',
            'non_bonusable_turnover_pv' => $turnoverPv,
            'referral_base_amount' => '0.00',
        ];
    }

    private function manualAssignmentTurnoverPv(Package $targetPackage, string $userPv): string
    {
        if (bccomp($userPv, '0', 2) <= 0) {
            return '0.00';
        }

        return $this->minDecimal($userPv, $targetPackage->turnoverPv());
    }

    /**
     * @param  array{user_pv: string, bonusable_turnover_pv: string, non_bonusable_turnover_pv: string, referral_base_amount: string}  $pvEffects
     */
    private function eligibleReferralAmountForManualAssignment(array $pvEffects): string
    {
        return $pvEffects['referral_base_amount'];
    }

    private function minDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function positiveOrZero(string $amount): string
    {
        return bccomp($amount, '0', 2) > 0 ? $amount : '0.00';
    }
}
