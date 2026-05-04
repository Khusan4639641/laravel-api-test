<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;

class ReferralService
{
    public function assignSponsor(User $user, User $sponsor): void
    {
        // TODO: Validate sponsor status and assign sponsor_id to the user.
    }

    public function accrueReferralBonus(User $sponsor, User $referral, float|string $amount): BonusTransaction
    {
        // TODO: Calculate referral bonus by active package settings.
        // TODO: Create bonus transaction and wallet transaction for the sponsor.
    }
}
