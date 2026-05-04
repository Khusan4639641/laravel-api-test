<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'code' => 'START',
                'name' => 'START',
                'price' => 30000,
                'sort_order' => 1,
            ],
            [
                'code' => 'BUSINESS',
                'name' => 'BUSINESS',
                'price' => 60000,
                'sort_order' => 2,
            ],
            [
                'code' => 'VIP',
                'name' => 'VIP',
                'price' => 180000,
                'sort_order' => 3,
            ],
            [
                'code' => 'ELITE',
                'name' => 'ELITE',
                'price' => 300000,
                'sort_order' => 4,
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(
                ['code' => $package['code']],
                [
                    ...$package,
                    'slug' => strtolower($package['code']),
                    'pv' => $package['price'],
                    'referral_percent' => 0,
                    'binary_percent' => 0,
                    'status' => 'active',
                    'is_active' => true,
                    'is_upgradeable' => true,
                ]
            );
        }
    }
}
