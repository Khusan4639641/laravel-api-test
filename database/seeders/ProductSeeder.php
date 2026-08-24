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
                'name_translations' => [
                    'ru' => 'Safi Face Serum',
                    'kk' => 'Safi бет сарысуы',
                    'en' => 'Safi Face Serum',
                    'mn' => 'Safi нүүрний серум',
                ],
                'sku' => 'SAFI-FACE-SERUM',
                'description' => 'Интенсивная сыворотка для лица с пептидами, алоэ и пантенолом для ежедневного ухода.',
                'description_translations' => [
                    'ru' => 'Интенсивная сыворотка для лица с пептидами, алоэ и пантенолом для ежедневного ухода.',
                    'kk' => 'Күнделікті күтімге арналған пептид, алоэ және пантенол қосылған бет сарысуы.',
                    'en' => 'Intensive face serum with peptides, aloe and panthenol for daily care.',
                    'mn' => 'Өдөр тутмын арчилгаанд зориулсан пептид, алоэ, пантенолтой нүүрний серум.',
                ],
                'category_translations' => ['ru' => 'Красота', 'kk' => 'Сұлулық', 'en' => 'Beauty', 'mn' => 'Гоо сайхан'],
                'short_description_translations' => [
                    'ru' => 'Омолаживающая сыворотка с пептидами для ровного тона кожи.',
                    'kk' => 'Тері реңін тегістейтін пептидті жасартатын сарысу.',
                    'en' => 'Rejuvenating peptide serum for an even skin tone.',
                    'mn' => 'Арьсны өнгийг жигд болгох пептидтэй залуужуулах серум.',
                ],
                'benefits_translations' => [
                    'ru' => ['Антивозрастной уход', 'Выравнивание тона', 'Глубокое увлажнение'],
                    'kk' => ['Қартаюға қарсы күтім', 'Реңді тегістеу', 'Терең ылғалдандыру'],
                    'en' => ['Anti-age care', 'Even skin tone', 'Deep hydration'],
                    'mn' => ['Хөгшрөлтийн эсрэг арчилгаа', 'Өнгө жигдрүүлэх', 'Гүн чийгшүүлэх'],
                ],
                'composition_translations' => [
                    'ru' => ['Пептидный комплекс', 'Экстракт алоэ', 'Пантенол', 'Гиалуроновая кислота'],
                    'kk' => ['Пептид кешені', 'Алоэ сығындысы', 'Пантенол', 'Гиалурон қышқылы'],
                    'en' => ['Peptide complex', 'Aloe extract', 'Panthenol', 'Hyaluronic acid'],
                    'mn' => ['Пептидийн комплекс', 'Алоэны ханд', 'Пантенол', 'Гиалуроны хүчил'],
                ],
                'usage_translations' => [
                    'ru' => 'Наносить 2-3 капли на очищенную кожу лица утром и вечером.',
                    'kk' => 'Таңертең және кешке тазартылған бет терісіне 2-3 тамшы жағыңыз.',
                    'en' => 'Apply 2-3 drops to cleansed face skin morning and evening.',
                    'mn' => 'Өглөө, орой цэвэрлэсэн нүүрний арьсанд 2-3 дусал түрхэнэ.',
                ],
                'price' => 21000,
                'pv' => 35,
                'stock_quantity' => 75,
                'is_deposit_product' => false,
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
                'name_translations' => [
                    'ru' => 'Safi Collagen',
                    'kk' => 'Safi коллагені',
                    'en' => 'Safi Collagen',
                    'mn' => 'Safi коллаген',
                ],
                'sku' => 'SAFI-COLLAGEN',
                'description' => 'Биодоступный морской коллаген с витамином C для поддержки кожи, волос, ногтей и суставов.',
                'description_translations' => [
                    'ru' => 'Биодоступный морской коллаген с витамином C для поддержки кожи, волос, ногтей и суставов.',
                    'kk' => 'Тері, шаш, тырнақ және буындарды қолдайтын C дәрумені бар теңіз коллагені.',
                    'en' => 'Bioavailable marine collagen with vitamin C for skin, hair, nails and joints.',
                    'mn' => 'Арьс, үс, хумс, үе мөчийг дэмжих C витаминтай далайн коллаген.',
                ],
                'category_translations' => ['ru' => 'Красота', 'kk' => 'Сұлулық', 'en' => 'Beauty', 'mn' => 'Гоо сайхан'],
                'short_description_translations' => [
                    'ru' => 'Морской коллаген с витамином C для упругости кожи.',
                    'kk' => 'Терінің серпімділігіне арналған C дәрумені бар теңіз коллагені.',
                    'en' => 'Marine collagen with vitamin C for skin elasticity.',
                    'mn' => 'Арьсны уян хатан байдалд зориулсан C витаминтай далайн коллаген.',
                ],
                'benefits_translations' => [
                    'ru' => ['Поддержка упругости кожи', 'Укрепление волос и ногтей', 'Забота о суставах'],
                    'kk' => ['Терінің серпімділігін қолдау', 'Шаш пен тырнақты нығайту', 'Буындарға күтім'],
                    'en' => ['Supports skin elasticity', 'Strengthens hair and nails', 'Joint care'],
                    'mn' => ['Арьсны уян хатан байдлыг дэмжинэ', 'Үс, хумсыг бэхжүүлнэ', 'Үе мөчний арчилгаа'],
                ],
                'composition_translations' => [
                    'ru' => ['Пептиды морского коллагена', 'Витамин C', 'Гиалуроновая кислота'],
                    'kk' => ['Теңіз коллагені пептидтері', 'C дәрумені', 'Гиалурон қышқылы'],
                    'en' => ['Marine collagen peptides', 'Vitamin C', 'Hyaluronic acid'],
                    'mn' => ['Далайн коллагены пептид', 'C витамин', 'Гиалуроны хүчил'],
                ],
                'usage_translations' => [
                    'ru' => '1 мерную ложку порошка развести в стакане воды. Принимать утром.',
                    'kk' => '1 өлшеуіш қасық ұнтақты бір стақан суға араластырып, таңертең ішіңіз.',
                    'en' => 'Dissolve 1 scoop in a glass of water. Take in the morning.',
                    'mn' => '1 хэмжүүр нунтагийг аяга усанд найруулж өглөө хэрэглэнэ.',
                ],
                'price' => 18000,
                'pv' => 30,
                'stock_quantity' => 90,
                'is_deposit_product' => false,
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
                'name_translations' => ['ru' => 'Safi Omega 3', 'kk' => 'Safi Omega 3', 'en' => 'Safi Omega 3', 'mn' => 'Safi Omega 3'],
                'sku' => 'SAFI-OMEGA-3',
                'description' => 'Высокоочищенная Омега 3 для поддержки сердца, сосудов, мозга и общего тонуса.',
                'description_translations' => [
                    'ru' => 'Высокоочищенная Омега 3 для поддержки сердца, сосудов, мозга и общего тонуса.',
                    'kk' => 'Жүрек, қан тамырлары, ми және жалпы сергектікті қолдайтын жоғары тазартылған Омега 3.',
                    'en' => 'Highly purified Omega 3 for heart, vessels, brain and overall tone.',
                    'mn' => 'Зүрх, судас, тархи болон ерөнхий эрч хүчийг дэмжих өндөр цэвэршилттэй Омега 3.',
                ],
                'category_translations' => ['ru' => 'Здоровье', 'kk' => 'Денсаулық', 'en' => 'Health', 'mn' => 'Эрүүл мэнд'],
                'short_description_translations' => [
                    'ru' => 'Омега 3 для поддержки сердца, сосудов и концентрации.',
                    'kk' => 'Жүрек, қан тамырлары және зейінді қолдайтын Омега 3.',
                    'en' => 'Omega 3 for heart, vessels and focus.',
                    'mn' => 'Зүрх, судас болон төвлөрлийг дэмжих Омега 3.',
                ],
                'benefits_translations' => [
                    'ru' => ['Поддержка сердечно-сосудистой системы', 'Концентрация и память', 'Здоровье суставов'],
                    'kk' => ['Жүрек-қантамыр жүйесін қолдау', 'Зейін және есте сақтау', 'Буын денсаулығы'],
                    'en' => ['Cardiovascular support', 'Focus and memory', 'Joint health'],
                    'mn' => ['Зүрх судасны дэмжлэг', 'Төвлөрөл ба ой тогтоолт', 'Үе мөчний эрүүл мэнд'],
                ],
                'composition_translations' => [
                    'ru' => ['Рыбий жир', 'ЭПК', 'ДГК', 'Витамин E'],
                    'kk' => ['Балық майы', 'EPA', 'DHA', 'E дәрумені'],
                    'en' => ['Fish oil', 'EPA', 'DHA', 'Vitamin E'],
                    'mn' => ['Загасны тос', 'EPA', 'DHA', 'E витамин'],
                ],
                'usage_translations' => [
                    'ru' => 'По 1 капсуле 2 раза в день во время еды.',
                    'kk' => 'Тамақпен бірге күніне 2 рет 1 капсуладан қабылдаңыз.',
                    'en' => 'Take 1 capsule twice daily with meals.',
                    'mn' => 'Хоолтой хамт өдөрт 2 удаа 1 капсул хэрэглэнэ.',
                ],
                'price' => 12500,
                'pv' => 20,
                'stock_quantity' => 140,
                'is_deposit_product' => false,
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
                'name_translations' => ['ru' => 'Safi Detox Tea', 'kk' => 'Safi детокс шайы', 'en' => 'Safi Detox Tea', 'mn' => 'Safi детокс цай'],
                'sku' => 'SAFI-DETOX-TEA',
                'description' => 'Натуральный травяной чай для мягкого очищения организма и поддержки пищеварения.',
                'description_translations' => [
                    'ru' => 'Натуральный травяной чай для мягкого очищения организма и поддержки пищеварения.',
                    'kk' => 'Ағзаны жұмсақ тазартуға және ас қорытуды қолдауға арналған табиғи шөп шайы.',
                    'en' => 'Natural herbal tea for gentle cleansing and digestion support.',
                    'mn' => 'Биеийг зөөлөн цэвэрлэж, хоол боловсруулалтыг дэмжих байгалийн ургамлын цай.',
                ],
                'category_translations' => ['ru' => 'Здоровье', 'kk' => 'Денсаулық', 'en' => 'Health', 'mn' => 'Эрүүл мэнд'],
                'short_description_translations' => [
                    'ru' => 'Травяной чай для мягкого очищения и легкости.',
                    'kk' => 'Жұмсақ тазарту және жеңілдік үшін шөп шайы.',
                    'en' => 'Herbal tea for gentle cleansing and lightness.',
                    'mn' => 'Зөөлөн цэвэрлэгээ, хөнгөн мэдрэмж өгөх ургамлын цай.',
                ],
                'benefits_translations' => [
                    'ru' => ['Мягкое очищение', 'Поддержка пищеварения', 'Легкость в теле'],
                    'kk' => ['Жұмсақ тазарту', 'Ас қорытуды қолдау', 'Денедегі жеңілдік'],
                    'en' => ['Gentle cleansing', 'Digestion support', 'Body lightness'],
                    'mn' => ['Зөөлөн цэвэрлэгээ', 'Хоол боловсруулалт дэмжих', 'Биеийн хөнгөн байдал'],
                ],
                'composition_translations' => [
                    'ru' => ['Ромашка', 'Мята перечная', 'Расторопша', 'Корень солодки'],
                    'kk' => ['Түймедақ', 'Жалбыз', 'Шұбаршөп', 'Мия тамыры'],
                    'en' => ['Chamomile', 'Peppermint', 'Milk thistle', 'Licorice root'],
                    'mn' => ['Балжингарам', 'Гаатай навч', 'Сүүт өвс', 'Чихэр өвсний үндэс'],
                ],
                'usage_translations' => [
                    'ru' => '1 фильтр-пакет залить кипятком, настоять 10-15 минут.',
                    'kk' => '1 сүзгі-пакетті қайнаған сумен құйып, 10-15 минут тұндырыңыз.',
                    'en' => 'Pour boiling water over 1 tea bag and steep for 10-15 minutes.',
                    'mn' => '1 цайны уутанд буцалсан ус хийж 10-15 минут хандална.',
                ],
                'price' => 4500,
                'pv' => 8,
                'stock_quantity' => 180,
                'is_deposit_product' => true,
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
