<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = is_array($this->item_snapshot) ? $this->item_snapshot : [];
        $productMetadata = $this->resource->relationLoaded('product') ? ($this->product?->metadata ?? []) : [];
        $imageUrl = $this->imageUrl(
            $snapshot['image_url'] ?? null,
            $snapshot['image_path'] ?? null,
            $this->resource->relationLoaded('product') ? $this->product?->image_path : null,
            is_array($productMetadata) ? ($productMetadata['image_url'] ?? $productMetadata['image'] ?? null) : null,
        );

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'image_url' => $imageUrl,
            'imageUrl' => $imageUrl,
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

    private function imageUrl(?string ...$candidates): ?string
    {
        $path = collect($candidates)->first(fn (?string $candidate): bool => is_string($candidate) && trim($candidate) !== '');

        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset(Storage::url($path));
    }
}
