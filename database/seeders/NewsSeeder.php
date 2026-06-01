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
                'title_translations' => [
                    'ru' => 'Safi Life открывает новый сезон развития',
                    'kk' => 'Safi Life дамудың жаңа маусымын ашады',
                    'en' => 'Safi Life opens a new season of growth',
                    'mn' => 'Safi Life хөгжлийн шинэ улирлыг эхлүүллээ',
                ],
                'category' => 'Компания',
                'category_translations' => ['ru' => 'Компания', 'kk' => 'Компания', 'en' => 'Company', 'mn' => 'Компани'],
                'content' => 'Safi Life запускает новый сезон партнерского роста: обновленные обучающие материалы, прозрачные инструменты для структуры и поддержка активных лидеров уже доступны в кабинете.',
                'content_translations' => [
                    'ru' => 'Safi Life запускает новый сезон партнерского роста: обновленные обучающие материалы, прозрачные инструменты для структуры и поддержка активных лидеров уже доступны в кабинете.',
                    'kk' => 'Safi Life серіктестік өсімнің жаңа маусымын бастайды: жаңартылған оқу материалдары, құрылымға арналған ашық құралдар және белсенді лидерлерге қолдау кабинетте қолжетімді.',
                    'en' => 'Safi Life starts a new season of partner growth: updated training materials, transparent structure tools and active leader support are already available in the dashboard.',
                    'mn' => 'Safi Life түншийн өсөлтийн шинэ улирлыг эхлүүлж байна: шинэчилсэн сургалтын материал, бүтцийн ил тод хэрэгсэл, идэвхтэй лидерүүдийн дэмжлэг кабинетад бэлэн боллоо.',
                ],
                'image_url' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Обновление маркетинг-плана Safi Life',
                'title_translations' => [
                    'ru' => 'Обновление маркетинг-плана Safi Life',
                    'kk' => 'Safi Life маркетинг жоспары жаңартылды',
                    'en' => 'Safi Life marketing plan update',
                    'mn' => 'Safi Life маркетингийн төлөвлөгөө шинэчлэгдлээ',
                ],
                'category' => 'Маркетинг',
                'category_translations' => ['ru' => 'Маркетинг', 'kk' => 'Маркетинг', 'en' => 'Marketing', 'mn' => 'Маркетинг'],
                'content' => 'В маркетинг-план добавлены улучшенные условия для активных партнеров, понятная логика PV, бонусы за развитие команд и удобная проверка статусов в dashboard.',
                'content_translations' => [
                    'ru' => 'В маркетинг-план добавлены улучшенные условия для активных партнеров, понятная логика PV, бонусы за развитие команд и удобная проверка статусов в dashboard.',
                    'kk' => 'Маркетинг жоспарына белсенді серіктестерге жақсартылған шарттар, түсінікті PV логикасы, команда дамыту бонустары және dashboard-та статус тексеру қосылды.',
                    'en' => 'The marketing plan now includes improved conditions for active partners, clear PV logic, team growth bonuses and convenient status checks in the dashboard.',
                    'mn' => 'Маркетингийн төлөвлөгөөнд идэвхтэй түншүүдийн сайжруулсан нөхцөл, ойлгомжтой PV логик, баг хөгжүүлэх бонус, dashboard дахь статус шалгалт нэмэгдлээ.',
                ],
                'image_url' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Новая продуктовая линейка Safi',
                'title_translations' => [
                    'ru' => 'Новая продуктовая линейка Safi',
                    'kk' => 'Safi өнімдерінің жаңа желісі',
                    'en' => 'New Safi product line',
                    'mn' => 'Safi бүтээгдэхүүний шинэ шугам',
                ],
                'category' => 'Продукты',
                'category_translations' => ['ru' => 'Продукты', 'kk' => 'Өнімдер', 'en' => 'Products', 'mn' => 'Бүтээгдэхүүн'],
                'content' => 'В каталоге доступны Safi Face Serum, Safi Collagen, Safi Omega 3 и Safi Detox Tea. Продукты подобраны для ежедневного ухода, красоты и поддержки здоровья.',
                'content_translations' => [
                    'ru' => 'В каталоге доступны Safi Face Serum, Safi Collagen, Safi Omega 3 и Safi Detox Tea. Продукты подобраны для ежедневного ухода, красоты и поддержки здоровья.',
                    'kk' => 'Каталогта Safi Face Serum, Safi Collagen, Safi Omega 3 және Safi Detox Tea қолжетімді. Өнімдер күнделікті күтім, сұлулық және денсаулықты қолдау үшін таңдалған.',
                    'en' => 'Safi Face Serum, Safi Collagen, Safi Omega 3 and Safi Detox Tea are available in the catalog. The products are selected for daily care, beauty and health support.',
                    'mn' => 'Каталогт Safi Face Serum, Safi Collagen, Safi Omega 3, Safi Detox Tea байна. Бүтээгдэхүүнүүдийг өдөр тутмын арчилгаа, гоо сайхан, эрүүл мэндийн дэмжлэгт зориулан сонгосон.',
                ],
                'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(9),
            ],
            [
                'title' => 'Офлайн-встреча партнеров в Алматы',
                'title_translations' => [
                    'ru' => 'Офлайн-встреча партнеров в Алматы',
                    'kk' => 'Алматыдағы серіктестердің офлайн кездесуі',
                    'en' => 'Offline partner meeting in Almaty',
                    'mn' => 'Алматы дахь түншүүдийн офлайн уулзалт',
                ],
                'category' => 'События',
                'category_translations' => ['ru' => 'События', 'kk' => 'Оқиғалар', 'en' => 'Events', 'mn' => 'Үйл явдал'],
                'content' => 'Команда Safi Life провела встречу партнеров с презентацией продуктов, разбором структуры и практическим блоком по работе с личным кабинетом.',
                'content_translations' => [
                    'ru' => 'Команда Safi Life провела встречу партнеров с презентацией продуктов, разбором структуры и практическим блоком по работе с личным кабинетом.',
                    'kk' => 'Safi Life командасы өнім таныстырылымы, құрылым талдауы және жеке кабинетпен жұмыс бойынша практикалық бөлім бар серіктестер кездесуін өткізді.',
                    'en' => 'The Safi Life team held a partner meeting with a product presentation, structure review and practical dashboard session.',
                    'mn' => 'Safi Life баг бүтээгдэхүүний танилцуулга, бүтцийн задлан шинжилгээ, хувийн кабинет ашиглах практик хэсэгтэй түншүүдийн уулзалт зохион байгууллаа.',
                ],
                'image_url' => 'https://images.unsplash.com/photo-1515169067865-5387ec356754?auto=format&fit=crop&q=80&w=1200',
                'published_at' => now()->subDays(14),
            ],
        ];

        foreach ($articles as $index => $article) {
            $excerptTranslations = [];

            foreach ($article['content_translations'] as $language => $content) {
                $excerptTranslations[$language] = Str::limit($content, 150);
            }

            News::query()->updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    ...$article,
                    'slug' => Str::slug($article['title']),
                    'excerpt' => Str::limit($article['content'], 150),
                    'excerpt_translations' => $excerptTranslations,
                    'status' => 'published',
                    'is_published' => true,
                    'sort_order' => $index + 1,
                    'metadata' => ['source' => 'demo'],
                ]
            );
        }
    }
}
