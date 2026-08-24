<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StatusBonusService;
use App\Services\X2BonusService;
use Illuminate\Database\Seeder;

class BonusAwardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $statusBonusService = app(StatusBonusService::class);
        $x2BonusService = app(X2BonusService::class);

        User::query()
            ->whereIn('role', [User::ROLE_USER, User::ROLE_SUPER_ADMIN])
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($statusBonusService): void {
                $statusBonusService->awardEligible($user);
            });

        User::query()
            ->whereIn('role', [User::ROLE_USER, User::ROLE_SUPER_ADMIN])
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($x2BonusService): void {
                $x2BonusService->awardEligible($user);
            });
    }
}
