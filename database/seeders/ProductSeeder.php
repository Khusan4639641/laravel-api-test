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
                'name' => 'Safi Life Omega-3 Plus',
                'sku' => 'BAD-OMEGA-001',
                'description' => 'Высокоочищенная Омега-3 для поддержки сердца, сосудов, суставов и концентрации.',
                'price' => 12500,
                'pv' => 20,
                'stock_quantity' => 140,
                'metadata' => [
                    'category' => 'Здоровье',
                    'short_description' => 'Чистая Омега-3 для поддержки сердца и сосудов.',
                    'benefits' => ['Снижение уровня холестерина', 'Улучшение памяти и концентрации', 'Поддержка суставов'],
                    'composition' => ['Рыбий жир (ЭПК/ДГК)', 'Витамин E', 'Желатиновая капсула'],
                    'usage' => 'По 1 капсуле 2 раза в день во время еды.',
                    'image' => 'https://images.unsplash.com/photo-1584362917165-526a968579e8?auto=format&fit=crop&q=80&w=400&h=400',
                ],
            ],
            [
                'name' => 'Safi Collagen Glow',
                'sku' => 'SAFI-COLLAGEN-GLOW',
                'description' => 'Биодоступный морской коллаген с гиалуроновой кислотой и витамином C.',
                'price' => 18000,
                'pv' => 30,
                'stock_quantity' => 90,
                'metadata' => [
                    'category' => 'Красота',
                    'short_description' => 'Морской коллаген с витамином C для упругости кожи.',
                    'benefits' => ['Глубокое увлажнение кожи', 'Укрепление волос и ногтей', 'Поддержка здоровья костей'],
                    'composition' => ['Пептиды морского коллагена', 'Витамин C', 'Гиалуроновая кислота'],
                    'usage' => '1 мерную ложку порошка развести в стакане воды. Принимать утром натощак.',
                    'image' => 'https://images.unsplash.com/photo-1627467959081-97831c0d645f?q=80&w=1383&auto=format&fit=crop',
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
                    'short_description' => 'Натуральный травяной чай для мягкого очищения организма.',
                    'benefits' => ['Вывод токсинов и шлаков', 'Улучшение метаболизма', 'Легкость в теле'],
                    'composition' => ['Лист сенны', 'Ромашка', 'Мята перечная', 'Корень солодки', 'Расторопша'],
                    'usage' => '1 фильтр-пакет залить кипятком, настоять 10-15 минут. Принимать вечером.',
                    'image' => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?auto=format&fit=crop&q=80&w=400&h=400',
                ],
            ],
            [
                'name' => 'Safi Active Multi',
                'sku' => 'SAFI-ACTIVE-MULTI',
                'description' => 'Витаминно-минеральный комплекс для ежедневной поддержки иммунитета и энергии.',
                'price' => 9000,
                'pv' => 15,
                'stock_quantity' => 160,
                'metadata' => [
                    'category' => 'Здоровье',
                    'short_description' => 'Витаминно-минеральный комплекс для всей семьи.',
                    'benefits' => ['Укрепление иммунитета', 'Повышение энергии', 'Восполнение дефицитов'],
                    'composition' => ['Витамины A, B, C, D, E', 'Цинк', 'Селен', 'Магний', 'Кальций'],
                    'usage' => 'По 1 таблетке в день во время завтрака.',
                    'image' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?auto=format&fit=crop&q=80&w=400&h=400',
                ],
            ],
            [
                'name' => 'Safi Face Serum',
                'sku' => 'COS-HYDRA-001',
                'description' => 'Интенсивная сыворотка для лица с пептидами, алоэ и пантенолом.',
                'price' => 21000,
                'pv' => 35,
                'stock_quantity' => 75,
                'metadata' => [
                    'category' => 'Красота',
                    'short_description' => 'Омолаживающая сыворотка с пептидами.',
                    'benefits' => ['Антивозрастной эффект', 'Выравнивание тона', 'Питание клеток кожи'],
                    'composition' => ['Вода', 'Глицерин', 'Пептидный комплекс', 'Экстракт алоэ', 'Пантенол'],
                    'usage' => 'Наносить 2-3 капли на очищенную кожу лица утром и вечером.',
                    'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&q=80&w=400&h=400',
                ],
            ],
            [
                'name' => 'Safi Hair Strength',
                'sku' => 'SAFI-HAIR-STRENGTH',
                'description' => 'Укрепляющий шампунь без сульфатов и парабенов на основе натуральных экстрактов.',
                'price' => 6500,
                'pv' => 10,
                'stock_quantity' => 120,
                'metadata' => [
                    'category' => 'Ежедневное использование',
                    'short_description' => 'Укрепляющий шампунь на основе натуральных экстрактов.',
                    'benefits' => ['Остановка выпадения волос', 'Стимуляция роста', 'Бережное очищение'],
                    'composition' => ['Экстракт крапивы', 'Экстракт лопуха', 'Кератин', 'Пантенол'],
                    'usage' => 'Нанести на влажные волосы, вспенить, смыть теплой водой.',
                    'image' => 'https://images.unsplash.com/photo-1620916297397-a4a5402a3c6c?auto=format&fit=crop&q=80&w=400&h=400',
                ],
            ],
            [
                'name' => 'Safi Eco Shield',
                'sku' => 'SAFI-ECO-SHIELD',
                'description' => 'Многоцелевое экологичное концентрированное средство для уборки любых поверхностей.',
                'price' => 5500,
                'pv' => 8,
                'stock_quantity' => 110,
                'metadata' => [
                    'category' => 'Ежедневное использование',
                    'short_description' => 'Универсальное экологичное средство для дома.',
                    'benefits' => ['Безопасность', 'Экономичность', 'Эффективность'],
                    'composition' => ['Растительные ПАВ', 'Вода', 'Эфирное масло лимона', 'Лимонная кислота'],
                    'usage' => 'Развести 1 колпачок средства в 5 литрах воды или распылять локально.',
                    'image' => 'https://images.unsplash.com/photo-1650472615219-fdf6f4320434?q=80&w=1470&auto=format&fit=crop',
                ],
            ],
            [
                'name' => 'Safi Sleep Well',
                'sku' => 'SAFI-SLEEP-WELL',
                'description' => 'Растительный комплекс с мелатонином, валерианой, пустырником и магнием.',
                'price' => 8500,
                'pv' => 12,
                'stock_quantity' => 100,
                'metadata' => [
                    'category' => 'Здоровье',
                    'short_description' => 'Натуральный комплекс для нормализации сна.',
                    'benefits' => ['Быстрое засыпание', 'Глубокий сон', 'Бодрое утро'],
                    'composition' => ['Мелатонин', 'Экстракт валерианы', 'Экстракт пустырника', 'Магний'],
                    'usage' => 'По 1 капсуле за 30-40 минут до сна.',
                    'image' => 'https://images.unsplash.com/photo-1665757516783-36558f7ab5d2?q=80&w=1160&auto=format&fit=crop',
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
