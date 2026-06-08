<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;

class ReferralBonusBaseResolver
{
    private const PV_MONEY_RATE = '500';

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveForPackageActivation(Package $package, array $context = []): string
    {
        return match ($package->code) {
            'START', 'VIP' => $this->positiveOrZero(bcmul($package->activityPv(), self::PV_MONEY_RATE, 2)),
            default => '0.00',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveForPackageUpgrade(Package $fromPackage, Package $toPackage, array $context = []): string
    {
        if ($toPackage->code === 'ELITE') {
            return '0.00';
        }

        $paymentAmount = isset($context['payment_amount'])
            ? (string) $context['payment_amount']
            : bcsub((string) $toPackage->price, (string) $fromPackage->price, 2);

        return $this->positiveOrZero($paymentAmount);
    }

    public function resolveForProductOrder(Order $order): string
    {
        return $this->positiveOrZero((string) $order->total_amount);
    }

    private function positiveOrZero(string $amount): string
    {
        return bccomp($amount, '0', 2) > 0 ? $amount : '0.00';
    }
}
