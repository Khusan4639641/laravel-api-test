<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageService
{
    private const UPGRADE_CHAIN = [
        'START' => 'BUSINESS',
        'BUSINESS' => 'VIP',
        'VIP' => 'ELITE',
    ];

    private const CASHBACK_PERCENT = '10';

    public function __construct(
        private readonly BonusService $bonusService,
        private readonly PvService $pvService,
        private readonly WalletService $walletService,
    ) {
    }

    public function upgradePackage(User $user, Package $package, ?Order $sourceOrder = null): User
    {
        return DB::transaction(function () use ($user, $package): User {
            $user->forceFill([
                'current_package_id' => $package->id,
            ])->save();

            $this->pvService->addUserPv($user, $package->pv);
            $this->pvService->accruePvUpTree($user, $package->pv);

            if ($user->sponsor_id) {
                $sponsor = User::query()->find($user->sponsor_id);

                if ($sponsor) {
                    $this->bonusService->accrueReferralBonus($sponsor, $user->refresh(), $package->price);
                }
            }

            return $user->refresh()->load(['currentPackage', 'sponsor', 'wallets']);
        });
    }

    public function canUpgrade(User $user, Package $package): bool
    {
        $user->loadMissing('currentPackage');

        if (! $user->currentPackage) {
            return true;
        }

        return $package->sort_order >= $user->currentPackage->sort_order;
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

            if ($expectedNextCode !== $targetPackage->code) {
                throw ValidationException::withMessages([
                    'package' => 'Invalid package upgrade step.',
                ]);
            }

            $paymentAmount = bcsub((string) $targetPackage->price, (string) $currentPackage->price, 2);
            $additionalPv = bcsub((string) $targetPackage->pv, (string) $currentPackage->pv, 2);

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
                $this->pvService->accruePvUpTree($user, $additionalPv);
            }

            $cashbackAmount = '0.00';

            if (! ($currentPackage->code === 'VIP' && $targetPackage->code === 'ELITE')) {
                $cashbackAmount = bcdiv(bcmul($paymentAmount, self::CASHBACK_PERCENT, 2), '100', 2);

                if (bccomp($cashbackAmount, '0', 2) > 0) {
                    $this->walletService->createUserWallets($user);

                    $bonusWallet = $user->wallets()
                        ->where('type', 'bonus')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->walletService->credit(
                        $bonusWallet,
                        $cashbackAmount,
                        'package_upgrade_cashback',
                        null,
                        [
                            'current_package_id' => $currentPackage->id,
                            'target_package_id' => $targetPackage->id,
                            'payment_amount' => $paymentAmount,
                            'cashback_percent' => self::CASHBACK_PERCENT,
                        ]
                    );
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
}
