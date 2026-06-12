<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'sender_user_id',
    'recipient_user_id',
    'amount',
    'currency',
    'status',
    'sender_transaction_id',
    'recipient_transaction_id',
    'comment',
    'idempotency_key',
    'meta',
])]
class PartnerTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function senderTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'sender_transaction_id');
    }

    public function recipientTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'recipient_transaction_id');
    }
}
