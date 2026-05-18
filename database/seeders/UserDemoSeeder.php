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
            ['name' => 'Super Admin Safi', 'login' => 'admin', 'email' => 'admin@safilife.test', 'role' => User::ROLE_SUPER_ADMIN, 'package' => 'ELITE', 'status' => 'diamond_director', 'left_pv' => 245000, 'right_pv' => 221000, 'total_pv' => 520000, 'city' => 'Алматы', 'phone' => '+7 701 000 00 01', 'sponsor' => null],
            ['name' => 'Support Safi', 'login' => 'support', 'email' => 'support@safilife.test', 'role' => User::ROLE_SUPPORT, 'package' => 'START', 'status' => 'user', 'left_pv' => 0, 'right_pv' => 0, 'total_pv' => 0, 'city' => 'Алматы', 'phone' => '+7 701 000 00 02', 'sponsor' => null],
            ['name' => 'Айдар Алиханов', 'login' => 'aidar', 'email' => 'aidar@safilife.test', 'role' => User::ROLE_USER, 'package' => 'VIP', 'status' => 'bronze_director', 'left_pv' => 42500, 'right_pv' => 38700, 'total_pv' => 14500, 'city' => 'Алматы', 'phone' => '+7 701 000 00 03', 'sponsor' => null],
            ['name' => 'Ерлан Сыздыков', 'login' => 'erlan', 'email' => 'erlan@safilife.test', 'role' => User::ROLE_USER, 'package' => 'VIP', 'status' => 'director', 'left_pv' => 18200, 'right_pv' => 16400, 'total_pv' => 6200, 'city' => 'Астана', 'phone' => '+7 701 000 00 04', 'sponsor' => 'aidar'],
            ['name' => 'Алиса Петрова', 'login' => 'alisa', 'email' => 'alisa@safilife.test', 'role' => User::ROLE_USER, 'package' => 'BUSINESS', 'status' => 'leader', 'left_pv' => 9600, 'right_pv' => 8700, 'total_pv' => 3200, 'city' => 'Шымкент', 'phone' => '+7 701 000 00 05', 'sponsor' => 'aidar'],
            ['name' => 'Гульнара Касенова', 'login' => 'gulnara', 'email' => 'gulnara@safilife.test', 'role' => User::ROLE_USER, 'package' => 'ELITE', 'status' => 'director', 'left_pv' => 12200, 'right_pv' => 10700, 'total_pv' => 5400, 'city' => 'Караганда', 'phone' => '+7 701 000 00 06', 'sponsor' => 'erlan'],
            ['name' => 'Алексей Смирнов', 'login' => 'alexey', 'email' => 'alexey@safilife.test', 'role' => User::ROLE_USER, 'package' => 'BUSINESS', 'status' => 'manager', 'left_pv' => 2800, 'right_pv' => 2100, 'total_pv' => 1500, 'city' => 'Алматы', 'phone' => '+7 701 000 00 07', 'sponsor' => 'erlan'],
            ['name' => 'Динара Туленова', 'login' => 'dinara', 'email' => 'dinara@safilife.test', 'role' => User::ROLE_USER, 'package' => 'START', 'status' => 'manager', 'left_pv' => 1600, 'right_pv' => 900, 'total_pv' => 1200, 'city' => 'Астана', 'phone' => '+7 701 000 00 08', 'sponsor' => 'alisa'],
            ['name' => 'Тимур Омаров', 'login' => 'timur', 'email' => 'timur@safilife.test', 'role' => User::ROLE_USER, 'package' => 'BUSINESS', 'status' => 'leader', 'left_pv' => 3400, 'right_pv' => 2700, 'total_pv' => 2600, 'city' => 'Тараз', 'phone' => '+7 701 000 00 09', 'sponsor' => 'alisa'],
            ['name' => 'Мадина Нурланова', 'login' => 'madina', 'email' => 'madina@safilife.test', 'role' => User::ROLE_USER, 'package' => 'START', 'status' => 'user', 'left_pv' => 700, 'right_pv' => 400, 'total_pv' => 600, 'city' => 'Костанай', 'phone' => '+7 701 000 00 10', 'sponsor' => 'gulnara'],
            ['name' => 'Нурлан Беков', 'login' => 'nurlan', 'email' => 'nurlan@safilife.test', 'role' => User::ROLE_USER, 'package' => 'VIP', 'status' => 'manager', 'left_pv' => 2300, 'right_pv' => 1300, 'total_pv' => 1800, 'city' => 'Павлодар', 'phone' => '+7 701 000 00 11', 'sponsor' => 'gulnara'],
        ];

        foreach ($users as $userData) {
            $package = $packages->get($userData['package']);
            $user = User::query()->updateOrCreate(
                ['login' => $userData['login']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
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

            $nameParts = explode(' ', $userData['name'], 2);
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? null,
                    'phone' => $userData['phone'],
                    'country' => 'Казахстан',
                    'city' => $userData['city'],
                    'address' => 'Demo address',
                ]
            );
        }

        $createdUsers = User::query()->whereIn('login', collect($users)->pluck('login'))->get()->keyBy('login');

        foreach ($users as $userData) {
            if (! $userData['sponsor']) {
                continue;
            }

            $user = $createdUsers->get($userData['login']);
            $sponsor = $createdUsers->get($userData['sponsor']);

            if ($user && $sponsor) {
                $user->forceFill(['sponsor_id' => $sponsor->id])->save();
            }
        }
    }
}
