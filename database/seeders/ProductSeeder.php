<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Safi Face Serum',
                'sku' => 'SAFI-FACE-SERUM',
                'description' => 'Интенсивная сыворотка для лица с пептидами, алоэ и пантенолом для ежедневного ухода.',
                'price' => 21000,
                'pv' => 35,
                'stock_quantity' => 75,
                'metadata' => [
                    'category' => 'Красота',
                    'short_description' => 'Омолаживающая сыворотка с пептидами для ровного тона кожи.',
                    'benefits' => ['Антивозрастной уход', 'Выравнивание тона', 'Глубокое увлажнение'],
                    'composition' => ['Пептидный комплекс', 'Экстракт алоэ', 'Пантенол', 'Гиалуроновая кислота'],
                    'usage' => 'Наносить 2-3 капли на очищенную кожу лица утром и вечером.',
                    'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&q=80&w=800',
                ],
            ],
            [
                'name' => 'Safi Collagen',
                'sku' => 'SAFI-COLLAGEN',
                'description' => 'Биодоступный морской коллаген с витамином C для поддержки кожи, волос, ногтей и суставов.',
                'price' => 18000,
                'pv' => 30,
                'stock_quantity' => 90,
                'metadata' => [
                    'category' => 'Красота',
                    'short_description' => 'Морской коллаген с витамином C для упругости кожи.',
                    'benefits' => ['Поддержка упругости кожи', 'Укрепление волос и ногтей', 'Забота о суставах'],
                    'composition' => ['Пептиды морского коллагена', 'Витамин C', 'Гиалуроновая кислота'],
                    'usage' => '1 мерную ложку порошка развести в стакане воды. Принимать утром.',
                    'image' => 'https://images.unsplash.com/photo-1627467959081-97831c0d645f?q=80&w=1200&auto=format&fit=crop',
                ],
            ],
            [
                'name' => 'Safi Omega 3',
                'sku' => 'SAFI-OMEGA-3',
                'description' => 'Высокоочищенная Омега 3 для поддержки сердца, сосудов, мозга и общего тонуса.',
                'price' => 12500,
                'pv' => 20,
                'stock_quantity' => 140,
                'metadata' => [
                    'category' => 'Здоровье',
                    'short_description' => 'Омега 3 для поддержки сердца, сосудов и концентрации.',
                    'benefits' => ['Поддержка сердечно-сосудистой системы', 'Концентрация и память', 'Здоровье суставов'],
                    'composition' => ['Рыбий жир', 'ЭПК', 'ДГК', 'Витамин E'],
                    'usage' => 'По 1 капсуле 2 раза в день во время еды.',
                    'image' => 'https://images.unsplash.com/photo-1584362917165-526a968579e8?auto=format&fit=crop&q=80&w=800',
                ],
            ],
            [
                'name' => 'Safi Detox Tea',
                'sku' => 'SAFI-DETOX-TEA',
                'description' => 'Натуральный травяной чай для мягкого очищения организма и поддержки пищеварения.',
                'price' => 4500,
                'pv' => 8,
                'stock_quantity' => 180,
                'metadata' => [
                    'category' => 'Здоровье',
                    'short_description' => 'Травяной чай для мягкого очищения и легкости.',
                    'benefits' => ['Мягкое очищение', 'Поддержка пищеварения', 'Легкость в теле'],
                    'composition' => ['Ромашка', 'Мята перечная', 'Расторопша', 'Корень солодки'],
                    'usage' => '1 фильтр-пакет залить кипятком, настоять 10-15 минут.',
                    'image' => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?auto=format&fit=crop&q=80&w=800',
                ],
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
