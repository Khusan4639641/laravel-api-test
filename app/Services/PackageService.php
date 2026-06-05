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

    private const UPGRADE_CHAIN = [
        'START' => 'VIP',
        'VIP' => 'ELITE',
    ];

    public function __construct(
        private readonly BonusService $bonusService,
        private readonly PvService $pvService,
        private readonly ReferralBonusBaseResolver $referralBonusBaseResolver,
        private readonly StatusBonusService $statusBonusService,
        private readonly WalletService $walletService,
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
            $this->pvService->accrueTurnoverToUplines(
                $user,
                $turnoverPv,
                $this->packageTurnoverSource($package),
                [
                    'package_id' => $package->id,
                    'package_code' => $package->code,
                    'activity_pv' => $activityPv,
                    'turnover_pv' => $turnoverPv,
                ],
            );

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->referralBonusBaseResolver->resolveForPackageActivation($package, [
                        'buyer_id' => $user->id,
                    ]);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus(
                            $sponsor,
                            $user->refresh(),
                            $eligibleReferralAmount,
                            [
                                'source' => 'package_activation',
                                'base_resolver' => ReferralBonusBaseResolver::class,
                                'package_id' => $package->id,
                                'package_code' => $package->code,
                            ],
                            "package_activation:{$user->id}:{$package->id}",
                        );
                    }
                }
            }

            $this->checkMissedStatusBonusesForElite($user->refresh());

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
                $this->pvService->accrueTurnoverToUplines(
                    $user,
                    $pvEffects['bonusable_turnover_pv'],
                    $this->packageTurnoverSource($package),
                    [
                        'package_id' => $package->id,
                        'package_code' => $package->code,
                        'manual_assignment' => true,
                        'turnover_pv' => $pvEffects['bonusable_turnover_pv'],
                    ],
                );
            }

            if (bccomp($pvEffects['non_bonusable_turnover_pv'], '0', 2) > 0) {
                $this->pvService->accrueTurnoverToUplines(
                    $user,
                    $pvEffects['non_bonusable_turnover_pv'],
                    $this->packageTurnoverSource($package),
                    [
                        'package_id' => $package->id,
                        'package_code' => $package->code,
                        'manual_assignment' => true,
                        'turnover_pv' => $pvEffects['non_bonusable_turnover_pv'],
                    ],
                    null,
                    false,
                );
            }

            $this->creditManualPackageActivityAmount($user, $package, $pvEffects['user_pv']);

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->eligibleReferralAmountForManualAssignment($pvEffects);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus(
                            $sponsor,
                            $user->refresh(),
                            $eligibleReferralAmount,
                            [
                                'source' => 'manual_package_assignment',
                                'package_id' => $package->id,
                                'package_code' => $package->code,
                            ],
                            "manual_package_assignment:{$user->id}:{$package->id}",
                        );
                    }
                }
            }

            $this->checkMissedStatusBonusesForElite($user->refresh());

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
                $this->pvService->accrueTurnoverToUplines(
                    $user,
                    $additionalPv,
                    $this->packageTurnoverSource($targetPackage, true),
                    [
                        'package_id' => $targetPackage->id,
                        'package_code' => $targetPackage->code,
                        'current_package_id' => $currentPackage->id,
                        'current_package_code' => $currentPackage->code,
                        'upgrade' => true,
                        'additional_pv' => $additionalPv,
                    ],
                    null,
                    $this->isUpgradePvBonusable($targetPackage),
                );
            }

            $cashbackAmount = '0.00';

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $eligibleReferralAmount = $this->referralBonusBaseResolver->resolveForPackageUpgrade($currentPackage, $targetPackage, [
                        'buyer_id' => $user->id,
                        'payment_amount' => $paymentAmount,
                    ]);

                    if (bccomp($eligibleReferralAmount, '0', 2) > 0) {
                        $this->bonusService->accrueReferralBonus(
                            $sponsor,
                            $user->refresh(),
                            $eligibleReferralAmount,
                            [
                                'source' => 'package_upgrade',
                                'base_resolver' => ReferralBonusBaseResolver::class,
                                'from_package_id' => $currentPackage->id,
                                'from_package_code' => $currentPackage->code,
                                'to_package_id' => $targetPackage->id,
                                'to_package_code' => $targetPackage->code,
                            ],
                            "package_upgrade:{$user->id}:{$currentPackage->id}:{$targetPackage->id}",
                        );
                    }
                }
            }

            $this->checkMissedStatusBonusesForElite($user->refresh());

            return [
                'user' => $user->refresh()->load(['currentPackage', 'wallets']),
                'payment_amount' => $paymentAmount,
                'additional_pv' => $additionalPv,
                'cashback_amount' => $cashbackAmount,
            ];
        });
    }

    private function packageTurnoverSource(Package $package, bool $isUpgrade = false): string
    {
        if ($package->code === 'ELITE') {
            return 'package_elite_upgrade';
        }

        return match ($package->code) {
            'START' => 'package_start',
            'VIP' => 'package_vip',
            default => $isUpgrade ? 'package_upgrade' : 'package_activation',
        };
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

    private function creditManualPackageActivityAmount(User $user, Package $package, string $activityPv): void
    {
        if (bccomp($activityPv, '0', 2) <= 0) {
            return;
        }

        $amount = bcmul($activityPv, self::PV_MONEY_RATE, 2);

        if (bccomp($amount, '0', 2) <= 0) {
            return;
        }

        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $this->walletService->credit(
            $wallet,
            $amount,
            'package_activity_credit',
            $package,
            [
                'source' => 'manual_package_assignment',
                'package_id' => $package->id,
                'package_code' => $package->code,
                'activity_pv' => $activityPv,
                'pv_money_rate' => self::PV_MONEY_RATE,
                'package_price' => (string) $package->price,
                'price_used_as_pv' => false,
            ],
            "Manual package assignment: {$package->code}",
        );
    }

    private function checkMissedStatusBonusesForElite(User $user): void
    {
        $user->loadMissing('currentPackage');

        if ($user->currentPackage?->code === 'ELITE') {
            $this->statusBonusService->checkMissedStatusBonuses($user);
        }
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
