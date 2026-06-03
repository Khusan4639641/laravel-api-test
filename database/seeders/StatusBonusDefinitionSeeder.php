<?php

namespace Database\Seeders;

use App\Models\StatusBonusDefinition;
use App\Services\StatusService;
use Illuminate\Database\Seeder;

class StatusBonusDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StatusService::STATUS_DEFINITIONS as $index => $definition) {
            StatusBonusDefinition::query()->updateOrCreate(
                ['status_code' => $definition['id']],
                [
                    'status_name' => $definition['name'],
                    'threshold_pv' => $definition['pv'],
                    'reward_type' => $definition['reward_type'],
                    'amount' => $definition['amount'],
                    'cash_amount' => $definition['cash_amount'] ?? $definition['amount'],
                    'compensation_amount' => $definition['compensation_amount'] ?? '0.00',
                    'compensation_available' => $definition['compensation_available'] ?? false,
                    'currency' => 'KZT',
                    'reward_text' => $definition['reward'],
                    'is_cash_bonus' => $definition['is_cash_bonus'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
