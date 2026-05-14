<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];
        $image = $metadata['image'] ?? $metadata['image_url'] ?? null;
        $shortDescription = $metadata['short_description'] ?? $metadata['shortDescription'] ?? $this->description;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $metadata['category'] ?? null,
            'short_description' => $shortDescription,
            'shortDescription' => $shortDescription,
            'description' => $this->description,
            'benefits' => $metadata['benefits'] ?? [],
            'composition' => $metadata['composition'] ?? [],
            'usage' => $metadata['usage'] ?? null,
            'price' => $this->price,
            'pv' => $this->pv,
            'stock_quantity' => $this->stock_quantity,
            'stock' => $this->stock_quantity,
            'status' => $this->status,
            'image_url' => $image,
            'imageUrl' => $image,
            'image' => $image,
            'metadata' => $metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
