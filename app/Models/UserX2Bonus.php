<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'x2_bonus_definition_id',
    'bonus_transaction_id',
    'code',
    'qualified_count',
    'amount',
    'currency',
    'reward_text',
    'awarded_at',
    'metadata',
])]
class UserX2Bonus extends Model
{
    protected function casts(): array
    {
        return [
            'qualified_count' => 'integer',
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
        return $this->belongsTo(X2BonusDefinition::class, 'x2_bonus_definition_id');
    }

    public function bonusTransaction(): BelongsTo
    {
        return $this->belongsTo(BonusTransaction::class);
    }
}
