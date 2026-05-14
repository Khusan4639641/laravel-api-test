<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserDemoSeeder extends Seeder
{
    public function run(): void
    {
        $packages = Package::query()->get()->keyBy('code');

        $users = [
            ['name' => 'Admin Safi', 'login' => 'admin', 'email' => 'admin@safilife.test', 'role' => 'admin', 'package' => 'ELITE', 'status' => 'diamond_director', 'left_pv' => 245000, 'right_pv' => 221000, 'total_pv' => 520000, 'city' => 'Алматы', 'phone' => '+7 701 000 00 01'],
            ['name' => 'Айдар Алиханов', 'login' => 'aidar', 'email' => 'aidar@safilife.test', 'role' => 'user', 'package' => 'VIP', 'status' => 'leader', 'left_pv' => 14500, 'right_pv' => 12800, 'total_pv' => 2500, 'city' => 'Алматы', 'phone' => '+7 701 000 00 02'],
            ['name' => 'Ерлан Сыздыков', 'login' => 'erlan', 'email' => 'erlan@safilife.test', 'role' => 'user', 'package' => 'VIP', 'status' => 'manager', 'left_pv' => 4500, 'right_pv' => 2800, 'total_pv' => 1800, 'city' => 'Астана', 'phone' => '+7 701 000 00 03'],
            ['name' => 'Алиса Петрова', 'login' => 'alisa', 'email' => 'alisa@safilife.test', 'role' => 'user', 'package' => 'BUSINESS', 'status' => 'user', 'left_pv' => 1200, 'right_pv' => 900, 'total_pv' => 700, 'city' => 'Шымкент', 'phone' => '+7 701 000 00 04'],
            ['name' => 'Гульнара Касенова', 'login' => 'gulnara', 'email' => 'gulnara@safilife.test', 'role' => 'user', 'package' => 'ELITE', 'status' => 'director', 'left_pv' => 18000, 'right_pv' => 15200, 'total_pv' => 5200, 'city' => 'Караганда', 'phone' => '+7 701 000 00 05'],
            ['name' => 'Алексей Смирнов', 'login' => 'alexey', 'email' => 'alexey@safilife.test', 'role' => 'user', 'package' => 'BUSINESS', 'status' => 'manager', 'left_pv' => 2500, 'right_pv' => 1600, 'total_pv' => 1200, 'city' => 'Алматы', 'phone' => '+7 701 000 00 06'],
            ['name' => 'Динара Туленова', 'login' => 'dinara', 'email' => 'dinara@safilife.test', 'role' => 'user', 'package' => 'START', 'status' => 'user', 'left_pv' => 600, 'right_pv' => 300, 'total_pv' => 300, 'city' => 'Астана', 'phone' => '+7 701 000 00 07'],
        ];

        foreach ($users as $userData) {
            $package = $packages->get($userData['package']);
            $user = User::query()->updateOrCreate(
                ['login' => $userData['login']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password123'),
                    'role' => $userData['role'],
                    'current_package_id' => $package?->id,
                    'status' => $userData['status'],
                    'left_pv' => $userData['left_pv'],
                    'right_pv' => $userData['right_pv'],
                    'remaining_left_pv' => max($userData['left_pv'] - 1000, 0),
                    'remaining_right_pv' => max($userData['right_pv'] - 1000, 0),
                    'total_pv' => $userData['total_pv'],
                ]
            );

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => explode(' ', $userData['name'])[0],
                    'last_name' => explode(' ', $userData['name'])[1] ?? null,
                    'phone' => $userData['phone'],
                    'country' => 'Казахстан',
                    'city' => $userData['city'],
                    'address' => 'Demo address',
                ]
            );
        }

        $aidar = User::query()->where('login', 'aidar')->first();
        foreach (['erlan', 'alisa', 'gulnara', 'alexey', 'dinara'] as $login) {
            User::query()->where('login', $login)->update(['sponsor_id' => $aidar?->id]);
        }
    }
}
