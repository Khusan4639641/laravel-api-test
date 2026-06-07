<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemsCount = $this->items_count ?? ($this->relationLoaded('items') ? $this->items->sum('quantity') : null);
        $shippingAddress = is_array($this->shipping_address) ? $this->shipping_address : [];
        $recipientName = $this->recipient_name ?: ($shippingAddress['recipient_name'] ?? $shippingAddress['recipient'] ?? null);
        $phone = $this->phone ?: ($shippingAddress['phone'] ?? null);
        $city = $this->city ?: ($shippingAddress['city'] ?? null);
        $deliveryAddress = $this->delivery_address ?: ($shippingAddress['delivery_address'] ?? $shippingAddress['address'] ?? null);
        $comment = $this->comment ?: ($shippingAddress['comment'] ?? null);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'items_count' => $itemsCount === null ? null : (int) $itemsCount,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'total_pv' => $this->total_pv,
            'shipping_address' => $this->shipping_address,
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'city' => $city,
            'delivery_address' => $deliveryAddress,
            'comment' => $comment,
            'delivery' => [
                'recipient_name' => $recipientName,
                'phone' => $phone,
                'city' => $city,
                'delivery_address' => $deliveryAddress,
                'comment' => $comment,
            ],
            'metadata' => $this->metadata,
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
