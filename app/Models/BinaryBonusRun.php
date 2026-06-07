<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'bonus_transaction_id',
    'status',
    'period_start',
    'period_end',
    'weak_leg_pv',
    'used_left_pv',
    'used_right_pv',
    'carry_left_pv',
    'carry_right_pv',
    'amount',
    'pending_amount',
    'metadata',
])]
class BinaryBonusRun extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'weak_leg_pv' => 'decimal:2',
            'used_left_pv' => 'decimal:2',
            'used_right_pv' => 'decimal:2',
            'carry_left_pv' => 'decimal:2',
            'carry_right_pv' => 'decimal:2',
            'amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonusTransaction(): BelongsTo
    {
        return $this->belongsTo(BonusTransaction::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(BinaryBonusCalculation::class);
    }
}
