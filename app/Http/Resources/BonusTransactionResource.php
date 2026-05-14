<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonusTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'wallet_transaction_id' => $this->wallet_transaction_id,
            'source_user_id' => $this->source_user_id,
            'source_order_id' => $this->source_order_id,
            'bonus_type' => $this->bonus_type,
            'amount' => $this->amount,
            'left_pv' => $this->left_pv,
            'right_pv' => $this->right_pv,
            'matched_pv' => $this->matched_pv,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'calculated_at' => $this->calculated_at?->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'source_user' => new UserResource($this->whenLoaded('sourceUser')),
            'source_order' => new OrderResource($this->whenLoaded('sourceOrder')),
            'wallet_transaction' => new WalletTransactionResource($this->whenLoaded('walletTransaction')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
