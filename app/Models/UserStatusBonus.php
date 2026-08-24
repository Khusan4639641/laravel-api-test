<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'status_bonus_definition_id',
    'bonus_transaction_id',
    'status_code',
    'amount',
    'currency',
    'reward_text',
    'awarded_at',
    'metadata',
])]
class UserStatusBonus extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'awarded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(StatusBonusDefinition::class, 'status_bonus_definition_id');
    }

    public function bonusTransaction(): BelongsTo
    {
        return $this->belongsTo(BonusTransaction::class);
    }
}
