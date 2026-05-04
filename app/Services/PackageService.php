<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;

class PackageService
{
    public function upgradePackage(User $user, Package $package, ?Order $sourceOrder = null): void
    {
        // TODO: Validate package upgrade path and payment/source order.
        // TODO: Update user's current package and trigger PV/bonus flows.
    }

    public function canUpgrade(User $user, Package $package): bool
    {
        // TODO: Compare current package rank/sort_order with requested package.
    }
}
