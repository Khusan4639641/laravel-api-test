<?php

namespace App\Services;

use App\Models\User;
use App\Support\LocalizedValue;

class StatusService
{
    /**
     * @var array<int, array{id: string, name: string, pv: int, income_potential: int, reward: string, is_cash_bonus: bool, reward_type: string, amount: string, cash_amount: string, compensation_amount: string, compensation_available: bool}>
     */
    public const STATUS_DEFINITIONS = [
        ['id' => 'manager', 'name' => 'Менеджер', 'pv' => 1000, 'income_potential' => 500000, 'reward' => '2 продукта в подарок', 'is_cash_bonus' => false, 'reward_type' => 'gift', 'amount' => '0.00', 'cash_amount' => '0.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'leader', 'name' => 'Лидер', 'pv' => 2500, 'income_potential' => 1250000, 'reward' => 'Набор косметики', 'is_cash_bonus' => false, 'reward_type' => 'gift', 'amount' => '0.00', 'cash_amount' => '0.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'director', 'name' => 'Директор', 'pv' => 5000, 'income_potential' => 2500000, 'reward' => '250 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '250000.00', 'cash_amount' => '250000.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'bronze_director', 'name' => 'Бронзовый директор', 'pv' => 10000, 'income_potential' => 5000000, 'reward' => 'Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸', 'is_cash_bonus' => true, 'reward_type' => 'trip', 'amount' => '100000.00', 'cash_amount' => '100000.00', 'compensation_amount' => '400000.00', 'compensation_available' => true],
        ['id' => 'silver_director', 'name' => 'Серебряный директор', 'pv' => 25000, 'income_potential' => 12500000, 'reward' => 'Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸', 'is_cash_bonus' => true, 'reward_type' => 'foreign_trip', 'amount' => '250000.00', 'cash_amount' => '250000.00', 'compensation_amount' => '750000.00', 'compensation_available' => true],
        ['id' => 'gold_director', 'name' => 'Золотой директор', 'pv' => 50000, 'income_potential' => 25000000, 'reward' => '5 000 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '5000000.00', 'cash_amount' => '5000000.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'platinum_director', 'name' => 'Платиновый директор', 'pv' => 100000, 'income_potential' => 50000000, 'reward' => '6 000 000 ₸ cash bonus', 'is_cash_bonus' => true, 'reward_type' => 'cash', 'amount' => '6000000.00', 'cash_amount' => '6000000.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'emerald_director', 'name' => 'Изумрудный директор', 'pv' => 250000, 'income_potential' => 125000000, 'reward' => '10 000 000 ₸ auto bonus', 'is_cash_bonus' => true, 'reward_type' => 'auto', 'amount' => '10000000.00', 'cash_amount' => '10000000.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
        ['id' => 'diamond_director', 'name' => 'Бриллиантовый директор', 'pv' => 500000, 'income_potential' => 250000000, 'reward' => '20 000 000 ₸ apartment bonus', 'is_cash_bonus' => true, 'reward_type' => 'apartment', 'amount' => '20000000.00', 'cash_amount' => '20000000.00', 'compensation_amount' => '0.00', 'compensation_available' => false],
    ];

    private const STATUS_TRANSLATIONS = [
        'manager' => [
            'name' => ['ru' => 'Менеджер', 'kk' => 'Менеджер', 'en' => 'Manager', 'mn' => 'Менежер'],
            'reward' => ['ru' => '2 продукта в подарок', 'kk' => 'Сыйлыққа 2 өнім', 'en' => '2 products as a gift', 'mn' => 'Бэлгэнд 2 бүтээгдэхүүн'],
        ],
        'leader' => [
            'name' => ['ru' => 'Лидер', 'kk' => 'Лидер', 'en' => 'Leader', 'mn' => 'Лидер'],
            'reward' => ['ru' => 'Набор косметики', 'kk' => 'Косметика жиынтығы', 'en' => 'Cosmetics set', 'mn' => 'Гоо сайхны багц'],
        ],
        'director' => [
            'name' => ['ru' => 'Директор', 'kk' => 'Директор', 'en' => 'Director', 'mn' => 'Директор'],
            'reward' => ['ru' => '250 000 ₸ cash bonus', 'kk' => '250 000 ₸ ақшалай бонус', 'en' => '250,000 ₸ cash bonus', 'mn' => '250 000 ₸ мөнгөн бонус'],
        ],
        'bronze_director' => [
            'name' => ['ru' => 'Бронзовый директор', 'kk' => 'Қола директор', 'en' => 'Bronze Director', 'mn' => 'Хүрэл директор'],
            'reward' => ['ru' => 'Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸', 'kk' => 'Санаторий жолдамасы + 100 000 ₸, бас тартса 400 000 ₸ өтемақы', 'en' => 'Sanatorium trip + 100,000 ₸, 400,000 ₸ compensation if refused', 'mn' => 'Сувиллын эрх + 100 000 ₸, татгалзвал 400 000 ₸ нөхөн олговор'],
        ],
        'silver_director' => [
            'name' => ['ru' => 'Серебряный директор', 'kk' => 'Күміс директор', 'en' => 'Silver Director', 'mn' => 'Мөнгөн директор'],
            'reward' => ['ru' => 'Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸', 'kk' => 'Шетелдік сапар + 250 000 ₸, бас тартса 750 000 ₸ өтемақы', 'en' => 'International trip + 250,000 ₸, 750,000 ₸ compensation if refused', 'mn' => 'Гадаад аялал + 250 000 ₸, татгалзвал 750 000 ₸ нөхөн олговор'],
        ],
        'gold_director' => [
            'name' => ['ru' => 'Золотой директор', 'kk' => 'Алтын директор', 'en' => 'Gold Director', 'mn' => 'Алтан директор'],
            'reward' => ['ru' => '5 000 000 ₸ cash bonus', 'kk' => '5 000 000 ₸ ақшалай бонус', 'en' => '5,000,000 ₸ cash bonus', 'mn' => '5 000 000 ₸ мөнгөн бонус'],
        ],
        'platinum_director' => [
            'name' => ['ru' => 'Платиновый директор', 'kk' => 'Платина директор', 'en' => 'Platinum Director', 'mn' => 'Платинум директор'],
            'reward' => ['ru' => '6 000 000 ₸ cash bonus', 'kk' => '6 000 000 ₸ ақшалай бонус', 'en' => '6,000,000 ₸ cash bonus', 'mn' => '6 000 000 ₸ мөнгөн бонус'],
        ],
        'emerald_director' => [
            'name' => ['ru' => 'Изумрудный директор', 'kk' => 'Изумруд директор', 'en' => 'Emerald Director', 'mn' => 'Маргад директор'],
            'reward' => ['ru' => '10 000 000 ₸ auto bonus', 'kk' => '10 000 000 ₸ авто бонус', 'en' => '10,000,000 ₸ auto bonus', 'mn' => '10 000 000 ₸ авто бонус'],
        ],
        'diamond_director' => [
            'name' => ['ru' => 'Бриллиантовый директор', 'kk' => 'Бриллиант директор', 'en' => 'Diamond Director', 'mn' => 'Очир директор'],
            'reward' => ['ru' => '20 000 000 ₸ apartment bonus', 'kk' => '20 000 000 ₸ пәтер бонусы', 'en' => '20,000,000 ₸ apartment bonus', 'mn' => '20 000 000 ₸ байрны бонус'],
        ],
    ];

    /**
     * @return array<int, array{id: string, name: string, pv: int, income_potential: int, reward: string, is_cash_bonus: bool}>
     */
    public function publicStatuses(): array
    {
        return collect(self::STATUS_DEFINITIONS)
            ->map(function (array $definition): array {
                $translations = self::STATUS_TRANSLATIONS[$definition['id']] ?? [];

                $definition['name'] = LocalizedValue::get($translations['name'] ?? null, $definition['name']);
                $definition['reward'] = LocalizedValue::get($translations['reward'] ?? null, $definition['reward']);
                $definition['translations'] = $translations;

                return $definition;
            })
            ->values()
            ->all();
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
