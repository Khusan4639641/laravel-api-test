<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'user_id',
    'order_number',
    'status',
    'payment_status',
    'payment_strategy',
    'payment_provider',
    'payment_external_id',
    'payment_transaction_id',
    'paid_at',
    'payment_meta',
    'subtotal_amount',
    'discount_amount',
    'total_amount',
    'card_amount',
    'deposit_amount',
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
    public const PAYMENT_STRATEGY_CARD_100 = 'card_100';

    public const PAYMENT_STRATEGY_CARD_50_DEPOSIT_50 = 'card_50_deposit_50';

    public const PAYMENT_STRATEGY_DEPOSIT_100 = 'deposit_100';

    public const PAYMENT_PROVIDER_DEPOSIT = 'deposit';

    public const SOURCE_DEPOSIT_PURCHASE = 'deposit_purchase';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'card_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
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

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function scopeWithoutDepositPurchases(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('payment_provider')
                    ->orWhere('payment_provider', '!=', self::PAYMENT_PROVIDER_DEPOSIT);
            })
            ->whereDoesntHave('items.product', fn (Builder $query) => $query->where('is_deposit_product', true));
    }

    public function isDepositPurchase(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        if (
            $this->payment_strategy === self::PAYMENT_STRATEGY_DEPOSIT_100
            || $this->payment_provider === self::PAYMENT_PROVIDER_DEPOSIT
            || ($metadata['source'] ?? null) === self::SOURCE_DEPOSIT_PURCHASE
            || ($metadata['payment_wallet'] ?? null) === 'deposit'
        ) {
            return true;
        }

        return $this->hasDepositProducts();
    }

    public static function paymentStrategyLabel(?string $strategy): string
    {
        return match ($strategy) {
            self::PAYMENT_STRATEGY_CARD_50_DEPOSIT_50 => '50% карта + 50% депозит',
            self::PAYMENT_STRATEGY_DEPOSIT_100 => '100% депозит',
            default => '100% карта',
        };
    }

    public function hasDepositProducts(): bool
    {
        $this->loadMissing('items.product');

        return $this->items->contains(function (OrderItem $item): bool {
            $snapshot = is_array($item->item_snapshot) ? $item->item_snapshot : [];

            return (bool) ($item->product?->is_deposit_product ?? false)
                || (bool) ($snapshot['is_deposit_product'] ?? false);
        });
    }
}
