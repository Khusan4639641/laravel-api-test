<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

class PvService
{
    public function accruePvUpTree(User $sourceUser, float|string $pv, ?Order $sourceOrder = null): void
    {
        // TODO: Walk from source user's binary node to root and add PV to left/right branches.
        // TODO: Trigger binary bonus recalculation for affected ancestors.
    }

    public function addUserPv(User $user, float|string $pv): void
    {
        // TODO: Add personal/total PV to the user after package upgrade or product purchase.
    }
}
