<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PackageSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(NewsSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(UserDemoSeeder::class);
        $this->call(BinaryTreeDemoSeeder::class);
        $this->call(OrderDemoSeeder::class);
        $this->call(WalletDemoSeeder::class);
        $this->call(SupportTicketDemoSeeder::class);
    }
}
