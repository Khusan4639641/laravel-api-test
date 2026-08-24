<?php

namespace App\Services;

use App\Models\Package;

class PackageUpgradeResult
{
    /**
     * @param  array<int, string>  $upgradedPackages
     */
    public function __construct(
        public readonly ?int $userId,
        public readonly string $paidOrdersTotal,
        public readonly ?string $targetPackageCode,
        public readonly ?Package $finalPackage,
        public readonly array $upgradedPackages = [],
    ) {
    }

    public function upgraded(): bool
    {
        return $this->upgradedPackages !== [];
    }
}
