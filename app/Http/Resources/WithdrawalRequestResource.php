<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'wallet_id' => $this->wallet_id,
            'amount' => $this->amount,
            'fee_amount' => $this->fee_amount,
            'net_amount' => $this->net_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_details' => $this->payment_details,
            'admin_comment' => $this->admin_comment,
            'processed_at' => $this->processed_at?->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
