<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_id',
    'upline_id',
    'source_order_id',
    'source',
    'branch',
    'pv',
    'is_bonusable',
    'voided_at',
    'voided_by',
    'metadata',
])]
class PvTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pv' => 'decimal:2',
            'is_bonusable' => 'boolean',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upline_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }
}
