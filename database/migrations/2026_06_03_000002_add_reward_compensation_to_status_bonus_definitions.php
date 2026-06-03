<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('status_bonus_definitions', 'cash_amount')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->decimal('cash_amount', 18, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('status_bonus_definitions', 'compensation_amount')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->decimal('compensation_amount', 18, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('status_bonus_definitions', 'compensation_available')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->boolean('compensation_available')->default(false);
            });
        }

        DB::table('status_bonus_definitions')->update([
            'cash_amount' => DB::raw('amount'),
        ]);

        DB::table('status_bonus_definitions')
            ->where('status_code', 'bronze_director')
            ->update([
                'reward_type' => 'trip',
                'amount' => '100000.00',
                'cash_amount' => '100000.00',
                'compensation_amount' => '400000.00',
                'compensation_available' => true,
                'reward_text' => 'Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸',
            ]);

        DB::table('status_bonus_definitions')
            ->where('status_code', 'silver_director')
            ->update([
                'reward_type' => 'foreign_trip',
                'amount' => '250000.00',
                'cash_amount' => '250000.00',
                'compensation_amount' => '750000.00',
                'compensation_available' => true,
                'reward_text' => 'Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸',
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('status_bonus_definitions', 'compensation_available')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->dropColumn('compensation_available');
            });
        }

        if (Schema::hasColumn('status_bonus_definitions', 'compensation_amount')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->dropColumn('compensation_amount');
            });
        }

        if (Schema::hasColumn('status_bonus_definitions', 'cash_amount')) {
            Schema::table('status_bonus_definitions', function (Blueprint $table): void {
                $table->dropColumn('cash_amount');
            });
        }
    }
};
