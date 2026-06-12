<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sender_id' => $this->sender_user_id,
            'recipient_id' => $this->recipient_user_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'sender_transaction_id' => $this->sender_transaction_id,
            'recipient_transaction_id' => $this->recipient_transaction_id,
            'comment' => $this->comment,
            'meta' => $this->meta,
            'sender' => new TransferPartnerResource($this->whenLoaded('sender')),
            'recipient' => new TransferPartnerResource($this->whenLoaded('recipient')),
            'sender_transaction' => new WalletTransactionResource($this->whenLoaded('senderTransaction')),
            'recipient_transaction' => new WalletTransactionResource($this->whenLoaded('recipientTransaction')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
