<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'binary_bonus_run_id',
    'user_id',
    'bonus_transaction_id',
    'left_pv',
    'right_pv',
    'weak_leg_pv',
    'used_left_pv',
    'used_right_pv',
    'carry_left_pv',
    'carry_right_pv',
    'money_base_amount',
    'binary_percent',
    'bonus_amount',
    'main_amount',
    'deposit_amount',
    'metadata',
])]
class BinaryBonusCalculation extends Model
{
    protected function casts(): array
    {
        return [
            'left_pv' => 'decimal:2',
            'right_pv' => 'decimal:2',
            'weak_leg_pv' => 'decimal:2',
            'used_left_pv' => 'decimal:2',
            'used_right_pv' => 'decimal:2',
            'carry_left_pv' => 'decimal:2',
            'carry_right_pv' => 'decimal:2',
            'money_base_amount' => 'decimal:2',
            'binary_percent' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'main_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BinaryBonusRun::class, 'binary_bonus_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonusTransaction(): BelongsTo
    {
        return $this->belongsTo(BonusTransaction::class);
    }
}
