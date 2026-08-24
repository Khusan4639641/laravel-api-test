<?php

namespace Database\Seeders;

use App\Models\X2BonusDefinition;
use Illuminate\Database\Seeder;

class X2BonusDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'code' => 'five_directors',
                'required_status' => 'director',
                'required_count' => 5,
                'reward_type' => 'trip',
                'amount' => 0,
                'reward_text' => '5 Directors => warm country trip',
                'is_cash_bonus' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'five_gold_directors',
                'required_status' => 'gold_director',
                'required_count' => 5,
                'reward_type' => 'cash',
                'amount' => 5000000,
                'reward_text' => '5 Gold Directors => 5 000 000 ₸ cash bonus',
                'is_cash_bonus' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'five_diamond_directors',
                'required_status' => 'diamond_director',
                'required_count' => 5,
                'reward_type' => 'cash',
                'amount' => 20000000,
                'reward_text' => '5 Diamond Directors => 20 000 000 ₸ cash bonus',
                'is_cash_bonus' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($definitions as $definition) {
            X2BonusDefinition::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    ...$definition,
                    'currency' => 'KZT',
                    'is_active' => true,
                ]
            );
        }
    }
}
