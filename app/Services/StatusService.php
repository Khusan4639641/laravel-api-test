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

    /**
     * @return array<int, array{id: string, name: string, pv: int, income_potential: int, reward: string, is_cash_bonus: bool}>
     */
    public function publicStatuses(): array
    {
        return [
            ['id' => 'manager', 'name' => 'Менеджер', 'pv' => 1000, 'income_potential' => 500000, 'reward' => '2 продукта в подарок', 'is_cash_bonus' => false],
            ['id' => 'leader', 'name' => 'Лидер', 'pv' => 2500, 'income_potential' => 1250000, 'reward' => 'Набор косметики', 'is_cash_bonus' => false],
            ['id' => 'director', 'name' => 'Директор', 'pv' => 5000, 'income_potential' => 2500000, 'reward' => '250 000 тг', 'is_cash_bonus' => true],
            ['id' => 'bronze_director', 'name' => 'Бронзовый директор', 'pv' => 10000, 'income_potential' => 5000000, 'reward' => 'Путевка в санаторий или 400 000 тг', 'is_cash_bonus' => true],
            ['id' => 'silver_director', 'name' => 'Серебряный директор', 'pv' => 25000, 'income_potential' => 12500000, 'reward' => 'Путевка в теплые страны или 750 000 тг', 'is_cash_bonus' => true],
            ['id' => 'gold_director', 'name' => 'Золотой директор', 'pv' => 50000, 'income_potential' => 25000000, 'reward' => '5 000 000 тг', 'is_cash_bonus' => true],
            ['id' => 'platinum_director', 'name' => 'Платиновый директор', 'pv' => 100000, 'income_potential' => 50000000, 'reward' => '6 000 000 тг', 'is_cash_bonus' => true],
            ['id' => 'emerald_director', 'name' => 'Изумрудный директор', 'pv' => 250000, 'income_potential' => 125000000, 'reward' => '10 000 000 тг (автобонус)', 'is_cash_bonus' => true],
            ['id' => 'diamond_director', 'name' => 'Бриллиантовый директор', 'pv' => 500000, 'income_potential' => 250000000, 'reward' => '20 000 000 тг (жилищный бонус)', 'is_cash_bonus' => true],
        ];
    }

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
