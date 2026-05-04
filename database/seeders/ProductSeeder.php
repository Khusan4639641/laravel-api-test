<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Omega Balance',
                'sku' => 'BAD-OMEGA-001',
                'description' => 'Omega supplement for daily wellness.',
                'price' => 120000,
                'pv' => 120000,
                'stock_quantity' => 100,
                'metadata' => ['category' => 'supplements'],
            ],
            [
                'name' => 'Vitamin Complex',
                'sku' => 'BAD-VIT-002',
                'description' => 'Daily vitamin and mineral complex.',
                'price' => 90000,
                'pv' => 90000,
                'stock_quantity' => 150,
                'metadata' => ['category' => 'supplements'],
            ],
            [
                'name' => 'Hydra Face Cream',
                'sku' => 'COS-HYDRA-001',
                'description' => 'Moisturizing face cream.',
                'price' => 75000,
                'pv' => 75000,
                'stock_quantity' => 80,
                'metadata' => ['category' => 'cosmetics'],
            ],
            [
                'name' => 'Clean Skin Serum',
                'sku' => 'COS-SERUM-002',
                'description' => 'Cosmetic serum for daily care.',
                'price' => 110000,
                'pv' => 110000,
                'stock_quantity' => 60,
                'metadata' => ['category' => 'cosmetics'],
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    ...$product,
                    'status' => 'active',
                ]
            );
        }
    }
}
