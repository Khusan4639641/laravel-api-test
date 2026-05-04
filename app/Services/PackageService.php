<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PackageService
{
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
}
