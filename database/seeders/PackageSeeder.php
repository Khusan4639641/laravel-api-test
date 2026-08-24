<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'code' => 'START',
                'name' => 'START',
                'name_translations' => ['ru' => 'START', 'kk' => 'START', 'en' => 'START', 'mn' => 'START'],
                'slug' => 'start',
                'description' => 'Стартовый пакет для первого знакомства с продуктами и кабинетом.',
                'description_translations' => [
                    'ru' => 'Стартовый пакет для первого знакомства с продуктами и кабинетом.',
                    'kk' => 'Өнімдермен және кабинетпен алғашқы танысуға арналған бастапқы пакет.',
                    'en' => 'Starter package for first experience with products and the dashboard.',
                    'mn' => 'Бүтээгдэхүүн болон кабинеттай анх танилцах эхлэх багц.',
                ],
                'price' => 60000,
                'pv' => 100,
                'activity_pv' => 100,
                'turnover_pv' => 100,
                'referral_percent' => 10,
                'binary_percent' => 7,
                'sort_order' => 1,
            ],
            [
                'code' => 'VIP',
                'name' => 'VIP',
                'name_translations' => ['ru' => 'VIP', 'kk' => 'VIP', 'en' => 'VIP', 'mn' => 'VIP'],
                'slug' => 'vip',
                'description' => 'Популярный пакет для активного запуска продаж и бинарной структуры.',
                'description_translations' => [
                    'ru' => 'Популярный пакет для активного запуска продаж и бинарной структуры.',
                    'kk' => 'Сатылымды және бинарлық құрылымды белсенді бастауға арналған танымал пакет.',
                    'en' => 'Popular package for active sales launch and binary structure growth.',
                    'mn' => 'Борлуулалт болон хоёртын бүтцийг идэвхтэй эхлүүлэх түгээмэл багц.',
                ],
                'price' => 180000,
                'pv' => 300,
                'activity_pv' => 300,
                'turnover_pv' => 300,
                'referral_percent' => 10,
                'binary_percent' => 8,
                'sort_order' => 2,
            ],
            [
                'code' => 'ELITE',
                'name' => 'ELITE',
                'name_translations' => ['ru' => 'ELITE', 'kk' => 'ELITE', 'en' => 'ELITE', 'mn' => 'ELITE'],
                'slug' => 'elite',
                'description' => 'Максимальный пакет с расширенными возможностями и статусным ростом.',
                'description_translations' => [
                    'ru' => 'Максимальный пакет с расширенными возможностями и статусным ростом.',
                    'kk' => 'Кеңейтілген мүмкіндіктері және статус өсімі бар ең жоғары пакет.',
                    'en' => 'Maximum package with expanded opportunities and status growth.',
                    'mn' => 'Өргөтгөсөн боломж болон статусын өсөлттэй хамгийн дээд багц.',
                ],
                'price' => 300000,
                'pv' => 500,
                'activity_pv' => 500,
                'turnover_pv' => 200,
                'referral_percent' => 10,
                'binary_percent' => 10,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(
                ['code' => $package['code']],
                [
                    ...$package,
                    'status' => 'active',
                    'is_active' => true,
                    'is_upgradeable' => true,
                ]
            );
        }

        Package::query()
            ->where('code', 'BUSINESS')
            ->update([
                'status' => 'inactive',
                'is_active' => false,
                'is_upgradeable' => false,
            ]);
    }
}
