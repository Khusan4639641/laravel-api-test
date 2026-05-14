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
                'title' => 'Открытие нового офиса в Алматы',
                'category' => 'События',
                'content' => 'Мы рады сообщить об открытии нового офиса Safi Life в Алматы. Партнеров ждут презентации продуктов, обучение и встреча с командой.',
                'image_url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=800',
                'published_at' => now()->subDays(12),
            ],
            [
                'title' => 'Обновление маркетинг-плана',
                'category' => 'Важно',
                'content' => 'В маркетинг-план добавлены улучшенные условия для активных партнеров, прозрачный расчет PV и расширенные возможности апгрейда пакетов.',
                'published_at' => now()->subDays(8),
            ],
            [
                'title' => 'Пополнение ассортимента: новая линейка витаминов',
                'category' => 'Продукция',
                'content' => 'В каталоге Safi Life появилась новая линейка витаминных комплексов для ежедневной поддержки иммунитета и энергии.',
                'image_url' => 'https://images.unsplash.com/photo-1584308666744-24d5e4a83685?auto=format&fit=crop&q=80&w=800',
                'published_at' => now()->subDays(3),
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
                ]
            );
        }
    }
}
