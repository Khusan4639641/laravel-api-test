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
                'slug' => 'start',
                'description' => 'Стартовый пакет для первого знакомства с продуктами и кабинетом.',
                'price' => 30000,
                'pv' => 30000,
                'referral_percent' => 5,
                'binary_percent' => 5,
                'sort_order' => 1,
            ],
            [
                'code' => 'BUSINESS',
                'name' => 'BUSINESS',
                'slug' => 'business',
                'description' => 'Базовый бизнес-пакет для активного старта и развития структуры.',
                'price' => 60000,
                'pv' => 60000,
                'referral_percent' => 7,
                'binary_percent' => 7,
                'sort_order' => 2,
            ],
            [
                'code' => 'VIP',
                'name' => 'VIP',
                'slug' => 'vip',
                'description' => 'Популярный пакет для активного запуска продаж и бинарной структуры.',
                'price' => 180000,
                'pv' => 180000,
                'referral_percent' => 10,
                'binary_percent' => 10,
                'sort_order' => 3,
            ],
            [
                'code' => 'ELITE',
                'name' => 'ELITE',
                'slug' => 'elite',
                'description' => 'Максимальный пакет с расширенными возможностями и статусным ростом.',
                'price' => 300000,
                'pv' => 300000,
                'referral_percent' => 12,
                'binary_percent' => 12,
                'sort_order' => 4,
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
    }
}
