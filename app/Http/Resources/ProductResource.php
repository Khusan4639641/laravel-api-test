<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];
        $imagePath = $this->image_path ?: ($metadata['image'] ?? $metadata['image_url'] ?? null);
        $image = $this->imageUrl($imagePath);
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
            'reserved_quantity' => $this->reserved_quantity ?? 0,
            'stock' => $this->stock_quantity,
            'in_stock' => (int) $this->stock_quantity > 0 && $this->status === 'active',
            'is_in_stock' => (int) $this->stock_quantity > 0 && $this->status === 'active',
            'status' => $this->status,
            'is_deposit_product' => (bool) $this->is_deposit_product,
            'isDepositProduct' => (bool) $this->is_deposit_product,
            'image_path' => $this->image_path,
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

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset(Storage::url($path));
    }
}
