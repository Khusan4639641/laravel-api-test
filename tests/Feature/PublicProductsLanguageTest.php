<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductsLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_products_return_localized_names_and_categories(): void
    {
        Product::query()->create([
            'name' => 'Сыворотка',
            'sku' => 'PRODUCT-LANG-001',
            'description' => 'Русское описание',
            'price' => 1000,
            'pv' => 10,
            'status' => 'active',
            'name_translations' => ['ru' => 'Сыворотка', 'en' => 'Serum'],
            'description_translations' => ['ru' => 'Русское описание', 'en' => 'English description'],
            'category_translations' => ['ru' => 'Красота', 'en' => 'Beauty'],
            'short_description_translations' => ['ru' => 'Кратко', 'en' => 'Short'],
        ]);

        $this->getJson('/api/public/products', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Serum')
            ->assertJsonPath('products.0.category', 'Beauty')
            ->assertJsonPath('products.0.description', 'English description')
            ->assertJsonPath('products.0.shortDescription', 'Short');
    }

    public function test_public_catalog_shows_active_products_only(): void
    {
        $active = Product::query()->create([
            'name' => 'Active public product',
            'sku' => 'PUBLIC-ACTIVE-001',
            'description' => 'Active',
            'price' => 1000,
            'pv' => 10,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        Product::query()->create([
            'name' => 'Inactive public product',
            'sku' => 'PUBLIC-INACTIVE-001',
            'description' => 'Inactive',
            'price' => 1000,
            'pv' => 10,
            'stock_quantity' => 5,
            'status' => 'inactive',
        ]);

        $products = $this->getJson('/api/public/products')
            ->assertOk()
            ->json('products');

        $this->assertSame([$active->id], array_column($products, 'id'));
    }
}
