<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'status_code',
    'status_name',
    'threshold_pv',
    'reward_type',
    'amount',
    'currency',
    'reward_text',
    'is_cash_bonus',
    'is_active',
    'sort_order',
])]
class StatusBonusDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'threshold_pv' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_cash_bonus' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userBonuses(): HasMany
    {
        return $this->hasMany(UserStatusBonus::class);
    }
}
