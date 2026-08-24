<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'admin_id',
    'action',
    'old_amount',
    'new_amount',
    'old_payload',
    'new_payload',
    'reason',
])]
class TransactionAdminAudit extends Model
{
    public const ACTION_AMOUNT_UPDATED = 'amount_updated';

    public const ACTION_DELETED = 'deleted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'old_payload' => 'array',
            'new_payload' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
