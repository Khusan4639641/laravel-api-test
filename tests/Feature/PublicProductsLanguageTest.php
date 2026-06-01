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
}
