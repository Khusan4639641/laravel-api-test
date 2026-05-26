<?php

namespace App\Services;

use App\Models\User;

class StatusService
{
    /**
     * @var array<int, array{id: string, name: string, pv: int, income_potential: int, reward: string, is_cash_bonus: bool, reward_type: string, amount: string}>
     */
    public const STATUS_DEFINITIONS = [
        ['id' => 'manager', 'name' => 'Менеджер', 'pv' => 1000, 'income_potential' => 500000, 'reward' => '2 продукта в подарок', 'is_cash_bonus' => false, 'reward_type' => 'gift', 'amount' => '0.00'],
        ['id' => 'leader', 'name' => 'Лидер', 'pv' => 2500, 'income_potential' => 1250000, 'reward' => 'Набор косметики', 'is_cash_bonus' => false, 'reward_type' => 'gift', 'amount' => '0.00'],
        ['id' => 'director', 'name' => 'Директор', 'pv' => 5000, 'income_potential' => 2500000, 'reward' => '250 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '250000.00'],
        ['id' => 'bronze_director', 'name' => 'Бронзовый директор', 'pv' => 10000, 'income_potential' => 5000000, 'reward' => 'Путевка в санаторий + 100 000 ₸ или компенсация 400 000 ₸', 'is_cash_bonus' => true, 'reward_type' => 'trip_or_cash', 'amount' => '400000.00'],
        ['id' => 'silver_director', 'name' => 'Серебряный директор', 'pv' => 25000, 'income_potential' => 12500000, 'reward' => 'Путевка в теплые страны + 250 000 ₸ или компенсация 750 000 ₸', 'is_cash_bonus' => true, 'reward_type' => 'trip_or_cash', 'amount' => '750000.00'],
        ['id' => 'gold_director', 'name' => 'Золотой директор', 'pv' => 50000, 'income_potential' => 25000000, 'reward' => '5 000 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '5000000.00'],
        ['id' => 'platinum_director', 'name' => 'Платиновый директор', 'pv' => 100000, 'income_potential' => 50000000, 'reward' => '6 000 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '6000000.00'],
        ['id' => 'emerald_director', 'name' => 'Изумрудный директор', 'pv' => 250000, 'income_potential' => 125000000, 'reward' => '10 000 000 ₸ auto bonus', 'is_cash_bonus' => true, 'reward_type' => 'auto', 'amount' => '10000000.00'],
        ['id' => 'diamond_director', 'name' => 'Бриллиантовый директор', 'pv' => 500000, 'income_potential' => 250000000, 'reward' => '20 000 000 ₸ apartment bonus', 'is_cash_bonus' => true, 'reward_type' => 'apartment', 'amount' => '20000000.00'],
    ];

    /**
     * @return array<int, array{id: string, name: string, pv: int, income_potential: int, reward: string, is_cash_bonus: bool}>
     */
    public function publicStatuses(): array
    {
        return self::STATUS_DEFINITIONS;
    }

    public function statusForPv(float|string $totalPv): string
    {
        $totalPv = (string) $totalPv;

        foreach (array_reverse(self::STATUS_DEFINITIONS) as $definition) {
            if (bccomp($totalPv, (string) $definition['pv'], 2) >= 0) {
                return $definition['id'];
            }
        }

        return 'user';
    }

    public function rankForStatus(string $status): int
    {
        foreach (self::STATUS_DEFINITIONS as $index => $definition) {
            if ($definition['id'] === $status) {
                return $index + 1;
            }
        }

        return 0;
    }

    public function recalculate(User $user): User
    {
        $status = $this->statusForPv($user->total_pv);

        if ($user->status !== $status) {
            $user->forceFill([
                'status' => $status,
            ])->save();
        }

        $user = $user->refresh();

        app(StatusBonusService::class)->awardEligible($user);

        if ($user->sponsor_id) {
            $sponsor = $user->sponsor()->first();

            if ($sponsor) {
                app(X2BonusService::class)->awardEligible($sponsor);
            }
        }

        return $user;
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
