<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_returns_localized_public_data(): void
    {
        Product::query()->create([
            'name' => 'Русский продукт',
            'sku' => 'LANG-001',
            'description' => 'Описание',
            'price' => 1000,
            'pv' => 10,
            'status' => 'active',
            'name_translations' => [
                'ru' => 'Русский продукт',
                'kk' => 'Қазақша өнім',
                'kz' => 'KZ қазақша өнім',
                'kg' => 'Кыргызча өнүм',
                'en' => 'English product',
                'mn' => 'Монгол бүтээгдэхүүн',
            ],
        ]);

        $this->getJson('/api/public/products', ['Accept-Language' => 'ru'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Русский продукт');

        $this->getJson('/api/public/products', ['Accept-Language' => 'kk'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'KZ қазақша өнім');

        $this->getJson('/api/public/products', ['Accept-Language' => 'kz'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'KZ қазақша өнім');

        $this->getJson('/api/public/products', ['Accept-Language' => 'kg'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Кыргызча өнүм');

        $this->getJson('/api/public/products', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'English product');

        $this->getJson('/api/public/products', ['Accept-Language' => 'mn'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Монгол бүтээгдэхүүн');
    }

    public function test_legacy_kk_translation_is_used_for_kz_when_explicit_kz_is_missing(): void
    {
        Product::query()->create([
            'name' => 'Русский продукт',
            'sku' => 'LANG-LEGACY-KK',
            'description' => 'Описание',
            'price' => 1000,
            'pv' => 10,
            'status' => 'active',
            'name_translations' => [
                'ru' => 'Русский продукт',
                'kk' => 'Legacy қазақша өнім',
                'en' => 'English product',
            ],
        ]);

        $this->getJson('/api/public/products', ['Accept-Language' => 'kz'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Legacy қазақша өнім');
    }

    public function test_invalid_language_falls_back_to_russian(): void
    {
        Product::query()->create([
            'name' => 'Русский продукт',
            'sku' => 'LANG-002',
            'description' => 'Описание',
            'price' => 1000,
            'pv' => 10,
            'status' => 'active',
            'name_translations' => [
                'ru' => 'Русский продукт',
                'en' => 'English product',
            ],
        ]);

        $this->getJson('/api/public/products', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Русский продукт');
    }
}
