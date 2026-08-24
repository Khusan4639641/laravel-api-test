<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'required_status',
    'required_count',
    'reward_type',
    'amount',
    'currency',
    'reward_text',
    'is_cash_bonus',
    'is_active',
    'sort_order',
])]
class X2BonusDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'required_count' => 'integer',
            'amount' => 'decimal:2',
            'is_cash_bonus' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userBonuses(): HasMany
    {
        return $this->hasMany(UserX2Bonus::class);
    }
}
