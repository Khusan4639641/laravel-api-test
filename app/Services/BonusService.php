<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;

class BonusService
{
    public function accrueReferralBonus(User $sponsor, User $referral, float|string $baseAmount): BonusTransaction
    {
        // TODO: Resolve sponsor package referral percent and create bonus payout.
    }

    public function calculateBinaryBonus(User $user): ?BonusTransaction
    {
        // TODO: Match left/right remaining PV and calculate binary bonus by package percent.
        // TODO: Persist matched PV and update user remaining PV counters.
    }
}
