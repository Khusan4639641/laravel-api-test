<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'package_id' => $this->package_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'unit_pv' => $this->unit_pv,
            'total_pv' => $this->total_pv,
            'item_snapshot' => $this->item_snapshot,
            'product' => new ProductResource($this->whenLoaded('product')),
            'package' => new PackageResource($this->whenLoaded('package')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
