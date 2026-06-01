<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];
        $image = $metadata['image'] ?? $metadata['image_url'] ?? null;
        $name = LocalizedValue::get($this->name_translations, $this->name);
        $description = LocalizedValue::get($this->description_translations, $this->description);
        $category = LocalizedValue::get($this->category_translations, $metadata['category'] ?? null);
        $shortDescription = LocalizedValue::get(
            $this->short_description_translations,
            $metadata['short_description'] ?? $metadata['shortDescription'] ?? $description
        );
        $benefits = LocalizedValue::get($this->benefits_translations, $metadata['benefits'] ?? []);
        $composition = LocalizedValue::get($this->composition_translations, $metadata['composition'] ?? []);
        $usage = LocalizedValue::get($this->usage_translations, $metadata['usage'] ?? null);

        return [
            'id' => $this->id,
            'name' => $name,
            'sku' => $this->sku,
            'category' => $category,
            'short_description' => $shortDescription,
            'shortDescription' => $shortDescription,
            'description' => $description,
            'benefits' => $benefits,
            'composition' => $composition,
            'usage' => $usage,
            'price' => $this->price,
            'pv' => $this->pv,
            'stock_quantity' => $this->stock_quantity,
            'stock' => $this->stock_quantity,
            'status' => $this->status,
            'image_url' => $image,
            'imageUrl' => $image,
            'image' => $image,
            'metadata' => $metadata,
            'translations' => [
                'name' => $this->name_translations,
                'description' => $this->description_translations,
                'category' => $this->category_translations,
                'short_description' => $this->short_description_translations,
                'benefits' => $this->benefits_translations,
                'composition' => $this->composition_translations,
                'usage' => $this->usage_translations,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
