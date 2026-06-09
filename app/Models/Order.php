<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'order_number',
    'status',
    'payment_status',
    'payment_provider',
    'payment_external_id',
    'payment_transaction_id',
    'paid_at',
    'payment_meta',
    'subtotal_amount',
    'discount_amount',
    'total_amount',
    'total_pv',
    'shipping_address',
    'recipient_name',
    'phone',
    'city',
    'delivery_address',
    'comment',
    'metadata',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_pv' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_meta' => 'array',
            'shipping_address' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
