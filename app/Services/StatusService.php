<?php

namespace App\Services;

use App\Models\User;

class StatusService
{
    /**
     * @var array<string, string>
     */
    private const THRESHOLDS = [
        '500000' => 'diamond_director',
        '250000' => 'emerald_director',
        '100000' => 'platinum_director',
        '50000' => 'gold_director',
        '25000' => 'silver_director',
        '10000' => 'bronze_director',
        '5000' => 'director',
        '2500' => 'leader',
        '1000' => 'manager',
    ];

    public function statusForPv(float|string $totalPv): string
    {
        $totalPv = (string) $totalPv;

        foreach (self::THRESHOLDS as $threshold => $status) {
            if (bccomp($totalPv, $threshold, 2) >= 0) {
                return $status;
            }
        }

        return 'user';
    }

    public function recalculate(User $user): User
    {
        $status = $this->statusForPv($user->total_pv);

        if ($user->status !== $status) {
            $user->forceFill([
                'status' => $status,
            ])->save();
        }

        return $user->refresh();
    }

    public function recalculateAll(int $chunkSize = 500): int
    {
        $count = 0;

        User::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    $this->recalculate($user);
                    $count++;
                }
            });

        return $count;
    }
}
