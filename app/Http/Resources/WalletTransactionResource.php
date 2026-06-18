<?php

namespace App\Http\Resources;

use App\Support\SystemLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $paymentStrategy = $metadata['payment_strategy'] ?? null;
        $paymentStrategyLabel = $metadata['payment_strategy_label'] ?? null;

        return [
            'id' => $this->id,
            'transaction_type' => 'wallet_transaction',
            'wallet_id' => $this->wallet_id,
            'user_id' => $this->user_id,
            'partner_id' => $this->user_id,
            'type' => $this->type,
            'type_label' => SystemLabel::label('transaction_types', $this->type),
            'direction' => $this->direction,
            'direction_label' => SystemLabel::label('wallet_directions', $this->direction),
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'status' => $this->status,
            'status_label' => SystemLabel::transactionStatus($this->status),
            'affects_balance' => (bool) ($this->affects_balance ?? true),
            'affects_balance_label' => ($this->affects_balance ?? true)
                ? SystemLabel::label('transaction_effects', 'affects_balance')
                : SystemLabel::label('transaction_effects', 'does_not_affect_balance'),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'description' => $this->description,
            'comment' => $this->description,
            'payment_strategy' => $paymentStrategy,
            'payment_strategy_label' => $paymentStrategyLabel,
            'metadata' => $this->metadata,
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
