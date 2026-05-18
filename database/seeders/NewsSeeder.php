<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Safi Life открывает новый сезон развития',
                'category' => 'Компания',
                'content' => 'Safi Life запускает новый сезон партнерского роста: обновленные обучающие материалы, прозрачные инструменты для структуры и поддержка активных лидеров уже доступны в кабинете.',
                'image_url' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Обновление маркетинг-плана Safi Life',
                'category' => 'Маркетинг',
                'content' => 'В маркетинг-план добавлены улучшенные условия для активных партнеров, понятная логика PV, бонусы за развитие команд и удобная проверка статусов в dashboard.',
                'image_url' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Новая продуктовая линейка Safi',
                'category' => 'Продукты',
                'content' => 'В каталоге доступны Safi Face Serum, Safi Collagen, Safi Omega 3 и Safi Detox Tea. Продукты подобраны для ежедневного ухода, красоты и поддержки здоровья.',
                'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(9),
            ],
            [
                'title' => 'Офлайн-встреча партнеров в Алматы',
                'category' => 'События',
                'content' => 'Команда Safi Life провела встречу партнеров с презентацией продуктов, разбором структуры и практическим блоком по работе с личным кабинетом.',
                'image_url' => 'https://images.unsplash.com/photo-1515169067865-5387ec356754?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(14),
            ],
        ];

        foreach ($articles as $index => $article) {
            News::query()->updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    ...$article,
                    'slug' => Str::slug($article['title']),
                    'excerpt' => Str::limit($article['content'], 150),
                    'status' => 'published',
                    'is_published' => true,
                    'sort_order' => $index + 1,
                    'metadata' => ['source' => 'demo'],
                ]
            );
        }
    }
}
