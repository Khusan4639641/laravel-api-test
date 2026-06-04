<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'sku',
    'description',
    'price',
    'pv',
    'stock_quantity',
    'reserved_quantity',
    'status',
    'is_deposit_product',
    'image_path',
    'metadata',
    'name_translations',
    'description_translations',
    'category_translations',
    'short_description_translations',
    'benefits_translations',
    'composition_translations',
    'usage_translations',
])]
class Product extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pv' => 'decimal:2',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'is_deposit_product' => 'boolean',
            'metadata' => 'array',
            'name_translations' => 'array',
            'description_translations' => 'array',
            'category_translations' => 'array',
            'short_description_translations' => 'array',
            'benefits_translations' => 'array',
            'composition_translations' => 'array',
            'usage_translations' => 'array',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
